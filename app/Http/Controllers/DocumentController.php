<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\User;
use App\Models\Folder;
use App\Models\Document;
use App\Models\ShareDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Services\DocumentService;
use App\Models\DocumentWatcher;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Http\JsonResponse;


class DocumentController extends Controller
{

    public function __construct(protected DocumentService $documentService)
    {
    }


    public function index()
    {
        $user = Auth::user();
        
        // Get all documents the user has permission to view
        $documents = Document::with('tags')
            ->where(function ($query) use ($user) {
                // Owner's documents
                $query->where('owner_id', $user->id)
                    // Shared documents
                    ->orWhereIn('id', function ($subquery) use ($user) {
                        $subquery->select('share_id')
                            ->from('share_documents')
                            ->where('user_id', $user->id);
                    });
                    
                // Admin can see all documents
                if ($user->isAdmin()) {
                    $query->orWhere('visibility', 'private');
                }
                
                // Check for documents in shared folders
                $sharedFolderIds = ShareDocument::where('user_id', $user->id)
                    ->where('slug', 'folder')
                    ->pluck('share_id');
                    
                $query->orWhereIn('folder_id', $sharedFolderIds);
            })
            ->latest()
            ->get();

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
        $validated = $request->validate([
            'folder_id' => ['required', 'exists:folders,id'],
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer', 'exists:documents,id'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $this->documentService->setUpdateDocumentOrder($validated['folder_id'], $validated['document_ids']);
            });

            return response()->json(['url' => route('getFiles', $validated['folder_id'])], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }


    public function getFiles($folder)
    {
        $tags   = request()->tags ?? [];
        $result = $this->documentService->getFolderFiles($folder, $tags);

        // Return JSON data for React/Inertia frontend
        return response()->json([
            'documents'  => $result['documents'],
            'folderInfo' => $result['folderInfo'],
            'folders'    => $result['folderData'],
            'folder_id'  => $folder,
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
        $validated = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'visibility' => ['required', 'in:public,private'],
        ]);

        $document = Document::findOrFail($validated['document_id']);
        $this->authorize('update', $document);

        $document->update([
            'visibility' => $validated['visibility'] === 'private' ? 'public' : 'private',
        ]);

        return response()->json([
            'message' => 'Visibility updated successfully',
            'visibility' => $document->visibility,
            'url' => route('getFiles', $document->folder_id)
        ]);
    }


    public function sendDocumentEmail(Request $request)
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:100'],
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'content' => ['required', 'string'],
            'user_email' => ['nullable', 'email', 'max:255'],
        ]);

        $notifications = $this->documentService->setSendDocumentEmail($request);

        // Return JSON data for React frontend
        return response()->json([
            'message' => 'Email sent successfully!',
            'notifications' => $notifications
        ]);
    }


    public function getDocumentComments(Request $request)
    {
        $validated = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
        ]);

        $notifications = $this->documentService->getDocumentNotifications($validated['document_id']);

        // Return JSON data for React frontend
        return response()->json(['notifications' => $notifications]);
    }



    public function uploadDocumentFiles(StoreDocumentRequest $request)
    {
        Log::info('Upload request received:', [
            'has_files' => $request->hasFile('files'),
            'has_files_array' => $request->hasFile('files[]'),
            'files' => $request->file('files'),
            'files_array' => $request->file('files[]'),
            'all' => $request->all(),
        ]);
        
        try {
            // Handle both single file and array formats
            if (!$request->hasFile('files') && $request->hasFile('files[]')) {
                $request->merge(['files' => $request->file('files[]')]);
            }
            
            $folderId = $this->documentService->setUploadDocumentFiles($request);

            AccessLog::log('upload', 'document', $folderId, $request);

            return response()->json(['message' => 'Files uploaded successfully', 'url' => route('getFiles', $folderId)], 200);
        } catch (\InvalidArgumentException $e) {
            Log::error('Validation error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Upload error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'File upload failed: ' . $e->getMessage()], 500);
        }
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

        // Check if user has permission to view/download the document
        if (!$user->can('view', $document)) {
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

        AccessLog::log('download', 'document', $document->id, request());

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

    /**
     * Serve a document inline (for in-browser preview), decrypting if necessary.
     * Uses Content-Disposition: inline so the browser renders it instead of downloading.
     */
    public function view(Document $document)
    {
        $user = Auth::user();

        // Check if user has permission to view the document
        if (!$user->can('view', $document)) {
            abort(403, 'You do not have permission to view this document.');
        }

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

        // Derive MIME type from extension so it is correct even when the file
        // on disk is encrypted (raw ciphertext bytes would fool mime_content_type).
        $extensionMimeMap = [
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            'bmp'  => 'image/bmp',
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
            'mov'  => 'video/quicktime',
            'avi'  => 'video/x-msvideo',
            'ogg'  => 'video/ogg',
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'm4a'  => 'audio/mp4',
            'txt'  => 'text/plain',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        $ext      = strtolower($document->extension ?? pathinfo($document->file_path, PATHINFO_EXTENSION));
        $mimeType = $extensionMimeMap[$ext]
            ?? ($document->is_encrypted ? 'application/octet-stream' : (mime_content_type($absolutePath) ?: 'application/octet-stream'));

        $filename = $document->original_name ?? $document->name;

        AccessLog::log('view', 'document', $document->id, request());

        return response($content, 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
            'Content-Length'      => strlen($content),
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document)
    {
        // Check if user has permission to update the document
        if (!Auth::user()->can('update', $document)) {
            abort(403, 'You do not have permission to update this document.');
        }

        $validated = $request->validated();
        $user = Auth::user();

        $document->update(array_merge($validated, [
            'last_updated_at' => now(),
            'last_updated_by_user_id' => $user->id,
        ]));

        // Notify watchers about the update
        $this->notifyWatchers($document, $user);

        return response()->json([
            'message' => 'Document updated successfully',
            'document' => $document->fresh(['tags']),
        ]);
    }

    private function notifyWatchers(Document $document, $user)
    {
        $watchers = $document->watchers()->with('user')->get();
        
        foreach ($watchers as $watcher) {
            if ($watcher->user->id !== $user->id) {
                Notification::create([
                    'notifiable_id' => $watcher->user->id,
                    'notifiable_type' => User::class,
                    'activity_type' => 'document_updated',
                    'model_type' => Document::class,
                    'model_id' => $document->id,
                    'message' => "Document '{$document->name}' has been updated by {$user->name}",
                    'status' => 'UNREAD',
                    'dismiss_status' => 'UNDISMISSED',
                    'created_by_user_id' => $user->id,
                ]);
            }
        }
    }

    public function watch(Document $document)
    {
        $user = Auth::user();
        
        if (!$user->can('view', $document)) {
            abort(403, 'You do not have permission to watch this document.');
        }

        DocumentWatcher::firstOrCreate([
            'user_id' => $user->id,
            'document_id' => $document->id,
        ]);

        return response()->json(['message' => 'Document added to watch list']);
    }

    public function unwatch(Document $document)
    {
        $user = Auth::user();
        
        DocumentWatcher::where('user_id', $user->id)
            ->where('document_id', $document->id)
            ->delete();

        return response()->json(['message' => 'Document removed from watch list']);
    }

    public function isWatched(Document $document)
    {
        $user = Auth::user();
        $isWatched = $document->isWatchedByUser($user->id);

        return response()->json(['is_watched' => $isWatched]);
    }

    public function destroy(Document $document)
    {
        // Check if user has permission to delete the document
        if (!Auth::user()->can('delete', $document)) {
            abort(403, 'You do not have permission to delete this document.');
        }

        // Delete physical file
        $document->deleteFile();
        
        // Delete database record
        $document->delete();

        return response()->json(['message' => 'Document deleted successfully']);
    }

    public function changeFile(Request $request)
    {
        $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'folder_id' => ['required', 'integer', 'exists:folders,id'],
            'type' => ['required', 'in:file_name,owner,archive,file,folder'],
            'data' => ['nullable'],
            'file' => ['nullable', 'file'],
        ]);

        $documentId = $request->input('document_id');
        $document = Document::find($documentId);
        $user = Auth::user();

        $folderId = $this->documentService->setChangeFile($request);

        if ($folderId instanceof JsonResponse) {
            return $folderId;
        }

        // Notify watchers about the update
        $this->notifyWatchers($document, $user);

        return response()->json(['message' => 'Document updated successfully', 'url' => route('getFiles', $folderId)], 200);
    }
}