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

        // Validate segment structure
        foreach ($segments as $i => $segment) {
            if (!isset($segment['index'], $segment['chunk'], $segment['hash'])) {
                throw new Exception("Segment {$i} missing required fields: index, chunk, or hash");
            }

            if (!is_int($segment['index']) || $segment['index'] < 0) {
                throw new Exception("Segment {$i} has invalid index: must be a non-negative integer");
            }

            if (!is_string($segment['chunk'])) {
                throw new Exception("Segment {$segment['index']} chunk must be a string");
            }

            if (!is_string($segment['hash']) || strlen($segment['hash']) !== 64) {
                throw new Exception("Segment {$segment['index']} has invalid hash: must be 64-character hex string");
            }
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

        if (empty($data)) {
            throw new Exception('Reassembled data is empty');
        }

        return $data;
    }

    /**
     * Split a base64-encoded ciphertext into dynamic-sized binary chunks based on
     * carrier capacities, assigning one chunk per selected carrier.
     *
     * Uses greedy bin-packing: largest carriers are filled first until the full
     * payload is consumed. This reduces the number of carriers needed.
     *
     * Each chunk is validated against its carrier's byte capacity before the
     * array is returned so the caller gets a clean all-or-nothing result.
     *
     * @param  string $base64Ciphertext  Base64-encoded ciphertext from CryptoService::encrypt()
     * @param  int[]  $carrierCapacities Byte capacity of each available carrier (index-aligned)
     * @return array<int, array{ index: int, carrier_index: int, chunk: string, hash: string }>
     *         Each element: sequential segment_index, mapped carrier_index, raw binary chunk, SHA-256 of chunk
     * @throws \RuntimeException If total capacity insufficient, or a carrier is too small
     * @throws Exception         If the base64 ciphertext is malformed
     */
    public function split(string $base64Ciphertext, array $carrierCapacities): array
    {
        $rawData = base64_decode($base64Ciphertext, true);

        if ($rawData === false) {
            throw new Exception('Invalid base64 ciphertext provided to split().');
        }

        $dataLength = strlen($rawData);
        if ($dataLength === 0) {
            throw new Exception('Ciphertext payload is empty; cannot split().');
        }

        if (empty($carrierCapacities)) {
            throw new Exception('No carriers available for split().');
        }

        $carriers = [];
        foreach ($carrierCapacities as $index => $capacity) {
            $normalized = (int) $capacity;

            if ($normalized <= 0) {
                continue;
            }

            $carriers[] = [
                'carrier_index' => (int) $index,
                'capacity'      => $normalized,
            ];
        }

        if (empty($carriers)) {
            throw new Exception('No carriers with usable capacity were provided.');
        }

        usort($carriers, function (array $a, array $b): int {
            if ($a['capacity'] === $b['capacity']) {
                return $a['carrier_index'] <=> $b['carrier_index'];
            }

            return $b['capacity'] <=> $a['capacity'];
        });

        $totalCapacity = array_sum(array_column($carriers, 'capacity'));

        if ($totalCapacity < $dataLength) {
            throw new \RuntimeException(
                "Total carrier capacity ({$totalCapacity} bytes) is insufficient for data size ({$dataLength} bytes)."
            );
        }

        $requiredCarriers = $this->recommendedSegmentCount($dataLength, $carrierCapacities);
        $selectedCarriers = array_slice($carriers, 0, $requiredCarriers);

        // Split data greedily: fill largest carrier first, then continue.
        $segments = [];
        $offset = 0;
        $segmentIndex = 0;

        foreach ($selectedCarriers as $carrier) {
            if ($offset >= $dataLength) {
                break;
            }

            $remaining = $dataLength - $offset;
            $chunkSize = min($carrier['capacity'], $remaining);
            $chunk = substr($rawData, $offset, $chunkSize);

            $segments[] = [
                'index'         => $segmentIndex,
                'carrier_index' => $carrier['carrier_index'],
                'chunk'         => $chunk,
                'hash'          => hash('sha256', $chunk),
            ];

            $offset += $chunkSize;
            $segmentIndex++;
        }

        if ($offset < $dataLength) {
            throw new \RuntimeException('Combined carrier capacity is insufficient for the ciphertext size.');
        }

        return $segments;
    }

    /**
     * Calculate the optimal number of segments given available carrier files
     * and the size of the data to hide.
     *
     * The optimal number of segments is determined by:
     * 1. The number of carriers available
     * 2. The capacity of each carrier (each segment must fit into a carrier)
     * 3. Minimizing chunk size to maximize PSNR (fewer LSB modifications per carrier)
     *
     * @param  int   $dataLength      Length of data in bytes
     * @param  int[] $carrierCapacities Capacity of each carrier in bytes
     * @return int   Recommended number of segments
     * @throws Exception If no carrier has enough capacity
     */
    public function recommendedSegmentCount(int $dataLength, array $carrierCapacities): int
    {
        if ($dataLength <= 0) {
            throw new Exception('Data length must be greater than zero.');
        }

        if (empty($carrierCapacities)) {
            throw new Exception('No carriers available for segmentation.');
        }

        $normalized = array_values(array_filter(
            array_map(fn ($capacity) => (int) $capacity, $carrierCapacities),
            fn ($capacity) => $capacity > 0
        ));

        if (empty($normalized)) {
            throw new Exception('No carriers with usable capacity are available.');
        }

        $totalCapacity = array_sum($normalized);

        if ($totalCapacity < $dataLength) {
            throw new Exception(
                "Combined carrier capacity ({$totalCapacity} bytes) is insufficient " .
                "for data size ({$dataLength} bytes)."
            );
        }

        rsort($normalized);

        $accumulated = 0;
        $count = 0;
        foreach ($normalized as $capacity) {
            $accumulated += $capacity;
            $count++;

            if ($accumulated >= $dataLength) {
                return $count;
            }
        }

        throw new Exception('Unable to determine a valid segment count for the supplied carriers.');
    }
}
