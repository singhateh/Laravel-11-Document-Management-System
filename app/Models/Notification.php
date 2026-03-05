<?php

namespace App\Models;

use App\Traits\ActivityLoggable;
use App\Traits\FilterHasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class Notification extends Model
{
    use HasFactory;
    // use FilterHasCreatedBy;
    // use ActivityLoggable;

    // protected $deleted_at  = false;

    protected $fillable = [
        'notifiable_id',
        'notifiable_type',
        'activity_type',
        'model_type',
        'model_id',
        'message',
        'status',
        'dismiss_status',
        'created_by_user_id',
    ];


    function casts()
    {
        return ['created_at' => 'datetime'];
    }


    protected static function booted()
    {
        static::saved(function ($notification) {
            if ($notification->wasChanged()) {
                $user = Auth::user();
                $cacheKey = 'dashboard_data_' . $user?->id;
                Cache::forget($cacheKey);
                Cache::forget('notifications');
            }
        });

        static::updated(function ($notification) {
            if ($notification->wasChanged()) {
                $user = Auth::user();
                $cacheKey = 'dashboard_data_' . $user?->id;
                Cache::forget($cacheKey);
                Cache::forget('notifications');
            }
        });

        static::deleted(function ($notification) {
            $user = Auth::user();
            $cacheKey = 'dashboard_data_' . $user?->id;
            Cache::forget($cacheKey);
            Cache::forget('notifications');
        });
    }


    public function notifiable()
    {
        return $this->morphTo('notifiable', 'notifiable_type', 'notifiable_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function model()
    {
        return $this->morphTo();
    }

    // function customer(): BelongsTo
    // {
    //     return $this->belongsTo(Customer::class, 'user_id');
    // }

    // function notifications(): BelongsTo
    // {
    //     if (auth()->user()->hasRole('Customer')) {
    //         return $this->hasMany(Customer::class, 'user_id');
    //     } else {
    //         return $this->belongsTo(Customer::class, 'user_id');
    //     }
    // }
}