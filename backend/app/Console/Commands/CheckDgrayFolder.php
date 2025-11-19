<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\MailboxConnectionManager;
use Illuminate\Console\Command;

class CheckDgrayFolder extends Command
{
    protected $signature = 'imap:check-dgray';
    protected $description = 'Check Dgray folder specifically';

    public function handle(MailboxConnectionManager $manager): int
    {
        $account = EmailAccount::where('email', 'dgray@ar-ad.com')->first();
        
        if (!$account) {
            $this->error('Account not found');
            return 1;
        }

        try {
            $client = $manager->makeImapClient($account);
            
            // Check both INBOX and Dgray folder
            foreach (['INBOX', 'Dgray'] as $folderName) {
                try {
                    $this->info("\n=== Checking folder: {$folderName} ===");
                    $folder = $client->getFolder($folderName);
                    
                    $messages = collect($folder->messages()->all()->limit(20, 1)->get() ?? []);
                    
                    $this->info("Total messages fetched: {$messages->count()}");
                    
                    if ($messages->count() > 0) {
                        $this->info("\nMessages:");
                        foreach ($messages->take(10) as $idx => $msg) {
                            $subject = substr($msg->getSubject() ?? 'No subject', 0, 60);
                            $uid = $msg->getUid();
                            $date = $msg->getDate();
                            $this->line(sprintf("  %d. UID:%s | %s | %s", $idx + 1, $uid, $date, $subject));
                        }
                    } else {
                        $this->warn("No messages in this folder");
                    }
                } catch (\Throwable $e) {
                    $this->error("Error with folder {$folderName}: {$e->getMessage()}");
                }
            }
            
            $client->disconnect();
            return 0;
        } catch (\Throwable $e) {
            $this->error("Connection failed: {$e->getMessage()}");
            return 1;
        }
    }
}
