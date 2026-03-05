<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds psnr (Peak Signal-to-Noise Ratio) to stego_carriers.
 *
 * Populated during encode by StegoService::psnr().
 * Architecture requires PSNR >= 40 dB for image carriers; this column stores
 * the measured value per carrier for audit and UI display purposes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stego_carriers', function (Blueprint $table) {
            // Measured PSNR in dB; null for non-image carriers (audio/text/binary)
            $table->float('psnr', 8, 4)->nullable()->after('s3_url')
                ->comment('PSNR in dB measured after LSB embedding (images only; null otherwise)');
        });
    }

    public function down(): void
    {
        Schema::table('stego_carriers', function (Blueprint $table) {
            $table->dropColumn('psnr');
        });
    }
};
