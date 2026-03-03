<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stego carriers are image/audio/text files used to hide encrypted document data.
     * Separate from the existing "carriers" table (shipping carriers — different domain).
     */
    public function up(): void
    {
        Schema::create('stego_carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_path');
            $table->string('file_type');           // image, audio, text
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');    // bytes
            $table->string('s3_key')->nullable();  // AWS S3 object key
            $table->string('s3_url')->nullable();  // AWS S3 URL
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('file_type');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stego_carriers');
    }
};
