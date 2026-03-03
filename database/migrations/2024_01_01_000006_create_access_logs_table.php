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
            $table->timestamps();

            $table->index(['user_id', 'accessed_at']);
            $table->index(['resource', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
