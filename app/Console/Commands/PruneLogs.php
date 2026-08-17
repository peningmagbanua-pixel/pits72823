<?php

namespace App\Console\Commands;

use App\Models\Admin\AuditLog;
use App\Models\Applicant\ActivityLog;
use Illuminate\Console\Command;

class PruneLogs extends Command
{
    /** @var int Number of days of log history to keep */
    protected const RETENTION_DAYS = 30;

    protected $signature = 'app:prune-logs';

    protected $description = 'Delete audit logs and applicant activity logs older than 30 days';

    public function handle()
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        $auditDeleted = AuditLog::where('created_at', '<', $cutoff)->delete();
        $activityDeleted = ActivityLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$auditDeleted} audit log(s) and {$activityDeleted} applicant activity log(s) older than ".self::RETENTION_DAYS.' days.');

        return self::SUCCESS;
    }
}
