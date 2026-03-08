<?php

namespace App\Services\Stego;

use Exception;

/**
 * CryptoService
 *
 * Handles all cryptographic operations for StegoLock:
 *  - Master Key Derivation (MKD) via PBKDF2-SHA256
 *  - Document Encryption Key (DEK) derivation
 *  - AES-256-GCM symmetric encryption / decryption
 *  - SHA-256 document integrity hashing
 *
 * Replaces the gRPC crypto-service microservice described in the .md guide.
 * All operations use PHP's built-in OpenSSL extension — no extra packages needed.
 */
class CryptoService
{
    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    private const CIPHER         = 'AES-256-GCM';
    private const KEY_LENGTH     = 32;   // 256 bits
    private const IV_LENGTH      = 12;   // 96 bits (recommended for GCM)
    private const AUTH_TAG_LEN   = 16;   // 128 bits

    // MKD defaults (can be overridden via .env)
    private const MKD_ITERATIONS  = 100_000;
    private const MKD_SALT_LENGTH = 16;

    // DEK defaults
    private const DEK_ITERATIONS  = 10_000;
    private const DEK_SALT_LENGTH = 16;

    // -------------------------------------------------------------------------
    // Master Key Derivation (MKD)
    // -------------------------------------------------------------------------

    /**
     * Derive a Master Key from a user password using PBKDF2-SHA256.
     *
     * @param  string      $password   The user's plaintext password
     * @param  string|null $salt       Hex-encoded salt; generated if null
     * @param  int|null    $iterations PBKDF2 iteration count
     * @return array{ masterKey: string, salt: string, iterations: int }
     *              masterKey and salt are hex-encoded
     */
    public function deriveMasterKey(
        string $password,
        ?string $salt = null,
        ?int $iterations = null
    ): array {
        $iterations = $iterations ?? (int) config('stegolock.mkd_iterations', self::MKD_ITERATIONS);

        if ($salt === null) {
            $rawSalt = $this->secureRandom(self::MKD_SALT_LENGTH);
            $salt    = bin2hex($rawSalt);
        } else {
            $rawSalt = hex2bin($salt);
        }

        $masterKey = $this->pbkdf2Derive($password, $rawSalt, $iterations);

        return [
            'masterKey'  => $masterKey,
            'salt'       => $salt,
            'iterations' => $iterations,
        ];
    }

    // -------------------------------------------------------------------------
    // Document Encryption Key (DEK) Derivation
    // -------------------------------------------------------------------------

    /**
     * Derive a per-document DEK from the master key and a document identifier.
     *
     * Using a unique document ID ensures that each document has its own DEK,
     * so compromising one DEK does not expose others.
     *
     * @param  string      $masterKey  Hex-encoded master key
     * @param  string      $documentId Unique document identifier (UUID or int cast to string)
     * @param  string|null $salt       Hex-encoded salt; generated if null
     * @param  int|null    $iterations PBKDF2 iteration count
     * @return array{ dek: string, salt: string, iterations: int }
     *              dek and salt are hex-encoded
     */
    public function deriveDEK(
        string $masterKey,
        string $documentId,
        ?string $salt = null,
        ?int $iterations = null
    ): array {
        $iterations = $iterations ?? (int) config('stegolock.dek_iterations', self::DEK_ITERATIONS);

        if ($salt === null) {
            // Deterministic salt derived from documentId so the same DEK can be
            // re-derived without storing extra randomness.
            $rawSalt = substr(hash('sha256', $documentId . 'dek-salt', true), 0, self::DEK_SALT_LENGTH);
            $salt    = bin2hex($rawSalt);
        } else {
            $rawSalt = hex2bin($salt);
        }

        $dek = $this->pbkdf2Derive(hex2bin($masterKey), $rawSalt, $iterations);

        return [
            'dek'        => $dek,
            'salt'       => $salt,
            'iterations' => $iterations,
        ];
    }

    // -------------------------------------------------------------------------
    // Encryption
    // -------------------------------------------------------------------------

