<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\Admin\Program;

class ProgramsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = File::get("database/data/admin/programs.json");
        $programs = json_decode($file);

        foreach ($programs as $program) {
            Program::firstOrCreate(
                ['initials' => $program->initials],
                [
                    'name'        => $program->name,
                    'description' => $program->description,
                ]
            );
        }
    }
}
