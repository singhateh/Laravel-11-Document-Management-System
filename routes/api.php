<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController as ApiDocumentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StegoDocumentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| StegoLock API Routes
|--------------------------------------------------------------------------
|
| All routes in this file are automatically prefixed with /api and protected
| by the 'api' middleware group (stateless, no session/cookie).
|
| Authentication uses Laravel Sanctum personal access tokens (Bearer tokens).
| The .md guide specifies tymon/jwt-auth; Sanctum v4 is used instead since
| it is already installed and provides equivalent stateless token auth.
|
| Rate limiting:
|   - Auth endpoints  : 60 requests per minute  (brute-force protection)
|   - Authenticated   : 300 requests per minute (general API use)
|
*/

// ------------------------------------------------------------------------
// Health check (public, no auth)
// ------------------------------------------------------------------------

Route::get('/health', fn () => response()->json([
    'status'    => 'ok',
    'timestamp' => now()->toISOString(),
    'version'   => config('app.version', '1.0.0'),
]));

// ------------------------------------------------------------------------
// Authentication (public, rate-limited)
// ------------------------------------------------------------------------

Route::prefix('auth')->middleware('throttle:60,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->name('api.auth.register');

    Route::post('/login', [AuthController::class, 'login'])
        ->name('api.auth.login');
});

// ------------------------------------------------------------------------
// Authenticated routes (Sanctum token required)
// ------------------------------------------------------------------------

Route::middleware(['auth:sanctum', 'throttle:300,1'])->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('api.auth.logout');

        Route::get('/me', [AuthController::class, 'me'])
            ->name('api.auth.me');

        // Token management
        Route::get('/tokens',          [AuthController::class, 'listTokens'])->name('api.auth.tokens.index');
        Route::post('/tokens',         [AuthController::class, 'createToken'])->name('api.auth.tokens.create');
        Route::delete('/tokens/{id}',  [AuthController::class, 'revokeToken'])->name('api.auth.tokens.revoke');
    });

    // --------------------------------------------------------------------
    // StegoLock Document endpoints
    // (Phase 3 stub — controllers will be added in the Api layer)
    // --------------------------------------------------------------------

    Route::prefix('stego')->name('api.stego.')->group(function () {

        // List the authenticated user's stego documents
        Route::get('/documents',     [StegoDocumentController::class, 'index'])->name('documents.index');

         // Retrieve metadata for a single stego document
         Route::get('/documents/{id}', [StegoDocumentController::class, 'show'])->name('documents.show');
         
         // Retrieve lean decode status for polling
         Route::get('/documents/{id}/status', [StegoDocumentController::class, 'status'])->name('documents.status');

        // Encode a document (multipart form-data) — returns JSON { stego_document_id, quality_metrics }
        Route::post('/encode', [StegoDocumentController::class, 'encode'])->name('encode');

        // Decode — queues a decode operation (owner OR granted viewer)
        Route::post('/decode', [StegoDocumentController::class, 'decode'])->name('decode');
        
        // Download decoded document (owner OR granted viewer)
        Route::get('/decode/{id}', [StegoDocumentController::class, 'downloadDecoded'])->name('decode.download')
            ->whereNumber('id');

        // Grant viewer-decode access to another user (owner only)
        Route::post('/documents/{id}/grant',                      [StegoDocumentController::class, 'grant'])
            ->name('documents.grant')
            ->whereNumber('id');

        // List all grants for a stego document (owner only)
        Route::get('/documents/{id}/grants',                      [StegoDocumentController::class, 'listGrants'])
            ->name('documents.grants.index')
            ->whereNumber('id');

         // Estimate decoding time (owner OR granted viewer)
         Route::get('/documents/{id}/estimate-decoding-time', [StegoDocumentController::class, 'estimateDecodingTime'])
             ->name('documents.estimate-decoding-time')
             ->whereNumber('id');

         // Revoke a viewer's access (owner only)
         Route::delete('/documents/{id}/grant/{viewer_user_id}',   [StegoDocumentController::class, 'revokeGrant'])
             ->name('documents.grant.revoke')
             ->whereNumber(['id', 'viewer_user_id']);

     });

    // SPA convenience aliases
    Route::get('/user',  [AuthController::class, 'me'])->name('api.user');
    Route::get('/stego', [StegoDocumentController::class, 'index']);
    Route::delete('/stego/{id}', [StegoDocumentController::class, 'destroy']);

    // --------------------------------------------------------------------
    // Document REST endpoints  (W3-T01, W3-T06, W3-T09)
    // --------------------------------------------------------------------

    /**
     * POST /api/documents
     *   Upload one or more files into a folder.
     *   Body: multipart/form-data
     *     files[]    required  — file(s), max 50 MB each
     *     folder_id  required  — integer, must exist in folders table
     *     visibility optional  — 'public' (default) | 'private'
     *   Returns: 201 { message, documents[] }
     *
     * GET /api/documents/{id}
     *   Return metadata for a single document.
     *   Returns: 200 { document } | 403 | 404
     *
     * GET /api/documents
     *   Paginated lightweight list used by the SPA Encode dropdown.
     *   Returns: 200 paginated Document objects (id, name, extension, size)
     */
    Route::post('/documents',     [ApiDocumentController::class, 'store'])->name('api.documents.store');
    Route::get('/documents/{id}', [ApiDocumentController::class, 'show'])->name('api.documents.show')
        ->whereNumber('id');
    Route::get('/documents',      [ApiDocumentController::class, 'index'])->name('api.documents.index');

    // Role management endpoints
    Route::get('/roles', [RoleController::class, 'index'])->name('api.roles.index');
    Route::get('/roles/{role}/permissions', [RoleController::class, 'permissions'])->name('api.roles.permissions');

    // User management endpoints
    Route::get('/users', [UserController::class, 'index'])->name('api.users.index');
    Route::put('/users/{id}/role', [UserController::class, 'updateRole'])->name('api.users.updateRole')->whereNumber('id');
    Route::get('/users/search', [\App\Http\Controllers\UserController::class, 'search'])->name('api.users.search');

    // Dashboard stats / recent (for SPA)
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats',  [DashboardController::class, 'stats']);
        Route::get('/recent', [DashboardController::class, 'recent']);
    });

     // Collaboration and sharing endpoints
     Route::prefix('collaboration')->name('api.collaboration.')->group(function () {
         // Share management
         Route::post('/shares', [\App\Http\Controllers\ShareDocumentController::class, 'sharedDocuments'])
             ->name('shares.create');
         Route::put('/shares/{id}/permissions', [\App\Http\Controllers\ShareDocumentController::class, 'updatePermissions'])
             ->name('shares.permissions.update');
         Route::get('/shares/{id}/permissions', [\App\Http\Controllers\ShareDocumentController::class, 'getSharePermissions'])
             ->name('shares.permissions.get');
         Route::delete('/shares/{id}', [\App\Http\Controllers\ShareDocumentController::class, 'revokeShare'])
             ->name('shares.revoke');
         Route::get('/shares', [\App\Http\Controllers\ShareDocumentController::class, 'listSharedDocuments'])
             ->name('shares.list');
     });

 });
