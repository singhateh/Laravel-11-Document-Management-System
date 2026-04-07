<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated migration: emojis, emojiables.
 *
 * Creates the emoji lookup table and the polymorphic pivot for attaching
 * emojis to any model (documents, folders, etc.).
 *
 * NOTE: This is the clean-install version. For migrating existing data
 * from documents.emojis, see 2026_04_06_000001_create_emojis_table_and_migrate_from_documents.php.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── Emojis (lookup table) ─────────────────────────────────────
        if (!Schema::hasTable('emojis')) {
            Schema::create('emojis', function (Blueprint $table) {
                $table->id();
                $table->string('codepoint', 16)->unique();  // e.g. "1F4C4" for 📄
                $table->string('emoji', 8)->unique();       // e.g. "📄"
                $table->string('description')->nullable();  // e.g. "document"
                $table->timestamps();
            });
        }

        // ── Emojiables (polymorphic pivot) ────────────────────────────
        if (!Schema::hasTable('emojiables')) {
            Schema::create('emojiables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('emoji_id')->constrained('emojis')->cascadeOnDelete();
                $table->unsignedBigInteger('emojiable_id');
                $table->string('emojiable_type');
                $table->timestamps();

                $table->unique(['emoji_id', 'emojiable_id', 'emojiable_type']);
                $table->index(['emojiable_id', 'emojiable_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emojiables');
        Schema::dropIfExists('emojis');
    }
};
