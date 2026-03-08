<?php

namespace App\Http\Concerns;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use App\Models\StegoDocument;

/**
 * HasStegoEncoding
 *
 * Shared helpers consumed by both StegoWebController (Inertia/web) and
 * Api\StegoDocumentController (JSON/API).
 *
 * Contains every piece of logic that is identical between the two controllers:
 *   - Reading + asserting the session Master Key
 *   - Moving uploaded carrier files to unique temp paths
 *   - Releasing (unlinking) temp carrier files in finally blocks
 *   - Resolving plaintext bytes from a Document record
 *   - Building the download filename for a decoded document
 *
 * Each method is pure (no HTTP response logic) so it can be tested in
 * isolation without an HTTP kernel.
 */
trait HasStegoEncoding
{
    // -------------------------------------------------------------------------
    // Session Master Key
    // -------------------------------------------------------------------------

    /**
     * Return the in-session Master Key, or null if the session has expired.
     *
     * The key is a hex-encoded 256-bit value stored server-side only — it is
     * never transmitted to the client.  A null return means the user must
     * log in again.
     */
    protected function resolveSessionKey(): ?string
    {
        $key = session('stego_mkd');

        return empty($key) ? null : $key;
    }

    // -------------------------------------------------------------------------
    // Carrier file management
    // -------------------------------------------------------------------------

    /**
     * Move an array of UploadedFile objects to unique temp paths and return
     * the resulting absolute file paths.
     *
     * @param  \Illuminate\Http\UploadedFile[] $uploadedFiles
     * @return string[]
     */
    protected function storeCarriersTmp(array $uploadedFiles): array
    {
        $paths = [];

        foreach ($uploadedFiles as $file) {
            $tmpBase = tempnam(sys_get_temp_dir(), 'carrier_');
            $ext     = strtolower($file->getClientOriginalExtension());
            $dest    = $ext ? $tmpBase . '.' . $ext : $tmpBase;

            if ($ext) {
                // Remove the empty placeholder so the move target has the proper extension.
                @unlink($tmpBase);
            }

            $file->move(dirname($dest), basename($dest));
            $paths[] = $dest;
        }

        return $paths;
    }

    /**
     * Unlink temp carrier files silently (used in finally blocks).
     *
     * @param  string[] $paths
     */
    protected function releaseCarriers(array $paths): void
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Document helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the plaintext bytes for a Document from the public directory.
     *
     * Documents are stored under public/ and resolved via public_path(),
     * consistent with DocumentController::download() / view().
     *
     * @throws \RuntimeException if the physical file is missing on disk
     */
    protected function readDocumentPlaintext(Document $document): string
    {
        $absolutePath = public_path($document->file_path);

        if (!file_exists($absolutePath)) {
            throw new \RuntimeException(
                "Source document file not found on disk: {$document->name}"
            );
        }

        return file_get_contents($absolutePath);
    }

    /**
     * Build the download filename for a decoded document.
     *
     * Falls back to "decoded.bin" when the linked document record is missing.
     */
    protected function buildDecodeFilename(StegoDocument $stegoDoc): string
    {
        return ($stegoDoc->document->name      ?? 'decoded')
             . '.'
             . ($stegoDoc->document->extension ?? 'bin');
    }
}
