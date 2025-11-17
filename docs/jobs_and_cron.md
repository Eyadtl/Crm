# Background Jobs & Cron Orchestration

This document translates the TECHNICAL_SPEC into actionable guidance for the
Laravel + Supabase deployment.

## 1. Queue & Worker Topology
- **Driver**: Laravel database queue backed by Supabase Postgres (`jobs`, `failed_jobs`, `job_batches`).
- **Workers**: Each app container runs `php artisan queue:work --queue=default,emails,exports`.
- **Concurrency**: start with 4 workers (2 per container). Scale horizontally when `jobs` table exceeds 1,000 queued jobs or oldest job age surpasses 5 minutes.
- **Locking**: `mailbox_locks` table prevents overlapping IMAP syncs per account. Every `SyncMailboxJob` must acquire its lock prior to IMAP login.

## 2. Cron Entry Points
Supabase cron (pg_cron or Edge Functions) invokes the following HTTP endpoints:

| Schedule | Target | Notes |
|----------|--------|-------|
| Every 5 minutes | `POST /system/cron-run` | Protected by `X-Internal-Signature`. Endpoint runs `php artisan schedule:run`. |
| Daily 02:00 UTC | `POST /system/cron-run` | Scheduler dispatches `emails:archive` command which enqueues `ArchiveEmailBodiesJob`. |
| Hourly | `POST /system/cron-run` | Scheduler dispatches `sync:health-check` command for trend monitoring. |

Because all cron invocations call the same webhook, the Laravel scheduler
contains conditional logic to fan out commands based on current time.

## 3. Core Jobs
### SyncMailboxJob
- **Trigger**: Every 15 minutes per active account (adjustable via `sync_interval_minutes`).
- **Steps**:
  1. Acquire lock in `mailbox_locks`.
  2. Connect to IMAP using encrypted credentials (TLS/SSL only).
  3. Fetch new UIDs greater than `last_synced_uid`; hydrate `emails`, `email_participants`, and `email_attachments`.
  4. Mark `has_attachments`, schedule `FetchBodyJob` for flagged previews.
  5. Update `last_synced_uid`, `last_synced_at`, and `sync_state`.
- **Error handling**: retry 3x with exponential backoff (1, 5, 15 minutes).
  Persist final failure message into `email_accounts.sync_error` and flip
  `sync_state` to `warning` or `error`.

### FetchBodyJob
- **Trigger**: when a user opens an email missing a cached body or when TTL has expired.
- **Action**: pull the full body over IMAP, sanitize HTML, upload to Supabase Storage, update `emails.body_ref` + `body_cached_at`. Download inline attachments under size limit.
- **Timeout**: 2 minutes; requeue once before surfacing error toast to the user.

### SendEmailJob
- **Trigger**: `/emails/{id}/reply` or `/forward`.
- **Action**: uses Symfony Mailer + account SMTP settings, logs outgoing message into `emails` with `direction=outgoing`, links to selected project if provided.
- **Observability**: store provider response in `activity_logs` for auditing.

### ArchiveEmailBodiesJob
- **Trigger**: monthly via scheduler.
- **Action**: remove cached bodies older than `app_settings.email_hot_storage_months` months. Persist storage pointer in `archived_email_bodies` or delete storage object if policy dictates. Metadata remains untouched.

### ExportJob
- **Trigger**: `/exports/projects` or `/exports/contacts`.
- **Action**: stream filtered dataset to CSV, upload to Supabase Storage, set
  pre-signed URL + expiration on `data_exports`.
- **Throughput rule**: limit to 2 concurrent export jobs to minimize Postgres
  load. Excess jobs stay queued.

### SyncHealthCheckJob
- **Trigger**: hourly scheduler.
- **Action**: inspect `email_accounts` stuck in `warning` or `error` for more than 3 cycles, raise notifications (email + activity log) and optionally ping Slack/Webhook.

## 4. Monitoring & Alerting
- `/system/health` surfaces queue depth per named queue and any accounts with
  repeated failures. Use uptime robot/pager to watch this endpoint.
- Supabase Log Drains capture worker logs. Filter on `SyncMailboxJob` and
  `FetchBodyJob` tags for rapid triage.
- Create Grafana chart for:
  - average sync duration per account (derived from job start/finish logs),
  - queue backlog,
  - `mailbox_locks` contention rate.

## 5. Failure Recovery Playbook
1. **IMAP auth failures**: worker writes reason to `sync_error`, increments
   `retry_count`. Admin resolves credentials then hits `/email-accounts/{id}/test`
   to reset.
2. **Stuck cron**: uptime monitor alerts if `/system/health` not reachable; rerun
   Supabase cron manually and verify HMAC secret rotation.
3. **Queue bloat**: scale worker processes or temporarily throttle number of
   concurrent `SyncMailboxJob` dispatches via feature flag stored in `app_settings`.

This framework ensures the Laravel scheduler and Supabase cron stay in lock-step
while satisfying the 15-minute sync SLA for 200+ mailboxes.

