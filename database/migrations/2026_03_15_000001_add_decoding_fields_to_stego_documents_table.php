<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add decoding status and download path fields to stego_documents table
     * to support queued decoding operations.
     */
    public function up(): void
    {
        Schema::table('stego_documents', function (Blueprint $table) {
            $table->string('decoding_status')->default('idle')->comment('Decoding status: idle, pending, in_progress, completed, failed');
            $table->text('decoding_error')->nullable()->comment('Error message if decoding failed');
            $table->string('download_path')->nullable()->comment('Storage path to the decoded document');
        });
    }

    public function down(): void
    {
        Schema::table('stego_documents', function (Blueprint $table) {
            $table->dropColumn(['decoding_status', 'decoding_error', 'download_path']);
        });
    }
};
