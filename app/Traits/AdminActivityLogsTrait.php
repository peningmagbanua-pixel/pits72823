<?php

namespace App\Traits;

use App\Models\Admin\AdminActivityLog;
use App\Traits\UserRolesTrait;

trait AdminActivityLogsTrait
{
    use UserRolesTrait;

    public function logAdminActivity(string $action, string $description, ?array $metadata = null)
    {
        $user = $this->callLoggedUser();

        return AdminActivityLog::logActivity(
            $user?->id,
            $action,
            $description,
            $metadata,
            request()->ip(),
            request()->userAgent()
        );
    }
}