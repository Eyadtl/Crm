# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Arabia Talents CRM** is a full-stack email-centric CRM system designed to centralize 200+ IMAP email accounts with automated synchronization. The system provides project (deal) management, contact tracking, and searchable email communication linked to business opportunities.

**Technology Stack:**
- **Backend**: Laravel 11 (PHP 8.2+) REST API with Sanctum authentication
- **Frontend**: React 19 SPA with TypeScript, Vite, React Router, TanStack Query
- **Database**: PostgreSQL 15+ (Supabase or local) with UUID primary keys
- **Storage**: Supabase Storage for cached email bodies and attachments
- **Queue**: Laravel database queue driver (no Redis required)
- **Email**: PHP IMAP (webklex/php-imap) for fetching, Symfony Mailer for sending
- **Scheduler**: Supabase pg_cron triggering Laravel scheduler via HTTP webhook

## Development Commands

### Backend Setup & Development

**Initial Setup:**
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

**Development Server:**
```bash
php artisan serve                          # Start dev server on port 8000
php artisan queue:work database --queue=default,emails,exports  # Start queue worker
composer dev                               # Concurrent server + queue + logs (if configured)
```

**Testing & Code Quality:**
```bash
php artisan test                           # Run PHPUnit tests
composer lint                              # Run Laravel Pint (PSR-12 code style)
```

**Queue Management:**
```bash
php artisan queue:work database --queue=emails --tries=1
php artisan queue:failed                   # View failed jobs
php artisan queue:retry all                # Retry all failed jobs
php artisan queue:flush                    # Clear all failed jobs
```

**Custom Artisan Commands:**
```bash
php artisan mailboxes:dispatch-sync --limit=100   # Manually trigger sync job dispatch
php artisan sync:health-check                     # Check for failing email accounts
php artisan emails:archive                        # Archive old email bodies
```

### Frontend Setup & Development

**Initial Setup:**
```bash
cd frontend
npm install
```

**Development:**
```bash
npm run dev                                # Start Vite dev server on port 5173
```

**Testing & Build:**
```bash
npm run lint                               # Run ESLint
npm run test                               # Run Vitest tests
npm run build                              # Production build
npm run preview                            # Preview production build
```

## High-Level Architecture

### Metadata-First Email Storage Pattern

This system uses a **metadata-first architecture** to handle large-scale email synchronization efficiently:

1. **Scheduled Sync (Every 15 minutes)**:
   - `DispatchMailboxSyncCommand` queues `SyncMailboxJob` for each active account
   - `MailboxSyncService::sync()` fetches up to 200 messages (metadata only)
   - Creates `Email`, `EmailParticipant`, and `EmailAttachment` records
   - Updates `last_synced_uid` and `sync_state`
   - Acquires distributed lock via `mailbox_locks` table (5-minute TTL)

2. **On-Demand Body Fetch**:
   - When user opens email, frontend checks if `body_ref` exists and is within TTL (7 days)
   - If stale/missing, dispatches `FetchBodyJob`
   - `EmailBodyService::fetchAndCache()` retrieves body, sanitizes HTML, stores in Supabase Storage
   - Attachments downloaded if under 20MB limit

3. **Outbound Email Flow**:
   - `OutboundMailService::send()` builds Symfony MimeEmail and sends via SMTP
   - Records outbound email with `direction=outgoing`
   - Links to project if `project_id` provided

### Service Layer Architecture

The backend uses a service-oriented pattern for business logic:

**MailboxSyncService** (backend/app/Services/MailboxSyncService.php)
- Core IMAP sync orchestration with distributed locking
- Error handling: 3 retries with exponential backoff
- Updates sync state: `idle`, `queued`, `syncing`, `warning`, `error`

**EmailBodyService** (backend/app/Services/EmailBodyService.php)
- Lazy body fetching and caching to storage
- HTML sanitization: strips `<script>` tags and event handlers
- Manages body TTL and cache invalidation

**OutboundMailService** (backend/app/Services/OutboundMailService.php)
- SMTP sending with attachment support
- Records sent emails and links to projects

**MailboxConnectionManager** (backend/app/Services/MailboxConnectionManager.php)
- IMAP/SMTP client factory
- Decrypts credentials using Laravel's `Crypt` facade
- Manages TLS/SSL connections

