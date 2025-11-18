<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearStaleSyncsCommand extends Command
{
    protected $signature = 'mailboxes:clear-stale-syncs';
    protected $description = 'Clear stale mailbox locks and reset stuck sync states';

    public function handle(): int
    {
        // Clear all mailbox locks
        $locksCleared = DB::table('mailbox_locks')->delete();
        $this->info("Cleared {$locksCleared} stale locks");

        // Reset accounts stuck in syncing state
        $accountsReset = EmailAccount::where('sync_state', 'syncing')
            ->update(['sync_state' => 'idle']);

        $this->info("Reset {$accountsReset} accounts from 'syncing' to 'idle'");

        $this->info('✓ All stale syncs cleared');

        return self::SUCCESS;
    }
}
