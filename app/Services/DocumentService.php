<?php

namespace App\Services;

use App\Models\User;
use App\Models\Folder;
use App\Models\Document;
use App\Models\Notification;
use App\Jobs\SendDocumentJob;
use App\Services\Stego\CryptoService;
use Illuminate\Http\UploadedFile;
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

        // Overwrite the file on disk with raw ciphertext bytes.
        $rawCiphertext = base64_decode($encrypted['ciphertext'], true);
        if ($rawCiphertext === false) {
            throw new \RuntimeException('Failed to decode encrypted document ciphertext.');
        }
        file_put_contents($absolutePath, $rawCiphertext);

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

        // Read raw ciphertext bytes from disk for CryptoService.
        $ciphertext = file_get_contents($absolutePath);

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
            $ciphertext,
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
    // Private upload helpers (DRY)
    // =========================================================================

    /**
     * Persist a newly-moved uploaded file as a Document record and encrypt it at rest.
     *
     * Consolidates the identical Document-create + encrypt pattern that
     * previously appeared three times in uploadFolder() and uploadFiles().
     */
    private function createAndSaveDocument(
        UploadedFile $file,
        string $filePath,
        int $folderId,
        string $visibility = 'public'
    ): Document {
        // $file->getSize() throws RuntimeException if the temp file has already been
        // moved (SplFileInfo stat fails on the now-missing temp path).  Fall back to
        // reading the size from the already-moved destination file instead.
        try {
            $size = $file->getSize();
            if ($size === false || $size === null || $size < 0) {
                throw new \RuntimeException('Invalid size from getSize()');
            }
        } catch (\RuntimeException $e) {
            $absolutePath = public_path($filePath);
            $size = file_exists($absolutePath) ? filesize($absolutePath) : 0;
        }

        $document = Document::create([
            'name'          => $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'extension'     => $file->getClientOriginalExtension(),
            'file_path'     => $filePath,
            'size'          => (int) $size,
            'folder_id'     => $folderId,
            'visibility'    => $visibility,
            'owner_id'      => Auth::id(),
            'document_date' => now(),
        ]);

        if ($this->isEncryptionEnabled()) {
            $this->encryptDocumentFile($document);
        }

        return $document;
    }

    /**
     * Create a physical child directory and its Folder record exactly once per upload batch.
     *
     * The new folder's ID is written back via $createdChildFolderId so subsequent
     * files in the same request reuse the same subfolder without creating duplicates.
     *
     * @param  int|null $createdChildFolderId Written with the new folder's ID on first call; unchanged thereafter
     */
    private function ensureChildFolderOnce(
        string $physicalParentPath,
        string $childName,
        int $parentFolderId,
        string $visibility,
        ?int &$createdChildFolderId
    ): void {
        if (!file_exists($physicalParentPath)) {
            mkdir($physicalParentPath, 0777, true);
        }

        $childPath = $physicalParentPath . '/' . $childName;

        if (!file_exists($childPath) && $createdChildFolderId === null) {
            mkdir($childPath, 0777, true);

            $folder               = Folder::create([
                'name'       => $childName,
                'parent_id'  => $parentFolderId,
                'visibility' => $visibility,
            ]);
            $createdChildFolderId = $folder->id;
        }
    }

    /**
     * Return document IDs associated with any of the given tag IDs.
     * Returns an empty array when $tags is empty so callers can skip the filter.
     *
     * @param  int[] $tags
     * @return int[]
     */
    private function getDocumentIdsByTags(array $tags): array
    {
        if (empty($tags)) {
            return [];
        }

        return DB::table('taggables')
            ->where('taggable_type', \App\Models\Document::class)
            ->whereIn('tag_id', $tags)
            ->pluck('taggable_id')
            ->all();
    }

    // =========================================================================
    // Existing methods below
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
        $documentIds = $this->getDocumentIdsByTags($tags);

        $documents = Document::whereFolderId($folderId)
            ->when($documentIds !== [], fn ($q) => $q->whereIn('id', $documentIds))
            ->with('tags')
            ->latest()
            ->get();

        $folderInfo = $this->getFolderInfo($folderId);
        $folderData = generateSidebarMenu();

        return compact('documents', 'folderInfo', 'folderData');
    }

    public function setFilterDocumentByTag($folderId, $tags = [])
    {
        $documentIds = $this->getDocumentIdsByTags($tags);

        $documents = Document::whereFolderId($folderId)
            ->when($documentIds !== [], fn ($q) => $q->whereIn('id', $documentIds))
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
            'notifiable_id'      => $tagUser?->id,
            'notifiable_type'    => $tagUser ? User::class : null,
            'activity_type'      => $request->type,
            'model_type'         => Document::class,
            'model_id'           => $request->document_id,
            'message'            => $request->content,
            'created_by_user_id' => $auth->id,
        ]);

        if ($request->user_email) {
            dispatch(new SendDocumentJob($details));
        }

        return $this->getDocumentNotifications($request->document_id);
    }


    public function getDocumentNotifications($documentId)
    {
        return Notification::with('notifiable', 'createdBy')
            ->select('id', 'notifiable_id', 'notifiable_type', 'activity_type', 'model_type', 'model_id', 'message', 'status', 'dismiss_status', 'created_by_user_id', 'created_at')
            ->selectRaw("DATE_FORMAT(created_at, '%M %e %Y') as date, COUNT(*) as count")
            ->where('model_id', $documentId)
            ->groupBy('date', 'id', 'notifiable_id', 'notifiable_type', 'activity_type', 'model_type', 'model_id', 'message', 'status', 'dismiss_status', 'created_by_user_id', 'created_at')
            ->latest()
            ->get();
    }


    public function setUploadDocumentFiles($request)
    {
        $folderId = $request->input('folder_id');

        if ($request->has('url')) {
            $folderId = $this->uploadUrl($request);
        } elseif ($request->has('folder_name')) {
            $folderId = $this->uploadFolder($request);
        } else {
            $folderId = $this->uploadFiles($request);
        }

        return  $folderId;
    }


    public function setChangeFile($request)
    {
        $documentId  = $request->input('document_id');
        $type        = $request->input('type');
        $requestData = $request->input('data');
        $folderId    = $request->input('folder_id');

        $document = Document::find($documentId);
        $folder   = Folder::find($folderId);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!$folder) {
            return response()->json(['message' => 'Folder not found'], 404);
        }

        // Simple single-field mutations handled with a match expression.
        match ($type) {
            'file_name' => $document->update(['name' => $requestData]),
            'owner'     => $document->update(['owner_id' => $requestData]),
            'archive'   => $document->delete(),
            default     => null,
        };

        if ($request->hasFile('file') && $type === 'file') {
            $file     = $request->file('file');
            $fileName = $file->getClientOriginalName();

            if (!$file->isValid()) {
                Log::error("File {$fileName} is not valid");
                return response()->json(['message' => 'File is not valid'], 400);
            }

            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $relativePath = 'documents/' . $folder->name . '/' . $fileName;
            $file->move(public_path('documents/' . $folder->name), $fileName);

            $document->update([
                'file_path'     => $relativePath,
                'original_name' => $fileName,
                'size'          => $file->getSize(),
                'extension'     => $file->getClientOriginalExtension(),
            ]);
        }

        if ($type === 'folder') {
            $relativePath = 'documents/' . $folder->name . '/' . $document->original_name;
            Storage::disk('document_public')->move($document->file_path, $relativePath);

            $document->update([
                'folder'    => $folderId,
                'file_path' => $relativePath,
            ]);
        }

        return $folderId;
    }


    protected function uploadFolder($request)
    {
        $folderId         = (int) $request->input('folder_id');
        $parentFolderModel = Folder::find($folderId);
        if (!$parentFolderModel) {
            throw new \InvalidArgumentException('Invalid folder_id provided for upload.');
        }
        $parentFolderName = $parentFolderModel->name;
        $childFolderName  = $request->input('folder_name') ?? uniqid();
        $visibility       = $request->input('visibility') ?? 'public';
        $parentFolder     = public_path('documents/' . $parentFolderName);
        $createdChildFolder = null;

        if (!$request->hasFile('files')) {
            throw new \InvalidArgumentException('No files uploaded.');
        }

        foreach ($request->file('files') as $file) {
            if (!$file->isValid()) {
                Log::error("File {$file->getClientOriginalName()} is not valid");
                continue;
            }

            $this->ensureChildFolderOnce($parentFolder, $childFolderName, $folderId, $visibility, $createdChildFolder);

            $childFolder  = $parentFolder . '/' . $childFolderName;
            $relativePath = 'documents/' . $parentFolderName . '/' . $childFolderName . '/' . $file->getClientOriginalName();

            if (Storage::disk('public')->exists($relativePath)) {
                continue;
            }

            $file->move($childFolder, $file->getClientOriginalName());

            $this->createAndSaveDocument($file, $relativePath, $createdChildFolder ?? $folderId, $visibility);
        }

        return $createdChildFolder;
    }

    protected function uploadFiles($request)
    {
        $folderId        = (int) $request->input('folder_id');
        $folder          = Folder::find($folderId);
        if (!$folder) {
            throw new \InvalidArgumentException('Invalid folder_id provided for upload.');
        }
        $folderName      = $folder->name;
        $parentFolder    = public_path('documents/' . $folderName);
        $childFolderName = uniqid();
        $createdChildFolder = null;
        $lastDocument       = null;

        if (!$request->hasFile('files')) {
            throw new \InvalidArgumentException('No files uploaded.');
        }

        foreach ($request->file('files') as $file) {
            if (!$file->isValid()) {
                Log::error("File {$file->getClientOriginalName()} is not valid");
                continue;
            }

            if ($request->input('type') === 'folder' && $folderId) {
                $this->ensureChildFolderOnce($parentFolder, $childFolderName, $folderId, 'public', $createdChildFolder);

                $childFolder  = $parentFolder . '/' . $childFolderName;
                $relativePath = 'documents/' . $folderName . '/' . $childFolderName . '/' . $file->getClientOriginalName();

                if (Storage::disk('public')->exists($relativePath)) {
                    continue;
                }

                $file->move($childFolder, $file->getClientOriginalName());

                $lastDocument = $this->createAndSaveDocument($file, $relativePath, $createdChildFolder ?? $folderId);
            } else {
                $relativePath = 'documents/' . $folderName . '/' . $file->getClientOriginalName();
                $file->move($parentFolder, $file->getClientOriginalName());

                if (Storage::disk('public')->exists($relativePath)) {
                    continue;
                }

                $lastDocument = $this->createAndSaveDocument($file, $relativePath, $folderId);
            }
        }

        return $createdChildFolder ?? $folderId;
    }

    protected function uploadUrl($request): int
    {
        $folderId   = $request->input('folder_id');
        $urlName    = $request->input('name');
        $url        = $request->input('url');
        $visibility = $request->input('visibility');

        Document::create([
            'name'          => $urlName,
            'original_name' => $urlName,
            'extension'     => $this->isYouTubeUrl($url) ? 'youtube' : '',
            'file_path'     => $url,
            'url'           => $url,
            'size'          => 0,
            'folder_id'     => $folderId,
            'visibility'    => $visibility,
            'owner_id'      => Auth::id(),
            'document_date' => now(),
        ]);

        return $folderId;
    }

    protected function getFolderInfo($folderId)
    {
        $folder = Folder::find($folderId);

        if (!$folder) {
            return null;
        }

        $documents = Document::whereFolderId($folderId)->get();

        return [
            'folder_name'   => $folder->name,
            'num_documents' => [
                'total'   => $documents->count(),
                'public'  => $documents->where('visibility', 'public')->count(),
                'private' => $documents->where('visibility', 'private')->count(),
            ],
            'total_size' => $this->getFormatSize($documents->sum('size')),
            'created_at' => $folder->created_at->format('Y/m/d H:i:s'),
            'updated_at' => $folder->updated_at->format('Y/m/d H:i:s'),
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

    protected function isYouTubeUrl(string $url): bool
    {
        return (bool) preg_match('~(youtube\.com/watch\?v=|youtu\.be/)~', $url);
    }
}
