<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('ingest_status', 30)
                ->default('ready')
                ->after('document_date');
            $table->text('ingest_error')
                ->nullable()
                ->after('ingest_status');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['ingest_status', 'ingest_error']);
        });
    }
};
