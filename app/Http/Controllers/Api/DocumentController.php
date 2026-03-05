<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * DocumentController (API)
 *
 * REST API endpoints for document upload and retrieval.
 *
 * All routes require a valid Sanctum Bearer token (auth:sanctum middleware).
 * Audit entries (AccessLog) are written for every upload and view operation.
 *
 * Routes (registered in routes/api.php under /api):
 *   POST  /api/documents        — W3-T01: upload one or more files
 *   GET   /api/documents/{id}   — W3-T06: retrieve document metadata
 */
class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documentService) {}

    // -------------------------------------------------------------------------
    // W3-T09: GET /api/documents — Lightweight paginated document list
    // -------------------------------------------------------------------------

    /**
     * Return a lightweight paginated list of documents for the SPA Encode dropdown.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $docs = Document::select('id', 'name', 'extension', 'size')->latest()->paginate(50);
        return response()->json($docs);
    }

    // -------------------------------------------------------------------------
    // W3-T01: POST /api/documents — Upload document(s)
    // -------------------------------------------------------------------------

    /**
     * Upload one or more document files.
     *
     * Accepts multipart/form-data.
     *
     * Required fields:
     *   files[]    — one or more files (max 50 MB each)
     *   folder_id  — target folder ID (must exist in the folders table)
     *
     * Optional fields:
     *   visibility — 'public' (default) | 'private'
     *
     * Success: HTTP 201 with array of created document resources.
     * The files are stored under public/documents/{folder_id}/ and
     * encrypted at rest with AES-256-GCM when DOCUMENT_MASTER_KEY is set.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'files'      => ['required', 'array', 'min:1'],
            'files.*'    => ['required', 'file', 'max:51200'],
            'folder_id'  => ['required', 'integer', 'exists:folders,id'],
            'visibility' => ['sometimes', 'in:public,private'],
        ]);

        $user       = Auth::user();
        $folderId   = (int) $request->input('folder_id');
        $visibility = $request->input('visibility', 'public');
        $created    = [];

        DB::beginTransaction();

        try {
            foreach ($request->file('files') as $file) {
                $destDir  = 'documents/' . $folderId;
                $destPath = public_path($destDir);

                if (!file_exists($destPath)) {
                    mkdir($destPath, 0777, true);
                }

                $filename = $file->getClientOriginalName();
                $file->move($destPath, $filename);
                $relPath  = $destDir . '/' . $filename;

                $doc = Document::create([
                    'name'          => $filename,
                    'original_name' => $filename,
                    'extension'     => $file->getClientOriginalExtension(),
                    'file_path'     => $relPath,
                    'size'          => $file->getSize(),
                    'folder_id'     => $folderId,
                    'visibility'    => $visibility,
                    'owner_id'      => $user->id,
                    'document_date' => now(),
                ]);

                // W3-T02: encrypt at rest if DOCUMENT_MASTER_KEY is configured
                if ($this->documentService->isEncryptionEnabled()) {
                    $this->documentService->encryptDocumentFile($doc);
                    $doc->refresh();
                }

                $created[] = $this->documentResource($doc);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Upload failed: ' . $e->getMessage()], 500);
        }

        AccessLog::log('upload', 'document', implode(',', array_column($created, 'id')), $request, 201);

        return response()->json([
            'message'   => count($created) . ' document(s) uploaded successfully.',
            'documents' => $created,
        ], 201);
    }

    // -------------------------------------------------------------------------
    // W3-T06: GET /api/documents/{id} — Retrieve single document metadata
    // -------------------------------------------------------------------------

    /**
     * Return metadata for a single document identified by its primary key.
     *
     * Response includes: id, name, extension, size, visibility, folder_id,
     * owner_id, is_encrypted, sha256 hash, download_url, tags, timestamps.
     *
     * Returns 404 when the document does not exist (or is soft-deleted).
     * Returns 403 when the document is private and the caller is not the owner.
     *
     * @param  int     $id
     * @param  Request $request
     * @return JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $user = Auth::user();
        $doc  = Document::with('tags:id,name')->findOrFail($id);

        if ($doc->visibility === 'private' && $doc->owner_id !== $user->id) {
            return response()->json(['message' => 'Forbidden: this document is private.'], 403);
        }

        AccessLog::log('view', 'document', $doc->id, $request);

        return response()->json([
            'document' => $this->documentResource($doc),
        ]);
    }

    // -------------------------------------------------------------------------
    // Shared resource formatter
    // -------------------------------------------------------------------------

    /**
     * Serialize a Document model to a consistent API response array.
     * Used by both store() and show() to guarantee the same shape.
     */
    private function documentResource(Document $doc): array
    {
        return [
            'id'            => $doc->id,
            'name'          => $doc->name,
            'original_name' => $doc->original_name,
            'extension'     => $doc->extension,
            'size'          => $doc->size,
            'visibility'    => $doc->visibility,
            'folder_id'     => $doc->folder_id,
            'owner_id'      => $doc->owner_id,
            'is_encrypted'  => $doc->is_encrypted,
            'sha256'        => $doc->enc_hash_sha256,
            'url'           => $doc->url,
            'download_url'  => $doc->hasPhysicalFile()
                                ? url('/documents/' . $doc->id . '/download')
                                : null,
            'tags'          => $doc->tags
                                ?->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])
                                ?->all() ?? [],
            'created_at'    => $doc->created_at?->toISOString(),
            'updated_at'    => $doc->updated_at?->toISOString(),
        ];
    }
}
