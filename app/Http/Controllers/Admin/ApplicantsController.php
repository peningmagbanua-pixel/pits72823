<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ApplicantsResource;
use App\Mail\ApplicationRejectedMail;
use App\Mail\ApplicationResetToPendingMail;
use App\Mail\ApplicationStatusNotificationEmail;
use App\Models\Admin\ExamSchedule;
use App\Models\Applicant\ActivityLog;
use App\Models\Applicant\Applicant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ApplicantsController extends Controller
{
    public function index()
    {
        $paginate = request('paginate');
        $search = request('search', '');
        $program = request('program');
        $status = request('status');
        $sort_order = request('sort_order', 'desc');
        $sort_field = request('sort_field', 'created_at');

        if (! in_array($sort_order, ['asc', 'desc'])) {
            $sort_order = 'desc';
        }

        if (! in_array($sort_field, ['created_at', 'lastname', 'firstname'])) {
            $sort_field = 'created_at';
        }

        $applicants = Applicant::query()
            ->whereNotNull('program')
            ->when($program, function ($query) use ($program) {
                $query->where('program', $program);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('application_status', $status);
            })
            ->search(trim($search))
            ->orderBy($sort_field, $sort_order)
            ->paginate($paginate);

        return ApplicantsResource::collection($applicants);
    }

    public function printApproved()
    {
        $program = request('program');

        $applicants = Applicant::where('application_status', 'approved')
            ->whereNotNull('program')
            ->when($program, fn ($q) => $q->where('program', $program))
            ->orderBy('program')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['id', 'lastname', 'firstname', 'middlename', 'extname', 'program', 'created_at']);

        return response()->json([
            'spict' => $applicants->where('program', 'SPICT')->values(),
            'ste' => $applicants->where('program', 'STE')->values(),
        ]);
    }

    public function show($id)
    {
        $applicant = Applicant::findOrFail($id);

        return new ApplicantsResource($applicant);
    }

    /**
     * Return paginated activity logs for a specific applicant.
     */
    public function activityLogs($id)
    {
        $applicant = Applicant::findOrFail($id);

        $logs = ActivityLog::where('applicant_id', $applicant->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            ]);

        return response()->json(['data' => $logs]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,pending',
            'schedule_id' => 'nullable|exists:exam_schedules,id',
        ]);

        $applicant = Applicant::findOrFail($id);
        $newStatus = $request->status;
        $oldStatus = $applicant->application_status;

        return DB::transaction(function () use ($request, $applicant, $newStatus, $oldStatus) {
            // Lock the target schedule (if any) to prevent concurrent capacity violations
            $schedule = null;
            if ($request->filled('schedule_id')) {
                $schedule = ExamSchedule::lockForUpdate()->find($request->schedule_id);

                // Capacity check BEFORE any status change is persisted
                if ($schedule && $applicant->schedule_id !== $schedule->id) {
                    $assignedCount = $schedule->applicants()->count();
                    if ($assignedCount >= $schedule->capacity) {
                        return response()->json([
                            'message' => 'The selected schedule is already full.',
                        ], 422);
                    }
                }
            }

            $applicant->update([
                'application_status' => $newStatus,
            ]);

            if ($schedule) {
                $applicant->update([
                    'schedule_id' => $schedule->id,
                    'exam_status' => 'scheduled',
                ]);
            }

            // On approval: keep the account active and update the status only
            if ($newStatus === 'approved') {
                $applicant->update([
                    'is_active' => true,
                ]);

                // Notify the applicant of approval via email
                try {
                    Mail::to($applicant->email)
                        ->send(new ApplicationStatusNotificationEmail($applicant, 'approved'));
                } catch (\Exception $e) {
                    Log::error('Approval email failed for '.$applicant->email.': '.$e->getMessage());
                }

                // Log approval activity
                ActivityLog::logActivity(
                    $applicant->id,
                    'status_approved',
                    'Application approved by admin.',
                    ['old_status' => $oldStatus, 'new_status' => $newStatus],
                    request()->ip(),
                    request()->userAgent()
                );
            }

            // On rejection: notify the applicant without deactivating the account
            if ($newStatus === 'rejected') {
                try {
                    Mail::to($applicant->email)
                        ->send(new ApplicationRejectedMail($applicant));
                } catch (\Exception $e) {
                    Log::error('Rejection email failed for '.$applicant->email.': '.$e->getMessage());
                }

                // Log rejection activity
                ActivityLog::logActivity(
                    $applicant->id,
                    'status_rejected',
                    'Application rejected by admin. Rejection notification sent via email.',
                    ['old_status' => $oldStatus, 'new_status' => $newStatus],
                    request()->ip(),
                    request()->userAgent()
                );
            }

            // On reset to pending: notify the applicant without deactivating the account
            if ($newStatus === 'pending' && $oldStatus !== 'pending') {
                try {
                    Mail::to($applicant->email)
                        ->send(new ApplicationResetToPendingMail($applicant));
                } catch (\Exception $e) {
                    Log::error('Reset-to-pending email failed for '.$applicant->email.': '.$e->getMessage());
                }

                ActivityLog::logActivity(
                    $applicant->id,
                    'status_reset',
                    'Application status reset to pending by admin. Notification sent via email.',
                    ['old_status' => $oldStatus, 'new_status' => $newStatus],
                    request()->ip(),
                    request()->userAgent()
                );
            }

            return response()->json([
                'message' => 'Application status updated successfully.',
            ]);
        });
    }
}
