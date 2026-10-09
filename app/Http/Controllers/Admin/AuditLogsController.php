<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActionType;
use App\Enums\BadgeColor;
use App\Http\Controllers\Controller;
use App\Models\Admin\AdminActivityLog;
use App\Models\Admin\AuditLog;
use App\Models\Applicant\ActivityLog;
use App\Traits\FormatsTrait;
use App\Traits\UserRolesTrait;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditLogsController extends Controller
{
    use FormatsTrait, UserRolesTrait;

    /**
     * Modules derived from activity log actions.
     */
    private const ACTIVITY_MODULES = [
        'registered' => 'Authentication',
        'registered_via_google' => 'Authentication',
        'logged_in' => 'Authentication',
        'logged_in_via_google' => 'Authentication',
        'logged_out' => 'Authentication',
        'login_failed' => 'Authentication',
        'login_failed_inactive' => 'Authentication',
        'viewed_dashboard' => 'Dashboard',
        'viewed_application' => 'Applications',
        'application_submitted' => 'Applications',
        'status_approved' => 'Applications',
        'status_rejected' => 'Applications',
        'status_reset' => 'Applications',
        'approved_application' => 'Applications',
        'rejected_application' => 'Applications',
        'reset_application_status' => 'Applications',
        'viewed_applicants' => 'Applicants',
        'profile_updated' => 'Profile',
        'updated_applicant_account' => 'Applicant Accounts',
        'deleted_applicant_account' => 'Applicant Accounts',
        'created_program' => 'Programs',
        'updated_program' => 'Programs',
        'deleted_program' => 'Programs',
        'created_school_year' => 'School Years',
        'updated_school_year' => 'School Years',
        'deleted_school_year' => 'School Years',
        'created_exam_schedule' => 'Exam Scheduling',
        'updated_exam_schedule' => 'Exam Scheduling',
        'deleted_exam_schedule' => 'Exam Scheduling',
        'auto_assigned_exam_schedule' => 'Exam Scheduling',
        'manual_assigned_exam_schedule' => 'Exam Scheduling',
    ];

    /**
     * Display labels for activity log actions.
     */
    private const ACTIVITY_ACTION_LABELS = [
        'registered' => 'Registered',
        'registered_via_google' => 'Registered via Google',
        'logged_in' => 'Logged In',
        'logged_in_via_google' => 'Logged In via Google',
        'logged_out' => 'Logged Out',
        'login_failed' => 'Login Failed',
        'login_failed_inactive' => 'Login Attempt (Inactive)',
        'viewed_dashboard' => 'Viewed Dashboard',
        'viewed_application' => 'Viewed Application',
        'application_submitted' => 'Application Submitted',
        'profile_updated' => 'Profile Updated',
        'viewed_applicants' => 'Viewed Applicants',
        'status_approved' => 'Application Approved',
        'status_rejected' => 'Application Rejected',
        'status_reset' => 'Status Reset to Pending',
        'approved_application' => 'Approved Application',
        'rejected_application' => 'Rejected Application',
        'reset_application_status' => 'Reset Application Status',
        'created_program' => 'Created Program',
        'updated_program' => 'Updated Program',
        'deleted_program' => 'Deleted Program',
        'created_school_year' => 'Created School Year',
        'updated_school_year' => 'Updated School Year',
        'deleted_school_year' => 'Deleted School Year',
        'created_exam_schedule' => 'Created Exam Schedule',
        'updated_exam_schedule' => 'Updated Exam Schedule',
        'deleted_exam_schedule' => 'Deleted Exam Schedule',
        'auto_assigned_exam_schedule' => 'Auto-Assigned Exam Schedule',
        'manual_assigned_exam_schedule' => 'Manual-Assigned Exam Schedule',
        'updated_applicant_account' => 'Updated Applicant Account',
        'deleted_applicant_account' => 'Deleted Applicant Account',
    ];

    public function index()
    {
        $paginate = (int) request('paginate', 10);
        $search = trim(request('search', ''));
        $user = request('user');
        $action = request('action');
        $module = request('module');
        $date_from = request('date_from');
        $date_to = request('date_to');
        $sort_order = request('sort_order', 'desc');
        $sort_field = request('sort_field', 'created_at');

        if (! in_array($sort_order, ['asc', 'desc'])) {
            $sort_order = 'desc';
        }

        if (! in_array($sort_field, ['id', 'action', 'module', 'created_at'])) {
            $sort_field = 'created_at';
        }

        $logged_user = $this->callLoggedUser();
        $can_read_all = $logged_user->can('read audit-logs');
        $user_id = $can_read_all ? null : $logged_user->id;

        $logs = collect();

        // Audit logs (role changes on model updates, etc.)
        $logs = $logs->concat(
            AuditLog::with('user')
                ->when($user_id, fn ($query) => $query->where('user_id', $user_id))
                ->when($user, fn ($query) => $query->where('user_id', $user))
                ->when($action, fn ($query) => $query->where('action', $action))
                ->when($module, fn ($query) => $query->where('module', $module))
                ->when($date_from, fn ($query) => $query->whereDate('created_at', '>=', $date_from))
                ->when($date_to, fn ($query) => $query->whereDate('created_at', '<=', $date_to))
                ->search($search)
                ->get()
                ->map(fn ($log) => $this->mapAuditLog($log, $can_read_all))
        );

        $module_actions = $module ? $this->activityActionsForModule($module) : null;

        // Admin activity logs
        if ($module_actions === null || $module_actions !== []) {
            $logs = $logs->concat(
                AdminActivityLog::with('user')
                    ->when($search !== '', function ($query) use ($search) {
                        $term = "%{$search}%";
                        $query->where(function ($q) use ($term, $search) {
                            $q->where('action', 'like', $term)
                                ->orWhere('description', 'like', $term)
                                ->orWhereHas('user', fn ($userQuery) => $userQuery->search($search));
                        });
                    })
                    ->when($user_id, fn ($query) => $query->where('user_id', $user_id))
                    ->when($user, fn ($query) => $query->where('user_id', $user))
                    ->when($action, fn ($query) => $query->where('action', $action))
                    ->when($module_actions !== null, fn ($query) => $query->whereIn('action', $module_actions))
                    ->when($date_from, fn ($query) => $query->whereDate('created_at', '>=', $date_from))
                    ->when($date_to, fn ($query) => $query->whereDate('created_at', '<=', $date_to))
                    ->get()
                    ->map(fn ($log) => $this->mapActivityLog($log, 'admin'))
            );
        }

        // Applicant activity logs (user filter only matches admin users, so skip when set)
        if (($module_actions === null || $module_actions !== []) && ! $user) {
            $logs = $logs->concat(
                ActivityLog::with('applicant')
                    ->when($search !== '', function ($query) use ($search) {
                        $term = "%{$search}%";
                        $query->where(function ($q) use ($term, $search) {
                            $q->where('action', 'like', $term)
                                ->orWhere('description', 'like', $term)
                                ->orWhereHas('applicant', fn ($applicantQuery) => $applicantQuery->search($search));
                        });
                    })
                    ->when($action, fn ($query) => $query->where('action', $action))
                    ->when($module_actions !== null, fn ($query) => $query->whereIn('action', $module_actions))
                    ->when($date_from, fn ($query) => $query->whereDate('created_at', '>=', $date_from))
                    ->when($date_to, fn ($query) => $query->whereDate('created_at', '<=', $date_to))
                    ->get()
                    ->map(fn ($log) => $this->mapActivityLog($log, 'applicant'))
            );
        }

        $logs = $sort_order === 'desc'
            ? $logs->sortByDesc($sort_field)->values()
            : $logs->sortBy($sort_field)->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        $paginated = new LengthAwarePaginator(
            $logs->forPage($page, $paginate),
            $logs->count(),
            $paginate,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        return response()->json($paginated);
    }

    private function activityActionsForModule(string $module): array
    {
        return array_keys(array_filter(
            self::ACTIVITY_MODULES,
            fn ($activity_module) => $activity_module === $module
        ));
    }

    private function mapAuditLog(AuditLog $log, bool $can_read_details): array
    {
        $user = $log->user;

        if ($can_read_details) {
            $old_values = $log->old_values;
            $new_values = $log->new_values;
        } else {
            $old_values = '';
            $new_values = '';
        }

        if ($log->action === ActionType::Created->value) {
            $action_text = ActionType::Created->value;
            $action_color = BadgeColor::Primary->value;
        } else if ($log->action === ActionType::Updated->value) {
            $action_text = ActionType::Updated->value;
            $action_color = BadgeColor::Secondary->value;
        } else {
            $action_text = ActionType::Deleted->value;
            $action_color = BadgeColor::Danger->value;
        }

        return [
            'id' => $log->id,
            'source' => 'audit',
            'user' => $user ? $this->firstnameLastnameExtFormat($user) : 'Deleted user',
            'avatar' => $user?->avatar,
            'actor_name' => $user ? $this->firstnameLastnameExtFormat($user) : 'Deleted user',
            'actor_email' => $user?->email,
            'actor_role' => $user?->roles->pluck('name')->filter()->join(', '),
            'actor_type' => 'admin',
            'action' => $log->action,
            'action_text' => $action_text,
            'action_color' => $action_color,
            'module' => $log->module,
            'description' => $log->description,
            'old_values' => $old_values,
            'new_values' => $new_values,
            'metadata' => null,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function mapActivityLog($log, string $actorType): array
    {
        if ($actorType === 'admin') {
            $user = $log->user;
            $actorName = $user
                ? $this->firstnameLastnameExtFormat($user)
                : 'Deleted user';
            $actorEmail = $user?->email;
            $actorRole = $user?->roles->pluck('name')->filter()->join(', ') ?: 'Admin';
        } else {
            $applicant = $log->applicant;
            $actorName = $applicant
                ? $this->firstnameLastnameExtFormat($applicant)
                : 'Deleted applicant';
            $actorEmail = $applicant?->email;
            $actorRole = 'Applicant';
        }

        return [
            'id' => $log->id,
            'source' => $actorType === 'admin' ? 'admin_activity' : 'applicant_activity',
            'user' => $actorName,
            'avatar' => null,
            'actor_name' => $actorName,
            'actor_email' => $actorEmail,
            'actor_role' => $actorRole,
            'actor_type' => $actorType,
            'action' => $log->action,
            'action_text' => self::ACTIVITY_ACTION_LABELS[$log->action] ?? $log->action,
            'action_color' => $this->activityBadgeColor($log->action),
            'module' => self::ACTIVITY_MODULES[$log->action] ?? 'Other',
            'description' => $log->description,
            'old_values' => null,
            'new_values' => null,
            'metadata' => $log->metadata,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function activityBadgeColor(string $action): string
    {
        if (str_contains($action, 'approved') || in_array($action, ['logged_in', 'logged_in_via_google'])) {
            return BadgeColor::Success->value;
        }

        if (str_contains($action, 'rejected') || str_contains($action, 'failed')) {
            return BadgeColor::Danger->value;
        }

        if (str_contains($action, 'registered')) {
            return BadgeColor::Primary->value;
        }

        if (str_contains($action, 'deleted')) {
            return BadgeColor::Danger->value;
        }

        if (str_contains($action, 'reset') || str_contains($action, 'inactive')) {
            return BadgeColor::Warning->value;
        }

        if (str_contains($action, 'viewed')) {
            return BadgeColor::Info->value;
        }

        return BadgeColor::Secondary->value;
    }
}