**SystemHealthService** (backend/app/Services/SystemHealthService.php)
- Monitors queue backlog and failing accounts
- Provides health metrics for monitoring

### Queue Architecture

**Queue Topology:**
- **Driver**: Database (no Redis dependency)
- **Queues**: `default`, `emails`, `exports`
- **Worker Command**: `php artisan queue:work database --queue=emails --tries=1`

**Job Types:**
- `SyncMailboxJob` - IMAP metadata sync (emails queue, 3 retries)
- `FetchBodyJob` - On-demand body fetch (emails queue, 2 retries)
- `SendEmailJob` - Outbound SMTP sending (emails queue)
- `ArchiveEmailBodiesJob` - Monthly cleanup (default queue)
- `ExportJob` - CSV generation (exports queue)
- `SyncHealthCheckJob` - Health monitoring (default queue)

### Database Design Patterns

**UUID Primary Keys:**
- All tables use `gen_random_uuid()` instead of auto-increment
- UUIDs generated in migrations via `DEFAULT gen_random_uuid()`
- Foreign keys reference UUIDs

**Raw SQL Migrations:**
- All migrations use `DB::unprepared()` for PostgreSQL-specific features
- CHECK constraints for enum-like validations
- JSONB columns for flexible data (`encrypted_credentials`, `tags`, `context`)
- Composite indexes for query optimization

**Key Tables:**
- `email_accounts` - IMAP/SMTP configurations with encrypted credentials
- `emails` - Email metadata with body references
- `email_participants` - Sender/to/cc/bcc with participant type
- `email_attachments` - Attachment metadata with storage references
- `projects` - CRM deals with pipeline status
- `contacts` - Contact directory with JSONB tags
- `mailbox_locks` - Distributed sync locking
- `sync_logs` - Audit trail for sync events

### Frontend Architecture

**API Client Pattern** (frontend/src/api/):
- Centralized Axios instance with auth interceptor
- Modular API clients per resource (auth, emails, projects, etc.)
- Type-safe request/response interfaces

**State Management:**
- **Server State**: TanStack Query (React Query) for all API data
- **Auth State**: React Context (`AuthProvider`)
- **No Global State**: No Redux/Zustand, keeps it simple

**Route Protection:**
- `RequireAuth` component wraps authenticated routes
- Redirects to `/login` if no token in localStorage

**React Query Configuration:**
- Default stale time: 30 seconds
- No automatic refetch on window focus
- Manual refetch via query invalidation

## Key Patterns & Conventions

### Code Conventions
- **PSR-12** coding style (enforced by Laravel Pint)
- **Strict typing**: All PHP files use typed parameters and return types
- **UUID everywhere**: No auto-increment IDs in any table
- **Service injection**: Controllers receive services via constructor DI
- **Request validation**: Dedicated `FormRequest` classes for all POST/PATCH endpoints

### Security Patterns
- **Encrypted credentials**: IMAP/SMTP passwords encrypted at rest using Laravel `Crypt`
- **Cron webhook protection**: HMAC signature via `VerifyCronSignature` middleware
- **HTML sanitization**: Removes scripts and event handlers from email bodies
- **TLS/SSL**: All IMAP/SMTP connections use encryption
- **Rate limiting**: Login endpoint throttled (10 attempts per minute)

### Configuration Management
- **Environment-driven**: All settings in `.env` (see `.env.example`)
- **Config files**: `config/mailboxes.php` centralizes email sync settings
- **Feature flags**: `MAILBOX_FAKE_SYNC=true` for testing without real IMAP
- **Secrets**: Never commit `.env`, use encryption for stored credentials

### Error Handling
- **Job retries**: Automatic with exponential backoff (60s, 300s, 900s)
- **Sync state tracking**: Accounts marked `warning` after 1 failure, `error` after 3
- **Audit logging**: All sync events logged to `sync_logs` table
- **User-facing errors**: HTTP status codes + JSON error messages with clear context

## Critical Configuration

### Backend Environment Variables (.env)

