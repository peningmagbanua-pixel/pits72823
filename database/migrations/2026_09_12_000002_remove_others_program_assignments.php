<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove program assignment rows that are no longer supported.
     * Only SPICT and STE remain valid program assignments.
     */
    public function up(): void
    {
        DB::table('user_programs')
            ->whereNotIn('program', ['SPICT', 'STE'])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to restore: the removed program assignment rows cannot
        // be reliably recreated from the data available here.
    }
};