<?php

namespace Database\Seeders;

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Traits\ConstantsTrait;
use Illuminate\Database\Seeder;

class CoordinatorPermissionsSeeder extends Seeder
{
    use ConstantsTrait;

    /**
     * Permission slugs granted to the Coordinator role on a fresh install.
     *
     * Deliberately omitted:
     * - permission-titles (global security model, System Administrator only)
     * - system-management (it is the permission that unlocks permission
     *   titles and landing page settings through their "|" route alternatives)
     *
     * "roles" is intentionally not in this list: Coordinators get it through
     * COORDINATOR_EDIT_ONLY_SLUGS with edit rights only, so they can maintain
     * Staff (Encoder) accounts but never create or delete roles.
     */
    private const COORDINATOR_PERMISSION_SLUGS = [
        'dashboard',
        'applicant-accounts',
        'applications',
        'applicants',
        'exam-scheduling',
        'academic-setup',
        'school-years',
        'programs',
        'users',
        'settings',
        'online-application',
        'reports',
        'audit-logs',
    ];

    /**
     * Slugs granted with only these actions, so Coordinators can view and edit
     * Staff roles but cannot create or delete any role.
     */
    private const COORDINATOR_EDIT_ONLY_SLUGS = [
        'roles' => ['browse', 'read', 'update'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::where('name', $this->COORDINATOR)->first();

        if (! $role) {
            return;
        }

        $names = [];
        foreach (self::COORDINATOR_PERMISSION_SLUGS as $slug) {
            foreach ($this->PERMISSIONS_ACTION as $action) {
                $names[] = "$action $slug";
            }
        }

        foreach (self::COORDINATOR_EDIT_ONLY_SLUGS as $slug => $actions) {
            foreach ($actions as $action) {
                $names[] = "$action $slug";
            }
        }

        $permission_ids = Permission::whereIn('name', $names)->pluck('id');

        $role->permissions()->syncWithoutDetaching($permission_ids);
    }
}
