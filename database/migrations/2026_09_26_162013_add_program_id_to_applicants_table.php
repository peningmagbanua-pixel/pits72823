<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->uuid('program_id')->nullable()->after('program');
            $table->index('program_id');
        });

        // Backfill program_id from programs table using initials
        DB::statement("
            UPDATE applicants
            SET program_id = (
                SELECT id FROM programs
                WHERE programs.initials = applicants.program
                LIMIT 1
            )
            WHERE program IS NOT NULL
        ");

        Schema::table('applicants', function (Blueprint $table) {
            $table->foreign('program_id')
                ->references('id')->on('programs')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropForeign(['program_id']);
            $table->dropIndex(['program_id']);
            $table->dropColumn('program_id');
        });
    }
};