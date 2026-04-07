<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated migration: role_grants, access_logs.
 *
 * Combines the access control and audit logging tables into a single migration.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── Role Grants (RBAC permissions) ────────────────────────────
        if (!Schema::hasTable('role_grants')) {
            Schema::create('role_grants', function (Blueprint $table) {
                $table->id();
                $table->string('role');
                $table->string('permission');
                $table->string('resource')->nullable();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained()
                    ->onDelete('cascade');
                $table->boolean('is_granted')->default(true);
                $table->timestamps();

                $table->unique(['role', 'permission', 'resource', 'user_id']);
            });
        }

        // ── Access Logs (audit trail) ─────────────────────────────────
        if (!Schema::hasTable('access_logs')) {
            Schema::create('access_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained()
                    ->onDelete('set null');
                $table->string('action');
                $table->string('resource')->nullable();
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent')->nullable();
                $table->string('method', 10)->nullable();
                $table->string('url')->nullable();
                $table->integer('status_code')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('accessed_at')->useCurrent();

                $table->index(['user_id', 'accessed_at']);
                $table->index(['resource', 'resource_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_logs');
        Schema::dropIfExists('role_grants');
    }
};
