<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Concerns\HasStegoEncoding;
use App\Models\Document;
use App\Models\StegoDocument;
use App\Services\Stego\StegoDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * StegoDocumentController (API)
 *
 * Handles CRUD + encode/decode operations for StegoDocument resources.
 *
 * Routes (registered in routes/api.php):
 *   GET    /api/stego/documents        — paginated list for authenticated user
 *   GET    /api/stego/documents/{id}   — single doc with segments
 *   POST   /api/stego/encode           — encrypt + embed document into carriers
 *   POST   /api/stego/decode           — extract + decrypt → file download
 *   DELETE /api/stego/{id}             — delete (ownership enforced)
 *   GET    /api/stego                  — SPA alias → same as index()
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
        $docs = Auth::user()
            ->stegoDocuments()
            ->with(['document:id,name,extension'])
            ->withCount('segments')
            ->latest()
            ->paginate(20);

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
        $doc = Auth::user()
            ->stegoDocuments()
            ->with(['document', 'segments'])
            ->findOrFail($id);

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
            $plaintext    = $this->readDocumentPlaintext($document);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $carrierPaths = $this->storeCarriersTmp($request->file('carriers'));

        try {
            $result = $this->stegoService->encode(
                $user->id,
                $plaintext,
                $masterKey,
                $carrierPaths,
                $document->id
            );

            $stegoDoc = $result['stego_document'];

            return response()->json([
                'stego_document_id' => $stegoDoc->id,
                'document_id'       => $document->id,
                'document_name'     => $document->name,
                'segments_count'    => $stegoDoc->segments()->count(),
                'quality_metrics'   => $result['quality_metrics'],
                'created_at'        => $stegoDoc->created_at?->toISOString(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Encoding failed: ' . $e->getMessage()], 422);
        } finally {
            $this->releaseCarriers($carrierPaths);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/stego/decode
    // -------------------------------------------------------------------------

    /**
     * Extract + decrypt a previously encoded StegoDocument and return the
     * original file as a download.
     *
     * Reads the Master Key from the server-side session (populated at login via MKD).
     *
     * @param  Request $request  { stego_document_id: int }
     * @return StreamedResponse|JsonResponse
     */
    public function decode(Request $request): StreamedResponse|JsonResponse
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

        // Ownership check before decoding.
        $stegoDoc = StegoDocument::where('user_id', $user->id)
            ->where('id', $request->stego_document_id)
            ->with('document')
            ->firstOrFail();

        try {
            $plaintext = $this->stegoService->decode(
                $request->stego_document_id,
                $masterKey,
                $user->id
            );
        } catch (\Exception $e) {
            return response()->json(['message' => 'Decoding failed: ' . $e->getMessage()], 422);
        }

        $filename = $this->buildDecodeFilename($stegoDoc);

        return response()->streamDownload(
            fn () => print($plaintext),
            $filename,
            ['Content-Type' => 'application/octet-stream']
        );
    }
}
