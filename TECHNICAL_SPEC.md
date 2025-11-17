# Arabia Talents CRM – Technical Specification

## 1. Executive Overview
- **Goal**: Deliver an email-centric CRM that centralizes 200+ IMAP accounts, keeps project communication searchable, and links emails to deals and contacts with ≤15-min sync cadence.
- **Stack**: frontend React SPA (Vite + React Query), backend Laravel 11 API, Supabase-hosted Postgres (primary datastore) + Storage (attachments when cached), Supabase Functions for scheduled cron triggers, IMAP/SMTP connectivity via PHP IMAP + Symfony Mailer.
- **Guiding constraints from stakeholder choices**:
  - Email sync prioritizes steady 15-minute runs with retries over ultra-low latency.
  - CRM stores normalized metadata for every email; full bodies/attachments are pulled on-demand from providers and optionally cached.
  - Only app passwords are supported (no OAuth flows); Admins manually add and test every mailbox.
  - Lightweight security baseline: TLS everywhere, encrypted secrets, audit logs for sensitive changes.
  - Jobs/queues run through Laravel's database queue driver triggered by Supabase cron (no Redis/SQS).
  - Project linking and automation are manual for V1, dashboards remain simple count & table views.
  - Users are provisioned via invitation/password; statuses are maintained by Admins; emails auto-archive after a configurable number of months.

## 2. System Architecture
### 2.1 Logical Components
1. **React SPA**
   - Auth (JWT + refresh), role-aware navigation.
   - Inbox, Projects, Contacts, Dashboards, Admin settings.
2. **Laravel API**
   - Modules: Auth/RBAC, Email Accounts, IMAP Sync Service, SMTP Mailer, Projects/Contacts, Activity logs, Reporting, CSV exports.
   - Database queue worker (database driver) consuming `jobs` table.
3. **Supabase**
   - Postgres: primary database & queue tables.
   - Storage: optional cache for attachments when user downloads/links large files.
   - Cron (pg_cron / Edge Function scheduler): hits `artisan schedule:run` endpoint every 5 minutes.
4. **External Email Providers**
   - IMAP for fetching metadata, SMTP for send-as; relies on TLS/SSL with app passwords.

### 2.2 Deployment Topology
- **Backend**: Laravel container hosted on Supabase containers / Fly.io-equivalent (if Supabase-hosted functions require). Deploy via GitHub Actions -> Docker image -> Supabase environment (or render). Provide `.env` secrets referencing Supabase Postgres.
- **Frontend**: React SPA deployed via Vercel/Netlify (static) calling API over HTTPS.
- **Networking**: Custom domain with HTTPS, WAF in front of API. Outbound IMAP/SMTP from backend container only.
- **Scaling**: Horizontal scaling handled by multiple Laravel app containers; queue workers launched as separate processes. Supabase Postgres tuned for 200 IMAP connections per 15-min cycle (use connection pooling via PgBouncer).

### 2.3 Sequence Overview
1. Supabase cron calls `/artisan/schedule` (protected endpoint) → Laravel runs scheduled commands (sync, health, archival).
2. Sync job enqueues `SyncMailboxJob` records per active account to `jobs` table.
3. Worker processes job → connects to IMAP, fetches new headers, stores metadata, schedules `FetchBodyJob` if email opened.
4. User in SPA requests `/emails/{id}` → backend returns metadata; if body cache is stale, worker fetches via IMAP, stores sanitized HTML in object storage (per retention policy) and streams to client.
5. Project linking uses `/projects/{id}/emails` endpoint with transaction across `emails`, `project_email`, `contacts`, `contact_project`.

## 3. Module Specifications
### 3.1 Authentication & RBAC
- **Supabase Auth disabled**; Laravel handles auth via JWT (Sanctum tokens).
- Roles: Admin, Manager, Editor, Viewer stored in `roles` + `user_roles`.
- Permissions enforced via policies + middleware: `can:manage-projects`, `can:view-email`, etc.
- Manual invitation flow: Admin enters name/email, system sends invite link (temporary signed URL) to set password.

### 3.2 Email Account Management
- Admin UI for CRUD on `email_accounts`.
- Connection test uses IMAP + SMTP handshake before saving.
- Flags: `status` (active/disabled), last sync timestamp, error log pointer.
- Credentials stored encrypted with Laravel's `encrypt()`; at rest they reside in Postgres using pgcrypto (Supabase) or Laravel Eloquent encryption.
- Manual onboarding wizard: (1) Info, (2) Credentials, (3) Validation, (4) Summary.

### 3.3 IMAP Sync Service
- Scheduler enqueues `SyncMailboxJob` for each active account (respect `sync_interval` value default 15 min).
- Job steps:
  1. Acquire distributed lock row in `mailbox_locks`.
  2. Connect via PHP IMAP library with TLS.
  3. Fetch UIDs > `last_synced_uid`.
  4. Store metadata (subject, participants, message-id, snippet, flags) in `emails`.
  5. Capture attachments metadata without downloading files (> configured size defers download).
  6. Update `last_synced_uid`, `last_synced_at`, `sync_state`.
