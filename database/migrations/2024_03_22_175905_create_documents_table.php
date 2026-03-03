<?php

use App\Models\Folder;
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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('position')->default(0);
            $table->string('name')->nullable();
            $table->string('original_name')->nullable();
            $table->string('file_path')->nullable();
            $table->bigInteger('size')->nullable();
            $table->string('extension')->nullable();
            $table->foreignId('folder_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade') // or 'set null' depending on your requirements
                ->onUpdate('cascade'); // or 'set null' depending on your requirements

            // Changed from string to enum to match folders.visibility (type consistency).
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->bigInteger('share')->default(0);
            $table->bigInteger('download')->default(0);
            $table->string('email')->nullable();
            $table->string('url')->nullable();
            $table->string('contact')->nullable();
            // Replaced free-text 'owner' with owner_id FK to users (3NF fix).
            $table->foreignId('owner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('date')->nullable();
            $table->string('emojies')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};