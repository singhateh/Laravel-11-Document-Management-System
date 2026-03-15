<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StegoDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'ciphertext',
        'stego_iv',
        'stego_auth_tag',
        'stego_hash_sha256',
        'stego_dek_salt',
        'stego_dek_iter',
        'compressed',
        's3_key',
        'status',
        'failed_reason',
        'decoding_status',
        'decoding_error',
        'download_path',
    ];

    protected $casts = [
        'compressed' => 'boolean',
    ];

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

    public function viewerGrants()
    {
        return $this->hasMany(StegoDocumentGrant::class);
    }
}
