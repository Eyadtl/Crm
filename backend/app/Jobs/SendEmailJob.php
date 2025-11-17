<?php

namespace App\Jobs;

use App\Services\OutboundMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public function __construct(public array $payload)
    {
        $this->onQueue('emails');
    }

    public function backoff(): array
    {
        return [60];
    }

    public function handle(OutboundMailService $service): void
    {
        $service->send($this->payload);
    }
}
