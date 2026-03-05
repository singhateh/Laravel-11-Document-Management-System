<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the redundant `s3_url` column from stego_carriers and stego_documents.
 *
 * s3_url is a transitive dependency of s3_key — it can always be derived via
 * Storage::disk('s3')->url($s3_key). Storing it violates 3NF and creates a
 * maintenance burden (URL scheme changes require updates in three tables).
 *
 * Migration path: StegoCarrier and StegoDocument models now expose s3_url
 * as a computed Eloquent accessor so existing call sites need no changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stego_carriers', function (Blueprint $table) {
            $table->dropColumn('s3_url');
        });

        Schema::table('stego_documents', function (Blueprint $table) {
            $table->dropColumn('s3_url');
        });
    }

    public function down(): void
    {
        Schema::table('stego_carriers', function (Blueprint $table) {
            $table->string('s3_url')->nullable()->after('s3_key');
        });

        Schema::table('stego_documents', function (Blueprint $table) {
            $table->string('s3_url')->nullable()->after('s3_key');
        });
    }
};
