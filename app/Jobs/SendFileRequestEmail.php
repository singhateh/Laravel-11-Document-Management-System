<?php

namespace App\Jobs;

use App\Models\FileRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use App\Mail\FileRequestNotification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendFileRequestEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fileRequest;

    public function __construct(FileRequest  $fileRequest)
    {
        $this->fileRequest = $fileRequest;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Send the email
        Mail::to($this->fileRequest->request_to)
            ->send(new FileRequestNotification($this->fileRequest));
    }
}