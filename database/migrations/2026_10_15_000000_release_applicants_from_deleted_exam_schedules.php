<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('applicants')
            ->whereIn('schedule_id', function ($query) {
                $query->select('id')
                    ->from('exam_schedules')
                    ->whereNotNull('deleted_at');
            })
            ->update([
                'schedule_id' => null,
                'exam_status' => null,
            ]);
    }

    public function down(): void
    {
        //
    }
};
