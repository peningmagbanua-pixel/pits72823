<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\Admin\Role;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = File::get("database/data/admin/roles.json");
        $roles = json_decode($file);

        foreach ($roles as $role) {
            Role::firstOrCreate(
                [
                    'name' => $role->name
                ]
            );
        }
    }
}
