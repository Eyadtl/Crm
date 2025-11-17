<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('
            CREATE TABLE projects (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                deal_name VARCHAR(255) NOT NULL,
                product_name VARCHAR(255) NULL,
                marketing_manager_id UUID NULL REFERENCES users(id) ON DELETE SET NULL,
                deal_owner_id UUID NULL REFERENCES users(id) ON DELETE SET NULL,
                deal_status_id UUID NOT NULL REFERENCES deal_statuses(id),
                closed_lost_reason TEXT NULL,
                estimated_value DECIMAL(14, 2) NULL,
                notes TEXT NULL,
                expected_close_date DATE NULL,
                created_by UUID NULL REFERENCES users(id) ON DELETE SET NULL,
                updated_by UUID NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            );
            CREATE INDEX projects_deal_status_id_updated_at_index ON projects(deal_status_id, updated_at);
            CREATE INDEX projects_deal_owner_id_index ON projects(deal_owner_id);
            
            CREATE TABLE project_email (
                project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                email_id UUID NOT NULL REFERENCES emails(id) ON DELETE CASCADE,
                linked_by UUID NULL REFERENCES users(id) ON DELETE SET NULL,
                linked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (project_id, email_id)
            );
            
            CREATE TABLE contacts (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                name VARCHAR(255) NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                phone VARCHAR(255) NULL,
                notes TEXT NULL,
                tags JSONB NOT NULL DEFAULT \'[]\',
                created_from_email_id UUID NULL REFERENCES emails(id) ON DELETE SET NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            );
            
            CREATE TABLE contact_project (
                contact_id UUID NOT NULL REFERENCES contacts(id) ON DELETE CASCADE,
                project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                role VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (contact_id, project_id)
            );
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS contact_project CASCADE');
        DB::statement('DROP TABLE IF EXISTS contacts CASCADE');
        DB::statement('DROP TABLE IF EXISTS project_email CASCADE');
        DB::statement('DROP TABLE IF EXISTS projects CASCADE');
    }
};
