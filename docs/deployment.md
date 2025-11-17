# Deployment & Operations Guide

This document explains how to configure environments, database/storage resources, cron triggers, queue workers, and CI
tests so the Arabia Talents CRM runs reliably in Supabase-based infrastructure.

---

## 1. Supabase (or Neon) Provisioning

1. **Database**
   - Create a Postgres instance (Supabase, Neon, or self-hosted) with pgcrypto + citext extensions enabled.
   - Apply the schema via `supabase/schema.sql` or the Laravel migrations:
     ```bash
     cd backend
     php artisan migrate --seed
     ```
   - Ensure the `jobs`, `failed_jobs`, and `job_batches` tables reside in the same database to support the database queue driver.

2. **Storage**
   - Create a bucket named in `.env` as `SUPABASE_STORAGE_BUCKET` (default `email-bodies`).
   - Generate a service key (`SUPABASE_STORAGE_KEY`) with read/write permissions; store the bucket URL in `SUPABASE_STORAGE_URL`.
   - Attach a signing key (`SUPABASE_SIGNING_KEY`) if you plan to generate signed download URLs for cached bodies/attachments.

3. **Network / Secrets**
   - Allow outbound IMAP/SMTP egress for the Laravel runtime.
   - Store sensitive secrets (IMAP passwords, SMTP app passwords) in Vault or the hosting provider’s secret manager; reference them via `.env`.

---

## 2. Backend Environment Configuration

Copy `.env.example` to `.env` inside `backend/` and populate:

| Key | Purpose |
|-----|---------|
| `APP_URL`, `FRONTEND_URL` | Canonical API + SPA URLs. |
| `DB_*` | Connection string to Supabase/Neon. Set `DB_SSLMODE=require` when using managed Postgres. |
| `SUPABASE_STORAGE_*`, `SUPABASE_SIGNING_KEY` | Storage access. |
| `CRON_SHARED_SECRET` | HMAC shared secret for `/api/v1/system/cron-run`. Rotate regularly. |
| `EMAIL_HOT_STORAGE_MONTHS`, `IMAP_BODY_TTL_DAYS` | Retention policies. |
| `IMAP_*`, `SMTP_*`, `EMAIL_ATTACHMENT_DISK`, `EMAIL_BODY_DISK` | Timeouts, validation toggles, and target disks for cached objects. |
| `MAILBOX_SKIP_CONNECTIVITY`, `MAILBOX_FAKE_SYNC` | Developer toggles. Set both to `false` in production. |

Generate the Laravel key and run migrations:

```bash
cd backend
composer install
php artisan key:generate
php artisan migrate --seed
```

Frontend `.env` needs `VITE_API_URL` pointing to the API (`https://api.example.com/api/v1`).

---

## 3. Queue Workers & Cron Orchestration

1. **Queue Driver**
   - Use the database queue with the same Postgres. Enable a dedicated queue connection or leave `QUEUE_CONNECTION=database`.
   - Run workers per queue:
     ```bash
     php artisan queue:work database --queue=default --sleep=2 --tries=1
     php artisan queue:work database --queue=emails --sleep=2 --tries=1
     php artisan queue:work database --queue=exports --sleep=2 --tries=1
     ```
     Scale horizontally so `jobs` backlog stays <1k rows and oldest job age <5 minutes.

2. **Supabase Cron / Scheduler**
   - Configure pg_cron or an Edge Function that issues HTTP requests to `POST https://api.example.com/api/v1/system/cron-run`
     with header `X-Internal-Signature: <CRON_SHARED_SECRET>`.
   - Recommended schedule:
     | Schedule | Artisan Command |
     |----------|-----------------|
     | Every 5 minutes | `mailboxes:dispatch-sync` (via scheduler) |
     | Hourly | `sync:health-check` |
     | Daily 02:00 UTC | `emails:archive` |

   - The scheduler (`app/Console/Kernel.php`) already queues jobs when `php artisan schedule:run` executes.

3. **IMAP/SMTP Credentials**
   - When creating mailboxes through the UI or seeder, provide app-specific passwords. They are encrypted per row and only
     decrypted for IMAP/SMTP connections via `MailboxConnectionManager`.

---

## 4. Automated Tests

### Backend
```bash
cd backend
composer install
php artisan test
composer lint     # Laravel Pint
```
Ensure the PHP runtime has `pdo_sqlite`, `imap`, and `pcntl` extensions so tests (which default to sqlite in memory) can run.
For CI on Linux containers, install `php8.2-imap` and `php8.2-sqlite3`.

### Frontend
```bash
cd frontend
npm install
npm run lint
npm run test -- --run    # Vitest + Testing Library
npm run build
```
Vitest uses jsdom (`vitest.config.ts`) with setup in `src/tests/setup.ts`. Keep UI tests colocated under `src/**/*.test.tsx`.

---

## 5. CI/CD Checklist

1. **Backend Pipeline**
   - Install PHP extensions.
   - `composer install --no-dev` for production builds, `composer install` for CI.
   - Cache `vendor/` between runs.
   - Run `php artisan test`.
   - Build deployment artifact or Docker image (include `php artisan config:cache`, `route:cache`, etc.).

2. **Frontend Pipeline**
   - `npm ci`.
   - `npm run lint && npm run test -- --run`.
   - `npm run build` to produce the Vite bundle for Vercel/Netlify/S3.

3. **Deploy Steps**
   - Migrate database after deploying backend (`php artisan migrate --force`).
   - Restart queue workers to pick up new code.
   - Confirm Supabase cron is hitting `/system/cron-run` (monitor via logs or `SyncLog` table).

Document your chosen hosting provider’s specifics (GitHub Actions, Supabase Deploy, Render, etc.) in ops runbooks to keep
these steps tailored to your environment.
