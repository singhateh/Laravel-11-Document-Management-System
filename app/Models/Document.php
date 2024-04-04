<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;
    use SoftDeletes;


    protected static function boot()
    {
        parent::boot();

        // Define a global scope to always order by position
        static::addGlobalScope('position', function ($builder) {
            $builder->orderBy('position');
        });

        static::creating(function ($document) {
            if (!isset($document->position)) {
                $document->position = static::max('position') + 1;
            }
        });
    }

    protected $fillable = [
        'name', 'original_name', 'file_path', 'size', 'extension', 'folder_id', 'visibility', 'share', 'download', 'email',
        'url', 'contact', 'owner', 'tags', 'date', 'emojies', 'position'
    ];

    public function getFileIcon()
    {
        // Check if the extension is in the array
        if (!in_array($this->extension, getImageExtensions())) {
            if (!empty($this->extension)) {
                return asset('img/' . $this->extension . '.png');
            } else {
                return asset($this->file_path);
            }
        } else {
            return asset($this->file_path);
        }
    }

    public function isPublic()
    {
        return $this->visibility;
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    // Method to delete associated file from public path
    public function deleteFile()
    {
        $filePath = public_path($this->file_path);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
