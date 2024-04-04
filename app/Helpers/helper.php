<?php

use App\Models\Category;
use App\Models\Folder;
use Illuminate\Support\Str;

if (!function_exists('getAllFoldersWithSubfolders')) {
    function getAllFoldersWithSubfolders($parentFolder = null)
    {
        $folders = Folder::where('parent_id', $parentFolder)->get();

        foreach ($folders as $folder) {
            $folder->load('subfolders');
            getAllFoldersWithSubfolders($folder->id);
        }

        return $folders;
    }
}

if (!function_exists('generateDropdownOptions')) {
    function generateDropdownOptions($parentFolderId = null, $depth = 0)
    {
        $folders = Folder::where('parent_id', $parentFolderId)->get();
        $options = '';

        foreach ($folders as $folder) {
            $indentation = str_repeat('--', $depth); // Add indentation for visual hierarchy
            $options .= '<option value="' . $folder->id . '">' . $indentation . $folder->name . '</option>';

            // Recursively generate options for subfolders
            $options .= generateDropdownOptions($folder->id, $depth + 1);
        }

        return $options;
    }
}



if (!function_exists('generateCategoryTagsDropdownOptions')) {
    function generateCategoryTagsDropdownOptions($parentFolderId = null, $depth = 0)
    {
        $folders = Folder::where('parent_id', $parentFolderId)->get();
        $options = '';

        foreach ($folders as $folder) {
            $indentation = str_repeat('--', $depth); // Add indentation for visual hierarchy
            $options .= '<option value="' . $folder->id . '">' . $indentation . $folder->name . '</option>';

            // Recursively generate options for subfolders
            $options .= generateCategoryTagsDropdownOptions($folder->id, $depth + 1);

            // Fetch and append tags for the current category
            foreach ($folder->categories as $category) {
                $options .= '<option value="' . $category->id . '">----' . $category->name . '</option>';

                // Append tags for the current category
                foreach ($category->tags as $tag) {
                    $options .= '<option value="' . $tag->id . '">--------' . $tag->name . '</option>';
                }
            }
        }

        return $options;
    }
}



if (!function_exists('generateSidebarMenu')) {
    function generateSidebarMenu($parentFolderId = null, $depth = 0)
    {
        $folders = Folder::where('parent_id', $parentFolderId)->get();
        $menu = '';

        foreach ($folders as $folder) {
            $menu .= '<li class="folder-item" data-folder-id="' . $folder->id . '">';
            $menu .= '<a href="#" data-folder="' . $folder->name . '" data-url="' . route('getFiles', $folder) . '" onclick="fetchFiles(\'' . route('getFiles', $folder) . '\', \'folder\')">';
            $menu .= '<span class="folder-content">';
            $menu .= '<i class="fas fa-folder folder-icon"></i>';
            $menu .= '<span class="folder-name" title="' . $folder->name . '">' . Str::limit($folder->name, 15) . '</span>';
            $menu .= '</span>';
            $menu .= '</a>';

            // Check if the folder has subfolders
            if ($folder->subfolders->isNotEmpty()) {
                $menu .= '<button class="toggle-subfolders-btn" title="open" onclick="toggleSubfolders(this)">';
                $menu .= '<i class="fas fa-chevron-down"></i>';
                $menu .= '</button>';
                $menu .= '<ul class="subfolders" style="display: none;">';
                $menu .= generateSidebarMenu($folder->id, $depth + 1); // Recursive call
                $menu .= '</ul>';
            }

            $menu .= '</li>';
        }

        return $menu;
    }
}

function renderTagsWithCategories($folderId)
{
    $folder = Folder::find($folderId);
    $output = '';

    if ($folder?->categories?->isNotEmpty()) {
        $output .= '<div class="tags">';
        $output .= '<hr>';
        $output .= '<h4 class="mr-3"> <i class="fas fa-tags folder-icon text-warning"></i> Tags</h4>';
        $output .= '<hr>';
        $categoriesCount = count($folder->categories);
        $categoryIndex = 0;

        foreach ($folder->categories as $category) {
            $output .= '<label class="category"><input type="checkbox" class="category-checkbox" onclick="handleCategoryCheckboxChange()" value="' . $category->id . '"> ' . $category->name . '</label>';
            $output .= '<br>';
            foreach ($category->tags as $tag) {
                $output .= '<label class="category-tags"><input type="checkbox" class="tags-tosend tag-checkbox-' . $category->id . '" data-category-id="' . $category->id . '" onclick="handleTagCheckboxChange()" value="' . $tag->id . '"> ' . $tag->name . '</label>';
            }

            $categoryIndex++;
            if ($categoryIndex < $categoriesCount) {
                $output .= '<hr>';
            }
        }
        $output .= '</div>';
    }

    return $output;
}


if (!function_exists('getImageExtensions')) {
    function getImageExtensions()
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'tif', 'tiff'];
    }
}


if (!function_exists('getVideoExtensions')) {
    function getVideoExtensions()
    {
        return [
            'mp4',
            'avi',
            'wmv',
            'mov',
            'mkv',
            'flv',
            'webm',
            'mpeg',
            'mpg',
            '3gp',
            'ogg'
        ];
    }
}


if (!function_exists('getDueDateInList')) {
    function getDueDateInList()
    {
        return ['Day', 'Month', 'Year'];
    }
}