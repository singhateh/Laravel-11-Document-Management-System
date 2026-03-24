<?php

namespace App\Services\Stego;

use App\Models\Document;
use App\Models\StegoDocument;
use App\Services\Stego\CarrierPoolSelector;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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
        private readonly CarrierPoolSelector $carrierPoolSelector,
    ) {}

    // =========================================================================
    // ENCODE
    // =========================================================================

    /**
     * Hide a document inside one or more carrier files.
     *
     * @param  int    $userId              Authenticated user ID
     * @param  string $plaintext           Raw document bytes to protect
     * @param  string $masterKey           Hex-encoded master key (from CryptoService::deriveMasterKey)
     * @param  array|null $carrierPaths    Optional absolute local paths to carrier files. If null, uses carrier pool.
     * @param  int|null $documentId        Optional FK to an existing documents.id record
     * @param  bool   $useSystemCarriers   Whether to use system carriers as fallback
     * @return \App\Models\StegoDocument  The persisted StegoDocument with id
     * @throws Throwable
     */
    public function encode(
        int $userId,
        string $plaintext,
        string $masterKey,
        ?array $carrierPaths = null,
        ?int $documentId = null,
        ?int $existingDocId = null,
        bool $useSystemCarriers = false,
    ): array {
        // Validate inputs
        if ($userId <= 0) {
            throw new Exception('Invalid user ID');
        }

        if (empty($plaintext)) {
            throw new Exception('Plaintext cannot be empty');
        }

        if (empty($masterKey) || strlen($masterKey) !== 64) {
            throw new Exception('Master key must be a 64-character hex string');
        }

        // If carrier paths are provided, validate them
        if ($carrierPaths !== null) {
            if (empty($carrierPaths)) {
                throw new Exception('At least one carrier file is required for encoding.');
            }

            foreach ($carrierPaths as $index => $path) {
                if (empty($path) || !is_string($path)) {
                    throw new Exception("Carrier path at index {$index} is invalid");
                }

                if (!file_exists($path)) {
                    throw new Exception("Carrier file not found: {$path}");
                }

                if (!is_readable($path)) {
                    throw new Exception("Carrier file is not readable: {$path}");
                }

                $size = filesize($path);
                if ($size === false || $size === 0) {
                    throw new Exception("Carrier file is empty: {$path}");
                }
            }
        }

        // -----------------------------------------------------------------
        // Step 1 & 2: Derive DEK and encrypt the document
        // -----------------------------------------------------------------
        $documentRef = $documentId ? (string) $documentId : uniqid('stego_', true);

        $dekResult   = $this->crypto->deriveDEK($masterKey, $documentRef);
        $encrypted   = $this->crypto->encrypt($plaintext, $dekResult['dek']);
        $hash        = $this->crypto->hashDocument($plaintext);

        // -----------------------------------------------------------------
        // Step 3: Select carriers from pool or use provided paths
        // -----------------------------------------------------------------
        $selectedCarriers = null;
        $carrierPathsToUse = $carrierPaths;

        if ($carrierPaths === null) {
            // Use carrier pool - select carriers based on required capacity
            $ciphertextSize = strlen(base64_decode($encrypted['ciphertext']));
            $selectedCarriers = $this->carrierPoolSelector->select($userId, $ciphertextSize, $useSystemCarriers);
            $carrierPathsToUse = $selectedCarriers->map(fn($carrier) => $carrier->file_path)->toArray();
        }

        // -----------------------------------------------------------------
        // Step 4: Compute carrier capacities, then split ciphertext into chunks
        // -----------------------------------------------------------------
        // Cache capacity by file hash — avoids re-running the Python driver for
        // the same carrier image on repeated encode calls (e.g. test/dev cycles).
        $capacities  = array_map(function ($p) {
            $cacheKey = 'stego.capacity.' . hash_file('md5', $p);
            return Cache::remember($cacheKey, 3600, fn () => $this->stego->capacity($p));
        }, $carrierPathsToUse);
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
                // Keep heavy ciphertext out of DB for faster local fetches.
                'ciphertext'        => null,
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

            // Persist base64 ciphertext as a local file and store only its path.
            $ciphertextPath = $this->storeCiphertextOnLocalDisk($stegoDoc->id, $encrypted['ciphertext']);
            $stegoDoc->update([
                'ciphertext' => null,
                's3_key'     => $ciphertextPath,
            ]);
            $stegoDoc = $stegoDoc->fresh();

            foreach ($segments as $seg) {
                $idx        = $seg['index'];
                $carrierIdx = $seg['carrier_index'] ?? $idx;

                if (!isset($carrierPathsToUse[$carrierIdx])) {
                    throw new Exception("Carrier mapping missing for segment index {$idx}.");
                }

                $carrierPath = $carrierPathsToUse[$carrierIdx];
                $outputPath  = $tmpDir . DIRECTORY_SEPARATOR . "carrier_{$idx}_" . basename($carrierPath);

                // Embed the encrypted chunk into the carrier.
                $this->stego->embed($carrierPath, $seg['chunk'], $outputPath);

                // PSNR quality gate + metric collection (image carriers only).
                $psnrValue          = $this->measurePsnrOrFail($carrierPath, $outputPath);
                $qualityMetrics[$idx] = $this->buildQualityMetric($carrierPath, $psnrValue);

                // Upload the modified carrier to S3.
                $s3Key    = $this->cloud->carrierKey($userId, "doc{$stegoDoc->id}_seg{$idx}_" . basename($carrierPath));
                $s3Result = $this->cloud->uploadFile($outputPath, $s3Key);

                // If using pool carriers, update the existing carrier record
                if ($selectedCarriers !== null && isset($selectedCarriers[$carrierIdx])) {
                    $carrier = $selectedCarriers[$carrierIdx];
                    $carrier->update([
                        'file_path' => $outputPath,
                        's3_key'    => $s3Result['s3_key'],
                        'psnr'      => $psnrValue,
                    ]);
                } else {
                    // Persist the carrier record (for direct path uploads).
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
                }

                $carrierRecords[] = $carrier;

                // Persist the segment record.
                // base64-encode the raw binary chunk for safe storage in the longtext column.
                $segmentRecords[] = [
                    'stego_document_id' => $stegoDoc->id,
                    'stego_carrier_id'  => $carrier->id,
                    'segment_index'     => $idx,
                    'encrypted_chunk'   => base64_encode($seg['chunk']),
                    // Local mode keeps the encrypted chunk in DB; no object key is needed.
                    's3_key'            => null,
                    'chunk_hash'        => $seg['hash'],
                ];
            }

            $this->enforceAggregatePsnrThreshold($qualityMetrics);

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
        $tStart = microtime(true);

        // Validate inputs
        if ($stegoDocumentId <= 0) {
            throw new Exception('Invalid StegoDocument ID');
        }

        if ($userId <= 0) {
            throw new Exception('Invalid user ID');
        }

        if (empty($masterKey) || strlen($masterKey) !== 64) {
            throw new Exception('Master key must be a 64-character hex string');
        }

        // -----------------------------------------------------------------
        // Step 1: Load metadata and segments
        // -----------------------------------------------------------------
        $stegoDoc = $this->persistence->findStegoDocument($stegoDocumentId);
        $tMetaLoaded = microtime(true);

        $segments = $this->persistence->getSegments($stegoDocumentId);
        $tSegmentsLoaded = microtime(true);

        $tmpDir = $this->makeTmpDir('stegolock_dec_');

        try {
            $ciphertext = null;
            $ciphertextSource = null;

            if (!empty($segments)) {
                // -----------------------------------------------------------------
                // Steps 2 & 3: Download carriers from S3 and extract chunks
                // -----------------------------------------------------------------
                $reassemblySegments = [];

                foreach ($segments as $segment) {
                    // Use strict mode to fail fast if any stored segment is malformed.
                    $chunk = base64_decode($segment->encrypted_chunk, true);
                    if ($chunk === false) {
                        throw new Exception("Malformed segment payload at index {$segment->segment_index}.");
                    }

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
                $ciphertextSource = 'segments';
            } else {
                // Backward compatibility for records without segment rows.
                $ciphertext = $this->loadCiphertextForDecode($stegoDoc);
                $ciphertextSource = $stegoDoc->s3_key ? 'local_file' : 'db_column';
            }
            $tCipherReady = microtime(true);

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
            $tDecryptDone = microtime(true);

            // -----------------------------------------------------------------
            // Step 6: Verify document integrity hash
            // -----------------------------------------------------------------
            if (!$this->crypto->verifyHash($plaintext, $stegoDoc->stego_hash_sha256)) {
                throw new Exception('Document integrity check failed. Hash mismatch after decryption.');
            }
            $tHashVerified = microtime(true);

            // Log the decode operation.
            $this->persistence->logAccess([
                'user_id'     => $userId,
                'action'      => 'stego_decode',
                'resource'    => 'stego_document',
                'resource_id' => $stegoDoc->id,
                'payload'     => ['status' => 'success'],
            ]);

            $totalDuration = round(($tHashVerified - $tStart), 3);
            
            logger()->info('stego.decode.timing', [
                'stego_document_id' => $stegoDocumentId,
                'ciphertext_source' => $ciphertextSource,
                'db_meta_fetch_ms'  => round(($tMetaLoaded - $tStart) * 1000, 2),
                'db_segments_ms'    => round(($tSegmentsLoaded - $tMetaLoaded) * 1000, 2),
                'cipher_ready_ms'   => round(($tCipherReady - $tSegmentsLoaded) * 1000, 2),
                'decrypt_ms'        => round(($tDecryptDone - $tCipherReady) * 1000, 2),
                'hash_verify_ms'    => round(($tHashVerified - $tDecryptDone) * 1000, 2),
                'total_ms'          => round(($tHashVerified - $tStart) * 1000, 2),
            ]);
            
            // Save decoding duration
            $stegoDoc->update(['decoding_duration' => $totalDuration]);

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
    * Throws Exception if measured PSNR falls below configured threshold.
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

        $threshold = (float) config('stegolock.carrier_pool.psnr_threshold', 40.0);
        $psnr = (float) ($metrics['psnr'] ?? 0.0);

        if ($psnr < $threshold) {
            throw new Exception(sprintf(
                'Carrier "%s": PSNR %.2f dB is below the %.2f dB imperceptibility threshold. '
                . 'Choose a larger carrier image.',
                basename($carrierPath),
                $psnr,
                $threshold
            ));
        }

        return $psnr;
    }

    /**
     * Build the per-carrier quality metric entry included in the encode() return value.
     */
    private function buildQualityMetric(string $carrierPath, ?float $psnr): array
    {
        $threshold = (float) config('stegolock.carrier_pool.psnr_threshold', 40.0);

        return [
            'carrier'          => basename($carrierPath),
            'psnr'             => $psnr,
            'threshold_db'     => $threshold,
            'passed_threshold' => $psnr !== null ? ($psnr >= $threshold) : null,
            // Backward compatibility for existing API consumers.
            'threshold_40db'   => $psnr !== null ? ($psnr >= 40.0) : null,
        ];
    }

    /**
     * Additional PSNR safety guard for concentrated bin-packing payloads.
     *
     * @param array<int, array{carrier: string, psnr: ?float, threshold_db?: float}> $qualityMetrics
     * @throws Exception
     */
    private function enforceAggregatePsnrThreshold(array $qualityMetrics): void
    {
        $imageMetrics = array_values(array_filter(
            $qualityMetrics,
            fn (array $metric): bool => isset($metric['psnr']) && $metric['psnr'] !== null
        ));

        if (empty($imageMetrics)) {
            return;
        }

        $avgThreshold = (float) config('stegolock.carrier_pool.encode_average_psnr_threshold', 41.0);
        $avgPsnr = array_sum(array_map(fn (array $metric): float => (float) $metric['psnr'], $imageMetrics)) / count($imageMetrics);

        if ($avgPsnr < $avgThreshold) {
            throw new Exception(sprintf(
                'Average PSNR %.2f dB is below the %.2f dB encode safety threshold. Choose larger carriers.',
                $avgPsnr,
                $avgThreshold
            ));
        }
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

    private function storeCiphertextOnLocalDisk(int $stegoDocumentId, string $base64Ciphertext): string
    {
        $relativePath = "stego/ciphertext/{$stegoDocumentId}.enc";
        Storage::disk('local')->put($relativePath, $base64Ciphertext);

        return $relativePath;
    }

    private function loadCiphertextForDecode($stegoDoc): string
    {
        if (!empty($stegoDoc->s3_key) && Storage::disk('local')->exists($stegoDoc->s3_key)) {
            return Storage::disk('local')->get($stegoDoc->s3_key);
        }

        // Query legacy DB ciphertext only when needed to keep regular fetches lean.
        $legacyCiphertext = StegoDocument::query()
            ->whereKey($stegoDoc->id)
            ->value('ciphertext');

        if (!empty($legacyCiphertext)) {
            return $legacyCiphertext;
        }

        throw new Exception("StegoDocument #{$stegoDoc->id} has no recoverable ciphertext source.");
    }

    /**
     * Estimate decoding time for a pending StegoDocument based on historical decoding speed.
     *
     * @param int $stegoDocumentId Primary key of the StegoDocument to estimate
     * @return float|null Estimated decoding time in seconds, or null if insufficient data
     */
    public function estimateDecodingTime(int $stegoDocumentId): ?float
    {
        $stegoDoc = $this->persistence->findStegoDocument($stegoDocumentId);
        
        // If document has already been decoded, return actual duration
        if (!empty($stegoDoc->decoding_duration)) {
            return $stegoDoc->decoding_duration;
        }
        
        // Get total carrier size for this document
        $totalCarrierSize = $stegoDoc->segments()
            ->join('stego_carriers', 'stego_segments.stego_carrier_id', '=', 'stego_carriers.id')
            ->sum('stego_carriers.size');
        
        if ($totalCarrierSize <= 0) {
            return null;
        }
        
        // Calculate average decoding speed (bytes per second) from completed documents
        $averageSpeed = StegoDocument::query()
            ->whereNotNull('decoding_duration')
            ->where('decoding_duration', '>', 0)
            ->with(['segments' => function ($query) {
                $query->join('stego_carriers', 'stego_segments.stego_carrier_id', '=', 'stego_carriers.id')
                    ->select('stego_segments.stego_document_id', 'stego_carriers.size');
            }])
            ->get()
            ->map(function ($doc) {
                $docCarrierSize = $doc->segments->sum('size');
                return $docCarrierSize > 0 ? $docCarrierSize / $doc->decoding_duration : null;
            })
            ->filter()
            ->avg();
        
        if ($averageSpeed <= 0) {
            return null;
        }
        
        // Estimate decoding time
        $estimatedTime = $totalCarrierSize / $averageSpeed;
        
        return round($estimatedTime, 3);
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
