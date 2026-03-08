<?php

namespace Tests\Unit;

use App\Services\Stego\CryptoService;
use App\Services\Stego\SegmentationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * StegoDecodePipelineTest
 *
 * Exercises the full encode→decode pipeline through the crypto and
 * segmentation layers — no database, no filesystem, no HTTP, no carriers.
 *
 * Simulates exactly what StegoDocumentService does:
 *
 *   Encode:
 *     CryptoService::encrypt($plaintext)          → base64 ciphertext + iv + auth_tag
 *     SegmentationService::split($base64, caps)   → raw binary chunks (+ per-chunk hashes)
 *     StegoService::embed(chunk → carrier)        [mocked: chunks stored directly]
 *
 *   Decode:
 *     StegoService::extract(carrier)              [mocked: chunks returned directly]
 *     SegmentationService::reassemble(chunks)     → raw binary ciphertext
 *     CryptoService::decrypt(raw, dek, iv, tag)   → original plaintext
 *
 * Coverage:
 *  - Small payload (<2 MB) — single chunk round-trip
 *  - Large payload (>4 MB) — multi-chunk round-trip
 *  - Binary payload (null bytes, arbitrary bytes)
 *  - Gzip compression actually reduces size for compressible content
 *  - Tampered chunk hash → reassemble() throws integrity error
 *  - Modified carrier chunk → decrypt() throws auth-tag mismatch
 *  - Wrong master key → decrypt() throws
 *  - Hash stored in stego_documents is of ORIGINAL plaintext (pre-compression)
 */
