<?php

namespace App\Services\Stego;

use App\Models\Document;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * StegoDocumentService  —  Orchestrator
 *
 * Coordinates all 5 Stego micro-services to encode (hide) a document inside
 * carrier files and decode (recover) it again.
 *
 * Workflow — Encode:
 *   1. CryptoService  : derive DEK from master key + document ID
 *   2. CryptoService  : encrypt plaintext → { ciphertext, iv, auth_tag, hash }
 *   3. SegmentationService : split ciphertext into N chunks (one per carrier)
 *   4. StegoService   : embed each chunk into its carrier file
 *   5. CloudStorageService : upload modified carriers to S3
 *   6. PersistenceService  : persist StegoDocument + StegoCarriers + StegoSegments
 *
 * Workflow — Decode:
 *   1. PersistenceService  : load StegoDocument + ordered segments
 *   2. CloudStorageService : download carrier files (if stored on S3)
 *   3. StegoService   : extract encrypted chunk from each carrier
 *   4. SegmentationService : reassemble chunks (with hash verification)
 *   5. CryptoService  : re-derive DEK, decrypt → plaintext
 *   6. CryptoService  : verify document hash
 */
class StegoDocumentService
{
    public function __construct(
        private readonly CryptoService       $crypto,
        private readonly StegoService        $stego,
        private readonly SegmentationService $segmentation,
        private readonly CloudStorageService $cloud,
        private readonly PersistenceService  $persistence,
    ) {}

    // =========================================================================
    // ENCODE
    // =========================================================================

