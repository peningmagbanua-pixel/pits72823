<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\ActionType;
use App\Http\Resources\Admin\ExamScheduleResource;
use App\Mail\ExamScheduledMail;
use App\Models\Admin\ExamSchedule;
use App\Models\Admin\Program;
use App\Models\Admin\SchoolYear;
use App\Models\Applicant\Applicant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Traits\AdminActivityLogsTrait;
use App\Traits\AuditLogsTrait;
use App\Traits\ProgramScopeTrait;

class ExamScheduleController extends Controller
{
    use AdminActivityLogsTrait, AuditLogsTrait, ProgramScopeTrait;

    public function index(Request $request)
    {
        $paginate = $request->get('paginate', 10);
        $schoolYearId = $request->get('school_year_id');
        $search = trim((string) $request->get('search'));

        $query = $this->applyProgramScope(ExamSchedule::query())
            ->with(['schoolYear', 'program'])
            ->withCount('applicants')
            ->when($request->get('deleted') == '1', fn ($q) => $q->onlyTrashed())
            ->when($schoolYearId === 'unassigned', fn ($q) => $q->unassigned())
            ->when($schoolYearId && $schoolYearId !== 'unassigned', fn ($q) => $q->forSchoolYear($schoolYearId))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';

                $q->where(function ($sub) use ($like) {
                    $sub->where('date', 'like', $like)
                        ->orWhere('time_slot', 'like', $like)
                        ->orWhere('room', 'like', $like)
                        ->orWhereHas('program', function ($program) use ($like) {
                            $program->withTrashed()
                                ->where('initials', 'like', $like)
                                ->orWhere('name', 'like', $like);
                        })
                        ->orWhereHas('schoolYear', function ($schoolYear) use ($like) {
                            $schoolYear->where('name', 'like', $like);
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('date', 'desc');

        $schedules = $query->paginate($paginate);

        return ExamScheduleResource::collection($schedules);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'school_year_id' => 'nullable|uuid|exists:school_years,id',
            'program_id' => 'required|uuid|exists:programs,id',
            'date' => 'required|date',
            'time_slot' => 'required|string',
            'room' => 'required|string',
            'capacity' => 'required|integer|min:1',
        ]);

        if (Program::onlyTrashed()->where('id', $validated['program_id'])->exists()) {
            return response()->json([
                'message' => 'The selected program is deleted. Restore it first.',
            ], 400);
        }

        if (!empty($validated['school_year_id'])
            && SchoolYear::onlyTrashed()->where('id', $validated['school_year_id'])->exists()) {
            return response()->json([
                'message' => 'The selected school year is deleted. Restore it first.',
            ], 400);
        }

        $this->assertProgramAllowed($validated['program_id']);

        // Default to active school year if not provided
        if (empty($validated['school_year_id'])) {
            $activeSchoolYear = SchoolYear::where('is_active', true)->first();
            if ($activeSchoolYear) {
                $validated['school_year_id'] = $activeSchoolYear->id;
            }
        }

        try {
            $schedule = ExamSchedule::create($validated);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unable to create exam schedule. '.(app()->isLocal() ? $e->getMessage() : 'Please check your database connection and try again.'),
            ], 500);
        }

        $this->logAdminActivity('created_exam_schedule', 'Created exam schedule (' . $schedule->program?->initials . ', ' . $validated['date'] . ', ' . $validated['time_slot'] . ').');

        return response()->json([
            'message' => 'Exam schedule created successfully!',
            'schedule' => new ExamScheduleResource($schedule->load(['schoolYear', 'program'])),
        ]);
    }

