<?php

namespace App\Services;

use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\EmailAttachment;
use App\Models\MailboxLock;
use App\Models\SyncLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;

class MailboxSyncService
{
    public function __construct(private readonly MailboxConnectionManager $connections) {}

    public function sync(EmailAccount $account): int
    {
        $lock = $this->acquireLock($account);
        $client = null;
        $processedCount = 0;

        try {
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

            if (config('mailboxes.fake_sync')) {
                $this->markSuccess($account, $account->last_synced_uid);

                return 0;
            }

            $client = $this->connections->makeImapClient($account);
            $folder = $client->getFolder(config('mailboxes.default_folder', 'INBOX'));
            $messages = $this->fetchMessages($folder, $account);
            $processedCount = $messages->count();

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

        return $processedCount;
    }

    protected function fetchMessages($folder, EmailAccount $account): Collection
    {
        $limit = max(20, (int) config('mailboxes.max_messages_per_sync', 20));
        $messages = collect();

        try {
            Log::info('MailboxSyncService: Starting fetch', [
                'account_id' => $account->id,
                'account_email' => $account->email,
                'last_synced_uid' => $account->last_synced_uid,
                'limit' => $limit
            ]);

            // Use query() without setFetchOrder to avoid BAD errors while being memory efficient
            $query = $folder->query();
            $rawMessages = $query->limit($limit)->get();
            
            Log::info('MailboxSyncService: Raw IMAP fetch complete', [
                'account_id' => $account->id,
                'raw_count' => is_countable($rawMessages) ? count($rawMessages) : 0,
                'raw_type' => gettype($rawMessages)
            ]);

            $messages = collect($rawMessages ?? []);

            Log::info('MailboxSyncService: Fetched messages from IMAP', [
                'account_id' => $account->id,
                'count' => $messages->count(),
                'first_uid' => $messages->first()?->getUid(),
                'last_uid' => $messages->last()?->getUid(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch messages', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return collect([]);
        }

        // Filter by UID if we've synced before (extra safety in case server ignores UID query)
        if ($account->last_synced_uid > 0) {
            Log::info('MailboxSyncService: Filtering by UID', [
                'last_synced_uid' => $account->last_synced_uid
            ]);

            $messages = $messages->filter(function (Message $message) use ($account) {
                return (int) $message->getUid() > (int) $account->last_synced_uid;
            });

            Log::info('MailboxSyncService: After UID filter', [
                'count' => $messages->count(),
                'last_synced_uid' => $account->last_synced_uid,
            ]);
        }

        // Sort by the actual received date so callers always process most recent emails first
        $messages = $messages->sortByDesc(function (Message $message) {
            return $this->getMessageReceivedTimestamp($message);
        })->values();

        return $messages;
    }

    protected function getMessageReceivedTimestamp(Message $message): int
    {
        $dateAttr = $message->getDate();

        if ($dateAttr instanceof \DateTimeInterface) {
            return $dateAttr->getTimestamp();
        }

        if ($dateAttr && method_exists($dateAttr, 'toDate')) {
            $normalized = $dateAttr->toDate();

            if ($normalized instanceof \DateTimeInterface) {
                return $normalized->getTimestamp();
            }

            if (is_string($normalized)) {
                return strtotime($normalized) ?: 0;
            }
        }

        if (is_string($dateAttr)) {
            return strtotime($dateAttr) ?: 0;
        }

        return 0;
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
        $receivedAt = $this->resolveReceivedAt($message);

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
                'subject' => Str::limit($message->getSubject() ?? '', 255, ''),
                'snippet' => $snippet,
                'sent_at' => $receivedAt,  // Use the same processed date
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

    protected function resolveReceivedAt(Message $message): \Carbon\Carbon
    {
        $timestamp = $this->getMessageReceivedTimestamp($message);

        return $timestamp > 0
            ? \Carbon\Carbon::createFromTimestamp($timestamp)->setTimezone('UTC')
            : now();
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
        if (! $addresses) {
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
