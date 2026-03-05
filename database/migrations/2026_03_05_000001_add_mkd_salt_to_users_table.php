<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds mkd_salt (Master Key Derivation salt) to the users table.
 *
 * Generated at registration by CryptoService::deriveMasterKey().
 * Used at every login to re-derive the in-session Master Key from the user's
 * password — so the Master Key itself is NEVER stored anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 16-byte salt stored as 32 hex chars; nullable for existing rows
            $table->string('mkd_salt', 32)->nullable()->after('role')
                ->comment('PBKDF2-SHA256 salt for Master Key Derivation (hex, 32 chars = 16 bytes)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mkd_salt');
        });
    }
};
