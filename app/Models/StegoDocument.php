<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\StegoCarrier;

class StegoDocument extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (StegoDocument $stegoDoc): void {
            $carrierIds = $stegoDoc->segments()->pluck('stego_carrier_id')->all();
            if (empty($carrierIds)) {
                return;
            }

            StegoCarrier::whereIn('id', $carrierIds)->update(['is_in_use' => false]);
        });
    }

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
        'decoding_duration',
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
        $disk = (string) config('stegolock.storage.disk', 'local');

        if (!$this->s3_key) {
            return null;
        }

        if ($disk === 'local') {
            return Storage::disk('local')->path($this->s3_key);
        }

        $baseUrl = (string) config("filesystems.disks.{$disk}.url", '');

        return $baseUrl !== ''
            ? rtrim($baseUrl, '/') . '/' . ltrim($this->s3_key, '/')
            : $this->s3_key;
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
