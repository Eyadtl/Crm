<?php

namespace App\Services;

use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\EmailAttachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email as MimeEmail;

class OutboundMailService
{
    public function __construct(private readonly MailboxConnectionManager $connections)
    {
    }

    public function send(array $payload): Email
    {
        $account = EmailAccount::findOrFail($payload['from_account_id']);
        $mailer = $this->connections->makeMailer($account);
        $attachments = $this->prepareAttachments($payload['attachments'] ?? []);
        $message = $this->buildMessage($account, $payload, $attachments);

        $mailer->send($message);

        $headers = $message->getHeaders()->get('Message-ID');
        $messageId = $headers ? $headers->getBodyAsString() : sprintf('<%s@crm.local>', Str::uuid());

        $email = Email::create([
            'email_account_id' => $account->id,
            'message_id' => $messageId,
            'thread_id' => $payload['parent_email_id'] ?? $messageId,
            'direction' => 'outgoing',
            'subject' => $payload['subject'],
            'snippet' => Str::limit(strip_tags($payload['body']), 160),
            'sent_at' => now(),
            'received_at' => now(),
            'has_attachments' => !empty($payload['attachments']),
        ]);

        $this->storeOutboundParticipants($email, $account, $payload);
        $this->storeOutboundAttachments($email, $attachments);

        if (!empty($payload['project_id'])) {
            $email->projects()->syncWithoutDetaching([
                $payload['project_id'] => [
                    'linked_by' => $payload['user_id'] ?? null,
                    'linked_at' => now(),
                ],
            ]);
        }

        Log::info('Outbound email sent', [
            'email_id' => $email->id,
            'to' => $payload['to'],
        ]);

        return $email;
    }

    protected function buildMessage(EmailAccount $account, array $payload, array $attachments): MimeEmail
    {
        $message = (new MimeEmail())
            ->subject($payload['subject'])
            ->html($payload['body'])
            ->from(new Address($account->email, $account->display_name))
            ->to(...$this->mapAddresses($payload['to'] ?? []));

        if (!empty($payload['cc'])) {
            $message->cc(...$this->mapAddresses($payload['cc']));
        }

        if (!empty($payload['bcc'])) {
            $message->bcc(...$this->mapAddresses($payload['bcc']));
        }

        foreach ($attachments as $attachment) {
            $message->attach($attachment['content'], $attachment['name'], $attachment['mime']);
        }

        if (!empty($payload['parent_email_id'])) {
            $message->getHeaders()->addTextHeader('In-Reply-To', $payload['parent_email_id']);
        }

        return $message;
    }

    protected function mapAddresses(array $emails): array
    {
        return collect($emails)
            ->filter()
            ->map(fn ($address) => new Address($address))
            ->all();
    }

    protected function prepareAttachments(array $attachments): array
    {
        return collect($attachments)
            ->map(function ($encoded) {
                if (!is_string($encoded) || !str_contains($encoded, ';base64,')) {
                    return null;
                }
                [$meta, $content] = explode(';base64,', $encoded, 2);
                $mime = str_replace('data:', '', $meta);
                $binary = base64_decode($content, true);
                if ($binary === false) {
                    return null;
                }

                return [
                    'name' => 'attachment-'.Str::uuid().'.'.$this->guessExtension($mime),
                    'mime' => $mime ?: 'application/octet-stream',
                    'content' => $binary,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function guessExtension(?string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    protected function storeOutboundParticipants(Email $email, EmailAccount $account, array $payload): void
    {
        $email->participants()->create([
            'type' => 'sender',
            'address' => $account->email,
            'name' => $account->display_name,
        ]);

        foreach (['to', 'cc', 'bcc'] as $type) {
            foreach ($payload[$type] ?? [] as $address) {
                $email->participants()->create([
                    'type' => $type,
                    'address' => $address,
                ]);
            }
        }
    }

    protected function storeOutboundAttachments(Email $email, array $attachments): void
    {
        $disk = config('mailboxes.attachment_disk', config('filesystems.default'));

        foreach ($attachments as $attachment) {
            $path = sprintf(
                'emails/%s/%s/outgoing/%s',
                $email->email_account_id,
                $email->id,
                $attachment['name']
            );

            Storage::disk($disk)->put($path, $attachment['content']);

            EmailAttachment::create([
                'email_id' => $email->id,
                'filename' => $attachment['name'],
                'mime_type' => $attachment['mime'],
                'size_bytes' => strlen($attachment['content']),
                'storage_ref' => $path,
                'status' => 'uploaded',
                'downloaded_at' => now(),
            ]);
        }
    }
}
