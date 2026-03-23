<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (! Schema::hasTable('document_tag')) {
            return;
        }

        $uniqueName = 'document_tag_document_id_tag_id_unique';
        $documentFkName = 'document_tag_document_id_foreign';
        $tagFkName = 'document_tag_tag_id_foreign';

        $hasUnique = $this->indexExists('document_tag', $uniqueName);
        $hasDocumentForeign = $this->foreignKeyExists('document_tag', $documentFkName);
        $hasTagForeign = $this->foreignKeyExists('document_tag', $tagFkName);

        Schema::table('document_tag', function (Blueprint $table) use ($hasUnique, $hasDocumentForeign, $hasTagForeign) {
            if ($hasDocumentForeign) {
                $table->dropForeign(['document_id']);
            }

            if ($hasTagForeign) {
                $table->dropForeign(['tag_id']);
            }

            if ($hasUnique) {
                $table->dropUnique(['document_id', 'tag_id']);
            }

            if ($hasDocumentForeign) {
                $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            }

            if ($hasTagForeign) {
                $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
            }
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->whereRaw('constraint_schema = DATABASE()')
            ->where('table_name', $tableName)
            ->where('constraint_name', $constraintName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
