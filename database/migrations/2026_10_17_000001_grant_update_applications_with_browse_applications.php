<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Access to the Applications page implies the ability to act on those
     * applications, so roles already granted "browse applications" also get
     * "update applications" (approve/decline) and "read applicants" (open the
     * application) backfilled here.
     */
    public function up(): void
    {
        foreach (['update applications', 'read applicants'] as $permissionName) {
            $permissionId = DB::table('permissions')->where('name', $permissionName)->value('id');

            if (! $permissionId) {
                continue;
            }

            $this->assignToRoles(
                $permissionId,
                DB::table('role_permissions as assignments')
                    ->join('permissions', 'permissions.id', '=', 'assignments.permission_id')
                    ->where('permissions.name', 'browse applications')
                    ->distinct()
                    ->pluck('assignments.role_id')
            );
        }
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
