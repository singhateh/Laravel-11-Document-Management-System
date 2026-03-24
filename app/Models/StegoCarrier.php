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
        // Carrier pool fields
        'validation_status',    // pending, valid, invalid
        'validation_error',     // Error message if validation failed
        'capacity_bytes',       // Measured carrier capacity in bytes
        'is_in_use',            // Whether carrier is locked by an active encode
        'validated_at',         // Timestamp when validation completed
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'capacity_bytes' => 'integer',
            'is_in_use' => 'boolean',
            'validated_at' => 'datetime',
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
