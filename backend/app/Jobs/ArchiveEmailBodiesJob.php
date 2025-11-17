<?php

namespace App\Jobs;

use App\Models\ArchivedEmailBody;
use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ArchiveEmailBodiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $threshold = now()->subMonths(config('mailboxes.hot_storage_months'));

        Email::query()
            ->whereNotNull('body_ref')
            ->where('body_cached_at', '<', $threshold)
            ->chunk(100, function ($emails) {
                foreach ($emails as $email) {
                    ArchivedEmailBody::updateOrCreate(
                        ['email_id' => $email->id],
                        ['storage_ref' => $email->body_ref, 'archived_at' => now()]
                    );

                    $email->update([
                        'body_ref' => null,
                        'body_cached_at' => null,
                    ]);
                }
            });
    }
}
