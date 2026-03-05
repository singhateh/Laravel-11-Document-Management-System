<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops `created_at` and `updated_at` from access_logs.
 *
 * Audit log rows must be immutable — they should never be updated after
 * creation. Having `updated_at` on a log table is therefore misleading.
 * `created_at` duplicates `accessed_at`, which is the semantically correct
 * field for "when did this access happen".
 *
 * After this migration, AccessLog::$timestamps is set to false so Eloquent
 * no longer tries to populate these columns automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->timestamps();
        });
    }
};
