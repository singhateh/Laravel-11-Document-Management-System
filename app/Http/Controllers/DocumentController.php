<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Folder;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreDocumentRequest;
use App\Services\DocumentService;


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

        return view('documents.index', compact('documents', 'folders', 'owners', 'rightFolders'));
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

        // dd($folderInfo);

        
        $view = view('documents.contents', ['documents' => $documents])->render();
        $folderInfo = view('folders.info', ['folderInfo' => $folderInfo])->render();
        $folders = view('layouts.sidebar', ['folders' => $folderData])->render();
        $categoriesView = view('folders.categories', ['folder' => $folder])->render();


        return response()->json([
            'html' => $view, 'folderInfoHml' => $folderInfo,
            'folder_id' => $folder, 'folderHtml' => $folders, 'categoriesHtml' => $categoriesView
        ]);
    }


    function filterDocumentByTag(Request $request)
    {
        $documents = $this->documentService->setFilterDocumentByTag($request->folder,  $request->tags ?? []);

        $view = view('documents.contents', ['documents' => $documents])->render();

        return response()->json(['html' => $view]);
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

        $view = view('documents.comments', compact('notifications'))->render();

        return response()->json(['message' => 'Email sent successfully!', 'html' => $view]);
    }


    public function getDocumentComments(Request $request)
    {
        $notifications = $this->documentService->getDocumentNotifications($request->document_id);

        $view = view('documents.comments', compact('notifications'))->render();

        return response()->json(['html' => $view]);
    }



    public function uploadDocumentFiles(StoreDocumentRequest $request)
    {
        $folderId = $this->documentService->setUploadDocumentFiles($request);

        return response()->json(['message' => 'Files uploaded successfully', 'url' => route('getFiles', $folderId)], 200);
    }


    function changeFile(Request $request)
    {
        $folderId = $this->documentService->setChangeFile($request);

        return response()->json(['message' => 'Document updated successfully', 'url' => route('getFiles', $folderId)], 200);
    }
}