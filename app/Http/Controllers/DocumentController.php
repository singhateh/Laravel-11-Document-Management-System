<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\User;
use App\Models\Folder;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreDocumentRequest;
use App\Services\DocumentService;
use Inertia\Inertia;


class DocumentController extends Controller
{

    public function __construct(protected DocumentService $documentService)
    {
    }


    public function index()
    {
        $documents = Document::with('tags')
            ->latest()->get();

        $folders = generateSidebarMenu();
        $owners = User::get(['id', 'name', 'email']);
        $rightFolders = Folder::get(['id', 'name']);

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'folders' => $folders,
            'owners' => $owners,
            'rightFolders' => $rightFolders,
        ]);
    }


    public function updateDocumentOrder(Request $request)
    {
        try {

            // Begin transaction
            DB::beginTransaction();

            $folderId = $request->folder_id;

            $this->documentService->setUpdateDocumentOrder($folderId, $request->document_ids);

            // Commit transaction
            DB::commit();

            return response()->json(['url' => route('getFiles', $folderId)], 200);
        } catch (\Throwable $th) {
            // Rollback transaction on failure
            DB::rollback();
            return response()->json(['error' => $th->getMessage()]);
        }
    }


    public function getFiles($folder)
    {
        $tags = request()->tags ?? [];

        $documents = $this->documentService->getFolderFiles($folder,  $tags)['documents'];
        $folderInfo =  $this->documentService->getFolderFiles($folder,  $tags)['folderInfo'];
        $folderData =  $this->documentService->getFolderFiles($folder,  $tags)['folderData'];

        // Return JSON data for React/Inertia frontend
        return response()->json([
            'documents' => $documents,
            'folderInfo' => $folderInfo,
            'folders' => $folderData,
            'folder_id' => $folder,
        ]);
    }


    function filterDocumentByTag(Request $request)
    {
        $documents = $this->documentService->setFilterDocumentByTag($request->folder,  $request->tags ?? []);

        // Return JSON data for React frontend
        return response()->json(['documents' => $documents]);
    }


    public function updateVisibility(Request $request)
    {
        $documentId = $request->input('document_id');
        $visibility = $request->input('visibility');

        // Update the visibility of the document
        $document = Document::find($documentId);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $document->update(['visibility' => $visibility === 'private' ? 'public' : 'private']);

        return response()->json([
            'message' => 'Visibility updated successfully',
            'visibility' => $document->visibility,
            'url' => route('getFiles', $document->folder_id)
        ]);
    }


    public function sendDocumentEmail(Request $request)
    {
        $notifications = $this->documentService->setSendDocumentEmail($request);

        // Return JSON data for React frontend
        return response()->json([
            'message' => 'Email sent successfully!',
            'notifications' => $notifications
        ]);
    }


    public function getDocumentComments(Request $request)
    {
        $notifications = $this->documentService->getDocumentNotifications($request->document_id);

        // Return JSON data for React frontend
        return response()->json(['notifications' => $notifications]);
    }



    public function uploadDocumentFiles(StoreDocumentRequest $request)
    {
        $folderId = $this->documentService->setUploadDocumentFiles($request);

        // W3-T12: Audit log — record the upload action
        AccessLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'upload',
            'resource'    => 'document',
            'resource_id' => (string) $folderId,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'method'      => $request->method(),
            'url'         => $request->fullUrl(),
            'status_code' => 200,
            'accessed_at' => now(),
        ]);

        return response()->json(['message' => 'Files uploaded successfully', 'url' => route('getFiles', $folderId)], 200);
    }

    public function show(Document $document)
    {
        $document->load(['tags', 'comments']);

        return Inertia::render('Documents/Show', [
            'document' => $document,
        ]);
    }

    /**
     * Download a document, decrypting it first if it was stored with AES-256-GCM.
     *
     * - Encrypted documents are decrypted in memory and streamed to the browser.
     * - Legacy plaintext documents are served directly.
     * - URL-type documents (YouTube, etc.) redirect to the external URL.
     */
    public function download(Document $document)
    {
        $user = Auth::user();

        // Only the owner or admins may download private documents.
        if ($document->visibility === 'private' && $document->owner_id !== $user->id) {
            abort(403, 'You do not have permission to download this document.');
        }

        // For link-type documents, redirect to the external URL.
        if (!empty($document->url)) {
            return redirect($document->url);
        }

        $absolutePath = public_path($document->file_path);

        if (!file_exists($absolutePath)) {
            abort(404, 'The requested file could not be found on the server.');
        }

        try {
            $content = $this->documentService->decryptDocumentContent($document);
        } catch (\Exception $e) {
            abort(500, 'Failed to retrieve document: ' . $e->getMessage());
        }

        // W3-T12: Audit log — record the download action
        AccessLog::create([
            'user_id'     => $user->id,
            'action'      => 'download',
            'resource'    => 'document',
            'resource_id' => $document->id,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
            'method'      => request()->method(),
            'url'         => request()->fullUrl(),
            'status_code' => 200,
            'accessed_at' => now(),
        ]);

        $filename = $document->original_name ?? $document->name;
        $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';

        return response($content, 200, [
            'Content-Type'              => $mimeType,
            'Content-Disposition'       => 'attachment; filename="' . addslashes($filename) . '"',
            'Content-Length'            => strlen($content),
            'Cache-Control'             => 'no-store, no-cache, must-revalidate',
            'X-Encryption-Status'       => $document->is_encrypted ? 'AES-256-GCM' : 'plaintext',
        ]);
    }

    public function update(Request $request, Document $document)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'visibility' => 'sometimes|in:public,private',
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        $document->update($validated);

        return response()->json([
            'message' => 'Document updated successfully',
            'document' => $document->fresh(['tags']),
        ]);
    }

    public function destroy(Document $document)
    {
        // Delete physical file
        $document->deleteFile();
        
        // Delete database record
        $document->delete();

        return response()->json(['message' => 'Document deleted successfully']);
    }

    function changeFile(Request $request)
    {
        $folderId = $this->documentService->setChangeFile($request);

        return response()->json(['message' => 'Document updated successfully', 'url' => route('getFiles', $folderId)], 200);
    }
}