<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SchoolYearsResource;
use Illuminate\Http\Request;
use App\Models\Admin\SchoolYear;
use App\Traits\AuditLogsTrait;
use App\Traits\AdminActivityLogsTrait;
use App\Enums\ActionType;
use App\Enums\SchoolYearStatus;

class SchoolYearsController extends Controller
{
    use AuditLogsTrait, AdminActivityLogsTrait;
    public function index()
    {
        $paginate = request('paginate');
        $search = request('search', '');
        $sort_order = request('sort_order', 'desc');
        $sort_field = request('sort_field', 'created_at');


        if (!in_array($sort_order, ['asc', 'desc'])) {
            $sort_order = 'desc';
        }

        if (!in_array($sort_field, ['id', 'name', 'created_at'])) {
            $sort_field = 'created_at';
        }

        $school_years = SchoolYear::search(trim($search))
             ->when(request('deleted') == '1', fn ($query) => $query->onlyTrashed())
             ->orderBy($sort_field, $sort_order)
             ->paginate($paginate);  
             
             return SchoolYearsResource::collection($school_years);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'school_year_start' => 'required',
            'school_year_end' => 'required',
            'application_start' => 'nullable|date',
            'application_deadline' => 'nullable|date',
            'guidelines' => 'nullable|string',
        ]);

        $school_years = SchoolYear::where('start', $validated['school_year_start'])
            ->where('end', $validated['school_year_end'])->first();

        if ($school_years) {
            return response()->json([
                'message' => 'Duplicate school year!',
            ], 400);
        }


        $school_year_name = 'SY ' . $validated['school_year_start'] . ' - ' . $validated['school_year_end'];

        $school_year = SchoolYear::create([
            'start' => $validated['school_year_start'],
            'end' => $validated['school_year_end'],
            'name' => $school_year_name,
            'application_start' => $validated['application_start'] ?? null,
            'application_deadline' => $validated['application_deadline'] ?? null,
            'guidelines' => $validated['guidelines'] ?? null,
        ]);

        $active_school_year =  SchoolYear::where('is_active', true)->first();
        if($active_school_year) {
            $active_school_year->update([
                'is_active' => false,
            ]);
        }

        $action = ActionType::Created->value;
        $module = 'School Years';
        $description = 'Created school year ' . $school_year_name;
        $this->setAuditLog($action, $module, $description, null, $school_year->toArray());

        $this->logAdminActivity('created_school_year', 'Created school year ' . $school_year_name);

        return response()->json([
            'message' => 'School year created successfully!',
        ]);
    }

    public function update(Request $request, $id)
    {
        $school_year = SchoolYear::findOrFail($id);

        if (!$school_year) {
            return response()->json([
                'message' => 'School year not found!',
            ], 404);
        }

        $validated = $request->validate([
            'school_year_start' => 'required',
            'school_year_end' => 'required',
            'status' => 'required',
            'application_start' => 'nullable|date',
            'application_deadline' => 'nullable|date',
            'guidelines' => 'nullable|string',
        ]);

        $duplicate = SchoolYear::where('start', $validated['school_year_start'])
            ->where('end', $validated['school_year_end'])
            ->where('id', '!=', $id)
            ->first();

        if ($duplicate) {
            return response()->json([
                'message' => 'Duplicate school year!',
            ], 400);
        }

        $status = $validated['status'];

        if ($status === SchoolYearStatus::Active->value) {
            SchoolYear::where('is_active', true)
                ->where('id', '!=', $id)
                ->update([
                    'is_active' => false
                ]);
        }

        $old_values = $school_year->getOriginal();

        $school_year_name = 'SY ' . $validated['school_year_start'] . ' - ' . $validated['school_year_end'];

        $school_year->update([
            'start' => $validated['school_year_start'],
            'end' => $validated['school_year_end'],
            'name' => $school_year_name,
            'is_active' => $validated['status'],
            'application_start' => $validated['application_start'] ?? null,
            'application_deadline' => $validated['application_deadline'] ?? null,
            'guidelines' => $validated['guidelines'] ?? null,
        ]);

        $action = ActionType::Updated->value;
        $module = 'School Years';
        $description = 'Updated school year ' . $school_year_name;
        $this->setAuditLog($action, $module, $description, $old_values, $school_year->toArray());

        $this->logAdminActivity('updated_school_year', 'Updated school year ' . $school_year_name);

        return response()->json([
            'message' => 'School year updated successfully!',
        ]);
    }

    public function delete($id)
    {
        $school_year = SchoolYear::findOrFail($id);

        if(!$school_year) {
            return response()->json([
                'message' => 'School year not found!',
            ], 404);
        }

        $old_values = $school_year->getOriginal();
        $school_year_name = $school_year->name;

        if($school_year->delete()) {
            $action = ActionType::Deleted->value;
            $module = 'School Years';
            $description = 'Deleted school year ' . $school_year_name;
            $this->setAuditLog($action, $module, $description, $old_values, null);

            $this->logAdminActivity('deleted_school_year', 'Deleted school year ' . $school_year_name);

            return response()->json([
                'message' => 'School year deleted successfully!',
            ]);
        }
    }

    public function restore($id)
    {
        $school_year = SchoolYear::withTrashed()->findOrFail($id);

        if (!$school_year->trashed()) {
            return response()->json([
                'message' => 'School year is not deleted!',
            ], 400);
        }

        $school_year_name = $school_year->name;

        $school_year->restore();

        // Keep a single active school year: deactivate the restored one if another is already active
        if ($school_year->is_active && SchoolYear::where('is_active', true)->where('id', '!=', $school_year->id)->exists()) {
            $school_year->update(['is_active' => false]);
        }

        $action = ActionType::Updated->value;
        $module = 'School Years';
        $description = 'Restored school year ' . $school_year_name;
        $this->setAuditLog($action, $module, $description, null, $school_year->toArray());

        $this->logAdminActivity('updated_school_year', 'Restored school year ' . $school_year_name);

        return response()->json([
            'message' => 'School year restored successfully!',
        ]);
    }
}