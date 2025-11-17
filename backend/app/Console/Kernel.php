<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\DispatchMailboxSyncCommand::class,
        Commands\ArchiveEmailBodiesCommand::class,
        Commands\SyncHealthCheckCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('mailboxes:dispatch-sync')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('sync:health-check')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('emails:archive')
            ->dailyAt('02:00')
            ->onOneServer();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        $consoleRoutes = base_path('routes/console.php');
        if (file_exists($consoleRoutes)) {
            require $consoleRoutes;
        }
    }
}
