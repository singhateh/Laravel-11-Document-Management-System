<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'resource',
        'resource_id',
        'ip_address',
        'user_agent',
        'method',
        'url',
        'status_code',
        'payload',
        'accessed_at',
    ];

    protected $casts = [
        'payload'     => 'array',
        'accessed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
