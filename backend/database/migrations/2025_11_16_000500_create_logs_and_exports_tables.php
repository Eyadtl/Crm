<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('
            CREATE TABLE activity_logs (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                actor_id UUID NULL REFERENCES users(id) ON DELETE SET NULL,
                entity_type VARCHAR(255) NOT NULL,
                entity_id UUID NULL,
                action VARCHAR(255) NOT NULL,
                payload JSONB NOT NULL DEFAULT \'{}\',
                ip_address VARCHAR(255) NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );
            CREATE INDEX activity_logs_entity_type_entity_id_index ON activity_logs(entity_type, entity_id);
            
            CREATE TABLE archived_email_bodies (
                email_id UUID PRIMARY KEY REFERENCES emails(id) ON DELETE CASCADE,
                storage_ref VARCHAR(255) NOT NULL,
                archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                restored_at TIMESTAMP NULL,
                notes TEXT NULL
            );
            
            CREATE TABLE data_exports (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                type VARCHAR(255) NOT NULL,
                filters JSONB NOT NULL DEFAULT \'{}\',
                requested_by UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                job_id BIGINT NULL,
                status VARCHAR(255) NOT NULL DEFAULT \'queued\' CHECK (status IN (\'queued\', \'running\', \'ready\', \'failed\')),
                storage_ref VARCHAR(255) NULL,
                download_url VARCHAR(255) NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );
            CREATE INDEX data_exports_status_created_at_index ON data_exports(status, created_at);
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS data_exports CASCADE');
        DB::statement('DROP TABLE IF EXISTS archived_email_bodies CASCADE');
        DB::statement('DROP TABLE IF EXISTS activity_logs CASCADE');
    }
};
