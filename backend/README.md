# Arabia Talents CRM – Laravel API

This directory houses the Laravel 11 API that powers the Arabia Talents CRM.
It implements authentication/RBAC, IMAP sync pipelines, SMTP send-as flows,
projects/contacts CRUD, dashboards, exports, and scheduled jobs that run via
Supabase cron hitting the `/system/cron-run` webhook.

## Requirements

- PHP 8.2+
- Composer 2.7+
- PostgreSQL 15+ (Supabase-hosted for production, local Postgres for dev)
- Node 20+ (only for asset bundling / Vite if needed)
- ext-imap, ext-json, ext-pcntl (for queue workers)

## Getting Started

```bash
cd backend
cp .env.example .env
# update .env with Supabase credentials + cron shared secret
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Queue workers and the scheduler should run in separate processes:

```bash
php artisan queue:work database --queue=default,emails,exports --tries=1
php artisan schedule:work
```

## Cron / Supabase Integration
- Supabase pg_cron or Edge Functions should `POST /api/v1/system/cron-run`
  every 5 minutes with the `X-Internal-Signature` header that matches
  `CRON_SHARED_SECRET`.
- The Laravel scheduler dispatches `SyncMailboxJob`, `ArchiveEmailBodiesJob`,
  `ExportJob`, and `SyncHealthCheckJob` according to the technical spec. See
  `docs/jobs_and_cron.md` (repo root) for more context.

## Code Map

| Path | Description |
|------|-------------|
| `app/Http/Controllers/API` | Versioned REST controllers for auth, inbox, projects, contacts, exports, dashboards, admin |
| `app/Models` | Eloquent models mirroring the Supabase schema |
| `app/Jobs` | Queue jobs (`SyncMailboxJob`, `FetchBodyJob`, `SendEmailJob`, etc.) |
| `app/Services` | Domain services for IMAP sync, SMTP sending, export streaming |
| `database/migrations` | Postgres schema aligned with `supabase/schema.sql` |
| `routes/api.php` | `/api/v1` endpoints + middleware stack |

## Testing

```bash
php artisan test
```

The provided Pest/PHPUnit suites include feature tests for the auth flow,
project CRUD, and health endpoints. Add IMAP/SMTP fakes when extending tests.

## Linting & Formatting

- `composer lint`: runs Laravel Pint
- `composer test`: runs Pest

## Deployment Notes

- Containers should inject Supabase Postgres and Storage credentials plus the
  Cron shared secret.
- Horizon/Octane are optional; the current setup uses standard
  `php artisan queue:work`.
- For production, configure mail to hit Symfony Mailer-compatible SMTP (per
  mailbox) and ensure outbound IMAP/SMTP is allowed from the runtime.

