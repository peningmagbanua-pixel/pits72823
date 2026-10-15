<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\PermissionTitle;
use Illuminate\Http\Request;
use App\Models\Admin\Role;
use App\Http\Resources\Admin\RolesResource;
use App\Traits\AuditLogsTrait;
use App\Traits\ConstantsTrait;
use App\Traits\UserRolesTrait;
use App\Enums\ActionType;

class RolesController extends Controller
{
    use AuditLogsTrait, ConstantsTrait, UserRolesTrait;

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

        $roles = Role::search(trim($search))
            ->when(request('deleted') == '1', fn ($query) => $query->onlyTrashed())
            ->when(! $this->checkHasRoleSystemAdministrator(), fn ($query) => $query->whereNotIn('name', [$this->SYSTEM_ADMINISTRATOR, $this->COORDINATOR]))
            ->orderBy($sort_field, $sort_order)
            ->paginate($paginate);

        return RolesResource::collection($roles);
    }

    public function store(Request $request)
    {
        if ($request->filled('name') && Role::onlyTrashed()->where('name', $request->name)->exists()) {
            return response()->json([
                'message' => 'A deleted role with this name exists. Restore it instead.',
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
        ]);

        $action = ActionType::Created->value;
        $module = 'Roles';
        $description = 'Created role ' . $role->id;
        $this->setAuditLog($action, $module, $description, null, $role->toArray());

        return response()->json([
            'message' => 'Role created successfully!',
        ]);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        if (!$role) {
            return response()->json([
                'message' => 'Role not found!',
            ], 404);
        }

        if ($role->name === $this->SYSTEM_ADMINISTRATOR) {
            return response()->json([
                'message' => 'Cannot update System Administrator role!',
            ], 403);
        }

        if (! $this->checkHasRoleSystemAdministrator() && $role->name === $this->COORDINATOR) {
            return response()->json([
                'message' => 'You can only edit Staff roles.',
            ], 403);
        }

        if ($request->filled('name') && Role::onlyTrashed()
            ->where('name', $request->name)
            ->where('id', '!=', $id)
            ->exists()) {
            return response()->json([
                'message' => 'A deleted role with this name exists. Restore it instead.',
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $id,
        ]);

        $old_values = $role->getOriginal();

        $role->update([
            'name' => $validated['name'],
        ]);

        $action = ActionType::Updated->value;
        $module = 'Roles';
        $description = 'Updated role ' . $role->id;
        $this->setAuditLog($action, $module, $description, $old_values, $role->toArray());

        return response()->json([
            'message' => 'Role updated successfully!',
        ]);
    }

    public function delete($id)
    {
        $role = Role::findOrFail($id);

        if (!$role) {
            return response()->json([
                'message' => 'Role not found!',
            ], 404);
        }

        if ($role->name === $this->SYSTEM_ADMINISTRATOR) {
            return response()->json([
                'message' => 'Cannot delete System Administrator role!',
            ], 403);
        }

        $action = ActionType::Deleted->value;
        $module = 'Roles';
        $description = 'Deleted role ' . $role->id;
        $old_values = $role->getOriginal();

        if ($role->delete()) {
            $this->setAuditLog($action, $module, $description, $old_values, null);
            return response()->json([
                'message' => 'Role deleted successfully!',
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to delete role!',
            ], 500);
        }
    }

    public function restore($id)
    {
        $role = Role::withTrashed()->findOrFail($id);

        if (!$role->trashed()) {
            return response()->json([
                'message' => 'Role is not deleted!',
            ], 400);
        }

        $role->restore();

        $action = ActionType::Updated->value;
        $module = 'Roles';
        $description = 'Restored role ' . $role->id;
        $this->setAuditLog($action, $module, $description, null, $role->toArray());

        return response()->json([
            'message' => 'Role restored successfully!',
        ]);
    }

    public function list()
    {
        $roles = Role::where('name', '!=', $this->SYSTEM_ADMINISTRATOR);

        if (! $this->checkHasRoleSystemAdministrator()) {
            $roles->where('name', '!=', $this->COORDINATOR);
        }

        return $roles->get();
    }

    public function allRoles()
    {
        $roles = Role::orderBy('name', 'ASC');

        // Non-admins (e.g. Coordinators creating accounts) may not assign
        // privileged roles, so they are never offered here.
        if (! $this->checkHasRoleSystemAdministrator()) {
            $roles->whereNotIn('name', [$this->SYSTEM_ADMINISTRATOR, $this->COORDINATOR]);
        }

        return $roles->get();
    }

    public function getRoleUsers($id)
    {
        $is_admin = $this->checkHasRoleSystemAdministrator();

        $allowed = $is_admin ? null : $this->callLoggedUser()->programCodes();

        $role = Role::with(['users' => function ($query) use ($allowed) {
            $query->orderBy('firstname', 'asc');

            if ($allowed !== null) {
                if (empty($allowed)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query
                        ->whereHas('program_assignments', fn ($q) => $q->whereIn('program', $allowed))
                        ->whereDoesntHave('program_assignments', fn ($q) => $q->whereNotIn('program', $allowed))
                        ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', [$this->SYSTEM_ADMINISTRATOR, $this->COORDINATOR]));
                }
            }
        }])->where('id', $id)->first();

        if (!$role) {
            return response()->json([
                'message' => 'Role not found!',
            ], 404);
        }

        if (! $is_admin && in_array($role->name, [$this->SYSTEM_ADMINISTRATOR, $this->COORDINATOR], true)) {
            return response()->json([
                'message' => 'You can only view Staff roles.',
            ], 403);
        }

        $users = $role->users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->firstname . ' ' . $user->lastname . ($user->extname ? ' ' . $user->extname : ''),
                'email' => $user->email,
                'status_color' => $user->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger',
                'status_text' => $user->is_active ? 'Active' : 'Inactive',
            ];
        });

        return response()->json([
            'users' => $users,
        ]);
    }
    public function getPermissionMatrix()
    {
        $roles = Role::with('permissions')
            ->when(! $this->checkHasRoleSystemAdministrator(), fn ($query) => $query->whereNotIn('name', [$this->SYSTEM_ADMINISTRATOR, $this->COORDINATOR]))
            ->orderBy('name', 'asc')->get();

        $permissionTitles = PermissionTitle::with(['permissions' => function ($query) {
            $query->orderBy('name', 'asc'); 
        }])->orderBy('name', 'asc')->get();

        $rolesData = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('id')->toArray(),
            ];
        });

        $permissionTitlesData = $permissionTitles->map(function ($title) {
            return [
                'id' => $title->id,
                'name' => $title->name,
                'permissions' => $title->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                    ];
                }),
            ];
        });

        return response()->json([
            'roles' => $rolesData,
            'permission_titles' => $permissionTitlesData,
        ]);
    }
}
