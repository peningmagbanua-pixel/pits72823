<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissionIds = [];

        foreach (['Dashboard', 'Applicants', 'Online Application'] as $titleName) {
            $title = DB::table('permission_titles')->where('name', $titleName)->first();

            if (! $title) {
                $titleId = (string) Str::uuid();
                DB::table('permission_titles')->insert([
                    'id' => $titleId,
                    'name' => $titleName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $titleId = $title->id;
            }

            $permissionTitle = strtolower(str_replace(' ', '-', $titleName));

            foreach (['browse', 'read', 'create', 'update', 'delete'] as $action) {
                $permissionName = "$action $permissionTitle";

                if (! DB::table('permissions')
                    ->where('name', $permissionName)
                    ->where('permission_title_id', $titleId)
                    ->exists()) {
                    $id = (string) Str::uuid();
                    DB::table('permissions')->insert([
                        'id' => $id,
                        'name' => $permissionName,
                        'permission_title_id' => $titleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $permissionIds[$permissionName] = DB::table('permissions')
                    ->where('name', $permissionName)
                    ->where('permission_title_id', $titleId)
                    ->value('id');
            }
        }

        $this->assignToRoles(
            $permissionIds['browse dashboard'],
            DB::table('role_users')->distinct()->pluck('role_id')
        );

        $this->assignToRoles(
            $permissionIds['read applicants'],
            DB::table('role_permissions as assignments')
                ->join('permissions', 'permissions.id', '=', 'assignments.permission_id')
                ->where('permissions.name', 'browse applications')
                ->distinct()
                ->pluck('assignments.role_id')
        );

        $this->assignToRoles(
            $permissionIds['browse online-application'],
            DB::table('role_permissions as assignments')
                ->join('permissions', 'permissions.id', '=', 'assignments.permission_id')
                ->whereIn('permissions.name', ['browse settings', 'browse system-management'])
                ->distinct()
                ->pluck('assignments.role_id')
        );

        $this->assignToRoles(
            $permissionIds['update online-application'],
            DB::table('role_permissions as assignments')
                ->join('permissions', 'permissions.id', '=', 'assignments.permission_id')
                ->where('permissions.name', 'update settings')
                ->distinct()
                ->pluck('assignments.role_id')
        );
    }

    public function down(): void
    {
        // Preserve role permissions that may have been changed by administrators after deployment.
    }

    private function assignToRoles(string $permissionId, iterable $roleIds): void
    {
        $existingRoleIds = DB::table('role_permissions')
            ->where('permission_id', $permissionId)
            ->pluck('role_id')
            ->all();

        $now = now();
        $assignments = collect($roleIds)
            ->unique()
            ->diff($existingRoleIds)
            ->map(fn ($roleId) => [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($assignments !== []) {
            DB::table('role_permissions')->insert($assignments);
        }
    }
};
