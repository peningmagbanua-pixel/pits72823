<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ApplicantsResource;
use App\Mail\ApplicationRejectedMail;
use App\Mail\ApplicationResetToPendingMail;
use App\Mail\ApplicationStatusNotificationEmail;
use App\Models\Admin\ExamSchedule;
use App\Models\Admin\Program;
use App\Models\Applicant\ActivityLog;
use App\Models\Applicant\Applicant;
use App\Traits\AdminActivityLogsTrait;
use App\Traits\AuditLogsTrait;
use App\Traits\ProgramScopeTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ApplicantsController extends Controller
{
    use AdminActivityLogsTrait, AuditLogsTrait, ProgramScopeTrait;

    /**
     * Serve an uploaded applicant file to authorized staff.
     * Files live on the private disk under applications/{applicant_id}/...
     */
    public function serveFile(string $path)
    {
        if (! str_starts_with($path, 'applications/') || str_contains($path, '..')) {
            abort(403, 'You do not have access to this file.');
        }

        $applicant_id = explode('/', $path)[1] ?? '';
        $applicant = Applicant::withTrashed()->find($applicant_id);

        if (! $applicant) {
            abort(404, 'File not found.');
        }

        $this->assertProgramAllowed($applicant->program_id);

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->file(Storage::disk('local')->path($path));
    }

    public function index()
    {
        $paginate = request('paginate');
        $search = request('search', '');
        $programId = request('program_id');
        $status = request('status');
        $sort_order = request('sort_order', 'desc');
        $sort_field = request('sort_field', 'created_at');

        if (! in_array($sort_order, ['asc', 'desc'])) {
            $sort_order = 'desc';
        }

        if (! in_array($sort_field, ['created_at', 'lastname', 'firstname'])) {
            $sort_field = 'created_at';
        }

        if ($programId) {
            $this->assertProgramAllowed($programId);
        }

        $this->logAdminActivity('viewed_applicants', 'Viewed the list of applicants.', [
            'program_id' => $programId,
            'status' => $status,
        ]);

        $applicants = $this->applyProgramScope(Applicant::query())
            ->with('program')
            ->whereNotNull('program_id')
            ->when($programId, function ($query) use ($programId) {
                $query->where('program_id', $programId);
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
        $programId = request('program_id');

        if ($programId) {
            $this->assertProgramAllowed($programId);
        }

        $applicants = $this->applyProgramScope(Applicant::query())
            ->where('application_status', 'approved')
            ->whereNotNull('program_id')
            ->when($programId, fn ($q) => $q->where('program_id', $programId))
            ->orderBy('program_id')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['id', 'lastname', 'firstname', 'middlename', 'extname', 'program_id', 'created_at']);

        return response()->json([
            'spict' => $applicants->where('program_id', Program::withTrashed()->where('initials', 'SPICT')->value('id'))->values(),
            'ste' => $applicants->where('program_id', Program::withTrashed()->where('initials', 'STE')->value('id'))->values(),
        ]);
    }

    public function show($id)
    {
        $applicant = Applicant::with(['program', 'applicantDocuments'])->findOrFail($id);

        $this->assertProgramAllowed($applicant->program_id);

        return new ApplicantsResource($applicant);
    }

    /**
     * Return paginated activity logs for a specific applicant.
     */
    public function activityLogs($id)
    {
        $applicant = Applicant::findOrFail($id);

        $this->assertProgramAllowed($applicant->program_id);

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
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $rejectionReason = $request->status === 'rejected'
            ? trim((string) $request->input('rejection_reason'))
            : null;

        if ($request->status === 'rejected' && $rejectionReason === '') {
            return response()->json([
                'message' => 'A rejection reason is required to reject an application.',
            ], 422);
        }

        $applicant = Applicant::findOrFail($id);
        $this->assertProgramAllowed($applicant->program_id);
        $newStatus = $request->status;
        $oldStatus = $applicant->application_status;

        return DB::transaction(function () use ($request, $applicant, $newStatus, $oldStatus, $rejectionReason) {
            // Lock the target schedule (if any) to prevent concurrent capacity violations
            $schedule = null;
            if ($request->filled('schedule_id')) {
                $schedule = ExamSchedule::lockForUpdate()->find($request->schedule_id);

                if ($schedule) {
                    $this->assertProgramAllowed($schedule->program_id);
                }

                // Program validation: applicant and schedule must belong to the same program
                if ($schedule && $applicant->program_id !== $schedule->program_id) {
                    return response()->json([
                        'message' => 'Cannot assign applicant to an exam schedule from a different program.',
                    ], 422);
                }

                // School year validation: applicant and schedule must belong to same school year
                if ($schedule && $applicant->school_year_id !== $schedule->school_year_id) {
                    return response()->json([
                        'message' => 'Cannot assign applicant to exam schedule from a different school year.',
                    ], 422);
                }

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
                // The reason only applies to rejections; it is cleared on any
                // other status so stored data always matches the current status.
                'rejection_reason' => $rejectionReason,
            ]);

            $this->setAuditLog(
                ActionType::Updated->value,
                'Applicants',
                "Changed application status from {$oldStatus} to {$newStatus}",
                ['application_status' => $oldStatus],
                ['application_status' => $newStatus, 'rejection_reason' => $rejectionReason]
            );

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

                $this->logAdminActivity('approved_application', 'Approved the application of '.trim($applicant->firstname.' '.$applicant->lastname).'.', [
                    'applicant_id' => $applicant->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);
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
                    ['old_status' => $oldStatus, 'new_status' => $newStatus, 'rejection_reason' => $rejectionReason],
                    request()->ip(),
                    request()->userAgent()
                );

                $this->logAdminActivity('rejected_application', 'Rejected the application of '.trim($applicant->firstname.' '.$applicant->lastname).'.', [
                    'applicant_id' => $applicant->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'rejection_reason' => $rejectionReason,
                ]);
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

                $this->logAdminActivity('reset_application_status', 'Reset the application status of '.trim($applicant->firstname.' '.$applicant->lastname).' to pending.', [
                    'applicant_id' => $applicant->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);
            }

            $messages = [
                'approved' => 'Application approved.',
                'rejected' => 'Application rejected.',
                'pending' => 'Application status reset to pending.',
            ];

            return response()->json([
                'message' => $messages[$newStatus] ?? 'Application status updated.',
            ]);
        });
    }
}
