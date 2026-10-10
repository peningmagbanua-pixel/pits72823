<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('application_status');
        });

        $this->backfillSubmittedAt();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }

    /**
     * submitted_at replaces sf9_file as the "has submitted" marker.
     *
     * Preferred source is the earliest submission activity log (the real
     * submission moment). Applicants without such a log fall back to
     * updated_at, which is only an approximation of when they submitted.
     */
    private function backfillSubmittedAt(): void
    {
        $submittedAt = DB::table('applicant_activity_logs')
            ->whereIn('action', ['application_submitted', 'form_submitted'])
            ->groupBy('applicant_id')
            ->select('applicant_id', DB::raw('MIN(created_at) as submitted_at'))
            ->pluck('submitted_at', 'applicant_id');

        $candidates = DB::table('applicants')
            ->whereNotNull('sf9_file')
            ->get(['id', 'updated_at']);

        foreach ($candidates as $applicant) {
            DB::table('applicants')
                ->where('id', $applicant->id)
                ->update([
                    'submitted_at' => $submittedAt->get($applicant->id) ?? $applicant->updated_at,
                ]);
        }
    }
};