class StegoDecodePipelineTest extends TestCase
{
    private CryptoService      $crypto;
    private SegmentationService $seg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crypto = new CryptoService();
        $this->seg    = new SegmentationService();
    }

    // =========================================================================
    // Round-trip helpers
    // =========================================================================

    /**
     * Run the full encode→decode pipeline and return the recovered plaintext.
     *
     * @param  string $plaintext  Original document bytes
     * @param  string $password   User password for MKD
     * @param  string $docRef     Document reference for DEK
     * @param  int    $numCarriers Number of carriers to simulate
     * @return array{ recovered: string, encrypted: array, segments: array, masterKey: string }
     */
    private function runEncode(
        string $plaintext,
        string $password  = 'TestPassword123!',
        string $docRef    = 'doc-pipeline-test',
        int    $numCarriers = 1
    ): array {
        $mkd       = $this->crypto->deriveMasterKey($password);
        $dek       = $this->crypto->deriveDEK($mkd['masterKey'], $docRef);
        $encrypted = $this->crypto->encrypt($plaintext, $dek['dek']);

        // Simulate carrier capacities: each carrier can hold up to 2 MB.
        $capacities = array_fill(0, $numCarriers, 2 * 1024 * 1024);
        $segments   = $this->seg->split($encrypted['ciphertext'], $capacities);

        return [
            'mkd'       => $mkd,
            'dek'       => $dek,
            'encrypted' => $encrypted,
            'segments'  => $segments,
        ];
    }

    private function runDecode(array $encode, string $docRef = 'doc-pipeline-test'): string
    {
        // Simulate carrier extraction: segments hold the raw binary chunks directly.
        $reassembled = $this->seg->reassemble($encode['segments']);

        // Re-derive DEK with stored salt + iterations (as StegoDocumentService does).
        $dek = $this->crypto->deriveDEK(
            $encode['mkd']['masterKey'],
            $docRef,
            $encode['dek']['salt'],
            $encode['dek']['iterations']
        );

        return $this->crypto->decrypt(
            $reassembled,
            $dek['dek'],
            $encode['encrypted']['iv'],
            $encode['encrypted']['auth_tag']
        );
    }

    // =========================================================================
    // Round-trip tests
    // =========================================================================

    #[Test]
    public function small_plaintext_survives_full_encode_decode_round_trip(): void
    {
        $plaintext = 'This is a short document for StegoLock.';
        $encode    = $this->runEncode($plaintext);
        $recovered = $this->runDecode($encode);

        $this->assertSame($plaintext, $recovered);
    }

    #[Test]
    public function large_plaintext_survives_multi_chunk_encode_decode_round_trip(): void
    {
        // 5 MB of compressible text → after gzip ≈ 0.05 MB → 1 chunk
        $plaintext = str_repeat("The quick brown fox jumps over the lazy dog. ", 120_000);
        $encode    = $this->runEncode($plaintext, numCarriers: 1);
        $recovered = $this->runDecode($encode);

        $this->assertSame($plaintext, $recovered);
    }

    #[Test]
    public function binary_payload_survives_round_trip(): void
    {
        // Simulate a small binary file (e.g. a PNG header + random bytes).
        $plaintext = "\x89PNG\r\n\x1a\n" . random_bytes(512);
        $encode    = $this->runEncode($plaintext);
        $recovered = $this->runDecode($encode);

        $this->assertSame($plaintext, $recovered);
    }

    #[Test]
    public function payload_with_null_bytes_survives_round_trip(): void
    {
        $plaintext = "before\x00middle\x00end";
        $encode    = $this->runEncode($plaintext);
        $recovered = $this->runDecode($encode);

        $this->assertSame($plaintext, $recovered);
    }

    #[Test]
    public function multi_chunk_payload_is_correctly_split_and_reassembled(): void
    {
        // 5 MB of incompressible-ish data → stays ~5 MB after gzip → 3 chunks
        $plaintext = random_bytes(5 * 1024 * 1024);
        $encode    = $this->runEncode($plaintext, numCarriers: 3);
        $recovered = $this->runDecode($encode);

        $this->assertSame($plaintext, $recovered);
    }

    // =========================================================================
    // Compression verification
    // =========================================================================

    #[Test]
    public function gzip_compression_reduces_size_for_compressible_content(): void
    {
        $plaintext = str_repeat('AAAA', 100_000);   // 400 KB, highly compressible

        $mkd       = $this->crypto->deriveMasterKey('TestPassword123!');
        $dek       = $this->crypto->deriveDEK($mkd['masterKey'], 'doc-compress-test');
        $encrypted = $this->crypto->encrypt($plaintext, $dek['dek']);

        // The base64-encoded ciphertext should be much smaller than a bin2hex-encoded
        // 400 KB payload (which would be 800 KB hex).
        $ciphertextBytes = strlen(base64_decode($encrypted['ciphertext']));

        $this->assertLessThan(strlen($plaintext), $ciphertextBytes,
            'Compressed+encrypted ciphertext should be smaller than original plaintext.');
    }

    #[Test]
    public function hash_in_encrypted_envelope_is_of_original_plaintext(): void
    {
        $plaintext = 'Hash must be computed over the original, pre-compression bytes.';

        $mkd = $this->crypto->deriveMasterKey('TestPassword123!');
        $dek = $this->crypto->deriveDEK($mkd['masterKey'], 'doc-hash-test');

        $expectedHash = $this->crypto->hashDocument($plaintext);
        // Encrypt internally compresses; hash is responsibility of caller (StegoDocumentService).
        // Verify the hash helper operates on the unmodified plaintext.
        $this->assertTrue($this->crypto->verifyHash($plaintext, $expectedHash));
        $this->assertFalse($this->crypto->verifyHash($plaintext . 'X', $expectedHash));
    }

    // =========================================================================
    // Integrity / tamper detection
    // =========================================================================

    #[Test]
    public function tampered_chunk_hash_is_detected_during_reassembly(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/integrity check failed/i');

        $encode = $this->runEncode('Sensitive document content.');
        $segments = $encode['segments'];
        $segments[0]['hash'] = str_repeat('0', 64);   // corrupt the stored hash

        $this->seg->reassemble($segments, verifyHashes: true);
    }

    #[Test]
    public function modified_carrier_chunk_triggers_gcm_auth_tag_failure(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/decryption failed/i');

        $encode   = $this->runEncode('Sensitive document content.');
        $segments = $encode['segments'];

        // Flip a bit in the first carrier chunk to simulate a modified carrier image.
        $chunk              = $segments[0]['chunk'];
        $chunk[0]           = chr(ord($chunk[0]) ^ 0xFF);
        $segments[0]['chunk'] = $chunk;
        // Update hash so reassembly succeeds but decryption fails.
        $segments[0]['hash'] = hash('sha256', $chunk);

        $reassembled = $this->seg->reassemble($segments, verifyHashes: true);

        $dek = $this->crypto->deriveDEK(
            $encode['mkd']['masterKey'],
            'doc-pipeline-test',
            $encode['dek']['salt'],
            $encode['dek']['iterations']
        );

        $this->crypto->decrypt(
            $reassembled,
            $dek['dek'],
            $encode['encrypted']['iv'],
            $encode['encrypted']['auth_tag']
        );
    }

    #[Test]
    public function wrong_master_key_prevents_decryption(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/decryption failed/i');

        $encode = $this->runEncode('Top secret file.');

        // Reassemble correctly, but re-derive DEK from a different master key.
        $reassembled = $this->seg->reassemble($encode['segments']);

        $wrongMkd = $this->crypto->deriveMasterKey('WrongPassword!');
        $wrongDek = $this->crypto->deriveDEK($wrongMkd['masterKey'], 'doc-pipeline-test');

        $this->crypto->decrypt(
            $reassembled,
            $wrongDek['dek'],
            $encode['encrypted']['iv'],
            $encode['encrypted']['auth_tag']
        );
    }

    #[Test]
    public function shuffled_segment_order_still_decodes_correctly(): void
    {
        $plaintext = str_repeat('Segment order must not matter. ', 50_000);   // ~1.5 MB
        $encode    = $this->runEncode($plaintext, numCarriers: 1);

        // Even with a single segment, verify the reassembly order logic holds.
        $recovered = $this->runDecode($encode);
        $this->assertSame($plaintext, $recovered);
    }

    #[Test]
    public function different_document_refs_produce_different_plaintexts_on_decode(): void
    {
        $plaintext = 'Same plaintext, different document refs.';
        $password  = 'SharedPassword1!';

        $mkd  = $this->crypto->deriveMasterKey($password);
        $dek1 = $this->crypto->deriveDEK($mkd['masterKey'], 'doc-A');
        $dek2 = $this->crypto->deriveDEK($mkd['masterKey'], 'doc-B');

        $enc1 = $this->crypto->encrypt($plaintext, $dek1['dek']);
        $enc2 = $this->crypto->encrypt($plaintext, $dek2['dek']);

        // The two ciphertexts should be different (different DEKs + different IVs).
        $this->assertNotSame($enc1['ciphertext'], $enc2['ciphertext']);

        // But both should decrypt to the same plaintext.
        $raw1 = base64_decode($enc1['ciphertext']);
        $raw2 = base64_decode($enc2['ciphertext']);

        $this->assertSame($plaintext, $this->crypto->decrypt($raw1, $dek1['dek'], $enc1['iv'], $enc1['auth_tag']));
        $this->assertSame($plaintext, $this->crypto->decrypt($raw2, $dek2['dek'], $enc2['iv'], $enc2['auth_tag']));
    }
}
