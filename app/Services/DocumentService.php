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

class DocumentService
{

    public function setUpdateDocumentOrder($folderId, $documentIds)
    {
        foreach ($documentIds as $position => $documentId) {
            Document::whereFolderId($folderId)->whereId($documentId)->update(['position' => $position]);
        }

        return $folderId;
    }

    public function getFolderFiles($folderId, $tags = [])
    {
        $documentTags = DB::table('document_tag')
            ->whereIn('tag_id', $tags)
            ->pluck('document_id')
            ->values()
            ->toArray() ?? [];

        $documents = Document::whereFolderId($folderId)
            ->when(count($documentTags) > 0, function ($query) use ($documentTags) {
                $query->whereIn('id', $documentTags);
            })
            ->with('tags')
            ->latest()
            ->get();

        $folderInfo = $this->getFolderInfo($folderId);
        $folderData = generateSidebarMenu();

        return compact('documents', 'folderInfo', 'folderData');
    }

    public function setFilterDocumentByTag($folderId, $tags = [])
    {
        $documentTags = DB::table('document_tag')
            ->whereIn('tag_id', $tags)
            ->pluck('document_id')
            ->values()
            ->toArray() ?? [];

        $documents = Document::whereFolderId($folderId)
            ->when(count($documentTags) > 0, function ($query) use ($documentTags) {
                $query->whereIn('id', $documentTags);
            })
            ->with('tags')
            ->latest()
            ->get();

        return $documents->isEmpty() ? [] : $documents;
    }


    public function setSendDocumentEmail($request)
    {
        $details = [
            'title' => $request->title,
            'body' => $request->body,
        ];

        $auth = Auth::user();

        $tagUser = User::whereEmail($request->user_email)->first();

        Notification::create([
            'user_id' => $tagUser?->id,
            'user_type' => $tagUser ? User::class : Null,
            'activity_type' => $request->type,
            'model_type' => Document::class,
            'model_id' => $request->document_id,
            'message' => $request->content,
            'created_by_type' => User::class ?? Null,
            'created_by_id' => $auth->id ?? Null,
        ]);

        if ($request->user_email) {
            dispatch(new SendDocumentJob($details));
        }

        return $this->getDocumentNotifications($request->document_id);
    }


    public function getDocumentNotifications($documentId)
    {
        return Notification::with('user')
            ->select('id', 'user_id', 'user_type', 'activity_type', 'model_type', 'model_id', 'message', 'status', 'dismiss_status', 'created_by_type', 'created_by_id', 'created_at')
            ->selectRaw("DATE_FORMAT(created_at, '%M %e %Y') as date, COUNT(*) as count")
            ->where('model_id', $documentId)
            ->groupBy('date', 'id', 'user_id', 'user_type', 'activity_type', 'model_type', 'model_id', 'message', 'status', 'dismiss_status', 'created_by_type', 'created_by_id', 'created_at')
            ->latest()
            ->get();
    }


    public function setUploadDocumentFiles($request)
    {
        $folderId = $request->input('folder_id');

        if ($request->has('url')) {
            $this->uploadUrl($request);
        } elseif ($request->has('folder_name')) {
            $folderId = $this->uploadFolder($request);
        } else {
            $this->uploadFiles($request);
        }

        return  $folderId;
    }


    public function setChangeFile($request)
    {
        $documentId = $request->input('document_id');
        $type = $request->input('type');
        $requestData = $request->input('data');
        $document = Document::find($documentId);
        $documentName = $request->input('name');
        $folderId = $request->input('folder_id');
        $folder = Folder::find($folderId);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!$folder) {
            return response()->json(['message' => 'Folder not found'], 404);
        }

        if ($type === 'file_name') {
            $document->update(['name' => $requestData]);
        }

        if ($type === 'owner') {
            $document->update(['owner' => $requestData]);
        }

        if ($type === 'archive') {
            $document->delete();
        }

        if ($request->hasFile('file') && $type === 'file') {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileSize = $file->getSize();

            if (!$file->isValid()) {
                // Handle the case where the file is not valid (e.g., it doesn't exist or cannot be accessed)
                Log::error("File {$fileName} is not valid");
                return response()->json(['message' => 'File is not valid'], 400);
            }

            // Check if the file already exists in the folder
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $parentStringFolderPath = 'documents/' . $folder->name . '/' . $fileName;
            // Store the file directly in the 'uploads' folder
            $file->move(public_path('documents/' . $folder->name), $fileName);

            $document->update([
                'file_path' => $parentStringFolderPath,
                'original_name' => $fileName,
                'size' => $fileSize,
                'extension' => $extension,
            ]);
        }

