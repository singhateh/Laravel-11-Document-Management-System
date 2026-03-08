<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens stego_segments.encrypted_chunk from text (65 KB) to longtext (16 MB).
 *
 * Under the new encoding strategy, each chunk is up to 2 MB of raw binary
 * data, which is base64-encoded before storage (≈ 2.7 MB per chunk). The
 * original `text` column (MySQL 65 KB limit) is too small; `longtext` handles
 * up to 4 GB and provides ample headroom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stego_segments', function (Blueprint $table) {
            $table->longText('encrypted_chunk')->change();
        });
    }

    public function down(): void
    {
        Schema::table('stego_segments', function (Blueprint $table) {
            $table->text('encrypted_chunk')->change();
        });
    }
};
