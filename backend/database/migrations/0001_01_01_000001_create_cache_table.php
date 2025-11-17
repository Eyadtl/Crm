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
            CREATE TABLE cache (
                key VARCHAR(255) PRIMARY KEY,
                value TEXT NOT NULL,
                expiration INTEGER NOT NULL
            );
            
            CREATE TABLE cache_locks (
                key VARCHAR(255) PRIMARY KEY,
                owner VARCHAR(255) NOT NULL,
                expiration INTEGER NOT NULL
            );
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS cache_locks CASCADE');
        DB::statement('DROP TABLE IF EXISTS cache CASCADE');
    }
};
