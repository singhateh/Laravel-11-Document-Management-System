<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Concerns\HasStegoEncoding;
use App\Jobs\EncodeStegoDocumentJob;
use App\Jobs\DecodeStegoDocumentJob;
use App\Models\Document;
use App\Models\StegoDocument;
use App\Models\StegoDocumentGrant;
use App\Models\User;
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
     * Scoped to the authenticated user — returns 404 if not owned.
     *
     * @param  int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // Short TTL so pending→ready status transitions surface quickly.
        $doc = Cache::remember(
            "stego.doc.{$id}.u" . Auth::id(),
            30,
            fn () => Auth::user()
                ->stegoDocuments()
                ->with(['document', 'segments'])
                ->findOrFail($id)
        );

        return response()->json($doc);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/stego/{id}
    // -------------------------------------------------------------------------

    /**
     * Delete a stego document owned by the authenticated user.
     * Scoped query ensures users can only delete their own records.
     *
     * @param  int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $doc = Auth::user()->stegoDocuments()->findOrFail($id);
        $doc->delete();

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

        $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'carriers'    => ['required', 'array', 'min:1'],
            'carriers.*'  => ['required', 'file', 'mimes:png,bmp,jpeg,jpg', 'max:20480'],
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
        [$plainPath, $carrierPaths] = $this->stageForQueue($plaintext, $request->file('carriers'));

        EncodeStegoDocumentJob::dispatch(
            $user->id,
            $plainPath,
            $masterKey,
            $carrierPaths,
            $document->id,
            $pending->id,
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
     * @return JsonResponse      201 { message, grant } | 404 | 409 | 422
     */
    public function grant(Request $request, int $id): JsonResponse
    {
        // Ownership check — 404 if not the owner.
        $stegoDoc = Auth::user()->stegoDocuments()->findOrFail($id);

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
     * @return JsonResponse            200 | 404
     */
    public function revokeGrant(Request $request, int $id, int $viewerUserId): JsonResponse
    {
        // Ownership check — 404 if not the owner.
        $stegoDoc = Auth::user()->stegoDocuments()->findOrFail($id);

        $grant = StegoDocumentGrant::where('stego_document_id', $stegoDoc->id)
            ->where('viewer_user_id', $viewerUserId)
            ->firstOrFail();

        $grant->delete();

        return response()->json(['message' => 'Grant revoked.']);
    }
}
