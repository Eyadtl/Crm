<?php

namespace App\Jobs;

use App\Models\Email;
use App\Services\EmailBodyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchBodyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public function __construct(public string $emailId)
    {
        $this->onQueue('emails');
    }

    public function backoff(): array
    {
        return [120];
    }

    public function handle(EmailBodyService $service): void
    {
        $email = Email::findOrFail($this->emailId);
        $service->fetchAndCache($email);
    }
}
