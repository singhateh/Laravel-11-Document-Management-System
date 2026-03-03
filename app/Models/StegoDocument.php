<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StegoDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'ciphertext',
        'iv',
        'auth_tag',
        'hash_sha256',
        'dek_salt',
        'dek_iterations',
        's3_key',
        's3_url',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function segments()
    {
        return $this->hasMany(StegoSegment::class)->orderBy('segment_index');
    }
}
