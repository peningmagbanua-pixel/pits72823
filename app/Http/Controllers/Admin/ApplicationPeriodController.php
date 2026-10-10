<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SchoolYear;
use App\Traits\AdminActivityLogsTrait;
use App\Traits\AuditLogsTrait;
use App\Enums\ActionType;
use Illuminate\Http\Request;

class ApplicationPeriodController extends Controller
{
    use AuditLogsTrait, AdminActivityLogsTrait;

    /**
     * The online application period of the active school year.
     */
    public function show()
    {
        $school_year = SchoolYear::where('is_active', true)->first();

        return response()->json([
            'school_year' => $school_year?->name,
            'application_start' => $school_year?->application_start,
            'application_deadline' => $school_year?->application_deadline,
        ]);
    }

    /**
     * Save the online application period on the active school year.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'application_start' => 'required|date',
            'application_deadline' => 'required|date|after_or_equal:application_start',
        ]);

        $school_year = SchoolYear::where('is_active', true)->first();

        if (! $school_year) {
            return response()->json([
                'message' => 'No active school year found! Please activate a school year first.',
            ], 400);
        }

        $old_values = $school_year->getOriginal();

        $school_year->update([
            'application_start' => $validated['application_start'],
            'application_deadline' => $validated['application_deadline'],
        ]);

        $action = ActionType::Updated->value;
        $module = 'School Years';
        $description = 'Updated online application period of ' . $school_year->name;
        $this->setAuditLog($action, $module, $description, $old_values, $school_year->toArray());

        $this->logAdminActivity('updated_application_period', 'Updated online application period of ' . $school_year->name);

        return response()->json([
            'message' => 'Online application period saved successfully.',
            'school_year' => $school_year->name,
            'application_start' => $school_year->application_start,
            'application_deadline' => $school_year->application_deadline,
        ]);
    }
}
