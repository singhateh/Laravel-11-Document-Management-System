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

            // Encryption metadata (AES-256-GCM)
            $table->text('ciphertext');                     // hex-encoded encrypted content
            $table->string('iv', 64);                       // hex-encoded initialisation vector (12 bytes → 24 hex)
            $table->string('auth_tag', 64);                 // hex-encoded GCM authentication tag (16 bytes → 32 hex)
            $table->string('hash_sha256', 64);              // SHA-256 of original plaintext for integrity

            // Key derivation metadata
            $table->string('dek_salt', 64)->nullable();     // hex-encoded DEK derivation salt
            $table->unsignedInteger('dek_iterations')->default(10000);

            // Storage
            $table->string('s3_key')->nullable();           // AWS S3 object key for the stego file
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
