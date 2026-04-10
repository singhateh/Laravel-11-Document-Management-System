<?php

namespace App\Jobs;

use App\Models\B2UploadSession;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunStegoPostUploadWorkflowJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        private readonly int $documentId,
        private readonly int $uploadSessionId,
    ) {}

    public function handle(): void
    {
        $document = Document::find($this->documentId);
        if (!$document) {
            return;
        }

        $document->update([
            'ingest_status' => 'stego_queued',
            'ingest_error' => null,
        ]);

        B2UploadSession::whereKey($this->uploadSessionId)->update([
            'status' => 'stego_queued',
            'error_message' => null,
        ]);

        // Hook for optional stego-specific post-upload orchestration.

        $document->update([
            'ingest_status' => 'ready',
            'ingest_error' => null,
        ]);

        B2UploadSession::whereKey($this->uploadSessionId)->update([
            'status' => 'completed',
            'error_message' => null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Document::whereKey($this->documentId)->update([
            'ingest_status' => 'failed',
            'ingest_error' => substr($e->getMessage(), 0, 1000),
        ]);

        B2UploadSession::whereKey($this->uploadSessionId)->update([
            'status' => 'failed',
            'error_message' => substr($e->getMessage(), 0, 1000),
        ]);
    }
}
