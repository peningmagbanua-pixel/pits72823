<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Role;
use App\Models\Admin\Permission;
use App\Models\Admin\PermissionTitle;
use App\Traits\AuditLogsTrait;
use App\Traits\ConstantsTrait;
use App\Enums\ActionType;
use Illuminate\Support\Collection;

class PermissionsController extends Controller
{
    use AuditLogsTrait, ConstantsTrait;

    public function getRolePermissions($role_id)
    {
        $role = Role::with('permissions')->where('id', $role_id)->first();

        if (!$role) {
            return response()->json([
                'message' => 'Role not found!',
            ], 404);
        }

        if (! $this->checkHasRoleSystemAdministrator() && in_array($role->name, [$this->SYSTEM_ADMINISTRATOR, $this->COORDINATOR], true)) {
            return response()->json([
                'message' => 'You can only edit Staff roles.',
            ], 403);
        }

        $permission_titles = PermissionTitle::with(['permissions' => function ($query) {
            $query->orderBy('name');
        }])->get();
        
        $role_permission_ids = $this->withImpliedApplicationPermissions(
            collect($role->permissions->pluck('id')->toArray())
        );

        // Non-admins may only be offered the permissions they hold themselves,
        // so editing a Staff role can never mint a capability they lack.
        $grantable = $this->grantablePermissionNames();

        $grouped_permissions = [];
        foreach ($permission_titles as $title) {
            $granted_permissions = $grantable === null
                ? $title->permissions
                : $title->permissions->filter(fn ($permission) => $grantable->contains($permission->name));

            $grouped_permissions[] = [
                'title' => $title->name,
                // values() re-indexes after filter() so the group serializes as a
                // JSON array; with key gaps json_encode emits an object and the
                // permissions modal crashes on group.permissions.forEach.
                'permissions' => $granted_permissions->map(function ($permission) use ($role_permission_ids) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'checked' => in_array($permission->id, $role_permission_ids->all()),
                    ];
                })->values(),
            ];
        }

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
            ],
            'permissions' => $grouped_permissions,
        ]);
    }

    public function updateRolePermissions(Request $request, $roleId)
    {
        $role = Role::where('id', $roleId)->first();

        if (!$role) {
            return response()->json([
                'message' => 'Role not found!',
            ], 404);
        }

        if (! $this->checkHasRoleSystemAdministrator() && in_array($role->name, [$this->SYSTEM_ADMINISTRATOR, $this->COORDINATOR], true)) {
            abort(403, 'You can only edit Staff roles.');
        }

        $validated = $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $requested = $this->withImpliedApplicationPermissions(
            collect($validated['permissions'] ?? [])
        );

        $grantable = $this->grantablePermissionNames();

        if ($grantable !== null) {
            $requested_names = Permission::whereIn('id', $requested)->pluck('name');
            $foreign = $requested_names->diff($grantable);

            if ($foreign->isNotEmpty()) {
                abort(403, 'You cannot grant permissions you do not have.');
            }
        }

        $old_values = $role->permissions->pluck('id')->toArray();

        // Sync permissions (replace all with selected)
        $role->permissions()->sync($requested->all());

        $action = ActionType::Updated->value;
        $module = 'Role Permissions';
        $description = 'Updated permissions for role ' . $role->name;
        $this->setAuditLog($action, $module, $description, ['permissions' => $old_values], ['permissions' => $requested->all()]);

        return response()->json([
            'message' => 'Permissions updated successfully!',
        ]);
    }

    public function checkPermissions($category)
    {
        $lowercase_category = strtolower($category);

        $valid_categories = PermissionTitle::pluck('name')
            ->map(fn ($name) => strtolower(str_replace(' ', '-', $name)));

        abort_unless($valid_categories->contains($lowercase_category), 404);

        $auth_user = $this->callLoggedUser();

        return [
            'browse' => $auth_user->can("browse $lowercase_category"),
            'read' => $auth_user->can("read $lowercase_category"),
            'update' => $auth_user->can("update $lowercase_category"),
            'create' => $auth_user->can("create $lowercase_category"),
            'delete' => $auth_user->can("delete $lowercase_category"),
        ];
    }

    /**
     * Granting access to the Applications page also gives the role everything it
     * needs to act on those applications: open a student's application
     * (read applicants) and approve or decline it (update applications).
     */
    private function withImpliedApplicationPermissions(Collection $permission_ids): Collection
    {
        $names = Permission::whereIn('id', $permission_ids)->pluck('name');

        if (! $names->contains('browse applications')) {
            return $permission_ids;
        }

        $implied = Permission::whereIn('name', ['update applications', 'read applicants'])->pluck('id');

        return $permission_ids->merge($implied)->unique()->values();
    }

    /**
     * The permission names the caller is allowed to delegate, or null when the
     * caller is a System Administrator (unrestricted).
     */
    private function grantablePermissionNames()
    {
        if ($this->checkHasRoleSystemAdministrator()) {
            return null;
        }

        return $this->callLoggedUser()
            ->roles->flatMap(fn ($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }
}
