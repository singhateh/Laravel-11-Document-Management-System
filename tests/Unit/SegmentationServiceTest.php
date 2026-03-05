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
}
