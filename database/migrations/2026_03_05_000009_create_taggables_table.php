<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges folder_tag and document_tag into a single polymorphic pivot: taggables.
 *
 * Both tables were structurally identical (id, *_id, tag_id, timestamps) — same
 * purpose, same columns, differing only in the left-side FK. Unifying them:
 *   - removes a redundant table
 *   - follows Laravel's morphToMany convention
 *   - makes the tag system extensible to future models (e.g. comments, file_requests)
 *     without ever adding another pivot table
 *
 * Trade-off: taggable_id cannot be FK-constrained at the DB level (polymorphic
 * columns cannot reference multiple parent tables). Referential integrity is
 * maintained by Eloquent (morphToMany cascade) and the hard FK on tag_id.
 *
 * Data migration: existing folder_tag and document_tag rows are copied across
 * before those tables are dropped. Type strings are Laravel's default fully-
 * qualified class names: App\Models\Folder and App\Models\Document.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the unified polymorphic pivot.
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('taggable_id');
            $table->string('taggable_type');
            $table->timestamps();

            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
            $table->index(['taggable_id', 'taggable_type']);
        });

        // 2. Migrate folder_tag → taggables.
        DB::statement("
            INSERT INTO taggables (tag_id, taggable_id, taggable_type, created_at, updated_at)
            SELECT tag_id, folder_id, 'App\\\\Models\\\\Folder', created_at, updated_at
            FROM folder_tag
        ");

        // 3. Migrate document_tag → taggables.
        DB::statement("
            INSERT INTO taggables (tag_id, taggable_id, taggable_type, created_at, updated_at)
            SELECT tag_id, document_id, 'App\\\\Models\\\\Document', created_at, updated_at
            FROM document_tag
        ");

        // 4. Drop the now-redundant pivot tables.
        Schema::dropIfExists('folder_tag');
        Schema::dropIfExists('document_tag');
    }

    public function down(): void
    {
        // Recreate the original pivot tables.
        Schema::create('folder_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('folder_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
            $table->unique(['folder_id', 'tag_id']);
        });

        Schema::create('document_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
            $table->unique(['document_id', 'tag_id']);
        });

        // Restore data from taggables.
        DB::statement("
            INSERT INTO folder_tag (folder_id, tag_id, created_at, updated_at)
            SELECT taggable_id, tag_id, created_at, updated_at
            FROM taggables WHERE taggable_type = 'App\\\\Models\\\\Folder'
        ");

        DB::statement("
            INSERT INTO document_tag (document_id, tag_id, created_at, updated_at)
            SELECT taggable_id, tag_id, created_at, updated_at
            FROM taggables WHERE taggable_type = 'App\\\\Models\\\\Document'
        ");

        Schema::dropIfExists('taggables');
    }
};
