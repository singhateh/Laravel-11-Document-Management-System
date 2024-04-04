<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Mail\SendDocumentEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function __construct(public $details)
    {
    }

    public function handle()
    {
        $email = new SendDocumentEmail($this->details);
        Mail::to('recipient@example.com')->send($email);
    }
}