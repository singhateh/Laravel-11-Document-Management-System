<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('document_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['user_id', 'document_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('last_updated_at')->nullable()->after('updated_at');
            $table->text('last_updated_by')->nullable()->after('last_updated_at');
        });
    }

    public function down()
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('last_updated_at');
            $table->dropColumn('last_updated_by');
        });
        
        Schema::dropIfExists('document_watchers');
    }
};
