<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('
            CREATE TABLE personal_access_tokens (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tokenable_type VARCHAR(255) NOT NULL,
                tokenable_id UUID NOT NULL,
                name VARCHAR(255) NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                abilities TEXT NULL,
                last_used_at TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );
            CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON personal_access_tokens(tokenable_type, tokenable_id);
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS personal_access_tokens CASCADE');
    }
};