        if ($type === 'folder') {

            $parentStringFolderPath = 'documents/' . $folder->name . '/' . $document->original_name;

            // Move the file to the new folder
            Storage::disk('document_public')->move($document->file_path, $parentStringFolderPath);

            $document->update([
                'folder' => $folderId,
                'file_path' => $parentStringFolderPath,
            ]);
        }

        return $folderId;
    }


    protected function uploadFolder($request)
    {
        // dd($request);
        // Get the folder ID from the request
        $folderId = $request->input('folder_id');
        $parentFolderName = Folder::find($folderId)?->name;
        $createdChildFolder = null;
        $childFolderName =  $request->input('folder_name') ?? uniqid();
        $visibility =  $request->input('visibility') ?? 'public';
        $parentFolder = public_path('documents/' . $parentFolderName);


        // Handle the file upload
        if ($request->hasFile('files')) {

            foreach ($request->file('files') as $file) {

                if ($file->isValid()) {
                    // Get the size of the file
                    $fileSize = $file->getSize();
                } else {
                    // Handle the case where the file is not valid (e.g., it doesn't exist or cannot be accessed)
                    Log::error("File {$file->getClientOriginalName()} is not valid");
                    continue; // Skip processing this file and move to the next one
                }

                // Create the parent folder if it doesn't exist
                if (!file_exists($parentFolder)) {
                    mkdir($parentFolder, 0777, true);
                }

                // Create a unique ID for the child folder
                $childFolder = $parentFolder . '/' . $childFolderName;
                $parentStringFolderPath = 'documents/' . $parentFolderName . '/' . $childFolderName . '/' . $file->getClientOriginalName();

                // Create the child folder if it doesn't exist
                if (!file_exists($childFolder) && is_null($createdChildFolder)) {
                    mkdir($childFolder, 0777, true);

                    // This should create only once
                    $createdFolder = Folder::create([
                        'name' => $childFolderName,
                        'parent_id' => $folderId,
                        'visibility' => $visibility
                    ]);

                    $createdChildFolder = $createdFolder->id;
                    $folderId = $createdChildFolder;
                }

                // Check if the file already exists in the folder
                if (Storage::disk('public')->exists($parentStringFolderPath)) {
                    continue; // Skip processing this file if it already exists
                }

                // Store the file in the child folder
                $file->move($childFolder, $file->getClientOriginalName());

                // Create a new document record
                $document = new Document();
                $document->name = $file->getClientOriginalName();
                $document->original_name = $file->getClientOriginalName();
                $document->extension = $file->getClientOriginalExtension();
                $document->file_path = $parentStringFolderPath;
                $document->size =  $fileSize;
                $document->folder_id = $createdChildFolder;
                $document->visibility = 'public';
                $document->owner = User::first()->id;
                $document->date = now();
                $document->save();
            }
            return $createdChildFolder;
        } else {
            // No files uploaded
            return response()->json(['message' => 'No files uploaded'], 400);
        }
    }

    protected function uploadFiles($request)
    {
        // Get the folder ID from the request
        $folderId = $request->input('folder_id');
        $folderName = Folder::find($folderId)->name;
        $createdChildFolder = null;
        $childFolderName = uniqid();
        $parentFolder = public_path('documents/' . $folderName);


        // Handle the file upload
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {


                if ($file->isValid()) {
                    // Get the size of the file
                    $fileSize = $file->getSize();
                } else {
                    // Handle the case where the file is not valid (e.g., it doesn't exist or cannot be accessed)
                    Log::error("File {$file->getClientOriginalName()} is not valid");
                    continue; // Skip processing this file and move to the next one
                }

                // Check if type is folder and folder_id is present
                if ($request->input('type') === 'folder' && $folderId) {

                    // Create the child folder if it doesn't exist
                    if (!file_exists($parentFolder)) {
                        mkdir($parentFolder, 0777, true);
                    }

                    // Create a unique ID for the child folder
                    $childFolder = $parentFolder . '/' . $childFolderName;
                    $parentStringFolderPath = 'documents/' . $folderName . '/' . $childFolderName . '/' . $file->getClientOriginalName();

                    // Create the child folder if it doesn't exist
                    if (!file_exists($childFolder) && is_null($createdChildFolder)) {
                        mkdir($childFolder, 0777, true);

                        // This should create only once
                        $createdFolder = Folder::create(['name' => $childFolderName, 'parent_id' => $folderId, 'visibility' => 'public']);

                        $createdChildFolder = $createdFolder->id;
                        $folderId = $createdChildFolder;
                    }

                    // Check if the file already exists in the folder
                    if (Storage::disk('public')->exists($parentStringFolderPath)) {
                        continue; // Skip processing this file if it already exists
                    }

                    // Store the file in the child folder
                    $file->move($childFolder, $file->getClientOriginalName());

                    // Create a new document record
                    $document = new Document();
                    $document->name = $file->getClientOriginalName();
                    $document->original_name = $file->getClientOriginalName();
                    $document->extension = $file->getClientOriginalExtension();
                    $document->file_path = $parentStringFolderPath;
                    $document->size =  $fileSize;
                    $document->folder_id = $createdChildFolder;
                    $document->visibility = 'public';
                    $document->owner = User::first()->id;
                    $document->date = now();
                    $document->save();
                } else {

                    $parentStringFolderPath = 'documents/' . $folderName . '/' . $file->getClientOriginalName();
                    // Store the file directly in the 'uploads' folder
                    $file->move($parentFolder, $file->getClientOriginalName());

                    // Check if the file already exists in the folder
                    if (Storage::disk('public')->exists($parentStringFolderPath)) {
                        continue; // Skip processing this file if it already exists
                    }

                    // Create a new document record
                    $document = new Document();
                    $document->name = $file->getClientOriginalName();
                    $document->original_name = $file->getClientOriginalName();
                    $document->extension = $file->getClientOriginalExtension();
                    $document->file_path = $parentStringFolderPath;
                    $document->size =  $fileSize;
                    $document->folder_id = $folderId; // No folder ID
                    $document->visibility = 'public';
                    $document->owner = User::first()->id;
                    $document->date = now();
                    $document->save();
                }
            }
            // Return a success response
            return $document;
        } else {
            // No files uploaded
            return response()->json(['message' => 'No files uploaded'], 400);
        }
    }

    protected function uploadUrl($request)
    {
        // Get the folder ID from the request
        $folderId = $request->input('folder_id');
        $urlName = $request->input('name');
        $url = $request->input('url');
        $visibility = $request->input('visibility');

        // Create a new document record
        $document = new Document();
        $document->name = $urlName;
        $document->original_name = $urlName;
        $document->extension = $this->isYouTubeUrl($url) ? 'youtube' : '';
        $document->file_path = $url;
        $document->url = $url;
        $document->size =  0;
        $document->folder_id = $folderId; // No folder ID
        $document->visibility = $visibility;
        $document->owner = User::first()->id;
        $document->date = now();
        $document->save();

        return  $document;
    }

    protected function getFolderInfo($folderId)
    {
        // Get the folder
        $folder = Folder::find($folderId);

        if (!$folder) {
            return null; // Folder not found
        }

        // Get all documents in the folder
        $documents = Document::whereFolderId($folderId)->get();

        // Initialize counters for public and private documents
        $numPublicDocuments = 0;
        $numPrivateDocuments = 0;

        // Calculate total size of documents in the folder
        $totalSize = 0;
        foreach ($documents as $document) {
            // Increment the appropriate counter based on document visibility
            if ($document->visibility === 'public') {
                $numPublicDocuments++;
            } else {
                $numPrivateDocuments++;
            }

            // Accumulate the size of the document
            $totalSize += $document->size;
        }

        // Format the total size
        $formattedTotalSize = $this->getFormatSize($totalSize);

        // Count total number of documents in the folder
        $numTotalDocuments = $documents->count();

        return [
            'folder_name' => $folder->name,
            'num_documents' => [
                'total' => $numTotalDocuments,
                'public' => $numPublicDocuments,
                'private' => $numPrivateDocuments
            ],
            'total_size' => $formattedTotalSize,
            'created_at' => $folder->created_at->format('Y/m/d H:i:s'), // Format created date
            'updated_at' => $folder->updated_at->format('Y/m/d H:i:s') // Format updated date
        ];
    }

    protected function getFormatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }
        return round($bytes, 2) . ' ' . $units[$index];
    }

    protected function isYouTubeUrl($url)
    {
        // Check if the URL contains "youtube.com" and "watch?v="
        if (strpos($url, 'youtube.com') !== false && strpos($url, 'watch?v=') !== false) {
            return true;
        }

        // Check if the URL contains "youtu.be"
        if (strpos($url, 'youtu.be') !== false) {
            return true;
        }

        // If none of the above conditions are met, it's not a YouTube URL
        return false;
    }
}
