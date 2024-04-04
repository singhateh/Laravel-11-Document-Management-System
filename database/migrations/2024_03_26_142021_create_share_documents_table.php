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
        Schema::create('share_documents', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('token')->unique();
            $table->string('shared_id')->nullable();
            $table->string('url')->nullable();
            $table->date('valid_until')->nullable();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->enum('can_download', ['yes', 'no'])->default('yes');
            $table->enum('can_upload', ['yes', 'no'])->default('no');
            $table->morphs('user');
            $table->morphs('share');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('share_documents');
    }
};