- Retry/backoff: up to 3 tries with exponential delays; mark account `warning` or `error`.
- Body fetching occurs lazily: when user opens / previews an email older than cached TTL (e.g., 7 days), a `FetchBodyJob` fetches and caches sanitized HTML/text + attachments if under size limit. Metadata always available.

### 3.4 SMTP Sending
- Compose form uses `from_account_id` restricted by user access.
- Backend uses Symfony Mailer to send via account SMTP details from `email_accounts`.
- Sent emails stored in `emails` table with `direction = outgoing`, `body_ref` referencing cached body (if stored).
- Optional project linking at send-time writes to `project_email`.

### 3.5 Projects (Deals)
- CRUD API ensures `deal_status` uses admin-managed lookup table.
- Manual linking only: user selects `Import into Project` from email; no automatic routing rules for V1.
- Activity log entry on creation, status change, ownership updates.
- Supports `estimated_value`, `notes`, `closed_lost_reason`.
- Search filters: product, manager, owner, status, date updated.

### 3.6 Contacts
- Auto-creation triggered during email → project import.
- Manual edits allow name/phone/notes/tags.
- Contact linking ensures unique email constraint; same contact can map to multiple projects.
- Contact detail surfaces related projects + emails.

### 3.7 Inbox & Email UI
- Views: unified inbox, per-account filter, project-filtered view, search (subject/sender).
- Email detail pulls metadata immediately and lazy-loads body (if cache missing, show skeleton + stream once job completes).
- Actions: reply, reply all, forward, import to project, create project.

### 3.8 Dashboards & Reporting
- Basic metrics only: counts per status, per owner, latest imported emails table, top contacts by email count.
- CSV exports for projects and contacts using queued jobs writing to Supabase Storage, returning pre-signed URL.

### 3.9 Archival & Retention
- Config `email_hot_storage_months` (default 6). Supabase cron kicks `ArchiveEmailsCommand` monthly:
  - Move cached body references older than threshold to cold storage table or delete cached bodies while retaining metadata.
  - Keep metadata indefinitely.
- Provide restore endpoint to re-fetch body from IMAP on demand (subject to provider retention).

## 4. Data Model (Supabase Postgres)
> All timestamps in UTC, snake_case columns, indexed for frequent filters. Selected tables only.

| Table | Key Fields | Notes |
|-------|------------|-------|
| `users` | `id`, `name`, `email` (unique), `password_hash`, `timezone`, `status` | Soft deletes to preserve activity history. |
| `roles`, `user_roles` | `role_slug`, `user_id` | Many-to-many. |
| `email_accounts` | `id`, `email`, `display_name`, `imap_host`, `imap_port`, `smtp_host`, `smtp_port`, `security_type`, `auth_type`, `encrypted_credentials`, `status`, `last_synced_uid`, `last_synced_at`, `sync_state`, `sync_error` | `status` enum (active/disabled). |
| `emails` | `id`, `email_account_id`, `message_id`, `thread_id`, `direction`, `subject`, `snippet`, `sent_at`, `received_at`, `body_ref`, `body_cached_at`, `size_bytes`, `sync_id`, `is_archived` | Body references storage object path when cached. |
| `email_participants` | `id`, `email_id`, `type` (sender/to/cc/bcc), `address`, `name` | `address` indexed for search. |
| `email_attachments` | `id`, `email_id`, `filename`, `mime_type`, `size_bytes`, `storage_ref`, `status` (pending/downloaded/skipped) | Metadata stored always. |
| `projects` | `id`, `deal_name`, `product_name`, `marketing_manager_id`, `deal_owner_id`, `deal_status_id`, `closed_lost_reason`, `estimated_value`, `notes`, `created_by`, `updated_by` | `deal_status_id` references lookup. |
| `deal_statuses` | `id`, `name`, `is_default`, `is_terminal` | Admin-manageable list. |
| `project_email` | `project_id`, `email_id`, `linked_by`, `linked_at` | Composite PK. |
| `contacts` | `id`, `name`, `email` (unique), `phone`, `notes`, `tags` (jsonb), `created_from_email_id` | JSONB tags for simple filters. |
| `contact_project` | `contact_id`, `project_id`, `role` | Many-to-many linking. |
| `activity_logs` | `id`, `actor_id`, `entity_type`, `entity_id`, `action`, `payload`, `created_at` | Includes email import, status changes, etc. |
| `jobs` | Laravel queue table storing serialized jobs for database driver. |
| `mailbox_locks` | `email_account_id`, `locked_until` | Prevent overlapping sync for same mailbox. |
| `archived_email_bodies` | `email_id`, `storage_ref`, `archived_at` | For optional cold storage recall. |

Indexes: `emails (email_account_id, received_at)`, `emails (project_flag bool)`, `email_participants (address)`, `projects (deal_status_id, updated_at)`, `contacts (email)`.

