<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\PermissionTitle;

class CheckPermissions extends Command
{
    protected $signature = 'app:check-permissions';
    protected $description = 'Check all permissions and role assignments';

    public function handle()
    {
        $this->line('=== PERMISSION TITLES AND PERMISSIONS ===');
        $titles = PermissionTitle::with('permissions')->get();
        
        foreach ($titles as $t) {
            $this->line($t->name . ':');
            foreach ($t->permissions as $p) {
                $this->line("  - {$p->name}");
            }
        }
    }
}
