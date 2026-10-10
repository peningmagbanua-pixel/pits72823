<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ExamSchedule;
use App\Models\Admin\Program;
use App\Models\Admin\SchoolYear;
use App\Models\Applicant\Applicant;
use App\Traits\ProgramScopeTrait;
use App\Traits\UserRolesTrait;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    use ProgramScopeTrait, UserRolesTrait;

    private function formatSchoolYearName(?string $schoolYearId): string
    {
        if (!$schoolYearId) {
            return 'All School Years';
        }

        $sy = SchoolYear::find($schoolYearId);
        if (!$sy) {
            return 'All School Years';
        }

        return $sy->start && $sy->end ? "{$sy->start}-{$sy->end}" : $sy->name;
    }

    public function printApproved(Request $request)
    {
        $schoolYearId = $request->school_year_id;

        $query = Applicant::with('program:id,initials,name')
            ->where('application_status', 'approved')
            ->whereNotNull('program_id')
            ->orderBy('lastname')
            ->orderBy('firstname');

        if ($schoolYearId) {
            $query->where('school_year_id', $schoolYearId);
        }

        $this->applyProgramScope($query);

        $applicants = $query->get([
            'id', 'lastname', 'firstname', 'middlename', 'extname', 'email', 'program_id', 'created_at',
        ])->map(function ($a) {
            $row = $a->toArray();
            $row['program'] = $a->program?->initials ?? '';
            $row['date_added'] = $a->created_at?->format('Y-m-d');

            return $row;
        });

        return response()->json([
            'school_year' => $this->formatSchoolYearName($schoolYearId),
            'applicants'  => $applicants->values(),
            'total'       => $applicants->count(),
        ]);
    }

    public function printExamSchedule(Request $request)
    {
        $schoolYearId = $request->school_year_id;

        $query = ExamSchedule::withTrashed()->with([
            'program:id,initials,name',
            'applicants' => function ($q) use ($schoolYearId) {
                $q->with('program:id,initials,name')
                  ->where('application_status', 'approved')
                  ->when($schoolYearId, fn ($sq, $v) => $sq->where('school_year_id', $v))
                  ->orderBy('lastname')
                  ->orderBy('firstname')
                  ->select(['id', 'lastname', 'firstname', 'middlename', 'extname', 'program_id', 'email', 'schedule_id', 'created_at']);
            },
        ])->orderBy('date')->orderBy('time_slot');

        $this->applyProgramScope($query);

        $schedules = $query->get()->map(function ($sched) {
            $scheduleProgramId   = $sched->program_id;
            $scheduleInitials    = $sched->program?->initials;

            return [
                'id'         => $sched->id,
                'program_id' => $scheduleProgramId,
                'program'    => $scheduleInitials,
                'date'       => $sched->date?->format('Y-m-d'),
                'time_slot'  => $sched->time_slot,
                'room'       => $sched->room,
                'capacity'   => $sched->capacity,
                'applicants' => $sched->applicants                ->map(function ($a) use ($scheduleProgramId, $scheduleInitials) {
                    $programInitials = $a->program?->initials ?? $scheduleInitials;

                    if ($a->program_id && $scheduleProgramId && $a->program_id !== $scheduleProgramId) {
                        $programInitials = $scheduleInitials;
                    }

                    return [
                        'id'          => $a->id,
                        'lastname'    => $a->lastname,
                        'firstname'   => $a->firstname,
                        'middlename'  => $a->middlename,
                        'extname'     => $a->extname,
                        'email'       => $a->email,
                        'program_id'  => $a->program_id,
                        'program'     => $programInitials ?? '',
                        'date_added'  => $a->created_at?->format('Y-m-d'),
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'school_year' => $this->formatSchoolYearName($schoolYearId),
            'schedules'   => $schedules,
        ]);
    }

    public function printReport(Request $request)
    {
        $filter = $request->filter ?? 'all'; // all | spict | ste | approved | pending | rejected
        $schoolYearId = $request->school_year_id;

        $query = Applicant::whereNotNull('program_id')->orderBy('program_id')->orderBy('lastname')->orderBy('firstname');

        if ($schoolYearId) {
            $query->where('school_year_id', $schoolYearId);
        }

        if ($filter === 'spict') {
            $query->where('program_id', Program::withTrashed()->where('initials', 'SPICT')->value('id'));
        } elseif ($filter === 'ste') {
            $query->where('program_id', Program::withTrashed()->where('initials', 'STE')->value('id'));
        } elseif (in_array($filter, ['approved', 'pending', 'rejected'])) {
            $query->where('application_status', $filter);
        }

        $this->applyProgramScope($query);

        $applicants = $query->get([
            'id', 'lastname', 'firstname', 'middlename', 'extname',
            'sex', 'program_id', 'application_status', 'created_at',
        ]);

        return response()->json([
            'school_year' => $this->formatSchoolYearName($schoolYearId),
            'filter'      => $filter,
            'applicants'  => $applicants->values(),
            'total'       => $applicants->count(),
        ]);
    }

    public function index(Request $request)
    {
        $baseQuery = Applicant::whereNotNull('program_id');

        $this->applyProgramScope($baseQuery);

        $baseQuery->when($request->program_id, fn ($q, $v) => $q->where('program_id', $v));
        $baseQuery->when($request->status, fn ($q, $v) => $q->where('application_status', $v));
        $baseQuery->when($request->date_from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v));
        $baseQuery->when($request->date_to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
        $baseQuery->when($request->school_year_id, fn ($q, $v) => $q->where('school_year_id', $v));

        $total = (clone $baseQuery)->count();
        $approved = (clone $baseQuery)->where('application_status', 'approved')->count();
        $rejected = (clone $baseQuery)->where('application_status', 'rejected')->count();
        $pending = (clone $baseQuery)->where('application_status', 'pending')->count();

        $approval_rate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;
        $rejection_rate = $total > 0 ? round(($rejected / $total) * 100, 1) : 0;

        $spictId = Program::withTrashed()->where('initials', 'SPICT')->value('id');
        $steId = Program::withTrashed()->where('initials', 'STE')->value('id');

        $spict = (clone $baseQuery)->where('program_id', $spictId);
        $ste = (clone $baseQuery)->where('program_id', $steId);

        $programBreakdown = [
            'SPICT' => [
                'total' => $spict->count(),
                'approved' => (clone $spict)->where('application_status', 'approved')->count(),
                'rejected' => (clone $spict)->where('application_status', 'rejected')->count(),
                'pending' => (clone $spict)->where('application_status', 'pending')->count(),
            ],
            'STE' => [
                'total' => $ste->count(),
                'approved' => (clone $ste)->where('application_status', 'approved')->count(),
                'rejected' => (clone $ste)->where('application_status', 'rejected')->count(),
                'pending' => (clone $ste)->where('application_status', 'pending')->count(),
            ],
        ];

        $scheduled = (clone $baseQuery)->where('exam_status', 'scheduled')->count();
        $unscheduled = (clone $baseQuery)->where('application_status', 'approved')->whereNull('exam_status')->count();

        $male = (clone $baseQuery)->where('sex', 'Male')->count();
        $female = (clone $baseQuery)->where('sex', 'Female')->count();

        $monthlyQuery = clone $baseQuery;
        if (! $request->date_from && ! $request->date_to) {
            $monthlyQuery->where('created_at', '>=', now()->subMonths(6));
        }
        $monthly = $monthlyQuery
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(created_at, '%Y-%m') ASC")
            ->get()
            ->map(fn ($r) => [
                'month' => $r->month,
                'count' => $r->count,
            ]);

        return response()->json([
            'summary' => compact('total', 'approved', 'rejected', 'pending'),
            'rates' => compact('approval_rate', 'rejection_rate'),
            'programs' => $programBreakdown,
            'exam' => compact('scheduled', 'unscheduled'),
            'sex' => compact('male', 'female'),
            'monthly' => $monthly,
        ]);
    }
}
