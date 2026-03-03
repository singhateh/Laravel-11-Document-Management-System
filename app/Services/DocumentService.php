<?php

namespace App\Services;

use App\Models\User;
use App\Models\Folder;
use App\Models\Document;
use App\Models\Notification;
use App\Jobs\SendDocumentJob;
use App\Services\Stego\CryptoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    public function __construct(private readonly CryptoService $crypto) {}

    // -------------------------------------------------------------------------
    // AES-256-GCM Document Encryption
    // -------------------------------------------------------------------------

    /**
     * Check whether document-at-rest encryption is enabled.
     * Encryption requires DOCUMENT_MASTER_KEY to be set in .env.
     */
    public function isEncryptionEnabled(): bool
    {
        return !empty(env('DOCUMENT_MASTER_KEY'));
    }

    /**
     * Get the application-wide 256-bit master key from .env.
     * This key is used as the base for per-document DEK derivation.
     *
     * @throws \RuntimeException If the key is not configured
     */
    private function getMasterKey(): string
    {
        $key = env('DOCUMENT_MASTER_KEY');

        if (empty($key)) {
            throw new \RuntimeException(
                'DOCUMENT_MASTER_KEY is not set in .env. ' .
                'Generate one with: php -r "echo bin2hex(openssl_random_pseudo_bytes(32));"'
            );
        }

        return $key;
    }

    /**
     * Encrypt a document's file on disk in-place using AES-256-GCM and
     * store encryption metadata (IV, auth tag, DEK salt, hash) in the DB.
     *
     * Safe to call multiple times — skips already-encrypted documents.
     * Skips URL-type documents that have no physical file.
     *
     * @param  Document $document  A persisted document with a valid ID
     * @throws \RuntimeException   On encryption failure or missing master key
     */
    public function encryptDocumentFile(Document $document): void
    {
        if ($document->is_encrypted) {
            return; // Already encrypted — nothing to do
        }

        if (!$document->hasPhysicalFile()) {
            return; // URL document — no file to encrypt
        }

        $absolutePath = public_path($document->file_path);

        if (!file_exists($absolutePath)) {
            Log::warning("DocumentService: cannot encrypt — file not found: {$absolutePath}");
            return;
        }

        $plaintext   = file_get_contents($absolutePath);
        $masterKey   = $this->getMasterKey();

        // Derive a unique DEK for this document from the master key + document ID.
        $dekResult   = $this->crypto->deriveDEK($masterKey, (string) $document->id);

        // Encrypt with AES-256-GCM and compute plaintext integrity hash.
        $encrypted   = $this->crypto->encrypt($plaintext, $dekResult['dek']);
        $hash        = $this->crypto->hashDocument($plaintext);

        // Overwrite the file on disk with the raw ciphertext bytes.
        file_put_contents($absolutePath, hex2bin($encrypted['ciphertext']));

        // Persist encryption metadata so the file can be decrypted later.
        $document->update([
            'is_encrypted'       => true,
            'enc_iv'             => $encrypted['iv'],
            'enc_auth_tag'       => $encrypted['auth_tag'],
            'enc_dek_salt'       => $dekResult['salt'],
            'enc_dek_iterations' => $dekResult['iterations'],
            'enc_hash_sha256'    => $hash,
        ]);

        Log::info("DocumentService: document #{$document->id} encrypted with AES-256-GCM.");
    }

    /**
     * Read and decrypt a document's file, returning plaintext bytes.
     *
     * For unencrypted (legacy) documents the file is returned as-is.
     * For URL-type documents, returns an empty string.
     *
     * @param  Document $document
     * @return string   Raw plaintext bytes
     * @throws \RuntimeException On decryption failure or integrity mismatch
     */
    public function decryptDocumentContent(Document $document): string
    {
        if (!$document->hasPhysicalFile()) {
            return ''; // URL document — nothing to decrypt
        }

        $absolutePath = public_path($document->file_path);

        if (!file_exists($absolutePath)) {
            throw new \RuntimeException("File not found on disk: {$absolutePath}");
        }

        if (!$document->is_encrypted) {
            // Legacy plaintext document — serve directly.
            return file_get_contents($absolutePath);
        }

        // Read the raw ciphertext bytes and hex-encode for CryptoService.
        $cipherHex = bin2hex(file_get_contents($absolutePath));

        $masterKey = $this->getMasterKey();

        // Re-derive the exact same DEK using stored salt + iterations.
        $dekResult = $this->crypto->deriveDEK(
            $masterKey,
            (string) $document->id,
            $document->enc_dek_salt,
            $document->enc_dek_iterations
        );

        // Decrypt and authenticate.
        $plaintext = $this->crypto->decrypt(
            $cipherHex,
            $dekResult['dek'],
            $document->enc_iv,
            $document->enc_auth_tag
        );

        // Verify SHA-256 integrity hash to detect tampering.
        if (!$this->crypto->verifyHash($plaintext, $document->enc_hash_sha256)) {
            throw new \RuntimeException(
                "Document #{$document->id} integrity check failed. " .
                'File may be corrupt or tampered with.'
            );
        }

        return $plaintext;
    }

    // =========================================================================
    // Existing methods below (unchanged)
    // =========================================================================

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
            $document->update(['owner_id' => $requestData]);
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
                $document->owner_id = User::first()->id;
                $document->date = now();
                $document->save();

                // Encrypt the file at rest with AES-256-GCM.
                if ($this->isEncryptionEnabled()) {
                    $this->encryptDocumentFile($document);
                }
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
                    $document->owner_id = User::first()->id;
                    $document->date = now();
                    $document->save();

                    // Encrypt the file at rest with AES-256-GCM.
                    if ($this->isEncryptionEnabled()) {
                        $this->encryptDocumentFile($document);
                    }
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
                    $document->owner_id = User::first()->id;
                    $document->date = now();
                    $document->save();

                    // Encrypt the file at rest with AES-256-GCM.
                    if ($this->isEncryptionEnabled()) {
                        $this->encryptDocumentFile($document);
                    }
                }
            }
            // Return a success response
            return $document ?? null;
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
        $document->owner_id = User::first()->id;
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
