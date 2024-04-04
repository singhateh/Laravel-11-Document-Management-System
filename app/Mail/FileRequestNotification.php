<?php

namespace App\Mail;

use App\Models\FileRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class FileRequestNotification extends Mailable
{
    use Queueable, SerializesModels;

    protected $fileRequest;

    public function __construct(FileRequest  $fileRequest)
    {
        $this->fileRequest = $fileRequest;
    }


    public function build()
    {
        return $this->view('emails.file_request_notification', ['fileRequest' => $this->fileRequest]);
    }
}