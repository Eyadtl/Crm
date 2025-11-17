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
        // Neon uses pgcrypto for UUID generation
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS "pgcrypto"');
    }
};
