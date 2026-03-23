<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance optimisation migration (Phase 3 — 2026-03-11)
 *
 * stego_documents
 *   + status  enum('pending','ready','failed')  default 'ready'
 *     Tracks async encode job lifecycle.  Existing rows keep 'ready'.
 *   + failed_reason  text  nullable
 *     Human-readable error stored by EncodeStegoDocumentJob::failed().
 *   ~ ciphertext / stego_iv / stego_auth_tag / stego_hash_sha256 → nullable
 *     Allows the controller to pre-create a skeleton row (status='pending')
 *     before dispatching the encode job.
 *   + index(status) — for polling queries such as WHERE status = 'pending'
 *
 * stego_document_grants
 *   + index(viewer_user_id) — decode auth check uses WHERE viewer_user_id = ?
 *   + index(granted_by)     — useful for "revoke all grants by user" queries
 */
return new class extends Migration
{
    public function up(): void
    {
        // --------------------------------------------------------------------
        // stego_documents
        // --------------------------------------------------------------------
        Schema::table('stego_documents', function (Blueprint $table) {
            // Status column — inserted after 'id' for visibility.
            $table->enum('status', ['pending', 'ready', 'failed'])
                  ->default('ready')
                  ->after('id');

            $table->text('failed_reason')->nullable()->after('status');

            // Make crypto columns nullable so a skeleton 'pending' row can
            // be created before the encode job runs.
            $table->longText('ciphertext')->nullable()->change();
            $table->string('stego_iv', 64)->nullable()->change();
            $table->string('stego_auth_tag', 64)->nullable()->change();
            $table->string('stego_hash_sha256', 64)->nullable()->change();

            $table->index('status', 'stego_documents_status_index');
        });

        // --------------------------------------------------------------------
        // stego_document_grants
        // --------------------------------------------------------------------
        Schema::table('stego_document_grants', function (Blueprint $table) {
            // The existing unique([stego_document_id, viewer_user_id]) covers
            // lookups by stego_document_id but NOT standalone viewer_user_id
            // lookups (used in the decode auth orWhereHas query).
            $table->index('viewer_user_id', 'stego_grants_viewer_index');
            $table->index('granted_by', 'stego_grants_grantor_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('stego_documents')) {
            $hasStatusIndex = $this->indexExists('stego_documents', 'stego_documents_status_index');
            $hasStatusColumn = Schema::hasColumn('stego_documents', 'status');
            $hasFailedReasonColumn = Schema::hasColumn('stego_documents', 'failed_reason');
            $hasCiphertextColumn = Schema::hasColumn('stego_documents', 'ciphertext');
            $hasStegoIvColumn = Schema::hasColumn('stego_documents', 'stego_iv');
            $hasStegoAuthTagColumn = Schema::hasColumn('stego_documents', 'stego_auth_tag');
            $hasStegoHashColumn = Schema::hasColumn('stego_documents', 'stego_hash_sha256');

            Schema::table('stego_documents', function (Blueprint $table) use (
                $hasStatusIndex,
                $hasStatusColumn,
                $hasFailedReasonColumn,
                $hasCiphertextColumn,
                $hasStegoIvColumn,
                $hasStegoAuthTagColumn,
                $hasStegoHashColumn
            ) {
                if ($hasStatusIndex) {
                    $table->dropIndex('stego_documents_status_index');
                }

                if ($hasStatusColumn) {
                    $table->dropColumn('status');
                }

                if ($hasFailedReasonColumn) {
                    $table->dropColumn('failed_reason');
                }

                if ($hasCiphertextColumn) {
                    $table->longText('ciphertext')->nullable(false)->change();
                }

                if ($hasStegoIvColumn) {
                    $table->string('stego_iv', 64)->nullable(false)->change();
                }

                if ($hasStegoAuthTagColumn) {
                    $table->string('stego_auth_tag', 64)->nullable(false)->change();
                }

                if ($hasStegoHashColumn) {
                    $table->string('stego_hash_sha256', 64)->nullable(false)->change();
                }
            });
        }

        if (! Schema::hasTable('stego_document_grants')) {
            return;
        }

        $viewerFkName = 'stego_document_grants_viewer_user_id_foreign';
        $grantorFkName = 'stego_document_grants_granted_by_foreign';
        $hasViewerForeign = $this->foreignKeyExists('stego_document_grants', $viewerFkName);
        $hasGrantorForeign = $this->foreignKeyExists('stego_document_grants', $grantorFkName);
        $hasViewerIndex = $this->indexExists('stego_document_grants', 'stego_grants_viewer_index');
        $hasGrantorIndex = $this->indexExists('stego_document_grants', 'stego_grants_grantor_index');

        Schema::table('stego_document_grants', function (Blueprint $table) use ($hasViewerForeign, $hasGrantorForeign, $hasViewerIndex, $hasGrantorIndex) {
            // MySQL can bind FK checks to this index; drop FK first, then index.
            if ($hasViewerForeign) {
                $table->dropForeign(['viewer_user_id']);
            }

            if ($hasGrantorForeign) {
                $table->dropForeign(['granted_by']);
            }

            if ($hasViewerIndex) {
                $table->dropIndex('stego_grants_viewer_index');
            }

            if ($hasGrantorIndex) {
                $table->dropIndex('stego_grants_grantor_index');
            }

            if ($hasViewerForeign) {
                $table->foreign('viewer_user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            }

            if ($hasGrantorForeign) {
                $table->foreign('granted_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
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