**Database:**
- `DB_CONNECTION=pgsql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

**Supabase Storage:**
- `SUPABASE_STORAGE_URL` - Storage API endpoint
- `SUPABASE_STORAGE_BUCKET` - Bucket name for email bodies/attachments
- `SUPABASE_STORAGE_KEY` - Service role key with read/write permissions
- `SUPABASE_STORAGE_SIGNING_KEY` - For pre-signed URLs

**Email Sync Settings:**
- `EMAIL_HOT_STORAGE_MONTHS=6` - Body retention before archival
- `IMAP_BODY_TTL_DAYS=7` - Body cache TTL
- `IMAP_MAX_PER_SYNC=200` - Max messages per sync cycle
- `IMAP_TIMEOUT=60` - Connection timeout in seconds
- `IMAP_VALIDATE_CERT=true` - SSL certificate validation (always true in production)

**Scheduler:**
- `CRON_SHARED_SECRET` - HMAC key for `/system/cron-run` webhook protection

**Testing:**
- `MAILBOX_FAKE_SYNC=false` - Set to `true` to simulate sync without real IMAP

### Frontend Environment Variables (.env)

**API Configuration:**
- `VITE_API_URL` - Backend API base URL (e.g., `http://localhost:8000/api/v1`)

### Default Configuration Values

From `config/mailboxes.php`:
- Sync interval: 15 minutes per account
- Max messages per sync: 200
- Max attachment size: 20MB
- Body cache TTL: 7 days
- Hot storage retention: 6 months
- IMAP/SMTP timeout: 60 seconds

## Important Architectural Decisions

### Metadata-First Email Storage
**Decision**: Store only email headers in database, lazy-load bodies to object storage.

**Rationale**: Reduces database size, enables faster sync cycles, respects provider retention policies.

**Trade-off**: Body retrieval may fail if email provider deletes the message.

**Implementation**: `Email` model has `body_ref` pointing to storage path, `body_cached_at` for TTL tracking.

### Database Queue (No Redis)
**Decision**: Use Laravel database queue driver instead of Redis.

**Rationale**: Simplifies infrastructure, sufficient for 200 accounts, easier deployment.

**Scale limit**: Monitor `jobs` table growth, consider Redis if exceeding 10k jobs/day.

### Distributed Locking via Database
**Decision**: Use `mailbox_locks` table instead of Redis-based locks.

**Rationale**: Consistency with queue driver choice, simple implementation.

**Limitation**: Not suitable for high-concurrency scenarios (200 accounts is acceptable).

**Implementation**: 5-minute lock TTL, automatic cleanup via `created_at < NOW() - INTERVAL '5 minutes'`.

### UUID Primary Keys
**Decision**: All tables use UUIDs instead of auto-increment integers.

**Rationale**: Distributed-friendly, no collision risk, easier for multi-tenant architecture later.

**Trade-off**: Slightly larger indexes, non-sequential (mitigated by created_at sorting).

### No OAuth2 in V1
**Decision**: Only support IMAP/SMTP app passwords, no OAuth2 flows.

**Rationale**: Reduces complexity, sufficient for initial deployment with Google/Outlook app passwords.

**Future**: OAuth2 can be added via `auth_type` enum and additional credential fields.

### Manual Project Linking Only
**Decision**: No automatic email-to-project routing rules in V1.

**Rationale**: Ensures data quality, reduces complexity, user maintains control.

**Future**: Rule engine can be added with pattern matching on subject/sender/domain.

## Common Development Tasks

### Adding a New Email Account via API

```bash
# Test IMAP/SMTP connectivity first
curl -X POST http://localhost:8000/api/v1/email-accounts/test \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "imap_host": "imap.gmail.com",
    "imap_port": 993,
    "imap_username": "user@example.com",
    "imap_password": "app-password",
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "smtp_username": "user@example.com",
    "smtp_password": "app-password"
  }'

# If test passes, create the account
curl -X POST http://localhost:8000/api/v1/email-accounts \
  -H "Authorization: Bearer $TOKEN" \
  -d '{ ... }'
```

### Manually Triggering a Sync

```bash
# Dispatch sync jobs for all active accounts
php artisan mailboxes:dispatch-sync

# Watch queue worker process jobs
php artisan queue:work database --queue=emails --verbose

# Check sync logs
php artisan tinker
>>> SyncLog::latest()->limit(10)->get();
```

### Debugging Failed Jobs

```bash
# List failed jobs
php artisan queue:failed

# Inspect specific job
php artisan queue:failed | grep SyncMailboxJob

# Retry specific job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Clear failed jobs (use with caution)
php artisan queue:flush
```

