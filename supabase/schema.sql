-- Supabase / Postgres schema for Arabia Talents CRM
-- Generated from TECHNICAL_SPEC.md

CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS citext;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_status') THEN
        CREATE TYPE user_status AS ENUM ('invited', 'active', 'disabled');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'email_account_status') THEN
        CREATE TYPE email_account_status AS ENUM ('active', 'disabled');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'sync_state') THEN
        CREATE TYPE sync_state AS ENUM ('idle', 'queued', 'syncing', 'warning', 'error');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'email_direction') THEN
        CREATE TYPE email_direction AS ENUM ('incoming', 'outgoing');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'participant_type') THEN
        CREATE TYPE participant_type AS ENUM ('sender', 'to', 'cc', 'bcc');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'attachment_status') THEN
        CREATE TYPE attachment_status AS ENUM ('pending', 'downloaded', 'skipped');
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS roles (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    slug        text NOT NULL UNIQUE,
    name        text NOT NULL,
    description text,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS users (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name           text NOT NULL,
    email          citext NOT NULL UNIQUE,
    password_hash  text NOT NULL,
    timezone       text NOT NULL DEFAULT 'UTC',
    status         user_status NOT NULL DEFAULT 'invited',
    invited_at     timestamptz,
    last_login_at  timestamptz,
    disabled_at    timestamptz,
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS user_roles (
    user_id     uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id     uuid NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    assigned_by uuid REFERENCES users(id),
    assigned_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE IF NOT EXISTS auth_invitations (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email           citext NOT NULL UNIQUE,
    token           text NOT NULL UNIQUE,
    expires_at      timestamptz NOT NULL,
    invited_by      uuid REFERENCES users(id),
    accepted_at     timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS email_accounts (
    id                     uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email                  citext NOT NULL UNIQUE,
    display_name           text,
    imap_host              text NOT NULL,
    imap_port              integer NOT NULL DEFAULT 993,
    smtp_host              text NOT NULL,
    smtp_port              integer NOT NULL DEFAULT 587,
    security_type          text NOT NULL DEFAULT 'ssl', -- ssl / tls / starttls
    auth_type              text NOT NULL DEFAULT 'password',
    encrypted_credentials  jsonb NOT NULL,
    status                 email_account_status NOT NULL DEFAULT 'active',
    last_synced_uid        bigint DEFAULT 0,
    last_synced_at         timestamptz,
    sync_state             sync_state NOT NULL DEFAULT 'idle',
    sync_error             text,
    sync_interval_minutes  integer NOT NULL DEFAULT 15 CHECK (sync_interval_minutes > 0),
    retry_count            integer NOT NULL DEFAULT 0,
    disabled_reason        text,
    created_by             uuid REFERENCES users(id),
    updated_by             uuid REFERENCES users(id),
    created_at             timestamptz NOT NULL DEFAULT now(),
    updated_at             timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_email_accounts_status ON email_accounts(status);

CREATE TABLE IF NOT EXISTS mailbox_locks (
    email_account_id uuid PRIMARY KEY REFERENCES email_accounts(id) ON DELETE CASCADE,
    lock_owner       text NOT NULL,
    locked_until     timestamptz NOT NULL,
    updated_at       timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS emails (
    id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email_account_id  uuid NOT NULL REFERENCES email_accounts(id) ON DELETE CASCADE,
    message_id        text NOT NULL,
    thread_id         text,
    direction         email_direction NOT NULL DEFAULT 'incoming',
    subject           text,
    snippet           text,
    sent_at           timestamptz,
    received_at       timestamptz NOT NULL,
    body_ref          text,
    body_cached_at    timestamptz,
    size_bytes        bigint,
    sync_id           text,
    is_archived       boolean NOT NULL DEFAULT false,
    project_flag      boolean NOT NULL DEFAULT false,
    has_attachments   boolean NOT NULL DEFAULT false,
    body_checksum     text,
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now(),
    UNIQUE (email_account_id, message_id)
);

CREATE INDEX IF NOT EXISTS idx_emails_account_received ON emails(email_account_id, received_at);
CREATE INDEX IF NOT EXISTS idx_emails_project_flag ON emails(project_flag) WHERE project_flag = true;
CREATE INDEX IF NOT EXISTS idx_emails_thread ON emails(thread_id);

CREATE TABLE IF NOT EXISTS email_participants (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email_id      uuid NOT NULL REFERENCES emails(id) ON DELETE CASCADE,
    type          participant_type NOT NULL,
    address       citext NOT NULL,
    name          text,
    created_at    timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_email_participants_address ON email_participants(address);
CREATE INDEX IF NOT EXISTS idx_email_participants_type ON email_participants(email_id, type);

CREATE TABLE IF NOT EXISTS email_attachments (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email_id      uuid NOT NULL REFERENCES emails(id) ON DELETE CASCADE,
    filename      text NOT NULL,
    mime_type     text,
    size_bytes    bigint,
    storage_ref   text,
    status        attachment_status NOT NULL DEFAULT 'pending',
    downloaded_at timestamptz,
    checksum      text,
    created_at    timestamptz NOT NULL DEFAULT now(),
    UNIQUE (email_id, filename)
);

CREATE TABLE IF NOT EXISTS deal_statuses (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name        text NOT NULL UNIQUE,
    position    smallint NOT NULL DEFAULT 0,
    is_default  boolean NOT NULL DEFAULT false,
    is_terminal boolean NOT NULL DEFAULT false,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS projects (
    id                     uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    deal_name              text NOT NULL,
    product_name           text,
    marketing_manager_id   uuid REFERENCES users(id),
    deal_owner_id          uuid REFERENCES users(id),
    deal_status_id         uuid NOT NULL REFERENCES deal_statuses(id),
    closed_lost_reason     text,
    estimated_value        numeric(14,2),
    notes                  text,
    expected_close_date    date,
    created_by             uuid REFERENCES users(id),
    updated_by             uuid REFERENCES users(id),
    created_at             timestamptz NOT NULL DEFAULT now(),
    updated_at             timestamptz NOT NULL DEFAULT now(),
    deleted_at             timestamptz
);

CREATE INDEX IF NOT EXISTS idx_projects_status_updated ON projects(deal_status_id, updated_at DESC);
CREATE INDEX IF NOT EXISTS idx_projects_owner ON projects(deal_owner_id);

CREATE TABLE IF NOT EXISTS project_email (
    project_id uuid NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    email_id   uuid NOT NULL REFERENCES emails(id) ON DELETE CASCADE,
    linked_by  uuid REFERENCES users(id),
    linked_at  timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (project_id, email_id)
);

CREATE INDEX IF NOT EXISTS idx_project_email_email ON project_email(email_id);

CREATE TABLE IF NOT EXISTS contacts (
    id                     uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name                   text,
    email                  citext NOT NULL UNIQUE,
    phone                  text,
    notes                  text,
    tags                   jsonb NOT NULL DEFAULT '[]'::jsonb,
    created_from_email_id  uuid REFERENCES emails(id),
    created_at             timestamptz NOT NULL DEFAULT now(),
    updated_at             timestamptz NOT NULL DEFAULT now(),
    deleted_at             timestamptz
);

CREATE TABLE IF NOT EXISTS contact_project (
    contact_id uuid NOT NULL REFERENCES contacts(id) ON DELETE CASCADE,
    project_id uuid NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    role       text,
    created_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (contact_id, project_id)
);

CREATE INDEX IF NOT EXISTS idx_contact_project_project ON contact_project(project_id);

CREATE TABLE IF NOT EXISTS activity_logs (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    actor_id    uuid REFERENCES users(id),
    entity_type text NOT NULL,
    entity_id   uuid,
    action      text NOT NULL,
    payload     jsonb NOT NULL DEFAULT '{}'::jsonb,
    ip_address  inet,
    created_at  timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_activity_entity ON activity_logs(entity_type, entity_id);

CREATE TABLE IF NOT EXISTS archived_email_bodies (
    email_id     uuid PRIMARY KEY REFERENCES emails(id) ON DELETE CASCADE,
    storage_ref  text NOT NULL,
    archived_at  timestamptz NOT NULL DEFAULT now(),
    restored_at  timestamptz,
    notes        text
);

CREATE TABLE IF NOT EXISTS data_exports (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    type          text NOT NULL, -- projects or contacts
    filters       jsonb NOT NULL DEFAULT '{}'::jsonb,
    requested_by  uuid NOT NULL REFERENCES users(id),
    job_id        bigint,
    status        text NOT NULL DEFAULT 'queued', -- queued/running/ready/failed
    storage_ref   text,
    expires_at    timestamptz,
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_data_exports_status ON data_exports(status, created_at DESC);

-- Laravel queue tables (database driver)
CREATE TABLE IF NOT EXISTS jobs (
    id           bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    queue        text NOT NULL,
    payload      text NOT NULL,
    attempts     smallint NOT NULL,
    reserved_at  integer,
    available_at integer NOT NULL,
    created_at   integer NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_jobs_queue ON jobs(queue);

CREATE TABLE IF NOT EXISTS failed_jobs (
    id         bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    uuid       uuid NOT NULL,
    connection text NOT NULL,
    queue      text NOT NULL,
    payload    text NOT NULL,
    exception  text NOT NULL,
    failed_at  timestamptz NOT NULL DEFAULT now(),
    UNIQUE (uuid)
);

CREATE TABLE IF NOT EXISTS job_batches (
    id            uuid PRIMARY KEY,
    name          text NOT NULL,
    total_jobs    integer NOT NULL,
    pending_jobs  integer NOT NULL,
    failed_jobs   integer NOT NULL,
    failed_job_ids text NOT NULL,
    options       jsonb,
    cancelled_at  timestamptz,
    created_at    timestamptz,
    finished_at   timestamptz
);

-- Settings table for configurable values like email_hot_storage_months
CREATE TABLE IF NOT EXISTS app_settings (
    key         text PRIMARY KEY,
    value       jsonb NOT NULL,
    updated_by  uuid REFERENCES users(id),
    updated_at  timestamptz NOT NULL DEFAULT now()
);

INSERT INTO app_settings(key, value)
    VALUES ('email_hot_storage_months', to_jsonb(6))
ON CONFLICT (key) DO NOTHING;
