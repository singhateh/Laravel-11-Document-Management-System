<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `compressed` boolean flag to stego_documents.
 *
 * Tracks whether the plaintext was gzip-compressed before AES-256-GCM
 * encryption. Default is true — all records created after this migration
 * use the new gzip + base64 encoding strategy.
 *
 * The flag enables backward-compatible decryption if old un-compressed
 * records were ever needed (they aren't in this dev environment, but the
 * column provides a safety net for future production upgrades).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stego_documents', function (Blueprint $table) {
            $table->boolean('compressed')->default(true)->after('stego_dek_iter');
        });
    }

    public function down(): void
    {
        Schema::table('stego_documents', function (Blueprint $table) {
            $table->dropColumn('compressed');
        });
    }
};
