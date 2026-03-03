<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Expand the role ENUM to include 'user' and change the default to 'user'
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user','owner','admin') NOT NULL DEFAULT 'user'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner','admin') NOT NULL DEFAULT 'owner'");
    }
};
