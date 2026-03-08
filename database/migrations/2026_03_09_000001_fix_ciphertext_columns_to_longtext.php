<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change ciphertext (stego_documents) and encrypted_chunk (stego_segments)
     * from TEXT (65 535 B max) to LONGTEXT (4 GB max) so that large encrypted
     * payloads — which grow after base64 encoding — never truncate.
     */
    public function up(): void
    {
        Schema::table('stego_documents', function (Blueprint $table) {
            $table->longText('ciphertext')->change();
        });

        Schema::table('stego_segments', function (Blueprint $table) {
            $table->longText('encrypted_chunk')->change();
        });
    }

    public function down(): void
    {
        Schema::table('stego_documents', function (Blueprint $table) {
            $table->text('ciphertext')->change();
        });

        Schema::table('stego_segments', function (Blueprint $table) {
            $table->text('encrypted_chunk')->change();
        });
    }
};
