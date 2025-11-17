<?php

namespace App\Console\Commands;

use App\Jobs\SyncHealthCheckJob;
use Illuminate\Console\Command;

class SyncHealthCheckCommand extends Command
{
    protected $signature = 'sync:health-check';

    protected $description = 'Dispatch the SyncHealthCheckJob for monitoring mailboxes.';

    public function handle(): int
    {
        SyncHealthCheckJob::dispatch();
        $this->info('SyncHealthCheckJob dispatched.');

        return self::SUCCESS;
    }
}