## 5. API Contracts (REST)
> All endpoints prefixed `/api/v1`. Responses JSON. Pagination `page`+`per_page`.

- **Auth**
  - `POST /auth/login` → JWT tokens.
  - `POST /auth/logout`
  - `POST /auth/invite` (Admin) → send invite email.
  - `POST /auth/accept-invite` → set password.
- **Email Accounts**
  - `GET /email-accounts`
  - `POST /email-accounts` (validate & test connection before persist)
  - `POST /email-accounts/{id}/test`
  - `PATCH /email-accounts/{id}` (enable/disable)
- **Inbox**
  - `GET /emails` (filters: account_id, project_id, date range, search text)
  - `GET /emails/{id}` (returns metadata + signed URL for body if cached)
  - `POST /emails/{id}/fetch-body` (trigger immediate fetch job)
  - `POST /emails/{id}/reply`, `/forward`
- **Projects**
  - `GET /projects`, `GET /projects/{id}`
  - `POST /projects` (manual create)
  - `POST /projects/from-email/{email_id}`
  - `POST /projects/{id}/emails` (link existing email)
  - `PATCH /projects/{id}`
- **Contacts**
  - `GET /contacts`, `GET /contacts/{id}`
  - `PATCH /contacts/{id}`
- **Dashboards**
  - `GET /dashboards/summary` (counts, top contacts, latest emails)
- **Exports**
  - `POST /exports/projects`, `POST /exports/contacts` (returns job id)
  - `GET /exports/{job_id}` (download URL)
- **Admin / Status**
  - `GET /system/health` (queue backlog, sync errors)
  - `GET /sync/logs?email_account_id=`

Webhook-style endpoints (internal):
- `POST /system/cron-run` → protected by HMAC; invoked by Supabase schedule every 5 minutes to execute `php artisan schedule:run`.

## 6. Background Jobs & Scheduling
| Job | Trigger | Description |
|-----|---------|-------------|
| `SyncMailboxJob` | Every 15 min per active account | Fetch new metadata, update statuses. |
| `FetchBodyJob` | On-demand (email open) | Retrieve body & attachments, sanitize, store reference, update `body_cached_at`. |
| `SendEmailJob` | On compose send | Offload SMTP send and update `emails` row once provider confirms. |
| `ArchiveEmailBodiesJob` | Monthly | Remove cached bodies older than threshold; record in `archived_email_bodies`. |
| `ExportJob` | On CSV request | Query dataset, stream to CSV, upload to Supabase Storage. |
| `SyncHealthCheckJob` | Hourly | Evaluate accounts with repeated failures, raise alerts/notifications. |

Supabase pg_cron schedule examples:
```
- every 5 min → POST /system/cron-run
- daily 02:00 UTC → artisan emails:archive (ArchiveEmailBodiesJob)
- hourly → artisan sync:health-check
```

## 7. Security, Compliance & Observability
- TLS for all API endpoints; SMTP/IMAP requires STARTTLS or SSL.
- Secrets: stored in Supabase Vault or environment variables; credentials encrypted per-row.
- Logging: Laravel Monolog to Supabase Log Drain (or Logflare). Retain for 30 days.
- Audit logs in `activity_logs` for: email imports, deal status changes, account configuration, data exports.
- Rate limiting: throttle login and compose endpoints.
- Backups: Supabase automated snapshots daily + point-in-time recovery.
- Monitoring: health endpoint consumed by uptime monitor; queue length metrics via `/system/health`.

## 8. Implementation Roadmap
### Phase 1 – Foundations (3 weeks)
1. Repo setup, CI/CD, Supabase Postgres schema migration, auth & RBAC scaffolding.
2. Email account management UI/API with connection testing.
3. Basic inbox list reading metadata from mocked/seeded data.

### Phase 2 – Email Sync & Projects (4 weeks)
1. Implement IMAP sync job pipeline (metadata only) with Supabase cron + database queue.
2. Email view, fetch-body job, and SMTP send-as.
3. Projects & contacts CRUD with manual import flow; activity logging.

### Phase 3 – Dashboards, Reporting & Hardening (3 weeks)
1. Dashboards summary widgets + CSV exports.
2. Archival job + restore flow.
3. Observability enhancements, health checks, admin screens, UAT + performance tuning for 200 accounts.

## 9. Risks & Mitigations
- **IMAP providers throttling** → stagger sync start times and respect per-provider limits.
- **Metadata-only storage may break if provider deletes email** → warn users when body retrieval fails; optional cache retention extension per project.
- **Database queue contention** → implement `mailbox_locks` and tune worker concurrency; monitor `jobs` table growth.
- **Attachment size** → set policy (e.g., 20 MB) and provide UI messaging; fallback to direct provider download when larger.

## 10. Next Steps
1. Finalize Supabase project configuration (network egress to IMAP/SMTP providers, Cron scheduling endpoints).
2. Approve DB schema & migration order.
3. Begin Phase 1 sprint planning and create GitHub issues aligned with roadmap.

