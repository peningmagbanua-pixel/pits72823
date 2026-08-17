<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\SchoolYear;

class DashboardController extends Controller
{
    public function index()
    {
        $applicant = Auth::guard('applicant')->user();
        $applicant->load('schedule');

        $activeSchoolYear = SchoolYear::where('is_active', true)->first();

        return response()->json([
            'applicant' => [
                'name' => $applicant->firstname . ' ' . $applicant->lastname,
                'program' => $applicant->program,
                'application_status' => $applicant->application_status,
                'exam_status' => $applicant->exam_status,
            ],
            'schedule' => $applicant->schedule ? [
                'date' => $applicant->schedule->date,
                'time_slot' => $applicant->schedule->time_slot,
                'room' => $applicant->schedule->room,
                'program' => $applicant->schedule->program,
            ] : null,
            'school_year' => $activeSchoolYear ? [
                'name' => $activeSchoolYear->name,
                'application_start' => $activeSchoolYear->application_start,
                'application_deadline' => $activeSchoolYear->application_deadline,
                'exam_date' => $activeSchoolYear->exam_date,
                'guidelines' => $activeSchoolYear->guidelines,
            ] : null,
        ]);
    }
}