### Working with Encrypted Credentials

```php
use Illuminate\Support\Facades\Crypt;

// Encrypt credentials (done automatically in EmailAccount model)
$encrypted = Crypt::encryptString($password);

// Decrypt credentials (done in MailboxConnectionManager)
$decrypted = Crypt::decryptString($encrypted);

// Full credential object
$credentials = json_decode($emailAccount->encrypted_credentials, true);
$imapPassword = Crypt::decryptString($credentials['imap_password']);
```

### Testing Email Body Sanitization

The `EmailBodyService` sanitizes HTML to prevent XSS:

```php
use App\Services\EmailBodyService;

$service = app(EmailBodyService::class);

// Test sanitization
$dirty = '<p>Hello</p><script>alert("xss")</script><a onclick="evil()">Click</a>';
$clean = $service->sanitizeHtml($dirty);
// Result: '<p>Hello</p><a>Click</a>'
```

### Running Queue Workers in Production

Deploy separate worker processes for each queue:

```bash
# Worker 1: Email queue (high priority)
php artisan queue:work database --queue=emails --tries=1 --timeout=300

# Worker 2: Default queue (maintenance tasks)
php artisan queue:work database --queue=default --tries=3 --timeout=600

# Worker 3: Exports queue (CPU-intensive)
php artisan queue:work database --queue=exports --tries=2 --timeout=900
```

Use a process manager like `supervisord` to keep workers running:

```ini
[program:laravel-queue-emails]
command=php /path/to/artisan queue:work database --queue=emails --tries=1 --timeout=300
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/laravel-queue-emails.log
```

## Monitoring & Observability

### Health Check Endpoint

Monitor application health via `GET /api/v1/system/health`:

```json
{
  "queue_backlog": {
    "default": 10,
    "emails": 5,
    "exports": 0
  },
  "failing_accounts": [
    {
      "email_account_id": "uuid",
      "sync_state": "error",
      "sync_error": "Authentication failed"
    }
  ],
  "database_connections": 15,
  "uptime_seconds": 86400
}
```

**Alert Thresholds:**
- Queue backlog > 1000 jobs
- Failing accounts > 0
- Database connections > 80% of max

### Sync Logs

Query sync history for troubleshooting:

```php
use App\Models\SyncLog;

// Recent sync activity
SyncLog::with('emailAccount')
    ->where('status', 'error')
    ->latest()
    ->limit(20)
    ->get();

// Account-specific history
SyncLog::where('email_account_id', $accountId)
    ->orderBy('created_at', 'desc')
    ->paginate(50);
```

### Activity Logs

Track user actions and system events:

```php
use App\Models\ActivityLog;

// Recent user activity
ActivityLog::with('user')
    ->where('action_type', 'project_created')
    ->latest()
    ->limit(20)
    ->get();
```

## Deployment Considerations

### Pre-Deployment Checklist

1. **Environment Configuration**:
   - Set `APP_ENV=production` and `APP_DEBUG=false`
   - Generate new `APP_KEY` with `php artisan key:generate`
   - Configure production database credentials
   - Set `CRON_SHARED_SECRET` to secure random value

2. **Security**:
   - Enable SSL/TLS: `IMAP_VALIDATE_CERT=true`, `SMTP_VALIDATE_CERT=true`
   - Configure CORS for frontend domain only
   - Enable rate limiting on all API routes
   - Review encrypted credentials migration

3. **Storage Setup**:
   - Create Supabase Storage bucket with appropriate permissions
   - Configure service key with read/write access
   - Set signing key for pre-signed URLs
   - Test storage connectivity

4. **Queue Infrastructure**:
   - Deploy worker processes/containers for each queue
   - Configure process manager (supervisord, systemd)
   - Set up queue monitoring and alerting

5. **Scheduler Setup**:
   - Configure Supabase pg_cron or equivalent
   - Schedule: `*/5 * * * *` (every 5 minutes)
   - Endpoint: `POST https://api.example.com/api/v1/system/cron-run`
   - Header: `X-Internal-Signature: {HMAC_SHA256}`

### Performance Optimization

