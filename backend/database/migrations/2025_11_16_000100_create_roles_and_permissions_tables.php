<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('
            CREATE TABLE roles (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                slug VARCHAR(255) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );
            
            CREATE TABLE user_roles (
                user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                role_id UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
                assigned_by UUID NULL REFERENCES users(id) ON DELETE SET NULL,
                assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, role_id)
            );
            
            CREATE TABLE auth_invitations (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                email VARCHAR(255) UNIQUE NOT NULL,
                token VARCHAR(255) UNIQUE NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                invited_by UUID NULL REFERENCES users(id) ON DELETE SET NULL,
                accepted_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS auth_invitations CASCADE');
        DB::statement('DROP TABLE IF EXISTS user_roles CASCADE');
        DB::statement('DROP TABLE IF EXISTS roles CASCADE');
    }
};
