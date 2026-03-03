<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds AES-256-GCM encryption metadata columns to the documents table.
 *
 * When a document is uploaded, DocumentService encrypts the file on disk
 * and stores the GCM IV, authentication tag, DEK salt, and integrity hash
 * in these columns so the file can later be decrypted for download.
 *
 * Column prefix `enc_` distinguishes encryption metadata from other fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Flag: has this document's file been encrypted on disk?
            // NULL / false = legacy plaintext file; true = AES-256-GCM encrypted.
            $table->boolean('is_encrypted')->default(false)->after('position');

            // AES-256-GCM initialisation vector (12 bytes → 24 hex chars).
            $table->string('enc_iv', 24)->nullable()->after('is_encrypted');

            // GCM authentication tag (16 bytes → 32 hex chars).
            $table->string('enc_auth_tag', 32)->nullable()->after('enc_iv');

            // PBKDF2 salt used when deriving the per-document DEK (16 bytes → 32 hex chars).
            $table->string('enc_dek_salt', 32)->nullable()->after('enc_auth_tag');

            // PBKDF2 iteration count used for DEK derivation.
            // Stored so future iteration-count changes don't break existing files.
            $table->unsignedInteger('enc_dek_iterations')->nullable()->after('enc_dek_salt');

            // SHA-256 integrity hash of the plaintext (64 hex chars).
            // Verified after decryption to detect corruption or tampering.
            $table->string('enc_hash_sha256', 64)->nullable()->after('enc_dek_iterations');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'is_encrypted',
                'enc_iv',
                'enc_auth_tag',
                'enc_dek_salt',
                'enc_dek_iterations',
                'enc_hash_sha256',
            ]);
        });
    }
};
