<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ExamScheduledMail;
use App\Models\Admin\ExamSchedule;
use App\Models\Applicant\Applicant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ExamScheduleController extends Controller
{
    public function index(Request $request)
    {
        $paginate = $request->get('paginate', 10);
        $schedules = ExamSchedule::withCount('applicants')
            ->orderBy('date', 'desc')
            ->paginate($paginate);

        return response()->json($schedules);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'program' => 'required|string',
            'date' => 'required|date',
            'time_slot' => 'required|string',
            'room' => 'required|string',
            'capacity' => 'required|integer|min:1',
        ]);

        try {
            $schedule = ExamSchedule::create($validated);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unable to create exam schedule. '.(app()->isLocal() ? $e->getMessage() : 'Please check your database connection and try again.'),
            ], 500);
        }

        return response()->json([
            'message' => 'Exam schedule created successfully!',
            'schedule' => $schedule,
        ]);
    }

    public function update(Request $request, ExamSchedule $examSchedule)
    {
        $validated = $request->validate([
            'program' => 'required|string',
            'date' => 'required|date',
            'time_slot' => 'required|string',
            'room' => 'required|string',
            'capacity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($examSchedule, $validated) {
            // Lock to prevent concurrent modifications
            $schedule = ExamSchedule::lockForUpdate()->find($examSchedule->id);

            // Check if capacity is being reduced below current assigned count
            $currentAssigned = $schedule->applicants()->count();
            if ($validated['capacity'] < $currentAssigned) {
                return response()->json([
                    'message' => "Cannot set capacity lower than current assigned applicants ($currentAssigned).",
                ], 422);
            }

            try {
                $schedule->update($validated);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Unable to update exam schedule. '.(app()->isLocal() ? $e->getMessage() : 'Please check your database connection and try again.'),
                ], 500);
            }

            return response()->json([
                'message' => 'Exam schedule updated successfully!',
                'schedule' => $schedule,
            ]);
        });
    }

    public function destroy(ExamSchedule $examSchedule)
    {
        $examSchedule->delete();

        return response()->json([
            'message' => 'Exam schedule deleted successfully!',
        ]);
    }

    public function getUnscheduledApplicants()
    {
        return Applicant::whereNull('schedule_id')
            ->where('application_status', 'approved') // Only approved applicants get scheduled
            ->get();
    }

    public function autoAssign(Request $request)
    {
        $program = $request->program; // STE or SPICT

        return DB::transaction(function () use ($program) {
            $applicants = Applicant::whereNull('schedule_id')
                ->where('application_status', 'approved')
                ->where('program', $program)
                ->get();

            $availableSchedules = ExamSchedule::where('program', $program)
                ->where('date', '>=', now())
                ->lockForUpdate() // Lock to prevent concurrent modifications
                ->get()
                ->map(function ($schedule) {
                    $assigned = $schedule->applicants()->count();

                    return [
                        'schedule' => $schedule,
                        'remaining' => $schedule->capacity - $assigned,
                    ];
                })
                ->filter(function ($entry) {
                    return $entry['remaining'] > 0;
                })
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

            $assigned = $schedule->applicants()->count();
            $remainingCapacity = $schedule->capacity - $assigned;

            if (count($validated['applicant_ids']) > $remainingCapacity) {
                return response()->json([
                    'message' => "The selected schedule only has $remainingCapacity spots left.",
                ], 422);
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

            return response()->json([
                'message' => 'Applicants assigned successfully!',
            ]);
        });
    }

    public function getScheduleApplicants($id)
    {
        $schedule = ExamSchedule::findOrFail($id);
        $applicants = Applicant::where('schedule_id', $id)
            ->where('application_status', 'approved')
            ->get(['id', 'firstname', 'lastname', 'email', 'program']);

        return response()->json([
            'schedule' => $schedule,
            'applicants' => $applicants,
        ]);
    }
}
