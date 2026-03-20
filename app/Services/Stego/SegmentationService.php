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
     * carrier capacities, assigning one chunk per carrier. This minimizes LSB
     * modifications per carrier by distributing payload evenly across available
     * capacity, resulting in higher PSNR for each stego-image.
     *
     * Each chunk is validated against its carrier's byte capacity before the
     * array is returned so the caller gets a clean all-or-nothing result.
     *
     * @param  string $base64Ciphertext  Base64-encoded ciphertext from CryptoService::encrypt()
     * @param  int[]  $carrierCapacities Byte capacity of each available carrier (index-aligned)
     * @return array<int, array{ index: int, chunk: string, hash: string }>
     *         Each element: segment_index (0-based), raw binary chunk, SHA-256 of chunk
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
        $totalCapacity = array_sum($carrierCapacities);
        $numCarriers = count($carrierCapacities);

        if ($totalCapacity < $dataLength) {
            throw new \RuntimeException(
                "Total carrier capacity ({$totalCapacity} bytes) is insufficient for data size ({$dataLength} bytes)."
            );
        }

        // First, calculate how many carriers we actually need (each must hold at least 1 byte)
        $requiredCarriers = $this->recommendedSegmentCount($dataLength, $carrierCapacities);
        
        if ($requiredCarriers > $numCarriers) {
            $need  = $requiredCarriers;
            $have  = $numCarriers;
            $short = $need - $have;
            throw new \RuntimeException(
                "Document requires {$need} carrier image(s) but only {$have} were uploaded. " .
                "Please add {$short} more carrier image(s)."
            );
        }

        // Use only the required number of carriers (largest ones first for optimal distribution)
        $sortedCapacities = array_map(null, $carrierCapacities, array_keys($carrierCapacities));
        usort($sortedCapacities, function ($a, $b) {
            return $b[0] <=> $a[0]; // Sort by capacity descending
        });
        
        $selectedCapacities = array_slice($sortedCapacities, 0, $requiredCarriers);
        $selectedTotalCapacity = array_sum(array_column($selectedCapacities, 0));

        // Calculate chunk sizes based on selected carrier capacities (proportional distribution)
        $chunkSizes = [];
        $remainingData = $dataLength;

        foreach ($selectedCapacities as $index => list($capacity, $originalIndex)) {
            // Calculate proportional chunk size for this carrier
            $chunkSize = (int) round($dataLength * ($capacity / $selectedTotalCapacity));
            
            // Ensure we don't exceed remaining data or carrier capacity
            $chunkSize = min($chunkSize, $remainingData, $capacity);
            
            // Handle edge case where rounding might leave small remaining data
            if ($index === $requiredCarriers - 1) {
                $chunkSize = $remainingData;
            }
            
            $chunkSizes[$originalIndex] = $chunkSize;
            $remainingData -= $chunkSize;
        }

        // Verify all chunks fit within their carrier capacities
        foreach ($chunkSizes as $index => $chunkSize) {
            if ($chunkSize > $carrierCapacities[$index]) {
                $minDim = (int) ceil(sqrt(($chunkSize * 8) / 3));
                throw new \RuntimeException(
                    'Carrier image ' . ($index + 1) . ' is too small. ' .
                    'Needs ' . $chunkSize . ' bytes but its capacity is only ' . $carrierCapacities[$index] . ' bytes. ' .
                    "Use an image of at least {$minDim}×{$minDim} pixels."
                );
            }
        }

        // Split data into calculated chunks
        $segments = [];
        $offset = 0;

        foreach ($chunkSizes as $index => $chunkSize) {
            $chunk = substr($rawData, $offset, $chunkSize);
            
            $segments[] = [
                'index' => $index,
                'chunk' => $chunk,
                'hash'  => hash('sha256', $chunk),
            ];
            
            $offset += $chunkSize;
        }

        // Sort segments by original index to maintain consistency
        usort($segments, function ($a, $b) {
            return $a['index'] <=> $b['index'];
        });

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

        // Find the maximum number of segments possible where each segment can fit into
        // at least one carrier. More segments mean smaller chunks and higher PSNR.
        $maxPossibleSegments = 0;
        
        // Sort carrier capacities in descending order to prioritize larger carriers first
        $sortedCapacities = $carrierCapacities;
        rsort($sortedCapacities);
        
        $availableCapacity = 0;
        foreach ($sortedCapacities as $capacity) {
            // Calculate what chunk size would be if we use this many segments
            $currentSegmentCount = $maxPossibleSegments + 1;
            $requiredChunkSize = (int) ceil($dataLength / $currentSegmentCount);
            
            if ($capacity >= $requiredChunkSize) {
                $availableCapacity += $capacity;
                $maxPossibleSegments++;
                
                // Check if we have enough total capacity with this many segments
                if ($maxPossibleSegments > 0) {
                    $avgRequiredCapacity = $dataLength / $maxPossibleSegments;
                    $totalUsableCapacity = array_sum(array_filter(
                        $carrierCapacities,
                        fn($c) => $c >= $avgRequiredCapacity
                    ));
                    
                    if ($totalUsableCapacity >= $dataLength) {
                        continue; // Continue trying to find more segments
                    } else {
                        // Not enough capacity for more segments, return current count
                        return $maxPossibleSegments;
                    }
                }
            }
        }
        
        // If all carriers are usable and can fit the data, use maximum possible segments
        return $maxPossibleSegments > 0 ? $maxPossibleSegments : $numCarriers;
    }
}
