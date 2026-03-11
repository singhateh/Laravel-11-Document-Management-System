<?php

namespace App\Jobs;

use App\Models\StegoDocument;
use App\Services\Stego\StegoDocumentService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * EncodeStegoDocumentJob
 *
 * Offloads the CPU-heavy stego encoding pipeline (LSB embedding, PSNR
 * measurement, S3 upload) to a background worker, freeing the HTTP request
 * to return immediately with a 202 Accepted.
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
 * 1. Controller pre-creates a StegoDocument with status='pending' and returns
 *    its ID in the 202 response so the client can poll.
 * 2. handle() calls StegoDocumentService::encode() which transitions the
 *    record: pending → ready (or → failed on error).
 * 3. Stage files (plaintext + carriers) are always deleted in cleanupStage(),
 *    called from both handle() and failed().
 *
 * Queue: "stego" — isolate encoding work from the default queue.
 */
class EncodeStegoDocumentJob implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    /** No retries — master key must not linger in failed_jobs. */
    public int $tries = 1;

    /** Allow up to 5 minutes for large carrier images. */
    public int $timeout = 300;

    /**
     * @param int      $userId        Owner's user ID.
     * @param string   $plaintextPath Storage-relative path to the staged plaintext file.
     * @param string   $masterKey     Hex-encoded 256-bit master key (encrypted at rest).
     * @param string[] $carrierPaths  Storage-relative paths to staged carrier images.
     * @param int|null $documentId    Source Document primary key.
     * @param int      $pendingDocId  Pre-created StegoDocument PK with status='pending'.
     */
    public function __construct(
        private readonly int    $userId,
        private readonly string $plaintextPath,
        private readonly string $masterKey,
        private readonly array  $carrierPaths,
        private readonly ?int   $documentId,
        private readonly int    $pendingDocId,
    ) {
        $this->onQueue('stego');
    }

    public function handle(StegoDocumentService $service): void
    {
        $plaintext     = Storage::get($this->plaintextPath);
        $absolutePaths = array_map(fn ($p) => Storage::path($p), $this->carrierPaths);

        try {
            $service->encode(
                $this->userId,
                $plaintext,
                $this->masterKey,
                $absolutePaths,
                $this->documentId,
                $this->pendingDocId,
            );
        } finally {
            $this->cleanupStage();
        }
    }

    /**
     * Called by the queue worker when the job exhausts its attempts.
     * The StegoDocumentService may have already transitioned the record to
     * 'failed' inside its own catch; this guard handles the case where the
     * job bailed before the service even ran (e.g. storage read error).
     */
    public function failed(\Throwable $e): void
    {
        StegoDocument::where('id', $this->pendingDocId)
            ->where('status', 'pending')
            ->update([
                'status'        => 'failed',
                'failed_reason' => substr($e->getMessage(), 0, 500),
            ]);

        $this->cleanupStage();
    }

    // -------------------------------------------------------------------------

    private function cleanupStage(): void
    {
        $files = array_merge([$this->plaintextPath], $this->carrierPaths);

        foreach ($files as $path) {
            Storage::delete($path);
        }

        // Remove the (now empty) stage directory.
        $dir = dirname($this->plaintextPath);
        if ($dir && $dir !== '.') {
            Storage::deleteDirectory($dir);
        }
    }
}
