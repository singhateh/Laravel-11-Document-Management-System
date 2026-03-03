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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_grants');
    }
};
