<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('
            CREATE TABLE email_accounts (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                email VARCHAR(255) UNIQUE NOT NULL,
                display_name VARCHAR(255) NULL,
                imap_host VARCHAR(255) NOT NULL,
                imap_port SMALLINT NOT NULL DEFAULT 993,
                smtp_host VARCHAR(255) NOT NULL,
                smtp_port SMALLINT NOT NULL DEFAULT 587,
                security_type VARCHAR(255) NOT NULL DEFAULT \'ssl\',
                auth_type VARCHAR(255) NOT NULL DEFAULT \'password\',
                encrypted_credentials JSONB NOT NULL,
                status VARCHAR(255) NOT NULL DEFAULT \'active\' CHECK (status IN (\'active\', \'disabled\')),
                last_synced_uid BIGINT NOT NULL DEFAULT 0,
                last_synced_at TIMESTAMP NULL,
                sync_state VARCHAR(255) NOT NULL DEFAULT \'idle\' CHECK (sync_state IN (\'idle\', \'queued\', \'syncing\', \'warning\', \'error\')),
                sync_error TEXT NULL,
                sync_interval_minutes INTEGER NOT NULL DEFAULT 15,
                retry_count SMALLINT NOT NULL DEFAULT 0,
                disabled_reason TEXT NULL,
                created_by UUID NULL REFERENCES users(id) ON DELETE SET NULL,
                updated_by UUID NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );
            CREATE INDEX email_accounts_status_sync_state_index ON email_accounts(status, sync_state);
            
            CREATE TABLE mailbox_locks (
                email_account_id UUID PRIMARY KEY REFERENCES email_accounts(id) ON DELETE CASCADE,
                lock_owner VARCHAR(255) NOT NULL,
                locked_until TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE sync_logs (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                email_account_id UUID NULL REFERENCES email_accounts(id) ON DELETE SET NULL,
                event VARCHAR(255) NOT NULL,
                message TEXT NULL,
                context JSONB NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS sync_logs CASCADE');
        DB::statement('DROP TABLE IF EXISTS mailbox_locks CASCADE');
        DB::statement('DROP TABLE IF EXISTS email_accounts CASCADE');
    }
};
