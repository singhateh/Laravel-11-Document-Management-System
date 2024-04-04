<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::all();

        // Create tags and assign categories
        Tag::create(['name' => 'Tag 1'])->categories()->attach($categories->random());
        Tag::create(['name' => 'Tag 2'])->categories()->attach($categories->random());

        foreach ($categories as $category) {
            $tag = Tag::firstOrCreate(['name' => $category]);
            $category->tags()->attach($tag);
        }
    }
}