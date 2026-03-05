<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'background_color', 'foreground_color'];

    // -------------------------------------------------------------------------
    // Polymorphic inverse relationships (via taggables pivot)
    // -------------------------------------------------------------------------

    public function folders()
    {
        return $this->morphedByMany(Folder::class, 'taggable');
    }

    public function documents()
    {
        return $this->morphedByMany(Document::class, 'taggable');
    }

    // -------------------------------------------------------------------------
    // Direct FK — category_tag pivot was dropped; tags.category_id is the
    // single source of truth for the tag → category relationship.
    // -------------------------------------------------------------------------

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}