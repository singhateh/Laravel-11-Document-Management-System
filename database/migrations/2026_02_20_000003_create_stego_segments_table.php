<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stego documents store encryption metadata for documents hidden via steganography.
     * Links to the existing "documents" table (file management) without altering it.
     */
    public function up(): void
    {
        Schema::create('stego_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Encryption metadata (AES-256-GCM) — final column names with stego_ prefix
            $table->longText('ciphertext');
            $table->string('stego_iv', 64);
            $table->string('stego_auth_tag', 64);
            $table->string('stego_hash_sha256', 64);

            // Key derivation metadata
            $table->string('stego_dek_salt', 64)->nullable();
            $table->unsignedInteger('stego_dek_iter')->default(10000);
            $table->boolean('compressed')->default(true);

            // Storage
            $table->string('s3_key')->nullable();
            $table->string('s3_url')->nullable();

            $table->timestamps();

            $table->index('document_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stego_documents');
    }
};
