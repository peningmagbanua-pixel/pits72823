<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations - Convert exam_schedules id from auto-increment to UUID
     */
    public function up(): void
    {
        // 1. Drop foreign key constraint on applicants.schedule_id referencing exam_schedules
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
        });

        // 2. Add temporary columns to store UUIDs temporarily
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->uuid('uuid_id')->nullable()->after('id');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->uuid('new_schedule_id')->nullable()->after('schedule_id');
        });

        // 3. Generate UUIDs for each exam schedule
        $schedules = DB::table('exam_schedules')->get();
        foreach ($schedules as $schedule) {
            $uuid = (string) Str::uuid();
            DB::table('exam_schedules')
                ->where('id', $schedule->id)
                ->update(['uuid_id' => $uuid]);

            // Update applicants referencing this schedule ID
            DB::table('applicants')
                ->where('schedule_id', $schedule->id)
                ->update(['new_schedule_id' => $uuid]);
        }

        // 4. Drop the old auto-increment primary key ID on exam_schedules
        // MySQL requires us to remove auto_increment first. To do this, we alter the column to a standard bigint.
        DB::statement('ALTER TABLE exam_schedules MODIFY id BIGINT UNSIGNED NOT NULL');
        // Now drop the primary key constraint
        DB::statement('ALTER TABLE exam_schedules DROP PRIMARY KEY');
        // Drop the old id column
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        // 5. Make the uuid_id column non-nullable, rename it to id, and make it primary key
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->uuid('uuid_id')->nullable(false)->change();
            $table->renameColumn('uuid_id', 'id');
        });
        
        DB::statement('ALTER TABLE exam_schedules ADD PRIMARY KEY (id)');

        // 6. Drop the old schedule_id column on applicants and rename new_schedule_id to schedule_id
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn('schedule_id');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->renameColumn('new_schedule_id', 'schedule_id');
        });

        // 7. Add foreign key constraint back
        Schema::table('applicants', function (Blueprint $table) {
            $table->foreign('schedule_id')->references('id')->on('exam_schedules')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
        });

        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropPrimary();
            $table->dropColumn('id');
        });

        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->id('id');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn('schedule_id');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->constrained('exam_schedules')->onDelete('set null');
        });
    }
};
