<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('
            CREATE TABLE deal_statuses (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                name VARCHAR(255) UNIQUE NOT NULL,
                position SMALLINT NOT NULL DEFAULT 0,
                is_default BOOLEAN NOT NULL DEFAULT false,
                is_terminal BOOLEAN NOT NULL DEFAULT false,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );
            
            CREATE TABLE app_settings (
                key VARCHAR(255) PRIMARY KEY,
                value JSONB NOT NULL,
                updated_by UUID NULL REFERENCES users(id) ON DELETE SET NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS app_settings CASCADE');
        DB::statement('DROP TABLE IF EXISTS deal_statuses CASCADE');
    }
};
