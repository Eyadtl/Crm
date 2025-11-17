<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('
            CREATE TABLE emails (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                email_account_id UUID NOT NULL REFERENCES email_accounts(id) ON DELETE CASCADE,
                message_id VARCHAR(255) NOT NULL,
                thread_id VARCHAR(255) NULL,
                direction VARCHAR(255) NOT NULL DEFAULT \'incoming\' CHECK (direction IN (\'incoming\', \'outgoing\')),
                subject VARCHAR(255) NULL,
                snippet TEXT NULL,
                sent_at TIMESTAMP NULL,
                received_at TIMESTAMP NULL,
                body_ref VARCHAR(255) NULL,
                body_cached_at TIMESTAMP NULL,
                size_bytes BIGINT NULL,
                sync_id VARCHAR(255) NULL,
                is_archived BOOLEAN NOT NULL DEFAULT false,
                project_flag BOOLEAN NOT NULL DEFAULT false,
                has_attachments BOOLEAN NOT NULL DEFAULT false,
                body_checksum VARCHAR(255) NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE (email_account_id, message_id)
            );
            CREATE INDEX emails_email_account_id_received_at_index ON emails(email_account_id, received_at);
            CREATE INDEX emails_project_flag_index ON emails(project_flag);
            CREATE INDEX emails_thread_id_index ON emails(thread_id);
            
            CREATE TABLE email_participants (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                email_id UUID NOT NULL REFERENCES emails(id) ON DELETE CASCADE,
                type VARCHAR(255) NOT NULL CHECK (type IN (\'sender\', \'to\', \'cc\', \'bcc\')),
                address VARCHAR(255) NOT NULL,
                name VARCHAR(255) NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );
            CREATE INDEX email_participants_address_index ON email_participants(address);
            
            CREATE TABLE email_attachments (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                email_id UUID NOT NULL REFERENCES emails(id) ON DELETE CASCADE,
                filename VARCHAR(255) NOT NULL,
                mime_type VARCHAR(255) NULL,
                size_bytes BIGINT NULL,
                storage_ref VARCHAR(255) NULL,
                status VARCHAR(255) NOT NULL DEFAULT \'pending\' CHECK (status IN (\'pending\', \'downloaded\', \'skipped\')),
                downloaded_at TIMESTAMP NULL,
                checksum VARCHAR(255) NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE (email_id, filename)
            );
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS email_attachments CASCADE');
        DB::statement('DROP TABLE IF EXISTS email_participants CASCADE');
        DB::statement('DROP TABLE IF EXISTS emails CASCADE');
    }
};
