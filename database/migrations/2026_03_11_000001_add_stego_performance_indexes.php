<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('stego_documents', function (Blueprint $table) {
            $table->dropIndex('stego_documents_status_index');
            $table->dropColumn(['status', 'failed_reason']);
            $table->longText('ciphertext')->nullable(false)->change();
            $table->string('stego_iv', 64)->nullable(false)->change();
            $table->string('stego_auth_tag', 64)->nullable(false)->change();
            $table->string('stego_hash_sha256', 64)->nullable(false)->change();
        });

        Schema::table('stego_document_grants', function (Blueprint $table) {
            $table->dropIndex('stego_grants_viewer_index');
            $table->dropIndex('stego_grants_grantor_index');
        });
    }
};
