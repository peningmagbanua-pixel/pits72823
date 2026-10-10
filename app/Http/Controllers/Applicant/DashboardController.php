<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Admin\SchoolYear;
use App\Models\Applicant\ActivityLog;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $applicant = Auth::guard('applicant')->user();
        $applicant->load('schedule');

        ActivityLog::logActivity(
            $applicant->id,
            'viewed_dashboard',
            'Viewed the applicant dashboard.',
            [],
            request()->ip(),
            request()->userAgent()
        );

        $activeSchoolYear = SchoolYear::where('is_active', true)->first();

        $showName = ! empty($applicant->submitted_at);

        return response()->json([
            'applicant' => [
                'name' => $showName ? $this->firstnameLastnameExtFormat($applicant) : '',
                'program' => $applicant->program?->initials,
                'program_id' => $applicant->program_id,
                'application_status' => $applicant->application_status,
                'exam_status' => $applicant->exam_status,
            ],
            'schedule' => $applicant->schedule ? [
                'date' => $applicant->schedule->date?->format('Y-m-d'),
                'time_slot' => $applicant->schedule->time_slot,
                'room' => $applicant->schedule->room,
                'program' => $applicant->schedule->program?->initials,
            ] : null,
            'application_period' => SchoolYear::applicationPeriod(),
            'school_year' => $activeSchoolYear ? [
                'name' => $activeSchoolYear->name,
                'application_start' => $activeSchoolYear->application_start,
                'application_deadline' => $activeSchoolYear->application_deadline,
                'guidelines' => $activeSchoolYear->guidelines,
            ] : null,
        ]);
    }

    /**
     * Return the currently logged-in applicant's own activity logs only.
     */
    public function activityLogs()
    {
        $applicant = Auth::guard('applicant')->user();

        $paginate = (int) request('paginate', 10);
        $action = request('action');

        $logs = ActivityLog::where('applicant_id', $applicant->id)
            ->when($action, fn ($query) => $query->where('action', $action))
            ->orderBy('created_at', 'desc')
            ->paginate($paginate)
            ->through(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            ]);

        return response()->json($logs);
    }
}
