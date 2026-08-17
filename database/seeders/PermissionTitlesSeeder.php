<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\Admin\PermissionTitle;
use App\Models\Admin\Permission;
use App\Traits\ConstantsTrait;

class PermissionTitlesSeeder extends Seeder
{
    use ConstantsTrait;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = File::get("database/data/admin/permission-titles.json");
        $permission_titles = json_decode($file);

        foreach ($permission_titles as $permission_title) {
            $title = PermissionTitle::firstOrCreate(
                [
                    'name' => $permission_title->name
                ]
            );

            $permission_title_name = strtolower(str_replace(' ', '-', $permission_title->name));

            foreach ($this->PERMISSIONS_ACTION as $action) {
                Permission::firstOrCreate(
                    [
                        'name' => "$action $permission_title_name",
                        'permission_title_id' => $title->id,
                    ]
                );
            }
        }
    }
}
