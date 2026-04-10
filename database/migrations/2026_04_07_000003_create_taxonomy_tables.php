<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated migration: categories, tags, taggables, category_folder.
 *
 * Combines the taxonomy/classification tables into a single migration.
 * The taggables table is a polymorphic pivot that replaces the old
 * folder_tag and document_tag tables.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── Categories ────────────────────────────────────────────────
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable()->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // ── Tags ──────────────────────────────────────────────────────
        if (!Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('background_color')->nullable();
                $table->string('foreground_color')->nullable();
                $table->foreignId('category_id')
                    ->nullable()
                    ->constrained()
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
                $table->timestamps();
            });
        }

        // ── Taggables (polymorphic pivot) ─────────────────────────────
        if (!Schema::hasTable('taggables')) {
            Schema::create('taggables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('taggable_id');
                $table->string('taggable_type');
                $table->timestamps();

                $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
                $table->index(['taggable_id', 'taggable_type']);
            });
        }

        // ── Category ↔ Folder (many-to-many pivot) ────────────────────
        if (!Schema::hasTable('category_folder')) {
            Schema::create('category_folder', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('folder_id');
                $table->timestamps();

                $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
                $table->foreign('folder_id')->references('id')->on('folders')->onDelete('cascade');
                $table->unique(['category_id', 'folder_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_folder');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
