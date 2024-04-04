<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $categoriesData = [
            ['name' => 'Status'],
            ['name' => 'Documents'],
            ['name' => 'Financial Years'],
            ['name' => 'Category 4'],
            ['name' => 'Category 5'],
        ];

        // Create categories and their tags
        foreach ($categoriesData as $categoryData) {
            $category = Category::create($categoryData);

            // Create tags for each category
            for ($i = 1; $i <= 5; $i++) {
                Tag::create([
                    'name' => "Tag $i of {$category->name}",
                    'category_id' => $category->id,
                ]);
            }
        }
    }
}