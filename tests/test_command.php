<?php
require __DIR__.'/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

// Create Laravel application instance
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create a temporary test file
$testFile = tempnam(sys_get_temp_dir(), 'test');
file_put_contents($testFile, 'Test file content');

try {
    Log::info('Testing file validation');
    
    // Simulate a request with a single file
    $request = new Illuminate\Http\Request();
    $request->files->add([
        'files' => new Illuminate\Http\UploadedFile(
            $testFile,
            'test_file.txt',
            'text/plain',
            filesize($testFile),
            true
        )
    ]);
    
    $request->merge([
        'folder_id' => 1
    ]);
    
    // Try to validate the request
    $validated = $request->validate([
        'folder_id' => 'required|exists:folders,id',
        'files' => 'required|file'
    ]);
    
    echo "Validation passed for single file\n";
    var_dump($validated);
    
} catch (\Exception $e) {
    echo "Validation failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Cleanup
unlink($testFile);

echo "Test completed\n";
