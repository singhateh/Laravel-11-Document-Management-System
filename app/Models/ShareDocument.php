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
        'can_download', 'can_upload', 'can_edit', 'can_comment', 'can_share',
        'permission_level',
    ];

    protected $casts = [
        'can_download' => 'boolean',
        'can_upload'   => 'boolean',
        'can_edit'     => 'boolean',
        'can_comment'  => 'boolean',
        'can_share'    => 'boolean',
    ];

    // Permission levels
    const PERMISSION_VIEWER = 'viewer';
    const PERMISSION_COMMENTER = 'commenter';
    const PERMISSION_EDITOR = 'editor';
    const PERMISSION_CO_OWNER = 'co_owner';
    const PERMISSION_OWNER = 'owner';

    public static function getPermissionLevels()
    {
        return [
            self::PERMISSION_VIEWER => 'Viewer',
            self::PERMISSION_COMMENTER => 'Commenter',
            self::PERMISSION_EDITOR => 'Editor',
            self::PERMISSION_CO_OWNER => 'Co-owner',
            self::PERMISSION_OWNER => 'Owner',
        ];
    }

    public function setPermissionLevel($level)
    {
        $this->permission_level = $level;
        
        // Set individual permissions based on level
        switch ($level) {
            case self::PERMISSION_VIEWER:
                $this->can_download = true;
                $this->can_upload = false;
                $this->can_edit = false;
                $this->can_comment = false;
                $this->can_share = false;
                break;
            case self::PERMISSION_COMMENTER:
                $this->can_download = true;
                $this->can_upload = false;
                $this->can_edit = false;
                $this->can_comment = true;
                $this->can_share = false;
                break;
            case self::PERMISSION_EDITOR:
                $this->can_download = true;
                $this->can_upload = true;
                $this->can_edit = true;
                $this->can_comment = true;
                $this->can_share = false;
                break;
            case self::PERMISSION_CO_OWNER:
                $this->can_download = true;
                $this->can_upload = true;
                $this->can_edit = true;
                $this->can_comment = true;
                $this->can_share = true;
                break;
            case self::PERMISSION_OWNER:
                $this->can_download = true;
                $this->can_upload = true;
                $this->can_edit = true;
                $this->can_comment = true;
                $this->can_share = true;
                break;
        }
        
        return $this;
    }

    public function getPermissionLevel()
    {
        return $this->permission_level ?: self::PERMISSION_VIEWER;
    }

    public function hasPermission($permission)
    {
        return $this->$permission === true;
    }

    public function isOwner()
    {
        return $this->getPermissionLevel() === self::PERMISSION_OWNER;
    }

    public function isCoOwner()
    {
        return $this->getPermissionLevel() === self::PERMISSION_CO_OWNER;
    }

    public function isEditor()
    {
        return $this->getPermissionLevel() === self::PERMISSION_EDITOR;
    }

    public function isCommenter()
    {
        return $this->getPermissionLevel() === self::PERMISSION_COMMENTER;
    }

    public function isViewer()
    {
        return $this->getPermissionLevel() === self::PERMISSION_VIEWER;
    }


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