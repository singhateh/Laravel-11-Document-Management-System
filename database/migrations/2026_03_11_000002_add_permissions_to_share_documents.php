<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('share_documents', function (Blueprint $table) {
            $table->boolean('can_edit')->default(false)->after('can_upload');
            $table->boolean('can_comment')->default(false)->after('can_edit');
            $table->boolean('can_share')->default(false)->after('can_comment');
            $table->string('permission_level')->default('viewer')->after('can_share');
        });
    }

    public function down()
    {
        Schema::table('share_documents', function (Blueprint $table) {
            $table->dropColumn(['can_edit', 'can_comment', 'can_share', 'permission_level']);
        });
    }
};
