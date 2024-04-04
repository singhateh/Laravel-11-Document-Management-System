<?php

namespace App\Http\Controllers;

use ZipArchive;
use App\Models\Folder;
use Illuminate\Http\Request;
use App\Http\Requests\StoreFolderRequest;
use App\Services\FolderService;

class FolderController extends Controller
{

    public function __construct(protected FolderService $folderService)
    {
    }


    public function create()
    {
        $folders = Folder::with('categories', 'subfolders')->whereNull('parent_id')->get();

        return view('folders.create', compact('folders'));
    }


    public function store(StoreFolderRequest $request)
    {
        $folders = $this->folderService->setStoreFolder($request);

        return response()->json(['html' => $folders]);
    }



    public function updateFolderPositions(Request $request)
    {
        $this->folderService->setUpdateFolderPositions($request);

        return response()->json(['message' => 'Positions updated successfully for parent rows']);
    }


    public function updateFolderChildPositions(Request $request)
    {
        $this->folderService->setUpdateFolderChildPositions($request);

        return response()->json(['message' => 'Positions updated successfully for child rows']);
    }



    public function fetchDetails(Request $request)
    {
        $request->validate([
            'folder_ids' => 'required|array'
        ]);

        // Fetch folder details based on the received IDs
        $folders = Folder::with('documents')->whereIn('id', $request->folder_ids)->get();

        // Return folder details to frontend
        return response()->json(['folders' => $folders]);
    }



    public function downloadZip(Request $request)
    {
        $request->validate([
            'folders' => 'required|array',
        ]);

        $zipFilePath = $this->folderService->setDownloadZip($request)['zipFilePath'];
        $zipFileName = $this->folderService->setDownloadZip($request)['zipFileName'];

        if ($zipFilePath) {
            return response()->download($zipFilePath, $zipFileName)->deleteFileAfterSend(true);
        } else {
            return response()->json(['error' => 'Error generating zip file. The folder may be empty, and you cannot create a zip file from an empty folder.'], 400);
        }
    }


    function deleteSelecetdFolder(Request $request)
    {
        // Retrieve folder instance
        $folders = Folder::findOrFail($request->folder_ids);

        foreach ($folders as $key => $folder) {
            $folder->deleteFolder();
        }

        return response()->json(['html' =>  $this->getParentFolders(), 'message' => 'Folder and its related records deleted successfully'], 200);
    }


    function getParentFolders()
    {
        $folders = Folder::with(['categories'])->whereNull('parent_id')->get();
        return view('folders.table', compact('folders'))->render();
    }
}