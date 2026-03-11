<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('notifiable_id')->nullable();
            $table->string('notifiable_type')->nullable();
            $table->string('activity_type');
            $table->string('model_type');
            $table->string('model_id');
            $table->longText('message')->nullable();
            $table->enum('status', ['UNREAD', 'READ'])->default('UNREAD');
            $table->enum('dismiss_status', ['UNDISMISSED', 'DISMISSED'])->default('UNDISMISSED');
            $table->foreignId('created_by_user_id')
                  ->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['notifiable_id', 'notifiable_type', 'status', 'dismiss_status'],
                'notif_notifiable_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
