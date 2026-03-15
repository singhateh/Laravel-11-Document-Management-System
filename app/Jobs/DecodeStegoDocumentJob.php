<?php

namespace App\Jobs;

use App\Models\StegoDocument;
use App\Services\Stego\StegoDocumentService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * DecodeStegoDocumentJob
 *
 * Offloads the CPU-heavy stego decoding pipeline (LSB extraction, decryption)
 * to a background worker, freeing the HTTP request to return immediately with
 * a 202 Accepted.
 *
 * Security
 * --------
 * - Implements ShouldBeEncrypted: the master key stored in the jobs table is
 *   encrypted with the application key, never stored in plaintext.
 * - $tries = 1: master-key material must not persist in failed_jobs after a
 *   transient worker crash.
 *
 * Lifecycle
 * ---------
 * 1. Controller receives decode request and creates a job
 * 2. Job is dispatched to the stego queue
 * 3. handle() calls StegoDocumentService::decode() to recover the plaintext
 * 4. Decoded plaintext is stored temporarily for download
 * 5. Client polls for completion
 *
 * Queue: "stego" — isolate decoding work from the default queue.
 */
class DecodeStegoDocumentJob implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    /** No retries — master key must not linger in failed_jobs. */
    public int $tries = 1;

    /** Allow up to 5 minutes for large documents. */
    public int $timeout = 300;

    /**
     * @param int    $userId            Authenticated user ID (for access log)
     * @param int    $stegoDocumentId   StegoDocument to decode
     * @param string $masterKey         Hex-encoded 256-bit master key (encrypted at rest)
     */
    public function __construct(
        private readonly int    $userId,
        private readonly int    $stegoDocumentId,
        private readonly string $masterKey,
    ) {
        $this->onQueue('stego');
    }

    public function handle(StegoDocumentService $service): void
    {
        try {
            $plaintext = $service->decode(
                $this->stegoDocumentId,
                $this->masterKey,
                $this->userId
            );

            // Store decoded plaintext temporarily for download
            $stegoDoc = StegoDocument::find($this->stegoDocumentId);
            
            // Generate a default filename if no document is associated
            $filename = $stegoDoc->document ? 
                $stegoDoc->document->name . '_decoded_' . now()->format('YmdHis') . '.' . $stegoDoc->document->extension :
                'document_decoded_' . now()->format('YmdHis') . '.bin';
            
            $downloadPath = 'decoded/' . $stegoDoc->id . '/' . $filename;
            Storage::put($downloadPath, $plaintext);

            // Update stego document status to indicate decoding is complete
            $stegoDoc->update([
                'decoding_status' => 'completed',
                'download_path' => $downloadPath,
            ]);
        } catch (\Throwable $e) {
            // Update stego document status to indicate decoding failed
            StegoDocument::where('id', $this->stegoDocumentId)
                ->update([
                    'decoding_status' => 'failed',
                    'decoding_error' => substr($e->getMessage(), 0, 500),
                ]);
            throw $e;
        }
    }

    /**
     * Generate a unique path for storing the decoded document
     */
    private function generateDownloadPath(StegoDocument $stegoDoc, $document): string
    {
        $timestamp = now()->format('YmdHis');
        $filename = $document->name . '_decoded_' . $timestamp . '.' . $document->extension;
        return 'decoded/' . $stegoDoc->id . '/' . $filename;
    }
}
