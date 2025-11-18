<?php

namespace App\Services;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class MailboxConnectionManager
{
    public function __construct(private readonly ClientManager $clientManager) {}

    public function decryptCredentials(EmailAccount $account): array
    {
        return [
            'username' => Crypt::decryptString(data_get($account->encrypted_credentials, 'username')),
            'password' => Crypt::decryptString(data_get($account->encrypted_credentials, 'password')),
        ];
    }

    public function makeImapClient(EmailAccount $account, ?array $credentials = null): Client
    {
        $credentials ??= $this->decryptCredentials($account);

        $client = $this->clientManager->make([
            'host' => $account->imap_host,
            'port' => $account->imap_port,
            'encryption' => $this->mapEncryption($account->security_type),
            'protocol' => 'imap',
            'validate_cert' => config('mailboxes.validate_cert', true),
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'authentication' => 'password',
            'timeout' => config('mailboxes.timeout', 60),
            'options' => [
                'DISABLE_AUTHENTICATOR' => ['PLAIN'],
            ],
        ]);

        $client->connect();

        return $client;
    }

    public function makeMailer(EmailAccount $account, ?array $credentials = null): Mailer
    {
        return new Mailer($this->makeSmtpTransport($account, $credentials));
    }

    public function makeSmtpTransport(EmailAccount $account, ?array $credentials = null): EsmtpTransport
    {
        $credentials ??= $this->decryptCredentials($account);

        $encrypted = $account->security_type === 'ssl';
        $transport = new EsmtpTransport($account->smtp_host, $account->smtp_port, $encrypted);
        $usesStartTls = in_array($account->security_type, ['tls', 'starttls'], true);
        $transport->setAutoTls($usesStartTls);
        $transport->setRequireTls($account->security_type === 'starttls');

        $transport->setUsername($credentials['username']);
        $transport->setPassword($credentials['password']);

        if ($stream = $transport->getStream()) {
            $stream->setTimeout(config('mailboxes.smtp_timeout', 60));
        }

        if (! config('mailboxes.smtp_validate_cert', true)) {
            $transport->setStreamOptions([
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
        }

        return $transport;
    }

    protected function mapEncryption(?string $securityType): ?string
    {
        return match (Str::lower($securityType ?? '')) {
            'ssl' => 'ssl',
            'tls', 'starttls' => 'tls',
            default => null,
        };
    }
}
