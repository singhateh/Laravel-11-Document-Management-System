<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Emoji extends Model
{
    use HasFactory;

    protected $fillable = [
        'codepoint',
        'emoji',
        'description',
    ];

    // -------------------------------------------------------------------------
    // Polymorphic relationships (via emojiables pivot)
    // -------------------------------------------------------------------------

    public function documents()
    {
        return $this->morphedByMany(Document::class, 'emojiable');
    }
}
