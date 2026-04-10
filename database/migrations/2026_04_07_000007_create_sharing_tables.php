<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated migration: notifications, share_documents, file_requests.
 *
 * Combines the sharing and notification tables into a single migration.
 * Includes the final schema after all subsequent alterations (permission_level
 * as single source of truth, no redundant boolean columns).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── Notifications ─────────────────────────────────────────────
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->string('notifiable_id')->nullable();
                $table->string('notifiable_type')->nullable();
                $table->string('activity_type');
                $table->string('model_type');
                $table->string('model_id');
                $table->longText('message')->nullable();
                $table->enum('status', ['UNREAD', 'READ'])->default('UNREAD');
                $table->enum('dismiss_status', ['UNDISMISSED', 'DISMISSED'])->default('UNDISMISSED');
                $table->foreignId('created_by_user_id')
                    ->constrained('users');
                $table->timestamps();
                $table->softDeletes();

                $table->index(
                    ['notifiable_id', 'notifiable_type', 'status', 'dismiss_status'],
                    'notif_notifiable_status_idx'
                );
            });
        }

        // ── Share Documents ───────────────────────────────────────────
        if (!Schema::hasTable('share_documents')) {
            Schema::create('share_documents', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->string('token')->unique();
                $table->string('shared_id')->nullable();
                $table->date('valid_until')->nullable();
                $table->enum('visibility', ['public', 'private'])->default('public');
                // permission_level is the single source of truth for permissions.
                $table->string('permission_level')->default('viewer');
                $table->morphs('user');
                $table->morphs('share');
                $table->timestamps();
            });
        }

        // ── File Requests ─────────────────────────────────────────────
        if (!Schema::hasTable('file_requests')) {
            Schema::create('file_requests', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('request_to')->nullable();
                $table->unsignedBigInteger('folder_id')->nullable();
                $table->unsignedBigInteger('tag_id')->nullable();
                $table->unsignedBigInteger('due_date_in_number')->nullable();
                $table->longText('note')->nullable();
                $table->timestamps();

                $table->foreign('folder_id')->references('id')->on('folders')->onDelete('cascade');
                $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_requests');
        Schema::dropIfExists('share_documents');
        Schema::dropIfExists('notifications');
    }
};