    /**
     * Encrypt plaintext using AES-256-GCM.
     *
     * @param  string $plaintext Plaintext bytes to encrypt
     * @param  string $dek       Hex-encoded Document Encryption Key
     * @return array{ ciphertext: string, iv: string, auth_tag: string }
     *              All values are hex-encoded
     * @throws Exception
     */
    public function encrypt(string $plaintext, string $dek): array
    {
        // Compress BEFORE encrypting: encrypted output is random noise and
        // incompressible, so compression must come first to be effective.
        $compressed = gzcompress($plaintext, 6);

        $iv  = $this->secureRandom(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $compressed,
            self::CIPHER,
            hex2bin($dek),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::AUTH_TAG_LEN
        );

        if ($ciphertext === false) {
            throw new Exception('AES-256-GCM encryption failed: ' . openssl_error_string());
        }

        // Base64-encode all output: ~33% overhead vs 100% overhead for bin2hex.
        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv'         => base64_encode($iv),
            'auth_tag'   => base64_encode($tag),
        ];
    }

    // -------------------------------------------------------------------------
    // Decryption
    // -------------------------------------------------------------------------

    /**
     * Decrypt ciphertext using AES-256-GCM.
     *
     * @param  string $ciphertext Hex-encoded ciphertext
     * @param  string $dek        Hex-encoded Document Encryption Key
     * @param  string $iv         Hex-encoded initialisation vector
     * @param  string $authTag    Hex-encoded GCM authentication tag
     * @return string Decrypted plaintext
     * @throws Exception If decryption or authentication fails
     */
    public function decrypt(string $ciphertext, string $dek, string $iv, string $authTag): string
    {
        // $ciphertext is raw binary reassembled from carrier chunks.
        // $iv and $authTag are base64-encoded strings stored in stego_documents.
        $compressed = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            hex2bin($dek),
            OPENSSL_RAW_DATA,
            base64_decode($iv),
            base64_decode($authTag)
        );

        if ($compressed === false) {
            throw new Exception('AES-256-GCM decryption failed: authentication tag mismatch or corrupt data.');
        }

        // Decompress AFTER decrypting to recover the original plaintext.
        return gzuncompress($compressed);
    }

    // -------------------------------------------------------------------------
    // Hashing
    // -------------------------------------------------------------------------

    /**
     * Compute a SHA-256 integrity hash of the document plaintext.
     *
     * @param  string $plaintext
     * @return string 64-character hex string
     */
    public function hashDocument(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * Verify a SHA-256 integrity hash.
     *
     * @param  string $plaintext
     * @param  string $expectedHash Hex-encoded hash
     * @return bool
     */
    public function verifyHash(string $plaintext, string $expectedHash): bool
    {
        return hash_equals($expectedHash, $this->hashDocument($plaintext));
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Shared PBKDF2-SHA256 key derivation — used by both deriveMasterKey() and deriveDEK().
     *
     * Eliminates the duplicated hash_pbkdf2() call that previously existed in both methods.
     *
     * @param  string $input      Password string or raw master-key bytes
     * @param  string $rawSalt    Raw binary salt
     * @param  int    $iterations PBKDF2 iteration count
     * @return string             Hex-encoded derived key (KEY_LENGTH bytes = 64 hex chars)
     */
    private function pbkdf2Derive(string $input, string $rawSalt, int $iterations): string
    {
        return hash_pbkdf2(
            'sha256',
            $input,
            $rawSalt,
            $iterations,
            self::KEY_LENGTH * 2, // KEY_LENGTH bytes × 2 hex chars/byte
            false                 // return hex string, not raw binary
        );
    }

    /**
     * Generate cryptographically secure random bytes.
     *
     * @param  int $length Number of bytes
     * @return string Raw binary string
     * @throws Exception If the system RNG is not available
     */
    private function secureRandom(int $length): string
    {
        $bytes = openssl_random_pseudo_bytes($length, $strong);

        if (!$strong) {
            throw new Exception('Failed to generate cryptographically strong random bytes.');
        }

        return $bytes;
    }
}
