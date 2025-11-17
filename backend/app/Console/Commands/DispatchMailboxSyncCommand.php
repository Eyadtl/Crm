<?php

namespace App\Console\Commands;

use App\Jobs\SyncMailboxJob;
use App\Models\EmailAccount;
use App\Models\SyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class DispatchMailboxSyncCommand extends Command
{
    protected $signature = 'mailboxes:dispatch-sync {--limit=100 : Maximum accounts to enqueue per run}';

    protected $description = 'Queue SyncMailboxJob for each active email account that is due for a sync.';

    public function handle(): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $now = now();
        $dispatched = 0;

        EmailAccount::active()
            ->orderBy('last_synced_at')
            ->chunkById(100, function (Collection $accounts) use ($now, $limit, &$dispatched) {
                foreach ($accounts as $account) {
                    if ($dispatched >= $limit) {
                        return false;
                    }

                    if (!$account->shouldSync($now)) {
                        continue;
                    }

                    $account->forceFill([
                        'sync_state' => 'queued',
                        'sync_error' => null,
                    ])->save();

                    SyncLog::create([
                        'email_account_id' => $account->id,
                        'event' => 'sync_queued',
                        'message' => 'Sync job queued via scheduler.',
                        'context' => [
                            'sync_interval_minutes' => $account->sync_interval_minutes,
                        ],
                    ]);

                    SyncMailboxJob::dispatch($account->id);
                    $dispatched++;
                }
            });

        $this->info("Queued {$dispatched} mailbox sync job(s).");

        return self::SUCCESS;
    }
}
