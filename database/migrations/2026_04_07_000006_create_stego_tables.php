<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated migration: stego_carriers, stego_documents, stego_segments, stego_document_grants.
 *
 * Combines all steganography-related tables into a single migration.
 * Includes all subsequent column additions (status, decoding fields, pool columns).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── Stego Carriers ─────────────────────────────────────────────
        if (!Schema::hasTable('stego_carriers')) {
            Schema::create('stego_carriers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('file_path');
                $table->string('file_type');           // image, audio, text
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size');    // bytes
                $table->string('s3_key')->nullable();  // AWS S3 object key
                $table->float('psnr', 8, 4)->nullable()
                    ->comment('PSNR in dB measured after LSB embedding (images only; null otherwise)');
                // Pool management columns
                $table->enum('validation_status', ['pending', 'valid', 'invalid'])
                    ->default('pending')
                    ->comment('Carrier validation status: pending, valid, or invalid');
                $table->string('validation_error')->nullable()
                    ->comment('Validation failure reason if status is invalid');
                $table->unsignedBigInteger('capacity_bytes')->nullable()
                    ->comment('Measured carrier capacity in bytes');
                $table->boolean('is_in_use')->default(false)
                    ->comment('Whether carrier is locked by an active encode operation');
                $table->timestamp('validated_at')->nullable()
                    ->comment('Timestamp when validation completed');
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('file_type');
                $table->index('uploaded_by');
                $table->index(['uploaded_by', 'validation_status', 'is_in_use']);
            });
        }

        // ── Stego Documents ───────────────────────────────────────────
        if (!Schema::hasTable('stego_documents')) {
            Schema::create('stego_documents', function (Blueprint $table) {
                $table->id();
                // Status column for async encode job lifecycle
                $table->enum('status', ['pending', 'ready', 'failed'])
                    ->default('ready');
                $table->text('failed_reason')->nullable();
                $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                // Encryption metadata (AES-256-GCM) — nullable for pending skeleton rows
                $table->longText('ciphertext')->nullable();
                $table->string('stego_iv', 64)->nullable();
                $table->string('stego_auth_tag', 64)->nullable();
                $table->string('stego_hash_sha256', 64)->nullable();

                // Key derivation metadata
                $table->string('stego_dek_salt', 64)->nullable();
                $table->unsignedInteger('stego_dek_iter')->default(10000);
                $table->boolean('compressed')->default(true);

                // Storage
                $table->string('s3_key')->nullable();

                // Decoding fields
                $table->string('decoding_status')->default('idle')
                    ->comment('Decoding status: idle, pending, in_progress, completed, failed');
                $table->text('decoding_error')->nullable()
                    ->comment('Error message if decoding failed');
                $table->string('download_path')->nullable()
                    ->comment('Storage path to the decoded document');
                $table->decimal('decoding_duration', 10, 3)->nullable()
                    ->comment('Total decoding duration in seconds (with milliseconds precision)');

                $table->timestamps();

                $table->index('document_id');
                $table->index('user_id');
                $table->index('status', 'stego_documents_status_index');
            });
        }

        // ── Stego Segments ────────────────────────────────────────────
        if (!Schema::hasTable('stego_segments')) {
            Schema::create('stego_segments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stego_document_id')->constrained('stego_documents')->cascadeOnDelete();
                $table->foreignId('stego_carrier_id')->constrained('stego_carriers')->cascadeOnDelete();
                $table->unsignedSmallInteger('segment_index'); // order of this chunk (0-based, max 65 535)
                $table->longText('encrypted_chunk')->nullable(); // base64-encoded encrypted bytes
                $table->string('s3_key')->nullable();           // AWS S3 object key for this chunk
                $table->string('chunk_hash');                   // SHA-256 of this chunk for integrity
                $table->timestamps();

                $table->index(['stego_document_id', 'segment_index']);
                $table->index('stego_carrier_id');
            });
        }

        // ── Stego Document Grants ─────────────────────────────────────
        if (!Schema::hasTable('stego_document_grants')) {
            Schema::create('stego_document_grants', function (Blueprint $table) {
                $table->id();

                $table->foreignId('stego_document_id')
                    ->constrained('stego_documents')
                    ->onDelete('cascade');

                $table->foreignId('viewer_user_id')
                    ->constrained('users')
                    ->onDelete('cascade');

                $table->foreignId('granted_by')
                    ->constrained('users')
                    ->onDelete('cascade');

                $table->timestamps();

                // One grant per viewer per document.
                $table->unique(['stego_document_id', 'viewer_user_id']);
                $table->index('viewer_user_id', 'stego_grants_viewer_index');
                $table->index('granted_by', 'stego_grants_grantor_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stego_document_grants');
        Schema::dropIfExists('stego_segments');
        Schema::dropIfExists('stego_documents');
        Schema::dropIfExists('stego_carriers');
    }
};