    /**
     * Hide a document inside one or more carrier files.
     *
     * @param  int    $userId        Authenticated user ID
     * @param  string $plaintext     Raw document bytes to protect
     * @param  string $masterKey     Hex-encoded master key (from CryptoService::deriveMasterKey)
     * @param  array  $carrierPaths  Absolute local paths to carrier files (images / binaries)
     * @param  int|null $documentId  Optional FK to an existing documents.id record
     * @return \App\Models\StegoDocument  The persisted StegoDocument with id
     * @throws Throwable
     */
    public function encode(
        int $userId,
        string $plaintext,
        string $masterKey,
        array $carrierPaths,
        ?int $documentId = null,
        ?int $existingDocId = null,
    ): array {
        if (empty($carrierPaths)) {
            throw new Exception('At least one carrier file is required for encoding.');
        }

        // -----------------------------------------------------------------
        // Step 1 & 2: Derive DEK and encrypt the document
        // -----------------------------------------------------------------
        $documentRef = $documentId ? (string) $documentId : uniqid('stego_', true);

        $dekResult   = $this->crypto->deriveDEK($masterKey, $documentRef);
        $encrypted   = $this->crypto->encrypt($plaintext, $dekResult['dek']);
        $hash        = $this->crypto->hashDocument($plaintext);

        // -----------------------------------------------------------------
        // Step 3: Compute carrier capacities, then split ciphertext into
        //         fixed 2 MB chunks — one whole carrier per chunk.
        // -----------------------------------------------------------------
        // Cache capacity by file hash — avoids re-running the Python driver for
        // the same carrier image on repeated encode calls (e.g. test/dev cycles).
        $capacities  = array_map(function ($p) {
            $cacheKey = 'stego.capacity.' . hash_file('md5', $p);
            return Cache::remember($cacheKey, 3600, fn () => $this->stego->capacity($p));
        }, $carrierPaths);
        $segments    = $this->segmentation->split($encrypted['ciphertext'], $capacities);
        $numSegments = count($segments);

        // -----------------------------------------------------------------
        // Step 4 & 5: Embed each chunk into its carrier and upload to S3
        // -----------------------------------------------------------------
        $tmpDir = $this->makeTmpDir('stegolock_');

        $carrierRecords  = [];
        $segmentRecords  = [];
        $qualityMetrics  = [];   // keyed by segment index

        $stegoDoc = null;
        try {
            // Persist or update the StegoDocument shell so we have an ID for key naming.
            // When $existingDocId is provided the controller pre-created a 'pending' row;
            // we fill in the crypto fields here.  Otherwise create a new row.
            $coreData = [
                'document_id'       => $documentId,
                'user_id'           => $userId,
                'ciphertext'        => $encrypted['ciphertext'],
                'stego_iv'          => $encrypted['iv'],
                'stego_auth_tag'    => $encrypted['auth_tag'],
                'stego_hash_sha256' => $hash,
                'stego_dek_salt'    => $dekResult['salt'],
                'stego_dek_iter'    => $dekResult['iterations'],
                'compressed'        => true,
                's3_key'            => null,
                'status'            => 'pending',
            ];

            $stegoDoc = $existingDocId
                ? $this->persistence->updateStegoDocument($existingDocId, $coreData)
                : $this->persistence->createStegoDocument($coreData);

            foreach ($segments as $seg) {
                $idx         = $seg['index'];
                $carrierPath = $carrierPaths[$idx];
                $outputPath  = $tmpDir . DIRECTORY_SEPARATOR . "carrier_{$idx}_" . basename($carrierPath);

                // Embed the encrypted chunk into the carrier.
                $this->stego->embed($carrierPath, $seg['chunk'], $outputPath);

                // PSNR quality gate + metric collection (image carriers only).
                $psnrValue          = $this->measurePsnrOrFail($carrierPath, $outputPath);
                $qualityMetrics[$idx] = $this->buildQualityMetric($carrierPath, $psnrValue);

                // Upload the modified carrier to S3.
                $s3Key    = $this->cloud->carrierKey($userId, "doc{$stegoDoc->id}_seg{$idx}_" . basename($carrierPath));
                $s3Result = $this->cloud->uploadFile($outputPath, $s3Key);

                // Persist the carrier record.
                $carrier = $this->persistence->createStegoCarrier([
                    'name'        => basename($carrierPath),
                    'file_path'   => $outputPath,
                    'file_type'   => $this->resolveFileType($carrierPath),
                    'mime_type'   => mime_content_type($carrierPath) ?: null,
                    'size'        => filesize($outputPath),
                    's3_key'      => $s3Result['s3_key'],
                    'psnr'        => $psnrValue,
                    'uploaded_by' => $userId,
                ]);

                $carrierRecords[] = $carrier;

                // Persist the segment record.
                // base64-encode the raw binary chunk for safe storage in the longtext column.
                $segKey = $this->cloud->segmentKey($stegoDoc->id, $idx);
                $segmentRecords[] = [
                    'stego_document_id' => $stegoDoc->id,
                    'stego_carrier_id'  => $carrier->id,
                    'segment_index'     => $idx,
                    'encrypted_chunk'   => base64_encode($seg['chunk']),
                    's3_key'            => $segKey,
                    'chunk_hash'        => $seg['hash'],
                ];
            }

            // Bulk-persist all segments in one transaction.
            $this->persistence->createStegoSegments($segmentRecords);

            // Log the encode operation.
            $this->persistence->logAccess([
                'user_id'     => $userId,
                'action'      => 'stego_encode',
                'resource'    => 'stego_document',
                'resource_id' => $stegoDoc->id,
                'payload'     => ['document_id' => $documentId, 'num_segments' => $numSegments],
            ]);

            $stegoDoc->update(['status' => 'ready']);

            // Bust the user's paginated list cache so the new document
            // appears immediately on the next index() request.
            Cache::forget("stego.index.u{$userId}");

        } catch (\Throwable $e) {
            $stegoDoc?->update([
                'status'        => 'failed',
                'failed_reason' => substr($e->getMessage(), 0, 500),
            ]);
            throw $e;
        } finally {
            // Clean up temp files.
            $this->cleanupDir($tmpDir);
        }

        return [
            'stego_document'  => $stegoDoc->fresh(),
            'quality_metrics' => array_values($qualityMetrics),
        ];
    }

    // =========================================================================
    // DECODE
    // =========================================================================

