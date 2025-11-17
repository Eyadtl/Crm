<?php

namespace App\Services;

use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\EmailAttachment;
use App\Models\MailboxLock;
use App\Models\SyncLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;

class MailboxSyncService
{
    public function __construct(private readonly MailboxConnectionManager $connections)
    {
    }

    public function sync(EmailAccount $account): void
    {
        $lock = $this->acquireLock($account);
        $client = null;

        try {
            DB::transaction(function () use ($account) {
                $account->forceFill([
                    'sync_state' => 'syncing',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                ])->save();

                SyncLog::create([
                    'email_account_id' => $account->id,
                    'event' => 'sync_started',
                    'message' => 'Sync triggered via scheduler',
                ]);
            });

            if (config('mailboxes.fake_sync')) {
                $this->markSuccess($account, $account->last_synced_uid);
                return;
            }

            $client = $this->connections->makeImapClient($account);
            $folder = $client->getFolder(config('mailboxes.default_folder', 'INBOX'));
            $messages = $this->fetchMessages($folder, $account);

            $lastUid = $account->last_synced_uid;
            foreach ($messages as $message) {
                $email = $this->storeMessage($account, $message);
                $lastUid = max((int) $lastUid, (int) $message->getUid());

                Log::debug('Email synced', [
                    'email_account_id' => $account->id,
                    'message_id' => $email->message_id,
                    'uid' => $message->getUid(),
                ]);
            }

            $this->markSuccess($account, $lastUid);

            SyncLog::create([
                'email_account_id' => $account->id,
                'event' => 'sync_finished',
                'message' => sprintf('Processed %d messages.', $messages->count()),
            ]);
        } catch (Throwable $throwable) {
            $this->markFailure($account, $throwable);
            SyncLog::create([
                'email_account_id' => $account->id,
                'event' => 'sync_failed',
                'message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        } finally {
            if ($client) {
                $client->disconnect();
            }

            $this->releaseLock($lock);
        }
    }

    protected function fetchMessages($folder, EmailAccount $account): Collection
    {
        $limit = max(
            1,
            config('mailboxes.max_messages_per_sync', config('mailboxes.fetch_chunk_size', 50))
        );

        $query = $folder->messages()
            ->leaveUnread()
            ->setFetchOrder(IMAP::FT_UID)
            ->limit($limit);

        $since = $this->determineSince($account);
        if ($since) {
            $query->since($since);
        }

        $messages = collect($query->get() ?? []);

        if ($account->last_synced_uid) {
            $messages = $messages->filter(function (Message $message) use ($account) {
                return (int) $message->getUid() > (int) $account->last_synced_uid;
            });
        }

        return $messages->sortBy(function (Message $message) {
            return (int) $message->getUid();
        })->values();
    }

    protected function determineSince(EmailAccount $account): ?string
    {
        if ($account->last_synced_at) {
            return $account->last_synced_at->clone()->subMinutes(5)->toDateTimeString();
        }

        return now()->subDays(7)->toDateTimeString();
    }

    protected function storeMessage(EmailAccount $account, Message $message): Email
    {
        $messageId = $this->resolveMessageId($message);
        $receivedAt = optional($message->getDate())?->setTimezone('UTC') ?? now();
        $snippet = $this->buildSnippet($message);

        $threadId = method_exists($message, 'getThreadId') ? $message->getThreadId() : null;

        $email = Email::updateOrCreate(
            [
                'email_account_id' => $account->id,
                'message_id' => $messageId,
            ],
            [
                'thread_id' => $threadId ?? $message->getMessageId(),
                'direction' => 'incoming',
                'subject' => $message->getSubject(),
                'snippet' => $snippet,
                'sent_at' => optional($message->getDate())?->setTimezone('UTC'),
                'received_at' => $receivedAt,
                'size_bytes' => $message->getSize(),
                'sync_id' => (string) $message->getUid(),
                'has_attachments' => $message->getAttachments()->count() > 0,
            ]
        );

        $this->syncParticipants($email, $message);
        $this->recordAttachmentMetadata($email, $message);

        return $email;
    }

    protected function syncParticipants(Email $email, Message $message): void
    {
        $email->participants()->delete();

        $this->storeAddresses($email, 'sender', $message->getFrom());
        $this->storeAddresses($email, 'to', $message->getTo());
        $this->storeAddresses($email, 'cc', $message->getCc());
        $this->storeAddresses($email, 'bcc', $message->getBcc());
    }

    protected function storeAddresses(Email $email, string $type, $addresses): void
    {
        if (!$addresses) {
            return;
        }

        foreach ($addresses as $address) {
            $email->participants()->create([
                'type' => $type,
                'address' => $address->mail,
                'name' => $address->personal,
            ]);
        }
    }

    protected function recordAttachmentMetadata(Email $email, Message $message): void
    {
        foreach ($message->getAttachments() as $attachment) {
            $filename = $attachment->getName() ?: 'attachment-'.$attachment->getId();

            EmailAttachment::firstOrCreate(
                [
                    'email_id' => $email->id,
                    'filename' => $filename,
                    'size_bytes' => $attachment->getSize(),
                ],
                [
                    'mime_type' => $attachment->getMimeType(),
                    'status' => 'pending',
                ]
            );
        }
    }

    protected function buildSnippet(Message $message): ?string
    {
        $body = $message->getTextBody() ?: strip_tags((string) $message->getHTMLBody(true));

        return Str::of($body ?? '')
            ->replace("\r", ' ')
            ->replace("\n", ' ')
            ->squish()
            ->limit(160, '...');
    }

    protected function resolveMessageId(Message $message): string
    {
        return $message->getMessageId() ?: sprintf('<%s@crm.local>', Str::uuid());
    }

    protected function markSuccess(EmailAccount $account, ?int $lastSyncedUid): void
    {
        $account->forceFill([
            'last_synced_uid' => $lastSyncedUid,
            'last_synced_at' => now(),
            'sync_state' => 'idle',
            'retry_count' => 0,
            'sync_error' => null,
        ])->save();
    }

    protected function markFailure(EmailAccount $account, Throwable $throwable): void
    {
        $retryCount = $account->retry_count + 1;
        $maxRetries = max(1, config('mailboxes.job_max_retries', 3));
        $state = $retryCount >= $maxRetries ? 'error' : 'warning';

        $account->forceFill([
            'retry_count' => $retryCount,
            'sync_state' => $state,
            'sync_error' => Str::limit($throwable->getMessage(), 255),
        ])->save();
    }

    protected function acquireLock(EmailAccount $account): MailboxLock
    {
        $lockOwner = gethostname().':'.getmypid();
        $lock = MailboxLock::query()->firstOrNew(['email_account_id' => $account->id]);

        if ($lock->locked_until && $lock->locked_until->isFuture()) {
            throw new \RuntimeException('Mailbox currently syncing.');
        }

        $lock->fill([
            'lock_owner' => $lockOwner,
            'locked_until' => now()->addMinutes(5),
        ])->save();

        return $lock;
    }

    protected function releaseLock(MailboxLock $lock): void
    {
        $lock->update([
            'locked_until' => now(),
        ]);
    }
}
