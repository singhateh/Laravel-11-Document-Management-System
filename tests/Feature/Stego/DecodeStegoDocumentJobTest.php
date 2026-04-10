<?php

namespace Tests\Feature\Stego;

use App\Jobs\DecodeStegoDocumentJob;
use App\Models\StegoDocument;
use App\Services\Stego\StegoDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DecodeStegoDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the job updates decoding status on failure
     */
    public function test_job_updates_decoding_status_on_failure(): void
    {
        // Create a stego document with pending decoding status
        $stegoDoc = StegoDocument::factory()->create([
            'decoding_status' => 'pending',
        ]);

        // Create a mock service that throws an exception
        $mockService = $this->createMock(StegoDocumentService::class);
        $mockService->method('decode')
            ->willThrowException(new \Exception('Test decoding failure'));

        // Execute the job
        $job = new DecodeStegoDocumentJob(1, $stegoDoc->id, '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $job->handle($mockService);

        // Verify that the decoding status is updated to failed
        $this->assertDatabaseHas('stego_documents', [
            'id' => $stegoDoc->id,
            'decoding_status' => 'failed',
            'decoding_error' => 'Test decoding failure',
        ]);
    }

    /**
     * Test that the job cleans up partial downloads on failure
     */
    public function test_job_cleans_up_partial_download_on_failure(): void
    {
        // Create a stego document with pending decoding status
        $stegoDoc = StegoDocument::factory()->create([
            'decoding_status' => 'pending',
        ]);

        // Create a mock service that throws an exception
        $mockService = $this->createMock(StegoDocumentService::class);
        $mockService->method('decode')
            ->willThrowException(new \Exception('Test decoding failure'));

        // Create a dummy partial download file
        Storage::makeDirectory('decoded/' . $stegoDoc->id);
        Storage::put('decoded/' . $stegoDoc->id . '/test.txt', 'partial content');

        // Execute the job
        $job = new DecodeStegoDocumentJob(1, $stegoDoc->id, '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $job->handle($mockService);

        // Verify that the partial download directory is removed
        $this->assertFalse(Storage::exists('decoded/' . $stegoDoc->id));
    }

    /**
     * Test that the failed method handles pending decoding status
     */
    public function test_failed_method_handles_pending_status(): void
    {
        // Create a stego document with pending decoding status
        $stegoDoc = StegoDocument::factory()->create([
            'decoding_status' => 'pending',
        ]);

        // Create a dummy partial download file
        Storage::makeDirectory('decoded/' . $stegoDoc->id);
        Storage::put('decoded/' . $stegoDoc->id . '/test.txt', 'partial content');

        // Call the failed method
        $job = new DecodeStegoDocumentJob(1, $stegoDoc->id, '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        $job->failed(new \Exception('Test failure'));

        // Verify that the decoding status is updated to failed
        $this->assertDatabaseHas('stego_documents', [
            'id' => $stegoDoc->id,
            'decoding_status' => 'failed',
            'decoding_error' => 'Test failure',
        ]);

        // Verify that the partial download directory is removed
        $this->assertFalse(Storage::exists('decoded/' . $stegoDoc->id));
    }

    /**
     * Test that the failed method ignores already failed status
     */
    public function test_failed_method_ignores_already_failed_status(): void
    {
        // Create a stego document with failed decoding status
        $stegoDoc = StegoDocument::factory()->create([
            'decoding_status' => 'failed',
            'decoding_error' => 'Original error',
        ]);

        // Call the failed method
        $job = new DecodeStegoDocumentJob(1, $stegoDoc->id, '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        $job->failed(new \Exception('New error'));

        // Verify that the original error remains
        $this->assertDatabaseHas('stego_documents', [
            'id' => $stegoDoc->id,
            'decoding_status' => 'failed',
            'decoding_error' => 'Original error',
        ]);
    }
}
