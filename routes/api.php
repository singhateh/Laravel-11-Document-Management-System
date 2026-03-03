<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\StegoWebController;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Category;
use App\Models\Tag;
use App\Models\StegoDocument;
use Illuminate\Support\Facades\Auth;
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
        Route::get('/documents', function () {
            $docs = Auth::user()
                ->stegoDocuments()
                ->with('document')
                ->latest()
                ->paginate(20);

            return response()->json($docs);
        })->name('documents.index');

        // Retrieve metadata for a single stego document
        Route::get('/documents/{id}', function (int $id) {
            $doc = Auth::user()
                ->stegoDocuments()
                ->with(['document', 'segments'])
                ->findOrFail($id);

            return response()->json($doc);
        })->name('documents.show');

        // Encode a document (multipart form-data, same logic as web)
        Route::post('/encode', [StegoWebController::class, 'encode'])->name('encode');

        // Decode — returns file download
        Route::post('/decode', [StegoWebController::class, 'decode'])->name('decode');

    });

    // SPA convenience aliases
    Route::get('/user', [AuthController::class, 'me'])->name('api.user');

    // GET /api/stego — alias for stego/documents (used by SPA)
    Route::get('/stego', function () {
        return response()->json(
            Auth::user()->stegoDocuments()->with(['document:id,name,extension'])->withCount('segments')->latest()->paginate(20)
        );
    });

    // DELETE /api/stego/{id}
    Route::delete('/stego/{id}', function (int $id) {
        $doc = Auth::user()->stegoDocuments()->findOrFail($id);
        $doc->delete();
        return response()->json(['message' => 'Deleted.']);
    });

    // GET /api/documents — document list for SPA Encode dropdown
    Route::get('/documents', function () {
        return response()->json(Document::select('id', 'name', 'extension', 'size')->latest()->paginate(50));
    });

    // Dashboard stats / recent (for SPA)
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', function () {
            $user = Auth::user();
            return response()->json([
                'documents'       => Document::count(),
                'folders'         => Folder::count(),
                'categories'      => Category::count(),
                'tags'            => Tag::count(),
                'stego_documents' => StegoDocument::where('user_id', $user->id)->count(),
            ]);
        });
        Route::get('/recent', function () {
            $docs = Document::with('tags:id,name')
                ->withExists('stegoDocument as is_stegoed')
                ->latest()
                ->take(8)
                ->get(['id', 'name', 'extension', 'size', 'created_at']);
            return response()->json($docs);
        });
    });

});
