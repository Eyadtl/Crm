<?php

namespace App\Console\Commands;

use App\Jobs\ArchiveEmailBodiesJob;
use Illuminate\Console\Command;

class ArchiveEmailBodiesCommand extends Command
{
    protected $signature = 'emails:archive';

    protected $description = 'Dispatch the ArchiveEmailBodiesJob to cold-store stale email bodies.';

    public function handle(): int
    {
        ArchiveEmailBodiesJob::dispatch();
        $this->info('ArchiveEmailBodiesJob dispatched.');

        return self::SUCCESS;
    }
}
