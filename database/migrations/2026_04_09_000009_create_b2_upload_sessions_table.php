<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2_upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_token')->unique();
            $table->string('idempotency_token', 100)->nullable()->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('object_key');
            $table->string('original_filename');
            $table->string('sanitized_filename');
            $table->string('expected_mime', 150);
            $table->unsignedBigInteger('expected_size');
            $table->string('checksum_sha256', 64)->nullable();
            $table->foreignId('folder_id')->constrained('folders')->cascadeOnDelete();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->string('status', 30)->default('signed');
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2_upload_sessions');
    }
};
