<?php

namespace App\Helpers;

use App\Models\Folder;
use Illuminate\Support\Str;

class FolderHelper
{

    public static function getAllFoldersWithSubfolders($parentFolder = null)
    {
        $folders = Folder::where('parent_id', $parentFolder)->get();

        foreach ($folders as $folder) {
            $folder->load('subfolders');
            self::getAllFoldersWithSubfolders($folder->id);
        }

        return $folders;
    }

    public static  function generateDropdownOptions($parentFolderId = null, $depth = 0)
    {
        $folders = Folder::where('parent_id', $parentFolderId)->get();
        $options = '';

        foreach ($folders as $folder) {
            $indentation = str_repeat('--', $depth); // Add indentation for visual hierarchy
            $options .= '<option value="' . $folder->id . '">' . $indentation . $folder->name . '</option>';

            // Recursively generate options for subfolders
            $options .= self::generateDropdownOptions($folder->id, $depth + 1);
        }

        return $options;
    }


    public static function generateSidebarMenu($parentFolderId = null, $depth = 0)
    {
        $folders = Folder::where('parent_id', $parentFolderId)->get();
        $menu = '';

        foreach ($folders as $folder) {
            $menu .= '<li class="folder-item" data-folder-id="' . $folder->id . '">';
            $menu .= '<a href="#" data-url="' . route('getFiles', $folder) . '" onclick="fetchFiles(\'' . route('getFiles', $folder) . '\', \'folder\')">';
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
                $menu .= self::generateSidebarMenu($folder->id, $depth + 1); // Recursive call
                $menu .= '</ul>';
            }

            $menu .= '</li>';
        }

        return $menu;
    }
}