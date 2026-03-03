<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StegoWebController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FileRequestController;
use App\Http\Controllers\ShareDocumentController;
use App\Http\Controllers\SearchController;
use Inertia\Inertia;

// Root entry point - guests see welcome page, auth users go to documents
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('documents.index');
    }
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

// Dashboard
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

// Profile routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar.upload');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
});

// Application routes
Route::middleware('auth')->group(function () {

    // Documents index (main app entry point for auth users)
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');

    // Folder Index (list all folders)
    Route::get('/folders', [FolderController::class, 'index'])->name('folders.index');
    
    // Document Routes
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('/update-visibility', [DocumentController::class, 'updateVisibility'])->name('update.visibility');

    Route::get('/getFiles/{folder}', [DocumentController::class, 'getFiles'])->name('getFiles');
    Route::post('/send-email-document', [DocumentController::class, 'sendDocumentEmail'])->name('send.email');
    Route::get('/getDocumentComments', [DocumentController::class, 'getDocumentComments'])->name('getDocumentComments');

    Route::post('/upload', [DocumentController::class, 'uploadDocumentFiles'])->name('upload');
    Route::post('/change-document', [DocumentController::class, 'changeFile'])->name('changeFile');
    Route::get('/filter-documents-by-tags', [DocumentController::class, 'filterDocumentByTag'])->name('filterDocumentByTag');
    Route::post('/update-document-order', [DocumentController::class, 'updateDocumentOrder'])->name('update.document.order');

    Route::get('/api/users', [UserController::class, 'search'])->name('users.search');

    // File Request Routes
    Route::post('/request-document', [FileRequestController::class, 'store'])->name('fileRequest.store');

    // Folder Routes
    Route::resource('folders', FolderController::class);
    Route::post('/update-folder-positions', [FolderController::class, 'updateFolderPositions'])->name('folders.updatePositions');
    Route::post('/update-folder-child-positions', [FolderController::class, 'updateFolderChildPositions'])->name('folders.updateChildPositions');
    Route::post('/folders/details', [FolderController::class, 'fetchDetails'])->name('folders.fetchDetails');
    Route::post('/folders/download-zip', [FolderController::class, 'downloadZip'])->name('folders.downloadZip');
    Route::post('/folders/delete', [FolderController::class, 'deleteSelecetdFolder'])->name('folders.deleteSelecetdFolder');

    // Tags Routes
    Route::resource('tags', TagController::class);
    Route::get('/search-tags', [TagController::class, 'searchTags'])->name('searchTags');
    Route::get('/add-tag', [TagController::class, 'addTag'])->name('addTag');

    // Comments Routes
    Route::get('/comments', [App\Http\Controllers\CommentController::class, 'index'])->name('comments.index');
    Route::post('/comments', [App\Http\Controllers\CommentController::class, 'store'])->name('comments.store');
    Route::put('/comments/{comment}', [App\Http\Controllers\CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [App\Http\Controllers\CommentController::class, 'destroy'])->name('comments.destroy');

    // Categories Routes
    Route::resource('categories', App\Http\Controllers\CategoryController::class);

    // Search Route
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');

    Route::resource('workspaces', FolderController::class);

    // Home
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

    // StegoLock web routes
    Route::prefix('stego')->name('stego.')->group(function () {
        Route::get('/',        [StegoWebController::class, 'index'])->name('index');
        Route::get('/encode',  [StegoWebController::class, 'encodeForm'])->name('encode.form');
        Route::post('/encode', [StegoWebController::class, 'encode'])->name('encode');
        Route::get('/decode',  [StegoWebController::class, 'decodeForm'])->name('decode.form');
        Route::post('/decode', [StegoWebController::class, 'decode'])->name('decode');
        Route::get('/tokens',  [StegoWebController::class, 'tokens'])->name('tokens');        Route::delete('/{id}', [StegoWebController::class, 'destroy'])->name('destroy');    });

    // Standalone StegoLock React SPA shell
    Route::get('/stego-app/{any?}', fn () => view('stegolock'))
        ->where('any', '.*')
        ->name('stego.spa');

    // Projects and Contacts placeholder routes
    Route::get('/projects', function () {
        return Inertia::render('Projects/Index');
    })->name('projects.index');
    
    Route::get('/contacts', function () {
        return Inertia::render('Contacts/Index');
    })->name('contacts.index');
});

// Share Documents Route (public)
Route::get('/{slug?}/share/{id?}/{token?}', [ShareDocumentController::class, 'getSharedDocuments'])->name('getSharedDocuments');
Route::post('/share-document', [ShareDocumentController::class, 'sharedDocuments'])->name('sharedDocuments');

require __DIR__.'/auth.php';
