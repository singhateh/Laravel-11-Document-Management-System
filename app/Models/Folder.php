<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'parent_id', 'visibility', 'background_color', 'foreground_color', 'category_id', 'position'];



    protected static function boot()
    {
        parent::boot();

        // Define a global scope to always order by position
        static::addGlobalScope('position', function ($builder) {
            $builder->orderBy('position');
        });

        static::creating(function ($folder) {
            if (!isset($folder->position)) {
                $folder->position = static::max('position') + 1;
            }
        });
    }


    public function parent()
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function subfolders()
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('position');
    }

    public function tags()
    {
        return $this->hasMany(Tag::class, 'id', 'tag_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'folder_id', 'id');
    }

    public function deleteFolder()
    {
        // Check if the documents relationship is loaded
        if ($this->relationLoaded('documents')) {
            // Delete related documents and associated files
            foreach ($this->documents as $document) {
                $document->deleteFile();
                $document->delete();
            }
        } else {
            // If the relationship is not loaded, eager load it
            $this->load('documents');

            // Delete related documents and associated files
            foreach ($this->documents as $document) {
                $document->deleteFile();
                $document->delete();
            }
        }

        // Delete related categories and associated tags
        if ($this->relationLoaded('categories')) {
            foreach ($this->categories as $category) {
                // Detach tags associated with the category
                $category->tags()->detach();
                // Delete the category
                $category->delete();
            }
        } else {
            $this->load('categories');
            foreach ($this->categories as $category) {
                // Detach tags associated with the category
                $category->tags()->detach();
                // Delete the category
                $category->delete();
            }
        }
        // Detach related tags
        if ($this->relationLoaded('tags')) {
            $this->tags()->delete();
        } else {
            $this->load('tags');
            $this->tags()->delete();
        }

        // Check if the subfolders relationship is loaded
        if ($this->relationLoaded('subfolders')) {
            // Recursively delete subfolders and their related records
            foreach ($this->subfolders as $subfolder) {
                $subfolder->deleteFolder();
            }
        } else {
            // If the relationship is not loaded, eager load it
            $this->load('subfolders');

            // Recursively delete subfolders and their related records
            foreach ($this->subfolders as $subfolder) {
                $subfolder->deleteFolder();
            }
        }

        $filePath = public_path('documents/' . $this->name);

        if (file_exists($filePath)) {
            if (is_file($filePath)) {
                unlink($filePath); // Delete the file if it's a file
            } else {
                // Handle the case if $filePath is a directory
                $this->deleteDirectory($filePath);
            }
        }
        // Delete folder itself
        $this->delete();
    }

    // Method to recursively delete directory and its contents
    private function deleteDirectory($dirPath)
    {
        if (!is_dir($dirPath)) {
            return;
        }

        // Check if the directory is empty
        if (count(scandir($dirPath)) === 2) { // 2 because scandir() returns '.' and '..'
            rmdir($dirPath); // Remove the directory itself if it's empty
            return;
        }

        // If the directory is not empty, delete its contents recursively
        $files = glob($dirPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->deleteDirectory($file);
            }
        }
        // After deleting all contents, remove the directory itself
        rmdir($dirPath);
    }
}
