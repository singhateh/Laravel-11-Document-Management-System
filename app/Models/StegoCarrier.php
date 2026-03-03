<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        's3_url',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
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
