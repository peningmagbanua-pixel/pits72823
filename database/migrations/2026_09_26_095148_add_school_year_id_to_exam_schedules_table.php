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
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->uuid('school_year_id')->nullable()->after('id');
            $table->index('school_year_id');
        });

        // Backfill: try to match schedules to school years by date
        $schedules = DB::table('exam_schedules')->get();
        foreach ($schedules as $schedule) {
            $matchingYears = DB::table('school_years')
                ->where('start', '<=', $schedule->date)
                ->where('end', '>=', $schedule->date)
                ->get();

            if ($matchingYears->count() === 1) {
                DB::table('exam_schedules')
                    ->where('id', $schedule->id)
                    ->update(['school_year_id' => $matchingYears->first()->id]);
            }
            // If 0 or multiple matches, leave as NULL (Unassigned)
        }

        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->foreign('school_year_id')
                ->references('id')->on('school_years')
                ->onDelete('restrict');
        });

        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropForeign(['school_year_id']);
            $table->dropIndex(['school_year_id']);
            $table->dropColumn('school_year_id');
            $table->dropSoftDeletes();
        });
    }
};