    /**
     * Recover the original plaintext from a StegoDocument.
     *
     * @param  int    $stegoDocumentId  Primary key of the StegoDocument to decode
     * @param  string $masterKey        Hex-encoded master key
     * @param  int    $userId           Authenticated user ID (for access log)
     * @return string                   Recovered plaintext bytes
     * @throws Exception|Throwable
     */
    public function decode(int $stegoDocumentId, string $masterKey, int $userId): string
    {
        // -----------------------------------------------------------------
        // Step 1: Load metadata and segments
        // -----------------------------------------------------------------
        $stegoDoc = $this->persistence->findStegoDocument($stegoDocumentId);
        $segments = $this->persistence->getSegments($stegoDocumentId);

        if (empty($segments)) {
            throw new Exception("StegoDocument #{$stegoDocumentId} has no segments.");
        }

        $tmpDir = $this->makeTmpDir('stegolock_dec_');

        try {
            // -----------------------------------------------------------------
            // Steps 2 & 3: Download carriers from S3 and extract chunks
            // -----------------------------------------------------------------
            $reassemblySegments = [];

            foreach ($segments as $segment) {
                // Use the stored base64-encoded chunk instead of extracting from carrier
                $chunk = base64_decode($segment->encrypted_chunk);

                $reassemblySegments[] = [
                    'index' => $segment->segment_index,
                    'chunk' => $chunk,
                    'hash'  => $segment->chunk_hash,
                ];
            }

            // -----------------------------------------------------------------
            // Step 4: Reassemble chunks (with hash integrity verification)
            // -----------------------------------------------------------------
            $ciphertext = $this->segmentation->reassemble($reassemblySegments, verifyHashes: true);

            // -----------------------------------------------------------------
            // Step 5: Re-derive DEK and decrypt
            // -----------------------------------------------------------------
            $documentRef = $stegoDoc->document_id ? (string) $stegoDoc->document_id : (string) $stegoDoc->id;
            $dekResult   = $this->crypto->deriveDEK(
                $masterKey,
                $documentRef,
                $stegoDoc->stego_dek_salt,
                $stegoDoc->stego_dek_iter
            );

            $plaintext = $this->crypto->decrypt(
                $ciphertext,
                $dekResult['dek'],
                $stegoDoc->stego_iv,
                $stegoDoc->stego_auth_tag
            );

            // -----------------------------------------------------------------
            // Step 6: Verify document integrity hash
            // -----------------------------------------------------------------
            if (!$this->crypto->verifyHash($plaintext, $stegoDoc->stego_hash_sha256)) {
                throw new Exception('Document integrity check failed. Hash mismatch after decryption.');
            }

            // Log the decode operation.
            $this->persistence->logAccess([
                'user_id'     => $userId,
                'action'      => 'stego_decode',
                'resource'    => 'stego_document',
                'resource_id' => $stegoDoc->id,
                'payload'     => ['status' => 'success'],
            ]);

        } finally {
            $this->cleanupDir($tmpDir);
        }

        return $plaintext;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Create a unique temporary directory. Throws on failure.
     */
    private function makeTmpDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . uniqid();
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new Exception("Failed to create temp directory: {$dir}");
        }
        return $dir;
    }

    /**
     * Measure PSNR for an image carrier after embedding.
     * Returns null for non-image carriers (PSNR is not meaningful there).
     * Throws Exception if measured PSNR falls below 40 dB.
     *
     * @throws Exception
     */
    private function measurePsnrOrFail(string $carrierPath, string $outputPath): ?float
    {
        $mime = mime_content_type($carrierPath) ?: '';

        if (!str_starts_with($mime, 'image/')) {
            return null;
        }

        $metrics = $this->stego->psnr($carrierPath, $outputPath);

        if (!$metrics['threshold_40db']) {
            throw new Exception(sprintf(
                'Carrier "%s": PSNR %.2f dB is below the 40 dB imperceptibility threshold. '
                . 'Choose a larger carrier image.',
                basename($carrierPath),
                $metrics['psnr']
            ));
        }

        return (float) $metrics['psnr'];
    }

    /**
     * Build the per-carrier quality metric entry included in the encode() return value.
     */
    private function buildQualityMetric(string $carrierPath, ?float $psnr): array
    {
        return [
            'carrier'        => basename($carrierPath),
            'psnr'           => $psnr,
            'threshold_40db' => $psnr !== null ? ($psnr >= 40.0) : null,
        ];
    }

    private function resolveFileType(string $path): string
    {
        $mime = mime_content_type($path) ?: '';

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($mime, 'text/')) {
            return 'text';
        }

        return 'binary';
    }

    private function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($dir);
    }
}
