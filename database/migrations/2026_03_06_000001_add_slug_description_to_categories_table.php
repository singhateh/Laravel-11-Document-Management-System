<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Category;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->text('description')->nullable()->after('slug');
        });

        // Backfill slugs for any existing rows
        Category::whereNull('slug')->each(function (Category $cat) {
            $cat->update(['slug' => Str::slug($cat->name)]);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['slug', 'description']);
        });
    }
};
