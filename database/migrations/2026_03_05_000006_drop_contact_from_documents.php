<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the unused 'contact' column from documents.
 *
 * The column has no references in controllers, services, views, or jobs.
 * Dead columns accumulate technical debt and obscure schema intent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('contact');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('contact')->nullable()->after('url');
        });
    }
};
