<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\PermissionTitle;
use App\Models\Admin\Permission;
use App\Http\Resources\Admin\PermissionTitlesResource;
use App\Traits\AuditLogsTrait;
use App\Traits\ConstantsTrait;
use App\Enums\ActionType;

class PermissionTitlesController extends Controller
{
    use AuditLogsTrait, ConstantsTrait;

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

        $permissionTitles = PermissionTitle::withCount('permissions')
            ->when(request('deleted') == '1', fn ($query) => $query->onlyTrashed())
            ->orderBy('name', 'asc')
            ->search(trim($search))
            ->orderBy($sort_field, $sort_order)
            ->paginate($paginate);

        return PermissionTitlesResource::collection($permissionTitles);
    }

    public function store(Request $request)
    {
        if ($request->filled('name') && PermissionTitle::onlyTrashed()->where('name', $request->name)->exists()) {
            return response()->json([
                'message' => 'A deleted permission title with this name exists. Restore it instead.',
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permission_titles,name',
        ]);

        $permission_title = PermissionTitle::create([
            'name' => $validated['name'],
        ]);

        if ($permission_title->save()) {
            $permissions_action = $this->PERMISSIONS_ACTION;

            $change_space_to_dash = str_replace(" ", "-", $permission_title->name);
            $permission_title_name = strtolower($change_space_to_dash);

            foreach ($permissions_action as $action) {
                $permission_name = "$action $permission_title_name";
                Permission::create([
                    'name' => $permission_name,
                    'permission_title_id' => $permission_title->id,
                ]);
            }

            $this->setAuditLog(ActionType::Created->value, 'Permission Titles', 'Created permission title ' . $permission_title->id, null, $permission_title->toArray());

            return response()->json([
                'message' => 'Permission title created successfully!',
            ]);
        }

        return response()->json([
            'message' => 'Failed to create permission title!',
        ], 500);
    }

    public function update(Request $request, $id)
    {
        $permission_title = PermissionTitle::with('permissions')->findOrFail($id);

        if (!$permission_title) {
            return response()->json([
                'message' => 'Permission title not found!',
            ], 404);
        }

        if ($request->filled('name') && PermissionTitle::onlyTrashed()
            ->where('name', $request->name)
            ->where('id', '!=', $id)
            ->exists()) {
            return response()->json([
                'message' => 'A deleted permission title with this name exists. Restore it instead.',
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permission_titles,name,' . $id,
        ]);

        $old_values = $permission_title->getOriginal();

        // Update title
        $permission_title->update([
            'name' => $validated['name'],
        ]);

        // Same formatting logic as store()
        $change_space_to_dash = str_replace(" ", "-", $validated['name']);
        $permission_title_name = strtolower($change_space_to_dash);

        // Rename each permission, preserving its action prefix
        foreach ($permission_title->permissions as $permission) {
            $parts = explode(' ', $permission->name);
            $action = $parts[0] ?? '';

            $permission->update([
                'name' => "$action $permission_title_name",
            ]);
        }

        $this->setAuditLog(ActionType::Updated->value, 'Permission Titles', 'Updated permission title ' . $permission_title->id, $old_values, $permission_title->toArray());

        return response()->json([
            'message' => 'Permission title updated successfully!',
        ]);
    }

    public function delete($id)
    {
        $permission_title = PermissionTitle::findOrFail($id);

        if (!$permission_title) {
            return response()->json([
                'message' => 'Permission title not found!',
            ], 404);
        }

        $action = ActionType::Deleted->value;
        $module = 'Permission Titles';
        $description = 'Deleted permission title ' . $permission_title->id;
        $old_values = $permission_title->getOriginal();

        if ($permission_title->delete()) {
            $this->setAuditLog($action, $module, $description, $old_values, null);
            return response()->json([
                'message' => 'Permission title deleted successfully!',
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to delete permission title!',
            ], 500);
        }
    }

    public function restore($id)
    {
        $permission_title = PermissionTitle::withTrashed()->findOrFail($id);

        if (!$permission_title->trashed()) {
            return response()->json([
                'message' => 'Permission title is not deleted!',
            ], 400);
        }

        $permission_title->restore();

        $this->setAuditLog(
            ActionType::Updated->value,
            'Permission Titles',
            'Restored permission title ' . $permission_title->id,
            null,
            $permission_title->toArray()
        );

        return response()->json([
            'message' => 'Permission title restored successfully!',
        ]);
    }
}
