<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stego segments are encrypted chunks of a document split across multiple carriers.
     * Separate from the existing "segments" table (marketing/audience segments — different domain).
     */
    public function up(): void
    {
        Schema::create('stego_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stego_document_id')->constrained('stego_documents')->cascadeOnDelete();
            $table->foreignId('stego_carrier_id')->constrained('stego_carriers')->cascadeOnDelete();
            $table->unsignedTinyInteger('segment_index');   // order of this chunk (0-based)
            $table->text('encrypted_chunk');                // base64-encoded encrypted bytes
            $table->string('s3_key')->nullable();           // AWS S3 object key for this chunk
            $table->string('chunk_hash');                   // SHA-256 of this chunk for integrity
            $table->timestamps();

            $table->index(['stego_document_id', 'segment_index']);
            $table->index('stego_carrier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stego_segments');
    }
};
