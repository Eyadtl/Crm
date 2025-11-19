<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\MailboxConnectionManager;
use Illuminate\Console\Command;

class TestImapConnection extends Command
{
    protected $signature = 'imap:test {email}';
    protected $description = 'Test IMAP connection and list folders';

    public function handle(MailboxConnectionManager $manager): int
    {
        $account = EmailAccount::where('email', $this->argument('email'))->first();
        
        if (!$account) {
            $this->error('Account not found');
            return 1;
        }

        try {
            $this->info("Testing IMAP connection for: {$account->email}");
            
            $client = $manager->makeImapClient($account);
            
            $this->info("✓ Connected successfully");
            
            // List all folders
            $this->info("\nAvailable folders:");
            $folders = $client->getFolders();
            foreach ($folders as $folder) {
                $this->line("  - {$folder->name}");
            }
            
            // Try to get INBOX
            try {
                $inbox = $client->getFolder('INBOX');
                $this->info("\nINBOX folder:");
                $this->line("  Name: {$inbox->name}");
                
                // Get message count
                $query = $inbox->query();
                $query->setFetchOrder('desc');
                $messages = collect($query->limit(10)->get() ?? []);
                $count = $messages->count();
                
                $this->info("  Recent messages (limit 10): {$count}");
                
                if ($count > 0) {
                    $this->info("\nFirst 5 messages:");
                    foreach ($messages->take(5) as $msg) {
                        $subject = $msg->getSubject() ?? 'No subject';
                        $uid = $msg->getUid();
                        $date = $msg->getDate();
                        $this->line("  UID: {$uid} | Date: {$date} | Subject: " . substr($subject, 0, 50));
                    }
                } else {
                    $this->warn("  No messages found in INBOX");
                }
            } catch (\Throwable $e) {
                $this->error("Error accessing INBOX: {$e->getMessage()}");
            }
            
            $client->disconnect();
            
            return 0;
        } catch (\Throwable $e) {
            $this->error("Connection failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
