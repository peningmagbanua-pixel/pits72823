<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MoveApplicantFiles extends Command
{
    protected $signature = 'app:move-applicant-files';

    protected $description = 'Move applicant-uploaded files from the public disk to the private disk';

    public function handle()
    {
        $files = Storage::disk('public')->allFiles('applications');

        $moved = 0;

        foreach ($files as $file) {
            // Skip leftover temp/interrupted-upload directories
            if (str_contains($file, 'temp-')) {
                continue;
            }

            if (!Storage::disk('local')->exists($file)) {
                Storage::disk('local')->put($file, Storage::disk('public')->get($file));
            }

            Storage::disk('public')->delete($file);
            $moved++;
        }

        $this->info("Moved {$moved} applicant file(s) to the private disk.");

        return self::SUCCESS;
    }
}
