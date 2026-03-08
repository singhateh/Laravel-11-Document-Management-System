<?php

namespace Tests\Unit;

use App\Services\Stego\SegmentationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SegmentationServiceTest
 *
 * Unit tests for SegmentationService.
 * No database, no filesystem, no HTTP.
 *
 * Coverage goals:
 *  - Segment into N equal (or near-equal) chunks
 *  - Reassemble returns the original data
 *  - Per-chunk SHA-256 hashes are verified on reassemble
 *  - Tampered chunk hash causes reassemble to throw
 *  - Missing segment index causes reassemble to throw
 *  - Single-segment edge case works
 *  - Empty segments array throws
 *  - Segment count of zero throws
 */
class SegmentationServiceTest extends TestCase
{
    private SegmentationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new SegmentationService();
    }

    // -------------------------------------------------------------------------
    // segment()
    // -------------------------------------------------------------------------

    #[Test]
    public function segment_returns_correct_number_of_chunks(): void
    {
        $data     = str_repeat('x', 100);
        $segments = $this->svc->segment($data, 4);

        $this->assertCount(4, $segments);
    }

    #[Test]
    public function segment_assigns_sequential_indices(): void
    {
        $segments = $this->svc->segment('abcdefgh', 4);
        $indices  = array_column($segments, 'index');

        $this->assertSame([0, 1, 2, 3], $indices);
    }

    #[Test]
    public function segment_stores_sha256_hash_per_chunk(): void
    {
        $segments = $this->svc->segment('hello world', 2);

        foreach ($segments as $seg) {
            $this->assertSame(hash('sha256', $seg['chunk']), $seg['hash']);
        }
    }

    #[Test]
    public function segment_single_chunk_returns_one_element(): void
    {
        $data     = 'single chunk data';
        $segments = $this->svc->segment($data, 1);

        $this->assertCount(1, $segments);
        $this->assertSame($data, $segments[0]['chunk']);
    }

    #[Test]
    public function segment_throws_if_num_segments_is_zero(): void
    {
        $this->expectException(\Exception::class);

        $this->svc->segment('data', 0);
    }

    #[Test]
    public function segment_throws_on_empty_data(): void
    {
        $this->expectException(\Exception::class);

        $this->svc->segment('', 2);
    }

    // -------------------------------------------------------------------------
    // reassemble()
    // -------------------------------------------------------------------------

    #[Test]
    public function reassemble_returns_original_data(): void
    {
        $original = 'The quick brown fox jumps over the lazy dog.';
        $segments = $this->svc->segment($original, 5);
        $result   = $this->svc->reassemble($segments);

        $this->assertSame($original, $result);
    }

    #[Test]
    public function reassemble_is_resilient_to_shuffled_segment_order(): void
    {
        $original = str_repeat('abcdef', 20);
        $segments = $this->svc->segment($original, 6);
        shuffle($segments);   // shuffle before reassembly

        $result = $this->svc->reassemble($segments);

        $this->assertSame($original, $result);
    }

    #[Test]
    public function reassemble_throws_on_tampered_chunk_hash(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/integrity check failed/i');

        $segments            = $this->svc->segment('data to segment', 2);
        $segments[0]['hash'] = str_repeat('0', 64);   // invalid hash

        $this->svc->reassemble($segments, verifyHashes: true);
    }

    #[Test]
    public function reassemble_skips_hash_check_when_disabled(): void
    {
        $segments            = $this->svc->segment('data to segment', 2);
        $segments[0]['hash'] = str_repeat('0', 64);   // would fail if verified

        // Should succeed without throwing.
        $result = $this->svc->reassemble($segments, verifyHashes: false);

        $this->assertIsString($result);
    }

    #[Test]
    public function reassemble_throws_on_missing_segment(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/missing segment/i');

        $segments = $this->svc->segment('abcdefghij', 3);
        unset($segments[1]);   // remove middle segment

        $this->svc->reassemble(array_values($segments));
    }

    #[Test]
    public function reassemble_throws_on_empty_input(): void
    {
        $this->expectException(\Exception::class);

        $this->svc->reassemble([]);
    }

    // -------------------------------------------------------------------------
    // split() — one whole carrier per 2 MB chunk
    // -------------------------------------------------------------------------

    #[Test]
    public function split_small_payload_produces_single_chunk(): void
    {
        $raw            = str_repeat('x', 1024);          // 1 KB — fits in one 2 MB chunk
        $base64         = base64_encode($raw);
        $capacities     = [2 * 1024 * 1024];              // one carrier, more than enough

        $segments = $this->svc->split($base64, $capacities);

        $this->assertCount(1, $segments);
        $this->assertSame(0, $segments[0]['index']);
        $this->assertSame($raw, $segments[0]['chunk']);
    }

    #[Test]
    public function split_large_payload_produces_multiple_chunks(): void
    {
        $raw        = str_repeat('a', 5 * 1024 * 1024);   // 5 MB → 3 chunks of 2 MB
        $base64     = base64_encode($raw);
        $capacities = array_fill(0, 3, 2 * 1024 * 1024);

        $segments = $this->svc->split($base64, $capacities);

        $this->assertCount(3, $segments);
    }

    #[Test]
    public function split_assigns_sequential_indices(): void
    {
        $raw        = str_repeat('b', 5 * 1024 * 1024);
        $base64     = base64_encode($raw);
        $capacities = array_fill(0, 3, 2 * 1024 * 1024);

        $segments = $this->svc->split($base64, $capacities);
        $indices  = array_column($segments, 'index');

        $this->assertSame([0, 1, 2], $indices);
    }

    #[Test]
    public function split_chunks_reassemble_to_original_raw_binary(): void
    {
        $original   = str_repeat('z', 4 * 1024 * 1024 + 123);   // just over 4 MB
        $base64     = base64_encode($original);
        $capacities = array_fill(0, 3, 2 * 1024 * 1024);

        $segments    = $this->svc->split($base64, $capacities);
        $reassembled = $this->svc->reassemble($segments);

        $this->assertSame($original, $reassembled);
    }

    #[Test]
    public function split_stores_correct_sha256_hash_per_chunk(): void
    {
        $raw        = str_repeat('c', 3 * 1024 * 1024);
        $base64     = base64_encode($raw);
        $capacities = array_fill(0, 2, 2 * 1024 * 1024);

        $segments = $this->svc->split($base64, $capacities);

        foreach ($segments as $seg) {
            $this->assertSame(hash('sha256', $seg['chunk']), $seg['hash']);
        }
    }

    #[Test]
    public function split_throws_when_not_enough_carriers(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/carrier image/i');

        $raw        = str_repeat('d', 5 * 1024 * 1024);   // needs 3 carriers
        $base64     = base64_encode($raw);
        $capacities = [2 * 1024 * 1024];                  // only 1 supplied

        $this->svc->split($base64, $capacities);
    }

    #[Test]
    public function split_throws_when_carrier_is_too_small_for_its_chunk(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/too small/i');

        $raw        = str_repeat('e', 3 * 1024 * 1024);
        $base64     = base64_encode($raw);
        // Two carriers but the first is tiny and cannot hold a 2 MB chunk.
        $capacities = [512, 2 * 1024 * 1024];

        $this->svc->split($base64, $capacities);
    }
}
