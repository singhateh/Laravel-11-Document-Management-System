<?php
require __DIR__.'/vendor/autoload.php';

use App\Http\Controllers\DocumentController;
use App\Http\Requests\StoreDocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

// Create a mock request
$request = Request::create('/upload', 'POST');
$request->headers->set('Content-Type', 'multipart/form-data');

// Add test data
$testFile = tempnam(sys_get_temp_dir(), 'test');
file_put_contents($testFile, 'Test file content');

$request->merge([
    'folder_id' => 1
]);

$request->files->replace([
    'files' => new UploadedFile(
        $testFile,
        'test_file.txt',
        'text/plain',
        filesize($testFile),
        true
    )
]);

// Create and test the request
try {
    $formRequest = StoreDocumentRequest::createFromBase($request);
    $formRequest->validateResolved();
    
    echo "Validation passed\n";
    var_dump($formRequest->validated());
    var_dump($formRequest->file('files'));
    
} catch (\Exception $e) {
    echo "Validation failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Cleanup
unlink($testFile);
