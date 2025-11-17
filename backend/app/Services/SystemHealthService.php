<?php

namespace App\Services;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\DB;

class SystemHealthService
{
    public function getSummary(): array
    {
        return [
            'queue_backlog' => $this->queueBacklog(),
            'failing_accounts' => EmailAccount::query()
                ->select('id as email_account_id', 'sync_state', 'sync_error')
                ->whereIn('sync_state', ['warning', 'error'])
                ->get(),
            'last_cron_run_at' => now()->toIso8601String(),
            'database_connections' => $this->databaseConnections(),
            'uptime_seconds' => (int) (microtime(true) - (defined('LARAVEL_START') ? LARAVEL_START : microtime(true))),
        ];
    }

    protected function queueBacklog(): array
    {
        try {
            return DB::table('jobs')
                ->select('queue', DB::raw('count(*) as total'))
                ->groupBy('queue')
                ->pluck('total', 'queue')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function databaseConnections(): ?int
    {
        try {
            $result = DB::selectOne('select sum(numbackends) as total from pg_stat_database');
            return $result?->total ? (int) $result->total : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
