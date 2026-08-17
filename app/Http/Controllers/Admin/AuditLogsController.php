<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditLogsResource;
use App\Models\Admin\AuditLog;
use App\Traits\UserRolesTrait;

class AuditLogsController extends Controller
{
    use UserRolesTrait;

    public function index()
    {
        $user_id = $this->checkHasRoleSystemAdministrator() ? null : $this->callLoggedUser()->id;

        $paginate = request('paginate');
        $search = request('search', '');
        $user = request('user');
        $action = request('action');
        $sort_order = request('sort_order', 'desc');
        $sort_field = request('sort_field', 'created_at');

        if (! in_array($sort_order, ['asc', 'desc'])) {
            $sort_order = 'desc';
        }

        if (! in_array($sort_field, ['id', 'action', 'module', 'created_at'])) {
            $sort_field = 'created_at';
        }

        $audit_logs = AuditLog::with('user')
            ->when($user_id, function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            })
            ->when($user, function ($query) use ($user) {
                $query->where('user_id', $user);
            })
            ->when($action, function ($query) use ($action) {
                $query->where('action', $action);
            })
            ->search(trim($search))
            ->orderBy($sort_field, $sort_order)
            ->paginate($paginate);

        return AuditLogsResource::collection($audit_logs);
    }
}
