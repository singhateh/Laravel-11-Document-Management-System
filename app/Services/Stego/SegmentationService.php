<?php

namespace App\Services\Stego;

use Exception;

/**
 * SegmentationService
 *
 * Splits encrypted data into N equal-sized chunks for distribution across
 * multiple carrier files, and reassembles them in the correct order.
 *
 * Each chunk is assigned a zero-based segment_index so the PersistenceService
 * can store and retrieve them in the right order.
 *
 * Replaces the gRPC segmentation-service microservice described in the .md guide.
 */
class SegmentationService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Split binary data into a fixed number of chunks.
     *
     * The last chunk may be slightly larger if the data length is not evenly
     * divisible by $numSegments.
     *
     * @param  string $data        Raw binary data to split (typically hex-encoded ciphertext)
     * @param  int    $numSegments Number of chunks to produce (must be >= 1)
     * @return array<int, array{ index: int, chunk: string, hash: string }>
     *              Each element: segment_index (0-based), raw chunk bytes, SHA-256 hash of chunk
     * @throws Exception
     */
    public function segment(string $data, int $numSegments = 1): array
    {
        if ($numSegments < 1) {
            throw new Exception("numSegments must be at least 1, got {$numSegments}.");
        }

        $dataLength  = strlen($data);
        $chunkSize   = (int) ceil($dataLength / $numSegments);

        if ($chunkSize === 0) {
            throw new Exception('Data is empty; cannot segment empty data.');
        }

        $segments = [];
        $offset   = 0;

        for ($i = 0; $i < $numSegments; $i++) {
            $chunk = substr($data, $offset, $chunkSize);

            if ($chunk === '' || $chunk === false) {
                break; // data exhausted before numSegments reached
            }

            $segments[] = [
                'index' => $i,
                'chunk' => $chunk,
                'hash'  => hash('sha256', $chunk),
            ];

            $offset += $chunkSize;
        }

        return $segments;
    }

    /**
     * Reassemble segments back into the original data.
     *
     * @param  array<int, array{ index: int, chunk: string, hash: string }> $segments
     *         Segments as stored by PersistenceService (may be in any order)
     * @param  bool $verifyHashes Whether to verify each chunk's SHA-256 hash before reassembly
     * @return string Reassembled binary data
     * @throws Exception If hash verification fails or a segment is missing
     */
    public function reassemble(array $segments, bool $verifyHashes = true): string
    {
        if (empty($segments)) {
            throw new Exception('No segments provided for reassembly.');
        }

        // Sort by segment index to guarantee correct order.
        usort($segments, fn ($a, $b) => $a['index'] <=> $b['index']);

        // Verify continuity — no gaps allowed.
        foreach ($segments as $i => $segment) {
            if ($segment['index'] !== $i) {
                throw new Exception(
                    "Missing segment at index {$i}. Found segment index {$segment['index']} instead."
                );
            }
        }

        $data = '';

        foreach ($segments as $segment) {
            if ($verifyHashes) {
                $actualHash = hash('sha256', $segment['chunk']);
                if (!hash_equals($segment['hash'], $actualHash)) {
                    throw new Exception(
                        "Segment {$segment['index']} integrity check failed. " .
                        "Expected {$segment['hash']}, got {$actualHash}. Data may be corrupt."
                    );
                }
            }

            $data .= $segment['chunk'];
        }

        return $data;
    }

    /**
     * Split a base64-encoded ciphertext into fixed 2 MB binary chunks, assigning
     * one chunk per carrier ("one whole image per chunk" strategy).
     *
     * Each chunk is validated against its carrier's byte capacity before the
     * array is returned so the caller gets a clean all-or-nothing result.
     *
     * @param  string $base64Ciphertext  Base64-encoded ciphertext from CryptoService::encrypt()
     * @param  int[]  $carrierCapacities Byte capacity of each available carrier (index-aligned)
     * @return array<int, array{ index: int, chunk: string, hash: string }>
     *         Each element: segment_index (0-based), raw binary chunk, SHA-256 of chunk
     * @throws \RuntimeException If more chunks than carriers, or a carrier is too small
     * @throws Exception         If the base64 ciphertext is malformed
     */
    public function split(string $base64Ciphertext, array $carrierCapacities): array
    {
        $rawData = base64_decode($base64Ciphertext, true);

        if ($rawData === false) {
            throw new Exception('Invalid base64 ciphertext provided to split().');
        }

        $chunkSize = 2 * 1024 * 1024; // 2 MB per chunk
        $chunks    = str_split($rawData, $chunkSize);

        if (count($chunks) > count($carrierCapacities)) {
            $need  = count($chunks);
            $have  = count($carrierCapacities);
            $short = $need - $have;
            throw new \RuntimeException(
                "Document requires {$need} carrier image(s) but only {$have} were uploaded. " .
                "Please add {$short} more carrier image(s)."
            );
        }

        $segments = [];

        foreach ($chunks as $index => $chunk) {
            $capacity = $carrierCapacities[$index];

            if (strlen($chunk) > $capacity) {
                $minDim = (int) ceil(sqrt((strlen($chunk) * 8) / 3));
                throw new \RuntimeException(
                    'Carrier image ' . ($index + 1) . ' is too small. ' .
                    'Needs ' . strlen($chunk) . ' bytes but its capacity is only ' . $capacity . ' bytes. ' .
                    "Use an image of at least {$minDim}\u{00D7}{$minDim} pixels."
                );
            }

            $segments[] = [
                'index' => $index,
                'chunk' => $chunk,
                'hash'  => hash('sha256', $chunk),
            ];
        }

        return $segments;
    }

    /**
     * Calculate the optimal number of segments given available carrier files
     * and the size of the data to hide.
     *
     * Returns the minimum of: number of carriers available, and the number
     * of carriers whose combined byte-capacity exceeds the data size.
     *
     * @param  int   $dataLength      Length of data in bytes
     * @param  int[] $carrierCapacities Capacity of each carrier in bytes
     * @return int   Recommended number of segments
     * @throws Exception If no carrier has enough capacity
     */
    public function recommendedSegmentCount(int $dataLength, array $carrierCapacities): int
    {
        $numCarriers = count($carrierCapacities);

        if ($numCarriers === 0) {
            throw new Exception('No carriers available for segmentation.');
        }

        $totalCapacity = array_sum($carrierCapacities);

        if ($totalCapacity < $dataLength) {
            throw new Exception(
                "Combined carrier capacity ({$totalCapacity} bytes) is insufficient " .
                "for data size ({$dataLength} bytes)."
            );
        }

        // Use as many carriers as there are, up to the data length.
        $minChunkSize = 1; // at least 1 byte per segment
        $maxSegments  = min($numCarriers, $dataLength);

        return max(1, $maxSegments);
    }
}
