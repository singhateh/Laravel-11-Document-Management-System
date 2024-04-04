<?php

use App\Models\User;
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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('user_type')->nullable();
            $table->string('activity_type');
            $table->string('model_type');
            $table->string('model_id');
            $table->longText('message')->nullable();
            $table->enum('status', ['UNREAD', 'READ'])->default('UNREAD');
            $table->enum('dismiss_status', ['UNDISMISSED', 'DISMISSED'])->default('UNDISMISSED');
            $table->string('created_by_id');
            $table->string('created_by_type');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'status', 'dismiss_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};