<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesTableSeeder::class,
            ProgramsSeeder::class,
            RequirementsTableSeeder::class,
            UsersTableSeeder::class,
            PermissionTitlesSeeder::class,
            EncoderPermissionsSeeder::class,
            CoordinatorPermissionsSeeder::class,
            FaqsTableSeeder::class,
        ]);
    }
}
