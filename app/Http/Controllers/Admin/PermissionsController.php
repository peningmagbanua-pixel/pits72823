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

        $permission_titles = PermissionTitle::with(['permissions' => function ($query) {
            $query->orderBy('name');
        }])->get();
        
        $role_permission_ids = $role->permissions->pluck('id')->toArray();

        $grouped_permissions = [];
        foreach ($permission_titles as $title) {
            $grouped_permissions[] = [
                'title' => $title->name,
                'permissions' => $title->permissions->map(function ($permission) use ($role_permission_ids) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'checked' => in_array($permission->id, $role_permission_ids),
                    ];
                }),
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

        $validated = $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $old_values = $role->permissions->pluck('id')->toArray();

        // Sync permissions (replace all with selected)
        $role->permissions()->sync($validated['permissions'] ?? []);

        $action = ActionType::Updated->value;
        $module = 'Role Permissions';
        $description = 'Updated permissions for role ' . $role->name;
        $this->setAuditLog($action, $module, $description, ['permissions' => $old_values], ['permissions' => $validated['permissions'] ?? []]);

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
}
