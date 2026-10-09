<?php

namespace Database\Seeders;

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Traits\ConstantsTrait;
use Illuminate\Database\Seeder;

class EncoderPermissionsSeeder extends Seeder
{
    use ConstantsTrait;

    /**
     * Default permissions granted to the Encoder role on a fresh install.
     */
    private const ENCODER_PERMISSIONS = [
        'browse dashboard',
        'browse applicant-accounts',
        'read applicant-accounts',
        'update applicant-accounts',
        'browse applications',
        'read applications',
        'update applications',
        'read applicants',
        'browse exam-scheduling',
        'create exam-scheduling',
        'update exam-scheduling',
        'browse reports',
        'browse audit-logs',
        'browse academic-setup',
        'browse school-years',
        'browse programs',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::where('name', $this->ENCODER)->first();

        if (! $role) {
            return;
        }

        $permission_ids = Permission::whereIn('name', self::ENCODER_PERMISSIONS)->pluck('id');

        $role->permissions()->syncWithoutDetaching($permission_ids);
    }
}
