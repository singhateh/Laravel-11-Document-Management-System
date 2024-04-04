<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'background_color', 'foreground_color'];


    public function folders()
    {
        return $this->belongsToMany(Folder::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }
}