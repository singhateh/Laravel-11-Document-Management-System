<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two normalization fixes for share_documents:
 *
 * 1. Drop `url` — it is derivable from `token` (or `slug`) + a base path at the
 *    application layer, making it a transitive dependency (3NF violation).
 *
 * 2. Convert `can_download` / `can_upload` from ENUM('yes','no') to BOOLEAN.
 *    Using a string enum for a binary flag is inconsistent with documents.is_encrypted
 *    and complicates queries (WHERE can_download = 'yes' vs WHERE can_download = 1).
 *    Stored values 'yes'/'no' are migrated to 1/0 before the column type change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('share_documents', function (Blueprint $table) {
            $table->dropColumn('url');
        });

        // Migrate existing enum string values to integers before changing the column type.
        DB::statement("UPDATE share_documents SET can_download = 1 WHERE can_download = 'yes'");
        DB::statement("UPDATE share_documents SET can_download = 0 WHERE can_download = 'no'");
        DB::statement("UPDATE share_documents SET can_upload = 1 WHERE can_upload = 'yes'");
        DB::statement("UPDATE share_documents SET can_upload = 0 WHERE can_upload = 'no'");

        DB::statement("ALTER TABLE share_documents MODIFY can_download TINYINT(1) NOT NULL DEFAULT 1");
        DB::statement("ALTER TABLE share_documents MODIFY can_upload   TINYINT(1) NOT NULL DEFAULT 0");
    }

    public function down(): void
    {
        Schema::table('share_documents', function (Blueprint $table) {
            $table->string('url')->nullable()->after('valid_until');
        });

        DB::statement("ALTER TABLE share_documents MODIFY can_download ENUM('yes','no') NOT NULL DEFAULT 'yes'");
        DB::statement("ALTER TABLE share_documents MODIFY can_upload   ENUM('yes','no') NOT NULL DEFAULT 'no'");

        DB::statement("UPDATE share_documents SET can_download = 'yes' WHERE can_download = 1");
        DB::statement("UPDATE share_documents SET can_download = 'no'  WHERE can_download = 0");
        DB::statement("UPDATE share_documents SET can_upload   = 'yes' WHERE can_upload   = 1");
        DB::statement("UPDATE share_documents SET can_upload   = 'no'  WHERE can_upload   = 0");
    }
};
