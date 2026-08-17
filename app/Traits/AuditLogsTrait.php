<?php

namespace App\Traits;

use App\Models\Admin\AuditLog;
use App\Traits\UserRolesTrait;

trait AuditLogsTrait
{
    use UserRolesTrait;
    public function setAuditLog( $action, $module, $description, $old_values = null, ?array $new_values = null)
    {
        $log = new AuditLog();
        $log->user_id = $this->callLoggeduser()->id;
        $log->action = $action;
        $log->module = $module;
        $log->description = $description;
        $log->old_values = $old_values ? json_encode($old_values) : null;
        $log->new_values = $new_values ? json_encode($new_values) : null;
        $log->save();
    }
}
