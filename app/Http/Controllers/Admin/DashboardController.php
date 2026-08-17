<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant\Applicant;
use App\Models\Admin\ExamSchedule;

class DashboardController extends Controller
{
    public function stats()
    {
        $total = Applicant::count();
        $pending = Applicant::where('application_status', 'pending')->count();
        $approved = Applicant::where('application_status', 'approved')->count();
        $rejected = Applicant::where('application_status', 'rejected')->count();

        $recent = Applicant::orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->firstname . ' ' . $a->lastname . ($a->extname ? ' ' . $a->extname : ''),
                'email' => $a->email,
                'program' => $a->program,
                'application_status' => $a->application_status ?? 'pending',
                'created_at' => $a->created_at->format('Y-m-d H:i:s'),
            ]);

        $today_count = Applicant::whereDate('created_at', today())->count();
        $week_count = Applicant::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $month_count = Applicant::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)->count();

        $monthly = Applicant::where('created_at', '>=', now()->subMonths(6))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(created_at, '%Y-%m') ASC")
            ->get()
            ->map(fn($r) => [
                'month' => $r->month,
                'count' => $r->count,
            ]);

        $spict_count = Applicant::where('program', 'SPICT')->count();
        $ste_count = Applicant::where('program', 'STE')->count();

        $upcoming_exams = ExamSchedule::withCount('applicants')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date', 'asc')
            ->orderBy('time_slot', 'asc')
            ->take(3)
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'program' => $e->program,
                'date' => $e->date,
                'time_slot' => $e->time_slot,
                'room' => $e->room,
                'capacity' => $e->capacity,
                'applicants_count' => $e->applicants_count,
            ]);

        return response()->json(compact(
            'total', 'pending', 'approved', 'rejected', 'recent',
            'today_count', 'week_count', 'month_count',
            'monthly', 'spict_count', 'ste_count', 'upcoming_exams'
        ));
    }
}
