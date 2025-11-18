<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared('
            CREATE TABLE users (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                timezone VARCHAR(255) NOT NULL DEFAULT \'UTC\',
                status VARCHAR(255) NOT NULL DEFAULT \'invited\' CHECK (status IN (\'invited\', \'active\', \'disabled\')),
                invited_at TIMESTAMP NULL,
                last_login_at TIMESTAMP NULL,
                disabled_at TIMESTAMP NULL,
                remember_token VARCHAR(100) NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            );
            
            CREATE TABLE password_reset_tokens (
                email VARCHAR(255) PRIMARY KEY,
                token VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NULL
            );
            
            CREATE TABLE sessions (
                id VARCHAR(255) PRIMARY KEY,
                user_id UUID NULL REFERENCES users(id) ON DELETE SET NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                payload TEXT NOT NULL,
                last_activity INTEGER NOT NULL
            );
            
            CREATE INDEX sessions_last_activity_index ON sessions(last_activity);
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS sessions CASCADE');
        DB::statement('DROP TABLE IF EXISTS password_reset_tokens CASCADE');
        DB::statement('DROP TABLE IF EXISTS users CASCADE');
    }
};
