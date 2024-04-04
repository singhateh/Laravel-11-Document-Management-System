<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tag;
use App\Models\Folder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class FolderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $folders = [
            [
                'name' => 'Profit Maximizers',
                'parent_id' => null,
                'visibility' => 'public',
                'background_color' => '#ffcc00',
                'foreground_color' => '#000000',
                'categories' => ['Status', 'Document'],
                'tags' => ['New', 'Toapprove', 'Approved']
            ],
            [
                'name' => 'Invoices',
                'parent_id' => null,
                'visibility' => 'public',
                'background_color' => '#0099ff',
                'foreground_color' => '#ffffff',
                'categories' => ['Billing', 'Finance'],
                'tags' => ['Paid', 'TobePaid']
            ],
            [
                'name' => 'Reports',
                'parent_id' => null,
                'visibility' => 'public',
                'background_color' => '#00cc66',
                'foreground_color' => '#ffffff',
                'categories' => ['analysis', 'finance'],
                'tags' => ['Paid-1', 'TobePaid-2']
            ],
            [
                'name' => 'Contracts',
                'parent_id' => null,
                'visibility' => 'public',
                'background_color' => '#ff6666',
                'foreground_color' => '#ffffff',
                'categories' => ['legal', 'agreements'],
                'tags' => ['Paid-3', 'TobePaid-4']
            ],
            [
                'name' => 'Projects',
                'parent_id' => null,
                'visibility' => 'public',
                'background_color' => '#9933ff',
                'foreground_color' => '#ffffff',
                'categories' => ['management', 'development'],
                'tags' => ['Paid-5', 'TobePaid-5']
            ],
        ];

        foreach ($folders as $folderData) {
            $folder = Folder::create([
                'name' => $folderData['name'],
                'parent_id' => $folderData['parent_id'],
                'visibility' => $folderData['visibility'],
                'background_color' => $folderData['background_color'],
                'foreground_color' => $folderData['foreground_color']
            ]);

            // Attach categories and tags to the folder
            foreach ($folderData['categories'] as $categoryName) {
                $category = Category::firstOrCreate(['name' => $categoryName]);
                $folder->categories()->attach($category);

                // Attach tags to the category
                foreach ($folderData['tags'] as $tagName) {
                    $tag = Tag::firstOrCreate(['name' => $tagName]);
                    $category->tags()->attach($tag);
                }
            }
        }
    }
}