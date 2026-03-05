<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds stego_ prefix to AES-256-GCM encryption fields in stego_documents.
 *
 * Rationale: field names iv, auth_tag, hash_sha256, dek_salt, dek_iterations
 * were bare and visually identical to documents.enc_* fields, making it
 * impossible to distinguish steganographic encryption from file-at-rest
 * encryption without reading comments. The stego_ prefix makes the context
 * self-evident. dek_iterations is also shortened to stego_dek_iter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stego_documents', function (Blueprint $table) {
            $table->renameColumn('iv',             'stego_iv');
            $table->renameColumn('auth_tag',       'stego_auth_tag');
            $table->renameColumn('hash_sha256',    'stego_hash_sha256');
            $table->renameColumn('dek_salt',       'stego_dek_salt');
            $table->renameColumn('dek_iterations', 'stego_dek_iter');
        });
    }

    public function down(): void
    {
        Schema::table('stego_documents', function (Blueprint $table) {
            $table->renameColumn('stego_iv',          'iv');
            $table->renameColumn('stego_auth_tag',    'auth_tag');
            $table->renameColumn('stego_hash_sha256', 'hash_sha256');
            $table->renameColumn('stego_dek_salt',    'dek_salt');
            $table->renameColumn('stego_dek_iter',    'dek_iterations');
        });
    }
};
