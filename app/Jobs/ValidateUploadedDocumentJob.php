<?php

namespace App\Jobs;

use App\Models\B2UploadSession;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ValidateUploadedDocumentJob implements ShouldQueue
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
            'ingest_status' => 'validating',
            'ingest_error' => null,
        ]);

        B2UploadSession::whereKey($this->uploadSessionId)->update([
            'status' => 'validating',
            'error_message' => null,
        ]);

        $absolutePath = public_path($document->file_path ?? '');
        if (!is_file($absolutePath)) {
            throw new \RuntimeException('Uploaded file is missing on local storage.');
        }

        $actualSize = filesize($absolutePath) ?: 0;
        if ($actualSize <= 0 || $actualSize > 50 * 1024 * 1024) {
            throw new \RuntimeException('Uploaded file failed size validation.');
        }
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