    public function update(Request $request, ExamSchedule $examSchedule)
    {
        $this->assertProgramAllowed($examSchedule->program_id);

        $validated = $request->validate([
            'school_year_id' => 'nullable|uuid|exists:school_years,id',
            'program_id' => 'required|uuid|exists:programs,id',
            'date' => 'required|date',
            'time_slot' => 'required|string',
            'room' => 'required|string',
            'capacity' => 'required|integer|min:1',
        ]);

        $this->assertProgramAllowed($validated['program_id']);

        if (Program::onlyTrashed()->where('id', $validated['program_id'])->exists()) {
            return response()->json([
                'message' => 'The selected program is deleted. Restore it first.',
            ], 400);
        }

        if (!empty($validated['school_year_id'])
            && SchoolYear::onlyTrashed()->where('id', $validated['school_year_id'])->exists()) {
            return response()->json([
                'message' => 'The selected school year is deleted. Restore it first.',
            ], 400);
        }

        return DB::transaction(function () use ($examSchedule, $validated) {
            // Lock to prevent concurrent modifications
            $schedule = ExamSchedule::lockForUpdate()->find($examSchedule->id);

            // Prevent changing school_year_id if there are assigned applicants
            $newSchoolYearId = $validated['school_year_id'] ?? $schedule->school_year_id;
            if ($schedule->school_year_id !== $newSchoolYearId) {
                $assignedCount = $schedule->applicants()->count();
                if ($assignedCount > 0) {
                    return response()->json([
                        'message' => "Cannot change school year: $assignedCount applicant(s) already assigned to this schedule.",
                    ], 422);
                }
            }

            // Check if capacity is being reduced below current assigned count
            $currentAssigned = $schedule->applicants()->count();
            if ($validated['capacity'] < $currentAssigned) {
                return response()->json([
                    'message' => "Cannot set capacity lower than current assigned applicants ($currentAssigned).",
                ], 422);
            }

            // Only include school_year_id if explicitly provided (prevents nullifying existing value)
            $updateData = $validated;
            if (empty($updateData['school_year_id'])) {
                unset($updateData['school_year_id']);
            }

            try {
                $schedule->update($updateData);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Unable to update exam schedule. '.(app()->isLocal() ? $e->getMessage() : 'Please check your database connection and try again.'),
                ], 500);
            }

            $this->logAdminActivity('updated_exam_schedule', 'Updated exam schedule (' . $schedule->program?->initials . ', ' . $validated['date'] . ', ' . $validated['time_slot'] . ').');

            return response()->json([
                'message' => 'Exam schedule updated successfully!',
            'schedule' => new ExamScheduleResource($schedule->load('schoolYear')),
            ]);
        });
    }

    public function destroy(ExamSchedule $examSchedule)
    {
        $this->assertProgramAllowed($examSchedule->program_id);

        $action = ActionType::Deleted->value;
        $module = 'Exam Schedules';
        $description = 'Deleted exam schedule (' . $examSchedule->program?->initials . ', ' . $examSchedule->date . ', ' . $examSchedule->time_slot . ')';
        $old_values = $examSchedule->getOriginal();

        // Soft delete: hidden from the scheduling list, still shown in Reports
        $examSchedule->delete();

        $this->setAuditLog($action, $module, $description, $old_values, null);
        $this->logAdminActivity('deleted_exam_schedule', 'Deleted exam schedule (' . $examSchedule->program?->initials . ', ' . $examSchedule->date . ', ' . $examSchedule->time_slot . ').');

        return response()->json([
            'message' => 'Exam schedule deleted successfully!',
        ]);
    }

    public function restore($id)
    {
        $examSchedule = ExamSchedule::withTrashed()->findOrFail($id);

        $this->assertProgramAllowed($examSchedule->program_id);

        if (!$examSchedule->trashed()) {
            return response()->json([
                'message' => 'Exam schedule is not deleted!',
            ], 400);
        }

        $examSchedule->restore();

        $action = ActionType::Updated->value;
        $module = 'Exam Schedules';
        $description = 'Restored exam schedule (' . $examSchedule->program?->initials . ', ' . $examSchedule->date . ', ' . $examSchedule->time_slot . ')';
        $this->setAuditLog($action, $module, $description, null, $examSchedule->toArray());
        $this->logAdminActivity('updated_exam_schedule', 'Restored exam schedule (' . $examSchedule->program?->initials . ', ' . $examSchedule->date . ', ' . $examSchedule->time_slot . ').');

        return response()->json([
            'message' => 'Exam schedule restored successfully!',
        ]);
    }

    public function getUnscheduledApplicants(Request $request)
    {
        $schoolYearId = $request->get('school_year_id');
        $scheduleId = $request->get('schedule_id');

        $query = $this->applyProgramScope(Applicant::query())
            ->with('program:id,initials')
            ->whereNull('schedule_id')
            ->where('application_status', 'approved');

        // Only applicants that can actually be assigned to the given schedule
        if ($scheduleId) {
            $schedule = ExamSchedule::find($scheduleId);

            if (! $schedule) {
                return response()->json([]);
            }

            $query->where('program_id', $schedule->program_id);

            if ($schedule->school_year_id) {
                $query->where('school_year_id', $schedule->school_year_id);
            }
        } elseif ($schoolYearId) {
            $query->where('school_year_id', $schoolYearId);
        }

        return $query->get()->map(function ($applicant) {
            $row = $applicant->toArray();
            $row['program'] = $applicant->program?->initials ?? '';

            return $row;
        });
    }

    public function autoAssign(Request $request)
    {
        $programId = $request->program_id;
        $schoolYearId = $request->school_year_id;

        // Default to active school year if not provided
        if (empty($schoolYearId)) {
            $activeSchoolYear = SchoolYear::where('is_active', true)->first();
            if (!$activeSchoolYear) {
                return response()->json(['message' => 'No active school year found.'], 422);
            }
            $schoolYearId = $activeSchoolYear->id;
        }

        $this->assertProgramAllowed($programId);

        return DB::transaction(function () use ($programId, $schoolYearId) {
            $applicants = Applicant::whereNull('schedule_id')
                ->where('application_status', 'approved')
                ->where('program_id', $programId)
                ->where('school_year_id', $schoolYearId)
                ->get();

            $availableSchedules = ExamSchedule::where('program_id', $programId)
                ->where('date', '>=', now())
                ->active()
                ->forSchoolYear($schoolYearId)
                ->lockForUpdate()
                ->get()
                ->map(function ($schedule) {
                    $assigned = $schedule->applicants()->count();

                    return [
                        'schedule' => $schedule,
                        'remaining' => $schedule->capacity - $assigned,
                    ];
                })
                ->filter(fn ($entry) => $entry['remaining'] > 0)
                ->values()
                ->all();

            if ($applicants->isEmpty()) {
                return response()->json(['message' => 'No applicants to assign.'], 422);
            }

            if (empty($availableSchedules)) {
                return response()->json(['message' => 'No available schedules with capacity.'], 422);
            }

            $assignedCount = 0;
            $assignedApplicants = [];
            $scheduleIndex = 0;

            foreach ($applicants as $applicant) {
                if (empty($availableSchedules)) {
                    break;
                }

                if ($scheduleIndex >= count($availableSchedules)) {
                    $scheduleIndex = 0;
                }

                $current = $availableSchedules[$scheduleIndex];
                $schedule = $current['schedule'];

                // Validate school year match
                if ($applicant->school_year_id !== $schedule->school_year_id) {
                    continue; // Skip this applicant
                }

                $applicant->update([
                    'schedule_id' => $schedule->id,
                    'exam_status' => 'scheduled',
                ]);
                $assignedApplicants[] = $applicant;
                $assignedCount++;

                $availableSchedules[$scheduleIndex]['remaining']--;
                if ($availableSchedules[$scheduleIndex]['remaining'] <= 0) {
                    array_splice($availableSchedules, $scheduleIndex, 1);
                    if ($scheduleIndex >= count($availableSchedules)) {
                        $scheduleIndex = 0;
                    }
                } else {
                    $scheduleIndex++;
                }
            }

            // Send email notifications
            foreach ($assignedApplicants as $applicant) {
                $schedule = $applicant->schedule;
                Mail::to($applicant->email)->send(new ExamScheduledMail($schedule, $applicant));
            }

            $this->logAdminActivity('auto_assigned_exam_schedule', "Auto-assigned $assignedCount applicant(s) to exam schedules for program $programId.");

            return response()->json([
                'message' => "Successfully assigned $assignedCount applicants.",
            ]);
        });
    }

    public function manualAssign(Request $request)
    {
        $validated = $request->validate([
            'applicant_ids' => 'required|array',
            'applicant_ids.*' => 'exists:applicants,id',
            'schedule_id' => 'required|exists:exam_schedules,id',
        ]);

        return DB::transaction(function () use ($validated) {
            // Lock the schedule to prevent concurrent capacity violations
            $schedule = ExamSchedule::lockForUpdate()
                ->find($validated['schedule_id']);

            // Deleted (trashed) schedules are no longer assignable
            if (! $schedule) {
                return response()->json([
                    'message' => 'The selected schedule no longer exists.',
                ], 422);
            }

            $this->assertProgramAllowed($schedule->program_id);

            $assigned = $schedule->applicants()->count();
            $remainingCapacity = $schedule->capacity - $assigned;

            if (count($validated['applicant_ids']) > $remainingCapacity) {
                return response()->json([
                    'message' => "The selected schedule only has $remainingCapacity spots left.",
                ], 422);
            }

            // Validate program and school year match for each applicant
            $applicants = Applicant::with('program:id,initials')->whereIn('id', $validated['applicant_ids'])->get();
            foreach ($applicants as $applicant) {
                $this->assertProgramAllowed($applicant->program_id);

                if ($applicant->program_id !== $schedule->program_id) {
                    return response()->json([
                        'message' => "Applicant {$applicant->firstname} {$applicant->lastname} (".($applicant->program?->initials ?? 'N/A').') cannot be assigned to a '.($schedule->program?->initials ?? 'N/A').' exam schedule.',
                    ], 422);
                }

                if ($applicant->school_year_id !== $schedule->school_year_id) {
                    return response()->json([
                        'message' => "Applicant {$applicant->firstname} {$applicant->lastname} belongs to a different school year and cannot be assigned to this schedule.",
                    ], 422);
                }
            }

            Applicant::whereIn('id', $validated['applicant_ids'])
                ->update([
                    'schedule_id' => $schedule->id,
                    'exam_status' => 'scheduled',
                ]);

            // Send email notifications
            $assignedApplicants = Applicant::whereIn('id', $validated['applicant_ids'])->get();
            foreach ($assignedApplicants as $applicant) {
                Mail::to($applicant->email)->send(new ExamScheduledMail($schedule, $applicant));
            }

            $this->logAdminActivity('manual_assigned_exam_schedule', 'Manually assigned ' . count($validated['applicant_ids']) . ' applicant(s) to exam schedule (' . $schedule->program_id . ', ' . $schedule->date . ', ' . $schedule->time_slot . ').');

            return response()->json([
                'message' => 'Applicants assigned successfully!',
            ]);
        });
    }

    public function getScheduleApplicants($id)
    {
        $schedule = ExamSchedule::withTrashed()->with('program:id,initials,name')->findOrFail($id);

        $this->assertProgramAllowed($schedule->program_id);

        $applicants = Applicant::with('program:id,initials,name')
            ->where('schedule_id', $id)
            ->where('application_status', 'approved')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['id', 'firstname', 'lastname', 'email', 'program_id'])
            ->map(function ($a) use ($schedule) {
                $row = $a->toArray();
                $row['program'] = $a->program?->initials
                    ?? $schedule->program?->initials
                    ?? '';
                $row['program_mismatch'] = $a->program_id !== null
                    && $schedule->program_id !== null
                    && $a->program_id !== $schedule->program_id;

                return $row;
            });

        return response()->json([
            'schedule' => new ExamScheduleResource($schedule->load(['schoolYear', 'program'])),
            'applicants' => $applicants->values(),
            'mismatched_count' => $applicants->where('program_mismatch', true)->count(),
        ]);
    }
}