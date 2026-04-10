<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccessLog extends Model
{
    use HasFactory;

    /**
     * Log rows are immutable — created_at / updated_at have been dropped.
     * accessed_at is the authoritative timestamp for when the access occurred.
     */
    public $timestamps = false;

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

    // -------------------------------------------------------------------------
    // DRY audit helper — eliminates the repeated 10-field create() block
    // -------------------------------------------------------------------------

    /**
     * Write a single audit entry, deriving all request-context fields automatically.
     *
     * @param  string          $action      e.g. 'upload', 'download', 'view'
     * @param  string          $resource    e.g. 'document'
     * @param  string|int|null $resourceId  Primary key(s) of the affected resource
     * @param  Request         $request     Current HTTP request
     * @param  int             $statusCode  HTTP status code of the response
     */
    public static function log(
        string $action,
        string $resource,
        string|int|null $resourceId,
        Request $request,
        int $statusCode = 200,
    ): void {
        static::create([
            'user_id'     => Auth::id(),
            'action'      => $action,
            'resource'    => $resource,
            'resource_id' => (string) $resourceId,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'method'      => $request->method(),
            'url'         => $request->fullUrl(),
            'status_code' => $statusCode,
            'accessed_at' => now(),
        ]);
    }
}
