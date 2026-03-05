<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description'];


    public function tags()
    {
        // category_tag pivot was dropped; tags belong to a category via tags.category_id FK.
        return $this->hasMany(Tag::class);
    }
}