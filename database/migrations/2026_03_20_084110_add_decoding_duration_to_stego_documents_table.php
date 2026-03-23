<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stego_documents', function (Blueprint $table) {
            $table->decimal('decoding_duration', 10, 3)->nullable()->comment('Total decoding duration in seconds (with milliseconds precision)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stego_documents', function (Blueprint $table) {
            $table->dropColumn('decoding_duration');
        });
    }
};
