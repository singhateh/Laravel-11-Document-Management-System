<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use App\Http\Controllers\DocumentController;
use App\Http\Requests\StoreDocumentRequest;
use Illuminate\Http\UploadedFile;

class TestUploadCommand extends Command
{
    protected $signature = 'test:upload';
    protected $description = 'Test document upload functionality';

    public function handle()
    {
        $this->info('Testing document upload...');

        // Create temporary test file
        $testFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($testFile, 'Test content');
        $uploadedFile = new UploadedFile(
            $testFile,
            'test.txt',
            'text/plain',
            null,
            true
        );

        // Create mock request
        $request = new Request([
            'folder_id' => 1,
            'visibility' => 'public',
        ], [], [], [], [
            'files' => [$uploadedFile],
        ]);

        // Create request instance with validation
        $storeRequest = StoreDocumentRequest::createFromBase($request);
        $storeRequest->setContainer(app());

        try {
            // Validate the request
            $this->info('Validating request...');
            $this->info('Files in request: ' . print_r($storeRequest->file(), true));
            
            if (!$storeRequest->validateResolved()) {
                $this->error('Validation failed');
                $this->error(print_r($storeRequest->errors(), true));
            } else {
                $this->info('Request validation passed');
                $this->info('Validated data: ' . print_r($storeRequest->all(), true));
            }

            // Check if files are present
            if ($storeRequest->hasFile('files')) {
                $this->info('Files found in request');
                $files = $storeRequest->file('files');
                foreach (is_array($files) ? $files : [$files] as $file) {
                    $this->info('File: ' . $file->getClientOriginalName());
                }
            } else {
                $this->error('No files in request');
            }

        } catch (\Exception $e) {
            $this->error('Validation error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }

        // Cleanup
        unlink($testFile);
        $this->info('Test completed');
    }
}
