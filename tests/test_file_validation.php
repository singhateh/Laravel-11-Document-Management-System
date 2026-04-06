<?php
require __DIR__.'/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

// Create Laravel application instance
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create a temporary test file
$testFile = tempnam(sys_get_temp_dir(), 'test');
file_put_contents($testFile, 'Test file content');

try {
    $request = new Illuminate\Http\Request();
    $uploadedFile = new Illuminate\Http\UploadedFile(
        $testFile,
        'test_file.txt',
        'text/plain',
        filesize($testFile),
        true
    );
    
    $request->files->add(['files' => $uploadedFile]);
    $request->merge(['folder_id' => 1]);
    
    var_dump('hasFile(\'files\'):', $request->hasFile('files'));
    var_dump('file(\'files\'):', $request->file('files'));
    var_dump('is_array:', is_array($request->file('files')));
    var_dump('request all:', $request->all());
    
    // Try to validate
    $validated = $request->validate([
        'folder_id' => 'required|exists:folders,id',
        'files' => 'required|array|min:1',
        'files.*' => 'required|file'
    ]);
    
    echo "Validation passed:\n";
    var_dump($validated);
    
} catch (\Exception $e) {
    echo "Validation failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Cleanup
unlink($testFile);
