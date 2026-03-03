<?php

namespace App\Services\Stego;

use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * CloudStorageService
 *
 * Wraps Laravel's Storage facade to provide a clean interface for uploading,
 * downloading, and deleting StegoLock files on the configured S3 disk.
 *
 * The S3 disk is already defined in config/filesystems.php. Credentials are
 * read from the .env file (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, etc.).
 *
 * Replaces the gRPC cloud-storage-service microservice described in the .md guide.
 * Requires: league/flysystem-aws-s3-v3 (install via: composer require league/flysystem-aws-s3-v3)
 */
class CloudStorageService
{
    // ------------------------------------------------------------------
    // S3 key prefix structure:
    //   stego/carriers/{userId}/{filename}
    //   stego/documents/{userId}/{stegoDocumentId}
    //   stego/segments/{stegoDocumentId}/{segmentIndex}
    // ------------------------------------------------------------------

    private const DISK = 's3';

    // -------------------------------------------------------------------------
    // Upload
    // -------------------------------------------------------------------------

    /**
     * Upload a local file to S3 and return its S3 key and URL.
     *
     * @param  string $localPath  Absolute path to the local file
     * @param  string $s3Key      Destination key on S3 (e.g. "stego/carriers/1/image.png")
     * @param  string $visibility 'private' (default) or 'public'
     * @return array{ s3_key: string, s3_url: string }
     * @throws Exception
     */
    public function uploadFile(string $localPath, string $s3Key, string $visibility = 'private'): array
    {
        if (!file_exists($localPath)) {
            throw new Exception("Local file not found for upload: {$localPath}");
        }

        $stream = fopen($localPath, 'r');

        $ok = Storage::disk(self::DISK)->put($s3Key, $stream, $visibility);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if (!$ok) {
            throw new Exception("S3 upload failed for key: {$s3Key}");
        }

        return [
            's3_key' => $s3Key,
            's3_url' => $this->url($s3Key),
        ];
    }

    /**
     * Upload raw string content (e.g. an encrypted chunk) to S3.
     *
     * @param  string $content    Raw binary or string content
     * @param  string $s3Key      Destination key on S3
     * @param  string $visibility 'private' or 'public'
     * @return array{ s3_key: string, s3_url: string }
     * @throws Exception
     */
    public function uploadContent(string $content, string $s3Key, string $visibility = 'private'): array
    {
        $ok = Storage::disk(self::DISK)->put($s3Key, $content, $visibility);

        if (!$ok) {
            throw new Exception("S3 content upload failed for key: {$s3Key}");
        }

        return [
            's3_key' => $s3Key,
            's3_url' => $this->url($s3Key),
        ];
    }

    // -------------------------------------------------------------------------
    // Download
    // -------------------------------------------------------------------------

    /**
     * Download an S3 object and save it to a local path.
     *
     * @param  string $s3Key     S3 object key
     * @param  string $localPath Absolute path to save the file
     * @throws Exception
     */
    public function download(string $s3Key, string $localPath): void
    {
        $this->assertKeyExists($s3Key);

        $contents = Storage::disk(self::DISK)->get($s3Key);

        if ($contents === null) {
            throw new Exception("S3 download returned null for key: {$s3Key}");
        }

        $result = file_put_contents($localPath, $contents);

        if ($result === false) {
            throw new Exception("Could not write downloaded file to: {$localPath}");
        }
    }

    /**
     * Get the raw contents of an S3 object as a string.
     *
     * @param  string $s3Key
     * @return string
     * @throws Exception
     */
    public function getContents(string $s3Key): string
    {
        $this->assertKeyExists($s3Key);

        $contents = Storage::disk(self::DISK)->get($s3Key);

        if ($contents === null) {
            throw new Exception("S3 object is empty or unreadable: {$s3Key}");
        }

        return $contents;
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    /**
     * Delete a single S3 object.
     *
     * @param  string $s3Key
     * @throws Exception
     */
    public function delete(string $s3Key): void
    {
        if (!Storage::disk(self::DISK)->exists($s3Key)) {
            return; // idempotent — nothing to delete
        }

        $ok = Storage::disk(self::DISK)->delete($s3Key);

        if (!$ok) {
            throw new Exception("S3 delete failed for key: {$s3Key}");
        }
    }

    /**
     * Delete multiple S3 objects at once.
     *
     * @param  string[] $s3Keys
     */
    public function deleteMany(array $s3Keys): void
    {
        $existing = array_filter($s3Keys, fn ($k) => Storage::disk(self::DISK)->exists($k));

        if (!empty($existing)) {
            Storage::disk(self::DISK)->delete(array_values($existing));
        }
    }

    // -------------------------------------------------------------------------
    // URL / Existence
    // -------------------------------------------------------------------------

    /**
     * Get the public URL for an S3 object.
     * For private objects, use temporaryUrl() instead.
     *
     * @param  string $s3Key
     * @return string
     */
    public function url(string $s3Key): string
    {
        return Storage::disk(self::DISK)->url($s3Key);
    }

    /**
     * Generate a pre-signed temporary URL for a private S3 object.
     *
     * @param  string             $s3Key
     * @param  \DateTimeInterface $expiry When the URL should expire
     * @return string
     */
    public function temporaryUrl(string $s3Key, \DateTimeInterface $expiry): string
    {
        return Storage::disk(self::DISK)->temporaryUrl($s3Key, $expiry);
    }

    /**
     * Check if an S3 key exists.
     *
     * @param  string $s3Key
     * @return bool
     */
    public function exists(string $s3Key): bool
    {
        return Storage::disk(self::DISK)->exists($s3Key);
    }

    // -------------------------------------------------------------------------
    // S3 Key Builders (shared naming convention)
    // -------------------------------------------------------------------------

    public function carrierKey(int $userId, string $filename): string
    {
        return "stego/carriers/{$userId}/{$filename}";
    }

    public function documentKey(int $userId, int $stegoDocumentId): string
    {
        return "stego/documents/{$userId}/{$stegoDocumentId}";
    }

    public function segmentKey(int $stegoDocumentId, int $segmentIndex): string
    {
        return "stego/segments/{$stegoDocumentId}/{$segmentIndex}";
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertKeyExists(string $s3Key): void
    {
        if (!Storage::disk(self::DISK)->exists($s3Key)) {
            throw new Exception("S3 object not found: {$s3Key}");
        }
    }
}
