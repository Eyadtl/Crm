<?php

return [
    'body_ttl_days' => (int) env('IMAP_BODY_TTL_DAYS', 7),
    'max_attachment_mb' => (int) env('IMAP_MAX_ATTACHMENT_MB', 20),
    'hot_storage_months' => (int) env('EMAIL_HOT_STORAGE_MONTHS', 6),
    'fetch_chunk_size' => (int) env('IMAP_FETCH_CHUNK', 50),
    'job_max_retries' => (int) env('JOB_MAX_RETRIES', 3),
    'default_folder' => env('IMAP_DEFAULT_FOLDER', 'INBOX'),
    'validate_cert' => filter_var(env('IMAP_VALIDATE_CERT', true), FILTER_VALIDATE_BOOL),
    'timeout' => (int) env('IMAP_TIMEOUT', 60),
    'max_messages_per_sync' => (int) env('IMAP_MAX_PER_SYNC', 200),
    'smtp_timeout' => (int) env('SMTP_TIMEOUT', 60),
    'smtp_validate_cert' => filter_var(env('SMTP_VALIDATE_CERT', true), FILTER_VALIDATE_BOOL),
    'attachment_disk' => env('EMAIL_ATTACHMENT_DISK', env('FILESYSTEM_DISK', 'local')),
    'body_disk' => env('EMAIL_BODY_DISK', env('FILESYSTEM_DISK', 'local')),
    'skip_connectivity_checks' => filter_var(env('MAILBOX_SKIP_CONNECTIVITY', false), FILTER_VALIDATE_BOOL),
    'fake_sync' => filter_var(env('MAILBOX_FAKE_SYNC', false), FILTER_VALIDATE_BOOL),
];
