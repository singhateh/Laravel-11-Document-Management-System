<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;
use App\Models\Folder;

// Setup Laravel environment
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Create a mock request
$request = Request::create('/upload', 'POST', [
    'folder_id' => 1,
    'visibility' => 'public',
], [], [], [
    'CONTENT_TYPE' => 'multipart/form-data',
]);

// Add a test file
$testFile = tempnam(sys_get_temp_dir(), 'test');
file_put_contents($testFile, 'Test content');
$request->files->add([
    'files' => new Symfony\Component\HttpFoundation\File\UploadedFile(
        $testFile,
        'test.txt',
        'text/plain',
        null,
        true
    ),
]);

// Handle the request
$response = $kernel->handle($request);

// Output the response
echo $response->getContent();

// Cleanup
unlink($testFile);
