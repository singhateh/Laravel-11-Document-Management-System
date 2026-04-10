<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoleGrant extends Model
{
    use HasFactory;

    protected $fillable = [
        'role',
        'permission',
        'resource',
        'user_id',
        'is_granted',
    ];

    protected $casts = [
        'is_granted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
