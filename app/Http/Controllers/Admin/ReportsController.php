<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SchoolYear;
use App\Models\Applicant\Applicant;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = Applicant::whereNotNull('program');

        $baseQuery->when($request->program, fn ($q, $v) => $q->where('program', $v));
        $baseQuery->when($request->status, fn ($q, $v) => $q->where('application_status', $v));
        $baseQuery->when($request->date_from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v));
        $baseQuery->when($request->date_to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));

        if ($request->school_year_id) {
            $sy = SchoolYear::find($request->school_year_id);
            if ($sy && $sy->application_start && $sy->application_deadline) {
                $baseQuery->whereBetween('created_at', [
                    $sy->application_start,
                    $sy->application_deadline.' 23:59:59',
                ]);
            }
        }

        $total = (clone $baseQuery)->count();
        $approved = (clone $baseQuery)->where('application_status', 'approved')->count();
        $rejected = (clone $baseQuery)->where('application_status', 'rejected')->count();
        $pending = (clone $baseQuery)->where('application_status', 'pending')->count();

        $approval_rate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;
        $rejection_rate = $total > 0 ? round(($rejected / $total) * 100, 1) : 0;

        $spict = (clone $baseQuery)->where('program', 'SPICT');
        $ste = (clone $baseQuery)->where('program', 'STE');

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
