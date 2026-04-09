<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Concerns\HasStegoEncoding;
use App\Jobs\EncodeStegoDocumentJob;
use App\Jobs\DecodeStegoDocumentJob;
use App\Models\Document;
use App\Models\StegoCarrier;
use App\Models\StegoDocument;
use App\Models\StegoDocumentGrant;
use App\Models\User;
use App\Services\Stego\CloudStorageService;
use App\Services\Stego\StegoDocumentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * StegoDocumentController (API)
 *
 * Handles CRUD + encode/decode operations for StegoDocument resources,
 * plus owner-only access-grant management (grant / revokeGrant).
 *
 * Routes (registered in routes/api.php):
 *   GET    /api/stego/documents                              — paginated list for authenticated user
 *   GET    /api/stego/documents/{id}                        — single doc with segments
 *   POST   /api/stego/encode                                — encrypt + embed document into carriers
 *   POST   /api/stego/decode                                — extract + decrypt → file download (owner OR granted viewer)
 *   DELETE /api/stego/{id}                                  — delete (ownership enforced)
 *   GET    /api/stego                                       — SPA alias → same as index()
 *   POST   /api/stego/documents/{id}/grant                  — grant viewer access (owner only)
 *   DELETE /api/stego/documents/{id}/grant/{viewer_user_id} — revoke viewer access (owner only)
 */
class StegoDocumentController extends Controller
{
    use HasStegoEncoding;

    public function __construct(private readonly StegoDocumentService $stegoService) {}

    private ?CloudStorageService $cloudStorage = null;

    // -------------------------------------------------------------------------
    // GET /api/stego/documents  +  GET /api/stego  (SPA alias)
    // -------------------------------------------------------------------------

