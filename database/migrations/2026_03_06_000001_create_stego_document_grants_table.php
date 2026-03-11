<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the `stego_document_grants` table which records which users
     * have been granted view-decode access to a StegoDocument by its owner.
     *
     * Columns:
     *   stego_document_id — FK to stego_documents (cascade on delete)
     *   viewer_user_id    — FK to users; the user receiving the grant
     *   granted_by        — FK to users; the owner who created the grant
     *
     * A unique constraint on (stego_document_id, viewer_user_id) prevents
     * duplicate grants.
     */
    public function up(): void
    {
        if (Schema::hasTable('stego_document_grants')) {
            return;
        }

        Schema::create('stego_document_grants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stego_document_id')
                ->constrained('stego_documents')
                ->onDelete('cascade');

            $table->foreignId('viewer_user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('granted_by')
                ->constrained('users')
                ->onDelete('cascade');

            $table->timestamps();

            // One grant per viewer per document.
            $table->unique(['stego_document_id', 'viewer_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stego_document_grants');
    }
};