**Database:**
- Ensure indexes exist on `emails(email_account_id, received_at)`
- Monitor `mailbox_locks` contention (should be minimal)
- Archive old `sync_logs` (retention: 30 days)

**Queue:**
- Monitor queue depth per queue
- Scale workers if backlog consistently > 100 jobs
- Use `php artisan queue:restart` for zero-downtime worker updates

**Storage:**
- Configure CDN for cached email bodies if needed
- Monitor storage bucket size growth
- Implement archival strategy after 6 months

## Testing Strategy

### Backend Testing

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test --filter AuthTest

# Run with coverage (requires Xdebug)
php artisan test --coverage
```

**Test Database**: Uses SQLite in-memory for speed.

**Key Test Files**:
- `tests/Feature/AuthTest.php` - Authentication flows
- `tests/Unit/` - Service and model unit tests

### Frontend Testing

```bash
# Run all tests
npm run test

# Run in watch mode (development)
npm run test -- --watch

# Run with coverage
npm run test -- --coverage
```

**Test Environment**: Vitest with jsdom for DOM emulation.

### Manual Testing Workflows

**Email Account Setup:**
1. Create Gmail/Outlook app password
2. Test connectivity via API
3. Create account
4. Verify sync starts within 5 minutes
5. Check email list populates

**Email Body Fetch:**
1. Open email in frontend
2. Verify body loads within 5 seconds
3. Check storage bucket for cached body
4. Verify TTL updates on `body_cached_at`

**Project Linking:**
1. Create project from email
2. Verify email appears in project's email list
3. Link additional emails to same project
4. Verify contact auto-creation from participants

## Common Issues & Solutions

### Sync Jobs Not Processing

**Symptoms**: Emails not appearing, `sync_state` stuck on `queued`.

**Diagnosis**:
```bash
# Check queue worker status
ps aux | grep queue:work

# Check jobs table
php artisan tinker
>>> DB::table('jobs')->count();
```

**Solution**:
- Ensure queue worker is running: `php artisan queue:work database --queue=emails`
- Check worker logs for errors
- Restart worker: `php artisan queue:restart`

### IMAP Connection Failures

**Symptoms**: Account stuck in `error` state, sync logs show "Connection refused".

**Diagnosis**:
```bash
# Test connectivity
php artisan tinker
>>> $service = app(\App\Services\EmailConnectivityService::class);
>>> $service->testImap($emailAccount);
```

**Solutions**:
- Verify IMAP credentials and app password
- Check firewall/network restrictions
- Confirm IMAP is enabled in email provider settings
- Validate `IMAP_VALIDATE_CERT` setting (set to `false` for self-signed certs in dev only)

### Email Bodies Not Caching

**Symptoms**: Body fetch jobs fail, no files in storage bucket.

**Diagnosis**:
```bash
# Check storage configuration
php artisan tinker
>>> Storage::disk('supabase')->exists('test.txt');
>>> Storage::disk('supabase')->put('test.txt', 'test content');
```

**Solutions**:
- Verify Supabase Storage credentials in `.env`
- Check bucket permissions (service key needs read/write)
- Review `config/filesystems.php` configuration
- Test storage connectivity

### Mailbox Locks Not Releasing

**Symptoms**: Account stuck in `syncing` state indefinitely.

**Diagnosis**:
```bash
php artisan tinker
>>> \App\Models\MailboxLock::all();
```

**Solutions**:
- Locks auto-expire after 5 minutes
- Manually release: `MailboxLock::where('email_account_id', $id)->delete();`
- Check for crashed queue workers (they should release locks on shutdown)

### Frontend API Authentication Failures

**Symptoms**: 401 errors on all API requests after login.

**Diagnosis**:
- Check browser localStorage for `crm.user` and `crm.token`
- Verify token in request headers: `Authorization: Bearer {token}`

**Solutions**:
- Clear localStorage and re-login
- Verify backend `SANCTUM_STATEFUL_DOMAINS` includes frontend domain
- Check CORS configuration in `config/cors.php`

## Additional Documentation

- **README.md**: Quick start guide and project overview
- **TECHNICAL_SPEC.md**: Comprehensive technical requirements and specifications
- **docs/deployment-guide.md**: Detailed deployment instructions
- **docs/job-orchestration.md**: Queue and scheduler architecture
- **api/openapi.yaml**: OpenAPI specification for REST API
