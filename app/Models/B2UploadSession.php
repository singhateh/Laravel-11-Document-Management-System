<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2UploadSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_token',
        'idempotency_token',
        'user_id',
        'object_key',
        'original_filename',
        'sanitized_filename',
        'expected_mime',
        'expected_size',
        'checksum_sha256',
        'folder_id',
        'visibility',
        'status',
        'error_message',
        'expires_at',
        'uploaded_at',
        'finalized_at',
        'consumed_at',
        'document_id',
    ];

    protected $casts = [
        'expected_size' => 'integer',
        'expires_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'finalized_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
