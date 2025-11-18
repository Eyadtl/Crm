<?php

namespace App\Services;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailConnectivityService
{
    public function __construct(private readonly MailboxConnectionManager $connections) {}

    public function test(EmailAccount $account, array $credentials): array
    {
        if (config('mailboxes.skip_connectivity_checks')) {
            return [
                'status' => 'passed',
                'message' => 'Connectivity checks skipped (developer mode).',
                'last_run_at' => now()->toIso8601String(),
            ];
        }

        $imapResult = $this->attemptImap($account, $credentials);
        $smtpResult = $this->attemptSmtp($account, $credentials);

        $status = ($imapResult['passed'] && $smtpResult['passed']) ? 'passed' : 'failed';
        $message = $status === 'passed'
            ? 'IMAP and SMTP checks succeeded.'
            : 'One or more connectivity checks failed.';

        return [
            'status' => $status,
            'message' => $message,
            'last_run_at' => now()->toIso8601String(),
            'checks' => [
                'imap' => $imapResult,
                'smtp' => $smtpResult,
            ],
        ];
    }

    protected function attemptImap(EmailAccount $account, array $credentials): array
    {
        try {
            $client = $this->connections->makeImapClient($account, $credentials);
            $client->disconnect();

            return ['passed' => true];
        } catch (Throwable $throwable) {
            Log::warning('IMAP connectivity failed', [
                'email_account_id' => $account->id,
                'message' => $throwable->getMessage(),
            ]);

            return [
                'passed' => false,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    protected function attemptSmtp(EmailAccount $account, array $credentials): array
    {
        try {
            $transport = $this->connections->makeSmtpTransport($account, $credentials);
            $transport->start();
            $transport->stop();

            return ['passed' => true];
        } catch (Throwable $throwable) {
            Log::warning('SMTP connectivity failed', [
                'email_account_id' => $account->id,
                'message' => $throwable->getMessage(),
            ]);

            return [
                'passed' => false,
                'error' => $throwable->getMessage(),
            ];
        }
    }
}
