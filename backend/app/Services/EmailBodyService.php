<?php

namespace App\Services;

use App\Models\Email;
use App\Models\EmailAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;

class EmailBodyService
{
    public function __construct(private readonly MailboxConnectionManager $connections)
    {
    }

    public function fetchAndCache(Email $email): void
    {
        $account = $email->account()->firstOrFail();

        if (config('mailboxes.fake_sync')) {
            $this->cachePlaceholder($email);
            return;
        }

        $client = $this->connections->makeImapClient($account);
        $folder = $client->getFolder(config('mailboxes.default_folder', 'INBOX'));
        $message = $this->locateMessage($folder, $email);

        if (!$message) {
            $client->disconnect();
            throw new RuntimeException('Unable to locate email on IMAP server.');
        }

        $body = $message->getHTMLBody(true) ?: nl2br(e($message->getTextBody() ?? ''));
        $sanitized = $this->sanitize($body);
        $disk = config('mailboxes.body_disk', config('filesystems.default'));
        $path = sprintf('emails/%s/%s/body.html', $email->email_account_id, $email->id);

        Storage::disk($disk)->put($path, $sanitized);

        $email->forceFill([
            'body_ref' => $path,
            'body_cached_at' => now(),
        ])->save();

        $this->cacheAttachments($email, $message);

        $client->disconnect();
    }

    protected function cachePlaceholder(Email $email): void
    {
        $disk = config('mailboxes.body_disk', config('filesystems.default'));
        $path = sprintf('emails/%s/%s/body.html', $email->email_account_id, $email->id);
        Storage::disk($disk)->put($path, sprintf('<p>Preview not available in fake sync mode for %s.</p>', e($email->subject)));

        $email->forceFill([
            'body_ref' => $path,
            'body_cached_at' => now(),
        ])->save();
    }

    protected function locateMessage($folder, Email $email): ?Message
    {
        $query = $folder->messages()
            ->setFetchOrder(IMAP::FT_UID)
            ->leaveUnread()
            ->limit(10);

        if ($email->sync_id) {
            $query->since(now()->subMonths(6));
        }

        $messages = collect($query->get());

        if ($email->sync_id) {
            return $messages->first(function (Message $message) use ($email) {
                return (string) $message->getUid() === (string) $email->sync_id;
            });
        }

        return $messages->first(function (Message $message) use ($email) {
            return $message->getMessageId() === $email->message_id;
        });
    }

    protected function sanitize(string $body): string
    {
        return Str::of($body)
            ->replaceMatches('/<script\b[^>]*>(.*?)<\/script>/is', '')
            ->replaceMatches('/on\w+="[^"]*"/i', '')
            ->toString();
    }

    protected function cacheAttachments(Email $email, Message $message): void
    {
        $maxBytes = max(1, config('mailboxes.max_attachment_mb', 20)) * 1024 * 1024;
        $disk = config('mailboxes.attachment_disk', config('filesystems.default'));

        foreach ($message->getAttachments() as $attachment) {
            $size = $attachment->getSize();
            $filename = $attachment->getName() ?: 'attachment-'.$attachment->getId();
            $attributes = [
                'mime_type' => $attachment->getMimeType(),
                'size_bytes' => $size,
            ];

            $record = EmailAttachment::updateOrCreate(
                [
                    'email_id' => $email->id,
                    'filename' => $filename,
                ],
                $attributes + ['status' => $size <= $maxBytes ? 'pending' : 'skipped']
            );

            if ($size > $maxBytes) {
                continue;
            }

            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin';
            $sanitizedName = Str::slug($baseName ?: 'attachment', '-').'.'.$extension;

            $path = sprintf(
                'emails/%s/%s/attachments/%s-%s',
                $email->email_account_id,
                $email->id,
                now()->timestamp,
                $sanitizedName
            );

            Storage::disk($disk)->put($path, $attachment->getContent());

            $record->forceFill([
                'storage_ref' => $path,
                'status' => 'downloaded',
                'downloaded_at' => now(),
            ])->save();
        }
    }
}
