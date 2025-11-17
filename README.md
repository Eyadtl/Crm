# Arabia Talents CRM

Full-stack implementation of the Arabia Talents CRM described in `TECHNICAL_SPEC.md`.
The workspace contains:

- `backend/` – Laravel 11 API with Sanctum auth, IMAP/SMTP scaffolding, queue jobs,
  and REST endpoints that mirror the provided OpenAPI spec.
- `frontend/` - Vite + React (TypeScript) single-page app with React Query,
  auth context, dashboard/inbox/projects/contacts/email-account admin screens.
- `supabase/schema.sql` - canonical Postgres schema aligned with Supabase.
- `docs/jobs_and_cron.md` - cron + job orchestration guide for Supabase pg_cron.
- `docs/deployment.md` - environment, Supabase, queue, and CI/CD setup guide.

## Prerequisites

- PHP 8.2+, Composer 2.7+
- PostgreSQL 15+ (Supabase or local)
- Node.js 20+ and npm 10+
- ext-imap, ext-pcntl, ext-json enabled for PHP workers

## Backend Setup

```bash
cd backend
cp .env.example .env    # fill DB + Supabase credentials + CRON_SHARED_SECRET
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve        # http://localhost:8000
php artisan queue:work database --queue=default,emails,exports
```

Run tests and static analysis:

```bash
php artisan test
composer lint
```

The scheduler is triggered by Supabase cron calling
`POST /api/v1/system/cron-run` with the `X-Internal-Signature` header defined in
`CRON_SHARED_SECRET`. See `docs/jobs_and_cron.md` for queue architecture and schedules.

## Frontend Setup

```bash
cd frontend
npm install
npm run dev            # http://localhost:5173
```

Set `VITE_API_URL` in `frontend/.env` if the API is hosted elsewhere.

## Deploy / Ops Notes

- Provision Supabase Postgres using `supabase/schema.sql` or Laravel migrations.
- Configure Supabase Storage bucket for cached email bodies (`SUPABASE_STORAGE_BUCKET`).
- Ensure outbound IMAP/SMTP egress is enabled for the Laravel containers.
- Schedule pg_cron or Edge Functions to hit `/api/v1/system/cron-run` every 5 minutes
  and additional cron windows (monthly archival, hourly health check).
- Queue workers should watch `default`, `emails`, and `exports` queues separately.

For more operational detail (locks, retries, SyncMailboxJob flow, exports),
reference `docs/jobs_and_cron.md`.