    /**
     * Return the authenticated user's stego documents, paginated.
     *
     * Eager-loads the linked document stub (id, name, extension) and
     * a segments count so callers can display carrier count without extra requests.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $docs = Cache::remember(
            'stego.index.u' . Auth::id(),
            30,
            fn () => Auth::user()
                ->stegoDocuments()
                ->select([
                    'id',
                    'document_id',
                    'user_id',
                    'status',
                    'decoding_status',
                    'download_path',
                    'created_at',
                    'updated_at',
                    'stego_hash_sha256',
                ])
                ->with(['document:id,name,extension'])
                ->withCount('segments')
                ->latest()
                ->paginate(20)
        );

        return response()->json($docs);
    }

    // -------------------------------------------------------------------------
    // GET /api/stego/documents/{id}
    // -------------------------------------------------------------------------

    /**
     * Return a single stego document with its full document and all segments.
     * Authorized via StegoDocumentPolicy.
     *
     * @param  int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $stegoDoc = StegoDocument::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('viewerGrants', fn ($g) =>
                      $g->where('viewer_user_id', $user->id)
                  );
            })
            ->where('id', $id)
            ->select([
                'id',
                'document_id',
                'user_id',
                'status',
                'decoding_status',
                'decoding_error',
                'download_path',
                'created_at',
                'updated_at',
            ])
            ->with(['document:id,name,extension'])
            ->withCount('segments')
            ->firstOrFail();

        return response()->json($stegoDoc);
    }

    // -------------------------------------------------------------------------
    // GET /api/stego/documents/{id}/status
    // -------------------------------------------------------------------------

    /**
     * Return a lean status payload specifically for polling decode operations.
     * Contains only the essential fields needed to update the UI.
     *
     * @param  int $id
     * @return JsonResponse
     */
    public function status(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $stegoDoc = StegoDocument::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('viewerGrants', fn ($g) =>
                      $g->where('viewer_user_id', $user->id)
                  );
            })
            ->where('id', $id)
            ->select([
                'id',
                'decoding_status',
                'decoding_error',
                'download_path',
            ])
            ->firstOrFail();

        return response()->json([
            'status' => $stegoDoc->decoding_status,
            'error' => $stegoDoc->decoding_error,
            'download_path' => $stegoDoc->download_path,
        ]);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/stego/{id}
    // -------------------------------------------------------------------------

    /**
     * Delete a stego document.
     * Authorized via StegoDocumentPolicy.
     *
     * @param  int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $stegoDoc = StegoDocument::findOrFail($id);
        
        $this->authorize('delete', $stegoDoc);
        
        $stegoDoc->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // -------------------------------------------------------------------------
    // POST /api/stego/encode
    // -------------------------------------------------------------------------

    /**
     * Encrypt + steganographically embed a document into one or more carrier images.
     *
     * Reads the Master Key from the server-side session (populated at login via MKD).
     * Returns quality metrics (PSNR) alongside the created StegoDocument record.
     *
     * @param  Request $request  { document_id: int, carriers: UploadedFile[] }
     * @return JsonResponse      201 { stego_document_id, segments_count, quality_metrics }
     */
    public function encode(Request $request): JsonResponse
    {
        // Master Key must be present before we bother validating the payload.
        $masterKey = $this->resolveSessionKey();
        if ($masterKey === null) {
            return response()->json([
                'message' => 'Session expired. Please log in again to refresh the Master Key.',
            ], 401);
        }

        $useSystemCarriers = $request->boolean('use_system_carriers');

        $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'carriers'    => ['nullable', 'array'],
            'carriers.*'  => ['file', 'mimes:png,bmp,jpeg,jpg', 'max:51200'],
        ]);

        $user     = Auth::user();
        $document = Document::findOrFail($request->document_id);

        try {
            $plaintext = $this->readDocumentPlaintext($document);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Pre-create a pending skeleton so the client receives an ID to poll.
        $pending = StegoDocument::create([
            'document_id' => $document->id,
            'user_id'     => $user->id,
            'status'      => 'pending',
        ]);

        // Stage plaintext + carriers to persistent storage so the queue
        // worker can read them after the HTTP request has ended.
        $carrierFiles = $request->file('carriers', []);
        if (!is_array($carrierFiles)) {
            $carrierFiles = [$carrierFiles];
        }

        // Always stage plaintext so the queue worker can read it.
        // Carrier files may be empty when using pool/system fallback only.
        [$plainPath, $carrierPaths] = $this->stageForQueue($plaintext, $carrierFiles);

        EncodeStegoDocumentJob::dispatch(
            $user->id,
            $plainPath,
            $masterKey,
            $carrierPaths,
            $document->id,
            $pending->id,
            $useSystemCarriers,
        );

        return response()->json([
            'message'           => 'Encoding queued. Check the stego document status to track progress.',
            'stego_document_id' => $pending->id,
        ], 202);
    }

    // -------------------------------------------------------------------------
    // POST /api/stego/decode
    // -------------------------------------------------------------------------

    /**
     * Queue a decode operation for a previously encoded StegoDocument.
     *
     * Authorization: the authenticated user must be either the **owner**
     * (stego_documents.user_id) OR appear as a viewer in stego_document_grants.
     *
     * Reads the Master Key from the server-side session (populated at login via MKD).
     *
     * @param  Request $request  { stego_document_id: int }
     * @return JsonResponse
     */
    public function decode(Request $request): JsonResponse
    {
        // Master Key must be present before we bother validating the payload.
        $masterKey = $this->resolveSessionKey();
        if ($masterKey === null) {
            return response()->json([
                'message' => 'Session expired. Please log in again to refresh the Master Key.',
            ], 401);
        }

        $request->validate([
            'stego_document_id' => ['required', 'integer'],
        ]);

        $user = Auth::user();

        // Authorization: owner OR granted viewer.
        $stegoDoc = StegoDocument::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('viewerGrants', fn ($g) =>
                      $g->where('viewer_user_id', $user->id)
                  );
            })
            ->where('id', $request->stego_document_id)
            ->select(['id', 'document_id', 'user_id'])
            ->with('document')
            ->firstOrFail();

        // Update stego document status to indicate decoding is pending
        $stegoDoc->update([
            'decoding_status' => 'pending',
            'decoding_error' => null,
            'download_path' => null,
        ]);

        // Queue the decode operation
        DecodeStegoDocumentJob::dispatch(
            $user->id,
            $stegoDoc->id,
            $masterKey
        );

        return response()->json([
            'message' => 'Decoding queued. Check the stego document status to track progress.',
            'stego_document_id' => $stegoDoc->id,
        ], 202);
    }

    // -------------------------------------------------------------------------
    // GET /api/stego/decode/{id}
    // -------------------------------------------------------------------------

    /**
     * Download the decoded document if decoding is complete.
     *
     * @param  int $id StegoDocument id
     * @return StreamedResponse|JsonResponse
     */
    public function downloadDecoded(int $id): StreamedResponse|JsonResponse
    {
        $user = Auth::user();

        // Authorization: owner OR granted viewer.
        $stegoDoc = StegoDocument::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('viewerGrants', fn ($g) =>
                      $g->where('viewer_user_id', $user->id)
                  );
            })
            ->where('id', $id)
            ->select([
                'id',
                'document_id',
                'user_id',
                'decoding_status',
                'download_path',
            ])
            ->with('document')
            ->firstOrFail();

        if ($stegoDoc->decoding_status !== 'completed' || empty($stegoDoc->download_path)) {
            return response()->json([
                'message' => 'Decoding not completed yet. Please check again later.',
                'status' => $stegoDoc->decoding_status,
            ], 404);
        }

        // Check if the file exists
        if (!Storage::exists($stegoDoc->download_path)) {
            return response()->json([
                'message' => 'Decoded file not found. Please re-queue the decode operation.',
            ], 404);
        }

        // Read the decoded file
        $plaintext = Storage::get($stegoDoc->download_path);

        $filename = $this->buildDecodeFilename($stegoDoc);

        return response()->streamDownload(
            fn () => print($plaintext),
            $filename,
            ['Content-Type' => 'application/octet-stream']
        );
    }

    // -------------------------------------------------------------------------
    // POST /api/stego/documents/{id}/grant
    // -------------------------------------------------------------------------

    /**
     * Grant viewer-decode access to another user for a stego document.
     *
     * Only the document's owner may call this endpoint.
     * The same viewer cannot be granted twice (unique constraint → 409).
     *
     * @param  Request $request  { viewer_user_id: int }
     * @param  int     $id       StegoDocument id
     * @return JsonResponse      201 { message, grant } | 403 | 404 | 409 | 422
     */
    public function grant(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        
        $stegoDoc = StegoDocument::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $request->validate([
            'viewer_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $ownerId   = Auth::id();
        $viewerId  = (int) $request->viewer_user_id;

        // Owner cannot grant themselves.
        if ($viewerId === $ownerId) {
            return response()->json([
                'message' => 'You cannot grant access to yourself.',
            ], 422);
        }

        // Attempt to insert; catch duplicate-grant violation.
        try {
            $grant = StegoDocumentGrant::create([
                'stego_document_id' => $stegoDoc->id,
                'viewer_user_id'    => $viewerId,
                'granted_by'        => $ownerId,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Viewer already has access to this document.',
            ], 409);
        }

        return response()->json([
            'message' => 'Access granted.',
            'grant'   => [
                'id'                 => $grant->id,
                'stego_document_id'  => $grant->stego_document_id,
                'viewer_user_id'     => $grant->viewer_user_id,
                'granted_by'         => $grant->granted_by,
                'created_at'         => $grant->created_at?->toISOString(),
            ],
        ], 201);
    }

    // -------------------------------------------------------------------------
    // GET /api/stego/documents/{id}/grants
    // -------------------------------------------------------------------------

    /**
     * List all viewer grants for a stego document.
     *
     * Only the document's owner may call this endpoint.
     *
     * @param  int $id StegoDocument id
     * @return JsonResponse 200 { grants: [...] } | 403 | 404
     */
    public function listGrants(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $stegoDoc = StegoDocument::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $grants = $stegoDoc->viewerGrants()
            ->with(['viewer:id,name,email', 'grantor:id,name,email'])
            ->latest()
            ->get()
            ->map(function ($grant) {
                return [
                    'id'                 => $grant->id,
                    'stego_document_id'  => $grant->stego_document_id,
                    'viewer_user_id'     => $grant->viewer_user_id,
                    'viewer'             => [
                        'id'    => $grant->viewer->id,
                        'name'  => $grant->viewer->name,
                        'email' => $grant->viewer->email,
                    ],
                    'granted_by'         => $grant->granted_by,
                    'grantor'            => [
                        'id'    => $grant->grantor->id,
                        'name'  => $grant->grantor->name,
                        'email' => $grant->grantor->email,
                    ],
                    'created_at'         => $grant->created_at?->toISOString(),
                    'updated_at'         => $grant->updated_at?->toISOString(),
                ];
            });

        return response()->json(['grants' => $grants]);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/stego/documents/{id}/grant/{viewer_user_id}
    // -------------------------------------------------------------------------

    /**
     * Revoke a viewer's decode access for a stego document.
     *
     * Only the document's owner may call this endpoint.
     *
     * @param  Request $request
     * @param  int     $id             StegoDocument id
     * @param  int     $viewerUserId   User id whose grant should be removed
     * @return JsonResponse            200 | 403 | 404
     */
    /**
     * Preflight check for encoding a document.
     *
     * Checks if the user has enough valid carriers in their pool to encode
     * a document, using the same payload basis as split(): decoded ciphertext
     * bytes (compressed + encrypted payload before base64 transport encoding).
     *
     * @param  Request $request  { document_id: int }
     * @return JsonResponse      200 { can_encode, required_bytes, available_bytes, valid_carriers, message }
     */
    public function preflight(Request $request): JsonResponse
    {
        $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'use_system_carriers' => ['nullable', 'boolean'],
        ]);

        $user = Auth::user();
        $useSystemCarriers = (bool) $request->boolean('use_system_carriers');
        $document = Document::findOrFail($request->document_id);

        // Read document and compute required payload bytes on the same basis
        // used by SegmentationService::split() capacity validation.
        try {
            $plaintext = $this->readDocumentPlaintext($document);
            $compressed = gzcompress($plaintext, 6);
            if ($compressed === false) {
                throw new \RuntimeException('Unable to prepare document for preflight capacity check.');
            }

            // AES-GCM ciphertext length equals compressed plaintext length.
            $requiredBytes = strlen($compressed);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        // Get available carriers from pool
        $availableCarriers = StegoCarrier::where('uploaded_by', $user->id)
            ->where('validation_status', 'valid')
            ->where('is_in_use', false)
            ->get();

        $userPoolBytes = $availableCarriers->sum('capacity_bytes');
        $validCarrierCount = $availableCarriers->count();

        $systemPoolBytes = 0;
        if ($useSystemCarriers) {
            $adminId = User::where('role', 'admin')->value('id');
            if ($adminId) {
                $systemPoolBytes = StegoCarrier::where('uploaded_by', $adminId)
                    ->where('validation_status', 'valid')
                    ->where('is_in_use', false)
                    ->sum('capacity_bytes');
            }
        }

        $availableBytes = $userPoolBytes + $systemPoolBytes;

        $canEncode = $availableBytes >= $requiredBytes;

        return response()->json([
            'can_encode' => $canEncode,
            'required_bytes' => $requiredBytes,
            'required_bytes_basis' => 'decoded_ciphertext',
            'available_bytes' => $availableBytes,
            'user_pool_bytes' => $userPoolBytes,
            'system_pool_bytes' => $systemPoolBytes,
            'valid_carriers' => $validCarrierCount,
            'message' => $canEncode
                ? 'Sufficient carrier capacity available for decoded ciphertext payload bytes.'
                : 'Insufficient carrier capacity for decoded ciphertext payload bytes. Please upload more carriers.',
        ]);
    }

    /**
     * Validate whether stego-related files exist on the configured cloud disk.
     *
     * Supported payloads:
     * - { carrier_names: string[] }
     * - { stego_document_id: int }
     * - { stego_document_ids: int[] }
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function validateCloudFiles(Request $request): JsonResponse
    {
        $request->validate([
            'carrier_names' => ['nullable', 'array'],
            'carrier_names.*' => ['string', 'max:255'],
            'stego_document_id' => ['nullable', 'integer'],
            'stego_document_ids' => ['nullable', 'array'],
            'stego_document_ids.*' => ['integer'],
        ]);

        $carrierNames = $request->input('carrier_names', []);
        $singleDocId = $request->input('stego_document_id');
        $docIds = $request->input('stego_document_ids', []);

        if (empty($carrierNames) && empty($singleDocId) && empty($docIds)) {
            return response()->json([
                'message' => 'Provide carrier_names, stego_document_id, or stego_document_ids.',
            ], 422);
        }

        $user = Auth::user();
        $cloud = $this->cloudStorage();

        $checks = [];
        $overallValid = true;

        if (!empty($carrierNames)) {
            $carrierChecks = [];
            foreach ($carrierNames as $carrierName) {
                $normalizedName = basename((string) $carrierName);
                $key = $cloud->carrierKey($user->id, $normalizedName);
                $exists = $cloud->exists($key);

                $carrierChecks[] = [
                    'name' => $normalizedName,
                    'key' => $key,
                    'exists' => $exists,
                ];

                $overallValid = $overallValid && $exists;
            }

            $checks['carriers'] = [
                'all_in_cloud' => collect($carrierChecks)->every(fn (array $c) => $c['exists'] === true),
                'items' => $carrierChecks,
            ];
        }

        if (!empty($singleDocId)) {
            $docCheck = $this->buildDocumentCloudValidation((int) $singleDocId, $user->id);
            $checks['document'] = $docCheck;
            $overallValid = $overallValid && ($docCheck['all_segments_in_cloud'] ?? false);
        }

        if (!empty($docIds)) {
            $bulkChecks = [];
            foreach ($docIds as $docId) {
                $bulkChecks[] = $this->buildDocumentCloudValidation((int) $docId, $user->id);
            }

            $allBulkValid = collect($bulkChecks)->every(fn (array $item) => ($item['all_segments_in_cloud'] ?? false) === true);
            $checks['documents'] = [
                'all_in_cloud' => $allBulkValid,
                'items' => $bulkChecks,
            ];

            $overallValid = $overallValid && $allBulkValid;
        }

        return response()->json([
            'overall_valid' => $overallValid,
            'cloud_disk' => (string) config('stegolock.storage.disk', 'local'),
            'checks' => $checks,
        ]);
    }

    /**
     * Estimate decoding time for a stego document.
     *
     * Authorization: owner OR granted viewer.
     *
     * @param  int $id StegoDocument id
     * @return JsonResponse
     */
    public function estimateDecodingTime(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $stegoDoc = StegoDocument::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('viewerGrants', fn ($g) =>
                      $g->where('viewer_user_id', $user->id)
                  );
            })
            ->where('id', $id)
            ->firstOrFail();

        $estimatedTime = $this->stegoService->estimateDecodingTime($stegoDoc->id);

        return response()->json([
            'estimated_decoding_time' => $estimatedTime,
        ]);
    }

    public function revokeGrant(Request $request, int $id, int $viewerUserId): JsonResponse
    {
        $user = Auth::user();
        
        $stegoDoc = StegoDocument::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $grant = StegoDocumentGrant::where('stego_document_id', $stegoDoc->id)
            ->where('viewer_user_id', $viewerUserId)
            ->firstOrFail();

        $grant->delete();

        return response()->json(['message' => 'Grant revoked.']);
    }

    /**
     * @param  int $stegoDocumentId
     * @param  int $userId
     * @return array<string, mixed>
     */
    private function buildDocumentCloudValidation(int $stegoDocumentId, int $userId): array
    {
        $stegoDoc = StegoDocument::where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhereHas('viewerGrants', fn ($g) => $g->where('viewer_user_id', $userId));
            })
            ->where('id', $stegoDocumentId)
            ->with(['segments:id,stego_document_id,stego_carrier_id,segment_index,s3_key', 'segments.carrier:id,s3_key'])
            ->first();

        if (!$stegoDoc) {
            return [
                'stego_document_id' => $stegoDocumentId,
                'all_segments_in_cloud' => false,
                'error' => 'Stego document not found or access denied.',
            ];
        }

        $items = [];
        foreach ($stegoDoc->segments as $segment) {
            $key = $segment->s3_key ?: $segment->carrier?->s3_key;
            $keySource = $segment->s3_key
                ? 'segment.s3_key'
                : ($segment->carrier?->s3_key ? 'carrier.s3_key' : 'none');

            $exists = $key ? $this->cloudStorage()->exists($key) : false;

            $items[] = [
                'segment_index' => $segment->segment_index,
                'stego_carrier_id' => $segment->stego_carrier_id,
                'key' => $key,
                'key_source' => $keySource,
                'exists' => $exists,
            ];
        }

        $allSegmentsInCloud = !empty($items) && collect($items)->every(fn (array $item) => $item['exists'] === true);

        return [
            'stego_document_id' => $stegoDoc->id,
            'segments_total' => count($items),
            'all_segments_in_cloud' => $allSegmentsInCloud,
            'items' => $items,
        ];
    }

    private function cloudStorage(): CloudStorageService
    {
        if ($this->cloudStorage === null) {
            $this->cloudStorage = app(CloudStorageService::class);
        }

        return $this->cloudStorage;
    }
}
