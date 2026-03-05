<?php

namespace Tests\Unit;

use App\Services\Stego\CryptoService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CryptoServiceTest
 *
 * Unit tests for the CryptoService class.
 * All tests run entirely in-memory — no database, no filesystem, no HTTP.
 *
 * Coverage goals:
 *  - MKD: deterministic re-derivation with same salt
 *  - MKD: different salts → different keys
 *  - DEK: deterministic re-derivation from master key + document ID
 *  - Encrypt / decrypt round-trip returns original plaintext
 *  - Decryption with wrong key throws
 *  - Decryption with tampered ciphertext throws
 *  - SHA-256 hash + verify round-trip
 *  - Hash verification fails on altered content
 */
class CryptoServiceTest extends TestCase
{
    private CryptoService $crypto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crypto = new CryptoService();
    }

    // -------------------------------------------------------------------------
    // Master Key Derivation
    // -------------------------------------------------------------------------

    #[Test]
    public function mkd_is_deterministic_given_same_password_and_salt(): void
    {
        $result1 = $this->crypto->deriveMasterKey('SecurePass1!', null);
        $result2 = $this->crypto->deriveMasterKey('SecurePass1!', $result1['salt']);

        $this->assertSame($result1['masterKey'], $result2['masterKey']);
    }

    #[Test]
    public function mkd_generates_unique_salt_each_call_when_salt_is_null(): void
    {
        $result1 = $this->crypto->deriveMasterKey('SecurePass1!', null);
        $result2 = $this->crypto->deriveMasterKey('SecurePass1!', null);

        $this->assertNotSame($result1['salt'], $result2['salt']);
    }

    #[Test]
    public function mkd_different_salts_produce_different_keys(): void
    {
        $result1 = $this->crypto->deriveMasterKey('SecurePass1!', null);
        $result2 = $this->crypto->deriveMasterKey('SecurePass1!', null);

        $this->assertNotSame($result1['masterKey'], $result2['masterKey']);
    }

    #[Test]
    public function mkd_returns_256_bit_key_as_64_hex_chars(): void
    {
        $result = $this->crypto->deriveMasterKey('SecurePass1!');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result['masterKey']);
    }

    // -------------------------------------------------------------------------
    // Document Encryption Key (DEK)
    // -------------------------------------------------------------------------

    #[Test]
    public function dek_is_deterministic_given_same_master_key_document_id_and_salt(): void
    {
        $mkd     = $this->crypto->deriveMasterKey('SecurePass1!');
        $result1 = $this->crypto->deriveDEK($mkd['masterKey'], '42');
        $result2 = $this->crypto->deriveDEK($mkd['masterKey'], '42', $result1['salt']);

        $this->assertSame($result1['dek'], $result2['dek']);
    }

    #[Test]
    public function dek_differs_per_document_id(): void
    {
        $mkd     = $this->crypto->deriveMasterKey('SecurePass1!');
        $result1 = $this->crypto->deriveDEK($mkd['masterKey'], '42');
        $result2 = $this->crypto->deriveDEK($mkd['masterKey'], '99');

        $this->assertNotSame($result1['dek'], $result2['dek']);
    }

    // -------------------------------------------------------------------------
    // Encrypt / Decrypt round-trip
    // -------------------------------------------------------------------------

    #[Test]
    public function encrypt_decrypt_round_trip_returns_original_plaintext(): void
    {
        $mkd       = $this->crypto->deriveMasterKey('SecurePass1!');
        $dek       = $this->crypto->deriveDEK($mkd['masterKey'], 'doc-1');
        $plaintext = 'Hello, StegoLock! This is a secret document.';

        $encrypted = $this->crypto->encrypt($plaintext, $dek['dek']);
        $decrypted = $this->crypto->decrypt(
            $encrypted['ciphertext'],
            $dek['dek'],
            $encrypted['iv'],
            $encrypted['auth_tag']
        );

        $this->assertSame($plaintext, $decrypted);
    }

    #[Test]
    public function decrypt_with_wrong_key_throws(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/decryption failed/i');

        $mkd1      = $this->crypto->deriveMasterKey('CorrectPass1!');
        $mkd2      = $this->crypto->deriveMasterKey('WrongPass1!');
        $dek1      = $this->crypto->deriveDEK($mkd1['masterKey'], 'doc-1');
        $dek2      = $this->crypto->deriveDEK($mkd2['masterKey'], 'doc-1');
        $encrypted = $this->crypto->encrypt('secret', $dek1['dek']);

        $this->crypto->decrypt($encrypted['ciphertext'], $dek2['dek'], $encrypted['iv'], $encrypted['auth_tag']);
    }

    #[Test]
    public function decrypt_with_tampered_ciphertext_throws(): void
    {
        $this->expectException(\Exception::class);

        $mkd       = $this->crypto->deriveMasterKey('SecurePass1!');
        $dek       = $this->crypto->deriveDEK($mkd['masterKey'], 'doc-1');
        $encrypted = $this->crypto->encrypt('secret data', $dek['dek']);

        // Flip first hex nibble to tamper with the ciphertext.
        $tampered = $encrypted;
        $tampered['ciphertext'] = ($encrypted['ciphertext'][0] === 'f' ? '0' : 'f')
            . substr($encrypted['ciphertext'], 1);

        $this->crypto->decrypt($tampered['ciphertext'], $dek['dek'], $encrypted['iv'], $encrypted['auth_tag']);
    }

    #[Test]
    public function each_encrypt_call_uses_a_unique_iv(): void
    {
        $mkd = $this->crypto->deriveMasterKey('SecurePass1!');
        $dek = $this->crypto->deriveDEK($mkd['masterKey'], 'doc-1');

        $enc1 = $this->crypto->encrypt('same content', $dek['dek']);
        $enc2 = $this->crypto->encrypt('same content', $dek['dek']);

        $this->assertNotSame($enc1['iv'], $enc2['iv']);
    }

    // -------------------------------------------------------------------------
    // Hashing
    // -------------------------------------------------------------------------

    #[Test]
    public function hash_verify_round_trip_passes(): void
    {
        $content = 'document contents';
        $hash    = $this->crypto->hashDocument($content);

        $this->assertTrue($this->crypto->verifyHash($content, $hash));
    }

    #[Test]
    public function verify_hash_fails_on_altered_content(): void
    {
        $hash = $this->crypto->hashDocument('original content');

        $this->assertFalse($this->crypto->verifyHash('altered content', $hash));
    }
}
