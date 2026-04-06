# CHANGES — 2026-03-24 (Bin-Packing Segmentation)

## Scope
Implemented and integrated Fix 2 (Bin-Packing Segmentation) for StegoLock encode flow, including selector/index mapping safety and PSNR quality safeguards.

## Summary
- Replaced proportional chunk distribution with greedy largest-first bin-packing.
- Kept decode compatibility by preserving sequential segment indices for reassembly.
- Added explicit segment-to-carrier mapping to prevent wrong-carrier embeds when fewer carriers are used.
- Added configurable PSNR checks for both per-carrier and aggregate encode quality.
- Updated and expanded tests for new segmentation behavior and end-to-end decode safety.

## Files Updated

### Core logic
- app/Services/Stego/SegmentationService.php
  - split() now uses greedy bin-packing (largest carriers first).
  - split() now includes carrier_index in each segment payload for safe mapping.
  - recommendedSegmentCount() now returns the minimum number of carriers required (descending cumulative capacity).

- app/Services/Stego/StegoDocumentService.php
  - encode() now maps segment -> carrier using carrier_index (fallback to index for compatibility).
  - Added mapping guard if a carrier slot is missing.
  - Per-carrier PSNR threshold now uses config(stegolock.carrier_pool.psnr_threshold).
  - Added aggregate encode guard enforceAggregatePsnrThreshold().
  - Quality payload now includes threshold_db and passed_threshold.
  - Preserved threshold_40db for compatibility with existing consumers.

- app/Services/Stego/CarrierPoolSelector.php
  - select() now returns values() to ensure contiguous zero-based indexing for downstream mapping.

### Configuration
- config/stegolock.php
  - Added carrier_pool.encode_average_psnr_threshold
    - env: STEGOLOCK_ENCODE_AVG_PSNR_THRESHOLD
    - default: 41.0

### Tests
- tests/Unit/SegmentationServiceTest.php
  - Added split_bin_packing_uses_fewer_carriers_when_large_capacity_exists().
  - Updated expected behavior for uneven/small carrier segment count recommendations.
  - Extended chunk-size assertions for multi-chunk split scenario.

- tests/Unit/StegoEncodeDecodeTest.php
  - Added bin_packing_can_use_fewer_segments_than_available_carriers() to verify end-to-end correctness.

## Behavior Changes
- Encode may now use fewer carriers for the same ciphertext when large-capacity carriers are available.
- Segment records remain sequential for safe decode/reassemble.
- If concentrated payload causes low average image quality, encode fails with a quality-threshold exception.

## Verification Performed
- php artisan test tests/Unit/SegmentationServiceTest.php
  - PASS: 33 tests, 53 assertions

- php artisan test tests/Unit/StegoEncodeDecodeTest.php
  - PASS: 16 tests, 24 assertions

- php artisan test tests/Unit/StegoDecodePipelineTest.php
  - PASS: 12 tests, 18 assertions

## Notes
- No schema changes were required for Fix 2 itself.
- Decode path remains backward-compatible because it relies on persisted ordered segments.
- Existing unrelated workspace changes were not modified or reverted in this update.
