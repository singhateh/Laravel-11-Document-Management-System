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

        $masterKey = hash_pbkdf2(
            'sha256',
            $password,
            $rawSalt,
            $iterations,
            self::KEY_LENGTH * 2, // *2 because raw_output=false returns hex chars (2 per byte)
            false // return hex string
        );

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

        $dek = hash_pbkdf2(
            'sha256',
            hex2bin($masterKey),
            $rawSalt,
            $iterations,
            self::KEY_LENGTH * 2, // *2 because raw_output=false returns hex chars (2 per byte)
            false // return hex string
        );

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
        $iv  = $this->secureRandom(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
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

        return [
            'ciphertext' => bin2hex($ciphertext),
            'iv'         => bin2hex($iv),
            'auth_tag'   => bin2hex($tag),
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
        $plaintext = openssl_decrypt(
            hex2bin($ciphertext),
            self::CIPHER,
            hex2bin($dek),
            OPENSSL_RAW_DATA,
            hex2bin($iv),
            hex2bin($authTag)
        );

        if ($plaintext === false) {
            throw new Exception('AES-256-GCM decryption failed: authentication tag mismatch or corrupt data.');
        }

        return $plaintext;
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
