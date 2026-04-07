<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: document_watchers table + documents table additions.
 *
 * Creates the document_watchers table for tracking which users are watching
 * which documents, and adds last_updated_at / last_updated_by_user_id
 * columns to the documents table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── Document Watchers ─────────────────────────────────────────
        if (!Schema::hasTable('document_watchers')) {
            Schema::create('document_watchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');
                $table->timestamps();

                $table->unique(['user_id', 'document_id']);
            });
        }

        // ── Documents additions ───────────────────────────────────────
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (!Schema::hasColumn('documents', 'last_updated_at')) {
                    $table->timestamp('last_updated_at')->nullable()->after('updated_at');
                }
                if (!Schema::hasColumn('documents', 'last_updated_by_user_id')) {
                    // FIX: 3NF transitive dependency — replaced text column with FK to users.
                    $table->foreignId('last_updated_by_user_id')
                        ->nullable()
                        ->after('last_updated_at')
                        ->constrained('users')
                        ->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropForeign(['last_updated_by_user_id']);
                $table->dropColumn(['last_updated_at', 'last_updated_by_user_id']);
            });
        }

        Schema::dropIfExists('document_watchers');
    }
};
