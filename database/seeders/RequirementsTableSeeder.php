<?php

namespace Database\Seeders;

use App\Models\Admin\Program;
use App\Models\Admin\Requirement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class RequirementsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = File::get('database/data/admin/requirements.json');
        $requirements = json_decode($file);

        foreach ($requirements as $requirement) {
            $created = Requirement::firstOrCreate(
                [
                    'name' => $requirement->name,
                ],
                [
                    'description' => $requirement->description ?? null,
                    'notice' => $requirement->notice ?? null,
                    'field_key' => $requirement->field_key ?? null,
                    'is_required' => $requirement->is_required ?? true,
                    'is_active' => $requirement->is_active ?? true,
                    'sort_order' => $requirement->sort_order ?? 0,
                ]
            );

            if (! $created->wasRecentlyCreated) {
                continue;
            }

            // Programs are listed by initials and resolved against the
            // programs table; an empty list means every program.
            $programIds = Program::whereIn('initials', $requirement->programs ?? [])
                ->pluck('id')
                ->all();

            $created->programs()->sync($programIds);
        }
    }
}
