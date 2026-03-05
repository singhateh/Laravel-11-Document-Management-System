<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a unique composite index on (document_id, tag_id) in the document_tag pivot.
 *
 * Normalisation parity: folder_tag and category_folder already enforce uniqueness on their
 * composite keys. This migration brings document_tag to the same standard, preventing
 * duplicate tag associations on a document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_tag', function (Blueprint $table) {
            $table->unique(['document_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::table('document_tag', function (Blueprint $table) {
            $table->dropUnique(['document_id', 'tag_id']);
        });
    }
};
