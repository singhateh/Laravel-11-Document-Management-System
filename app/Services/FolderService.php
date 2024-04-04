<?php

namespace App\Services;

use App\Models\User;
use App\Models\Folder;
use App\Models\Document;
use App\Models\Notification;
use App\Jobs\SendDocumentJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ZipArchive;


class FolderService
{

    public function setStoreFolder($request)
    {
        if (!empty($request->parent_id)) {
            Folder::firstOrCreate(['name' => $request->folder_name, 'parent_id' => $request->parent_id]);
        } else {
            Folder::firstOrCreate(['name' => $request->folder_name]);
        }

        // Reload the folders after creating the new one
        return  $this->getParentFolders();
    }

    function getParentFolders()
    {
        $folders = Folder::with(['categories'])->whereNull('parent_id')->get();
        return view('folders.table', compact('folders'))->render();
    }


    public function setUpdateFolderPositions($request)
    {
        $positions = $request->positions;

        foreach ($positions as $folderId => $position) {
            $folder = Folder::find($folderId);
            if ($folder) {
                $folder->update(['position' => $position]);
            }
        }

        return $positions;
    }


    public function setUpdateFolderChildPositions($request)
    {
        $parentId = $request->parent_id;
        $positions = $request->positions;

        $parentFolder = Folder::find($parentId);
        if (!$parentFolder) {
            return response()->json(['error' => 'Parent folder not found'], 404);
        }

        foreach ($positions as $folderId => $position) {
            $childFolder = $parentFolder->subfolders()->find($folderId);
            if ($childFolder) {
                $childFolder->update(['position' => $position]);
            }
        }

        return $parentFolder;
    }


    public function setDownloadZip($request)
    {
        // Generate a unique name for the zip file
        $zipFileName = 'LLaMediaDocument_' . time() . '.zip';

        // Create a new ZipArchive instance
        $zip = new \ZipArchive();

        // Create the path where the ZIP file will be stored
        $zipFilePath = storage_path('app/' . $zipFileName);

        // Open the ZIP file for writing
        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            // Add each folder to the zip file
            foreach ($request->folders as $folder) {
                $this->addFolderToZip($zip, $folder);
            }
            $zip->close();

            return compact('zipFilePath', 'zipFileName');
        } else {
            return false;
        }
    }

    private function addFolderToZip(ZipArchive $zip, $folder)
    {
        if (isset($folder['documents'])) {
            foreach ($folder['documents'] as $file) {
                $filePath = public_path($file['file_path']);
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $folder['name'] . '/' . $file['original_name']);
                } else {
                    // Log a warning if the file does not exist
                    Log::warning('File does not exist: ' . $filePath);
                }
            }
        }

        // Add subfolders recursively
        if (isset($folder['subfolders'])) {
            foreach ($folder['subfolders'] as $subfolder) {
                $this->addFolderToZip($zip, $subfolder);
            }
        }
    }

    function deleteSelecetdFolder($request)
    {
        // Retrieve folder instance
        $folders = Folder::findOrFail($request->folder_ids);

        foreach ($folders as $key => $folder) {
            $folder->deleteFolder();
        }

        return response()->json(['html' =>  $this->getParentFolders(), 'message' => 'Folder and its related records deleted successfully'], 200);
    }
}