<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalized schema: category_tag pivot is redundant with tags.category_id.
     * Both expressed the same tag→category (many-to-one) relationship, creating
     * an update anomaly risk. tags.category_id FK alone is sufficient.
     * This migration drops the table so a fresh install stays normalized.
     */
    public function up(): void
    {
        Schema::dropIfExists('category_tag');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('category_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
            $table->unique(['category_id', 'tag_id']);
        });
    }
};