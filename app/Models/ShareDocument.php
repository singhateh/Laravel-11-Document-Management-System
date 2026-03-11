<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShareDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'shared_id', 'name', 'token',
        'slug', 'valid_until', 'visibility',
        'share_id', 'share_type', 'user_type', 'user_id',
        'can_download', 'can_upload',
    ];

    protected $casts = [
        'can_download' => 'boolean',
        'can_upload'   => 'boolean',
    ];


    public function folder()
    {
        return $this->hasMany(Folder::class, 'id', 'share_id');
    }


    public function document()
    {
        return $this->hasMany(Document::class, 'id', 'share_id');
    }


    public function sharesBySlug($slug)
    {
        if ($slug == "folder") {
            return $this->hasMany(Folder::class, 'id', 'share_id');
        } else {
            return $this->hasMany(Document::class, 'id', 'share_id');
        }
    }


    function scopeIsPublic(): bool
    {
        return $this->visibility === 'public' ? true : false;
    }

    public function scopeHasExpired()
    {
        $currentDateTime = Carbon::now();

        $date = $this->valid_until;

        // Convert input date to Carbon instance if it's a string
        $expirationDate = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $currentDateTime->gt($expirationDate);
    }
}