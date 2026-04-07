<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated migration: folders, documents, comments.
 *
 * Combines the core content tables into a single migration.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── Folders ───────────────────────────────────────────────────
        if (!Schema::hasTable('folders')) {
            Schema::create('folders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('position')->default(0);
                $table->string('name');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->foreign('parent_id')->references('id')->on('folders')->onDelete('cascade');
                $table->enum('visibility', ['public', 'private'])->default('public');
                $table->string('background_color')->nullable();
                $table->string('foreground_color')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── Documents ─────────────────────────────────────────────────
        if (!Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('position')->default(0);
                $table->string('name')->nullable();
                $table->string('original_name')->nullable();
                $table->string('file_path')->nullable();
                $table->bigInteger('size')->nullable();
                $table->string('extension')->nullable();
                $table->foreignId('folder_id')
                    ->nullable()
                    ->constrained()
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
                $table->enum('visibility', ['public', 'private'])->default('public');
                $table->bigInteger('share')->default(0);
                $table->bigInteger('download')->default(0);
                $table->string('email')->nullable();
                $table->string('url')->nullable();
                // AES-256-GCM encryption metadata
                $table->boolean('is_encrypted')->default(false);
                $table->string('enc_iv', 24)->nullable();
                $table->string('enc_auth_tag', 32)->nullable();
                $table->string('enc_dek_salt', 32)->nullable();
                $table->unsignedInteger('enc_dek_iterations')->nullable();
                $table->string('enc_hash_sha256', 64)->nullable();
                // Owner FK to users (3NF fix)
                $table->foreignId('owner_id')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('document_date')->nullable();
                $table->string('emojis')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── Comments ──────────────────────────────────────────────────
        if (!Schema::hasTable('comments')) {
            Schema::create('comments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id');
                $table->unsignedBigInteger('user_id');
                $table->text('content');
                $table->unsignedBigInteger('parent_id')->nullable(); // For replies
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('parent_id')->references('id')->on('comments')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('folders');
    }
};
