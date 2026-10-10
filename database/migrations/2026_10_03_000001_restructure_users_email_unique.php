<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        // Unique among active accounts only: soft-deleted rows get NULL,
        // and MySQL/MariaDB unique indexes allow multiple NULLs.
        DB::statement(
            'ALTER TABLE users ADD COLUMN live_email VARCHAR(255) '.
            'GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN email ELSE NULL END) STORED'
        );

        DB::statement(
            'CREATE UNIQUE INDEX users_live_email_unique ON users (live_email)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX users_live_email_unique ON users');

        DB::statement('ALTER TABLE users DROP COLUMN live_email');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
