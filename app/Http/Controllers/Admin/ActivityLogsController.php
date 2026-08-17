<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogsController extends Controller
{
    public function index(Request $request)
    {
        $paginate = $request->get('paginate', 10);
        $search = trim($request->get('search', ''));
        $action = $request->get('action');
        $sort_order = $request->get('sort_order', 'desc');

        if (! in_array($sort_order, ['asc', 'desc'])) {
            $sort_order = 'desc';
        }

        $logs = ActivityLog::with('applicant')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('applicant', function ($q) use ($search) {
                    $q->search($search);
                });
            })
            ->when($action, function ($query) use ($action) {
                $query->where('action', $action);
            })
            ->orderBy('created_at', $sort_order)
            ->paginate($paginate);

        $logs->through(function ($log) {
            $applicant = $log->applicant;

            return [
                'id' => $log->id,
                'applicant_id' => $log->applicant_id,
                'applicant_name' => $applicant
                    ? trim($applicant->firstname.' '.$applicant->lastname.($applicant->extname ? ' '.$applicant->extname : ''))
                    : 'Deleted applicant',
                'applicant_email' => $applicant?->email,
                'action' => $log->action,
                'description' => $log->description,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($logs);
    }
}
