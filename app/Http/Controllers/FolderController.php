<?php

namespace App\Http\Controllers;

use ZipArchive;
use App\Models\Folder;
use Illuminate\Http\Request;
use App\Http\Requests\StoreFolderRequest;
use App\Services\FolderService;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class FolderController extends Controller
{

    public function __construct(protected FolderService $folderService)
    {
    }


    public function index()
    {
        $user = Auth::user();
        
        // Get all folders the user has permission to view
        $folders = Folder::with(['categories', 'subfolders.categories', 'subfolders.subfolders'])
            ->whereNull('parent_id')
            ->where(function ($query) use ($user) {
                // Check if user is admin - can view all folders
                if ($user->isAdmin()) {
                    return;
                }
                
                // Check if folder is public or user has access to it
                $query->where(function ($q) use ($user) {
                    $q->where('visibility', 'public')
                        ->orWhere(function ($subq) use ($user) {
                            // Check if user has documents in this folder
                            $subq->whereHas('documents', function ($docQuery) use ($user) {
                                $docQuery->where('owner_id', $user->id);
                            });
                        })
                        ->orWhere(function ($subq) use ($user) {
                            // Check if folder is shared with user
                            $subq->whereIn('id', function ($shareQuery) use ($user) {
                                $shareQuery->select('share_id')
                                    ->from('share_documents')
                                    ->where('user_id', $user->id)
                                    ->where('slug', 'folder');
                            });
                        });
                });
            })
            ->orderBy('position')
            ->get();

        return Inertia::render('Folders/Index', [
            'folders' => $folders,
        ]);
    }

    public function create()
    {
        $folders = Folder::with('categories', 'subfolders')->whereNull('parent_id')->get();

        return Inertia::render('Folders/Create', [
            'folders' => $folders,
        ]);
    }


    public function store(StoreFolderRequest $request)
    {
        $folders = $this->folderService->setStoreFolder($request);

        if ($request->header('X-Inertia')) {
            return redirect()->route('folders.index');
        }

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

        if ($request->header('X-Inertia')) {
            return redirect()->route('folders.index');
        }

        return response()->json(['html' =>  $this->getParentFolders(), 'message' => 'Folder and its related records deleted successfully'], 200);
    }


    function getParentFolders()
    {
        $folders = Folder::with(['categories'])->whereNull('parent_id')->get();
        
        // Return JSON data for React frontend instead of rendered HTML
        return response()->json(['folders' => $folders]);
    }
}