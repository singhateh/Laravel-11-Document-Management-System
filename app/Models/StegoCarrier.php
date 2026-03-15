<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StegoCarrier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'file_path',
        'file_type',
        'mime_type',
        'size',
        's3_key',
        'psnr',         // PSNR in dB after embedding (images only; null otherwise)
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Computed attributes
    // -------------------------------------------------------------------------

    /**
     * Derive s3_url from s3_key at runtime so call sites need no change.
     * s3_url is no longer stored in the DB (3NF fix — it is a function of s3_key).
     */
    public function getS3UrlAttribute(): ?string
    {
        return $this->s3_key
            ? Storage::disk('local')->path($this->s3_key)
            : null;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function segments()
    {
        return $this->hasMany(StegoSegment::class);
    }
}
