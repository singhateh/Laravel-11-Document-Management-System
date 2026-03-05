<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two rename fixes for the documents table:
 *
 * 1. `emojies` → `emojis`   — corrects the typo.
 *
 * 2. `date` → `document_date` — disambiguates from created_at/updated_at.
 *    `date` is a reserved word in many SQL engines and gives no semantic
 *    information. `document_date` makes the purpose explicit (the user-facing
 *    date of the document, e.g. its publication or receipt date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('emojies', 'emojis');
            $table->renameColumn('date', 'document_date');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('emojis', 'emojies');
            $table->renameColumn('document_date', 'date');
        });
    }
};
