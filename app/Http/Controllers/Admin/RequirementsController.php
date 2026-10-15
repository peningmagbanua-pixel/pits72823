<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RequirementsResource;
use App\Models\Admin\Requirement;
use App\Traits\AuditLogsTrait;
use App\Traits\ProgramScopeTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequirementsController extends Controller
{
    use AuditLogsTrait, ProgramScopeTrait;

    public function index()
    {
        $requirements = Requirement::with(['programs' => fn ($programs) => $programs->withTrashed()])
            ->when(! $this->checkHasRoleSystemAdministrator(), fn ($query) => $this->scopeToCallerPrograms($query))
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return RequirementsResource::collection($requirements);
    }

    public function store(Request $request)
    {
        if ($request->filled('name') && Requirement::onlyTrashed()->where('name', $request->name)->exists()) {
            return response()->json([
                'message' => 'A deleted requirement with this name exists. Restore it instead.',
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:requirements,name',
            'description' => 'nullable|string|max:1000',
            'notice' => 'nullable|string|max:1000',
            'program_ids' => 'sometimes|array',
            'program_ids.*' => $this->programIdRule(),
            'is_required' => 'required|boolean',
            'is_active' => 'required|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $this->assertRequirementProgramsAllowed($validated['program_ids'] ?? []);

        $requirement = Requirement::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'notice' => $validated['notice'] ?? null,
            'is_required' => $validated['is_required'],
            'is_active' => $validated['is_active'],
            'sort_order' => $validated['sort_order'] ?? (Requirement::max('sort_order') + 1),
        ]);

        $requirement->programs()->sync($validated['program_ids'] ?? []);

        $this->setAuditLog(ActionType::Created->value, 'Settings', 'Created requirement '.$requirement->id, null, $requirement->toArray());

        return response()->json([
            'message' => 'Requirement created successfully!',
        ]);
    }

    public function update(Request $request, $id)
    {
        $requirement = Requirement::findOrFail($id);

        if ($request->filled('name') && Requirement::onlyTrashed()
            ->where('name', $request->name)
            ->where('id', '!=', $id)
            ->exists()) {
            return response()->json([
                'message' => 'A deleted requirement with this name exists. Restore it instead.',
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:requirements,name,'.$id,
            'description' => 'nullable|string|max:1000',
            'notice' => 'nullable|string|max:1000',
            'program_ids' => 'sometimes|array',
            'program_ids.*' => $this->programIdRule(),
            'is_required' => 'required|boolean',
            'is_active' => 'required|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // The requirement may only be touched when its current assignment and
        // the requested one both sit inside the caller's own programs.
        $this->assertRequirementProgramsAllowed($requirement->programs()->withTrashed()->get()->pluck('id')->all());

        if (array_key_exists('program_ids', $validated)) {
            $this->assertRequirementProgramsAllowed($validated['program_ids']);
        }

        $old_values = $requirement->getOriginal();

        $requirement->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'notice' => $validated['notice'] ?? null,
            'is_required' => $validated['is_required'],
            'is_active' => $validated['is_active'],
            'sort_order' => $validated['sort_order'] ?? $requirement->sort_order,
        ]);

        // Only touched when the client sends the key, so an omitted payload
        // (the activate/deactivate toggle) never clears the assignment.
        if (array_key_exists('program_ids', $validated)) {
            $requirement->programs()->sync($validated['program_ids']);
        }

        $this->setAuditLog(ActionType::Updated->value, 'Settings', 'Updated requirement '.$requirement->id, $old_values, $requirement->toArray());

        return response()->json([
            'message' => 'Requirement updated successfully!',
        ]);
    }

    public function delete($id)
    {
        $requirement = Requirement::findOrFail($id);

        $this->assertRequirementProgramsAllowed($requirement->programs()->withTrashed()->get()->pluck('id')->all());

        $old_values = $requirement->getOriginal();

        if ($requirement->delete()) {
            $this->setAuditLog(ActionType::Deleted->value, 'Settings', 'Deleted requirement '.$requirement->id, $old_values, null);

            return response()->json([
                'message' => 'Requirement deleted successfully!',
            ]);
        }

        return response()->json([
            'message' => 'Failed to delete requirement!',
        ], 500);
    }

    /**
     * Programs are chosen from the programs table — never a hardcoded list —
     * and deleted programs are not assignable.
     *
     * @return array<int, mixed>
     */
    private function programIdRule(): array
    {
        return ['uuid', Rule::exists('programs', 'id')->whereNull('deleted_at')];
    }

    /**
     * Keep requirement writes inside the caller's own programs.
     * Empty assignments ("All Programs") would affect the other program too,
     * so they are System Administrator territory.
     *
     * @param  array<int, string>  $program_ids
     */
    private function assertRequirementProgramsAllowed(array $program_ids): void
    {
        if ($this->checkHasRoleSystemAdministrator()) {
            return;
        }

        if (empty($program_ids)) {
            abort(403, 'This requirement must be assigned to your programs.');
        }

        foreach ($program_ids as $program_id) {
            $this->assertProgramAllowed($program_id);
        }
    }

    /**
     * Only requirements relevant to the caller's programs are listed;
     * unassigned ("All Programs") requirements remain visible read-only.
     */
    private function scopeToCallerPrograms($query)
    {
        $programs = $this->scopedPrograms();

        if (empty($programs)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($query) use ($programs) {
            $query->whereDoesntHave('programs')
                ->orWhereHas('programs', fn ($programs_query) => $programs_query
                    ->withTrashed()
                    ->whereIn('initials', $programs));
        });
    }
}
