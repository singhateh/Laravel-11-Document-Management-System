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
        'permission_level',
    ];

    /**
     * Computed attributes to append to JSON serialization.
     * These derive from permission_level (the single source of truth).
     */
    protected $appends = [
        'can_download',
        'can_upload',
        'can_edit',
        'can_comment',
        'can_share',
    ];

    // NOTE: The five boolean columns (can_download, can_upload, can_edit,
    // can_comment, can_share) were dropped in migration 2026_04_06_000002.
    // permission_level is now the single source of truth. Computed accessors
    // below derive boolean values at runtime.

    // Permission levels
    const PERMISSION_VIEWER = 'viewer';
    const PERMISSION_COMMENTER = 'commenter';
    const PERMISSION_EDITOR = 'editor';
    const PERMISSION_CO_OWNER = 'co_owner';
    const PERMISSION_OWNER = 'owner';

    /**
     * Permission capability matrix — the single source of truth for what each
     * role can do.  If you add a new permission level, update this map.
     */
    const PERMISSION_MATRIX = [
        self::PERMISSION_VIEWER   => ['download' => true,  'upload' => false, 'edit' => false, 'comment' => false, 'share' => false],
        self::PERMISSION_COMMENTER => ['download' => true,  'upload' => false, 'edit' => false, 'comment' => true,  'share' => false],
        self::PERMISSION_EDITOR   => ['download' => true,  'upload' => true,  'edit' => true,  'comment' => true,  'share' => false],
        self::PERMISSION_CO_OWNER => ['download' => true,  'upload' => true,  'edit' => true,  'comment' => true,  'share' => true],
        self::PERMISSION_OWNER    => ['download' => true,  'upload' => true,  'edit' => true,  'comment' => true,  'share' => true],
    ];

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
        return $this;
    }

    public function getPermissionLevel()
    {
        return $this->permission_level ?: self::PERMISSION_VIEWER;
    }

    // -------------------------------------------------------------------------
    // Computed accessors — derive boolean permissions from permission_level
    // -------------------------------------------------------------------------

    public function getCanDownloadAttribute(): bool
    {
        return self::PERMISSION_MATRIX[$this->getPermissionLevel()]['download'] ?? false;
    }

    public function getCanUploadAttribute(): bool
    {
        return self::PERMISSION_MATRIX[$this->getPermissionLevel()]['upload'] ?? false;
    }

    public function getCanEditAttribute(): bool
    {
        return self::PERMISSION_MATRIX[$this->getPermissionLevel()]['edit'] ?? false;
    }

    public function getCanCommentAttribute(): bool
    {
        return self::PERMISSION_MATRIX[$this->getPermissionLevel()]['comment'] ?? false;
    }

    public function getCanShareAttribute(): bool
    {
        return self::PERMISSION_MATRIX[$this->getPermissionLevel()]['share'] ?? false;
    }

    public function hasPermission($permission)
    {
        // Accept both 'can_download' and 'download' style keys
        $key = str_replace('can_', '', $permission);
        return $this->{'can_' . ucfirst($key)} === true;
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