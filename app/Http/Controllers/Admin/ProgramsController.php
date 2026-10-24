<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ProgramResource;
use Illuminate\Http\Request;
use App\Models\Admin\Program;
use App\Traits\AuditLogsTrait;
use App\Traits\AdminActivityLogsTrait;
use App\Traits\ProgramScopeTrait;
use App\Enums\ActionType;

class ProgramsController extends Controller
{
    use AuditLogsTrait, AdminActivityLogsTrait, ProgramScopeTrait;
    public function index()
    {
        $paginate = request('paginate');
        $search = request('search', '');
        $sort_order = request('sort_order', 'desc');
        $sort_field = request('sort_field', 'created_at');
        $deleted = request('deleted') == '1';

        if (!in_array($sort_order, ['asc', 'desc'])) {
            $sort_order = 'desc';
        }

        if (!in_array($sort_field, ['id', 'initials', 'name', 'created_at'])) {
            $sort_field = 'created_at';
        }

        $program = $this->applyProgramCodeScope(Program::search(trim($search)))
            ->when($deleted, fn ($query) => $query->onlyTrashed())
            ->orderBy($sort_field, $sort_order)
            ->paginate($paginate);

        return ProgramResource::collection($program);
    }

    public function store(Request $request)
    {
        // Creating a new program is a System Administrator task; coordinators
        // may manage their designated programs but never add new ones.
        if (! $this->checkHasRoleSystemAdministrator() && $this->checkHasRoleCoordinator()) {
            abort(403, 'Coordinators cannot create programs.');
        }

        $validated = $request->validate([
            'initials' => 'required',
            'name' => 'required',
            'description' => 'nullable',
        ]);

        $program = Program::where('initials', $validated['initials'])
            ->where('name', $validated['name'])->first();

        if ($program) {
            return response()->json([
                'message' => 'Duplicate program!',
            ], 400);
        }
        
        $program = Program::create([
            'initials' => $validated['initials'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $action = ActionType::Created->value;
        $module = 'Programs';
        $description = 'Created program ' . $validated['initials'] . ' - ' . $validated['name'];
        $this->setAuditLog($action, $module, $description, null, $program->toArray());

        $this->logAdminActivity('created_program', 'Created program ' . $validated['initials'] . ' - ' . $validated['name']);

        return response()->json([
            'message' => 'Program created successfully!'
        ]);
    }

    public function update(Request $request, $id)
    {
        $program = Program::findOrFail($id);

        if (!$program) {
            return response()->json([
                'message' => 'Program not found!'
            ], 404);
        }

        $validated = $request->validate([
            'initials' => 'required',
            'name' => 'required',
            'description' => 'nullable',
            'status' => 'required'
        ]);

        $old_values = $program->getOriginal();

        $program->update([
            'initials' => $validated['initials'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'is_active' => $validated['status'],
        ]);

        $action = ActionType::Updated->value;
        $module = 'Programs';
        $description = 'Updated program ' . $validated['initials'] . ' - ' . $validated['name'];
        $this->setAuditLog($action, $module, $description, $old_values, $program->toArray());

        $this->logAdminActivity('updated_program', 'Updated program ' . $validated['initials'] . ' - ' . $validated['name']);

        return response()->json([
            'message' => 'Program updated successfully!'
        ]);
    }

    public function delete($id)
    {
        $program = Program::findOrFail($id);

        if (!$program) {
            return response()->json([
                'message' => 'Program not found!'
            ], 404);
        }

        $old_values = $program->getOriginal();
        $program_name = $program->initials . ' - ' . $program->name;

        if ($program->delete()) {
            $action = ActionType::Deleted->value;
            $module = 'Programs';
            $description = 'Deleted program ' . $program_name;
            $this->setAuditLog($action, $module, $description, $old_values, null);

            $this->logAdminActivity('deleted_program', 'Deleted program ' . $program_name);

            return response()->json([
                'message' => 'Program deleted successfully!'
            ]);
        }
    }

    public function restore($id)
    {
        $program = Program::withTrashed()->findOrFail($id);

        if (!$program->trashed()) {
            return response()->json([
                'message' => 'Program is not deleted!'
            ], 400);
        }

        $program_name = $program->initials . ' - ' . $program->name;

        $program->restore();

        $action = ActionType::Updated->value;
        $module = 'Programs';
        $description = 'Restored program ' . $program_name;
        $this->setAuditLog($action, $module, $description, null, $program->toArray());

        $this->logAdminActivity('updated_program', 'Restored program ' . $program_name);

        return response()->json([
            'message' => 'Program restored successfully!'
        ]);
    }
}
