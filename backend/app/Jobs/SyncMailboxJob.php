<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use App\Services\MailboxSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMailboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public string $emailAccountId)
    {
        $this->onQueue('emails');
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MailboxSyncService $syncService): void
    {
        $account = EmailAccount::query()->findOrFail($this->emailAccountId);
        $processed = $syncService->sync($account);

        Log::info('Mailbox synced via queue job', [
            'email_account_id' => $account->id,
            'processed_messages' => $processed,
        ]);
    }
}
