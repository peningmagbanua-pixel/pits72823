<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\Admin\User;
use App\Traits\AuditLogsTrait;
use App\Traits\ConstantsTrait;
use App\Enums\ActionType;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use AuditLogsTrait, ConstantsTrait;

    public function profile()
    {
        $auth_user = $this->callLoggedUser();
        
        // Refresh user relationships from database to get latest permissions
        $auth_user->refresh();
        
        $roles = $auth_user->roles->pluck('name')->toArray();

        $view = [
            'dashboard' => $auth_user->can('browse dashboard'),

            // Admissions (section visible only when a child page is accessible)
            'applicant_accounts' => $auth_user->can('browse applicant-accounts'),
            'applications' => $auth_user->can('browse applications'),
            'applicants' => $auth_user->can('read applicants'),

            'admissions' =>
                $auth_user->can('browse applicant-accounts') ||
                $auth_user->can('browse applications') ||
                $auth_user->can('browse exam-scheduling'),

            // Exam Scheduling
            'exam_scheduling' => $auth_user->can('browse exam-scheduling'),

            // Reports & Monitoring
            'reports' => $auth_user->can('browse reports'),
            'audit_logs' => $auth_user->can('browse audit-logs'),
            'reports_monitoring' => $auth_user->can('browse audit-logs') ||
                $auth_user->can('browse reports'),

            // Academic Setup
            'school_years' => $auth_user->can('browse school-years'),
            'programs' => $auth_user->can('browse programs'),
            'academic_setup' =>
                $auth_user->can('browse school-years') ||
                $auth_user->can('browse programs'),

            // System Management
            'users' => $auth_user->can('browse users'),
            'roles' => $auth_user->can('browse roles'),
            'permission_titles' => $auth_user->can('browse permission-titles'),
            'online_application' => $auth_user->can('browse online-application'),
            'settings' => $auth_user->can('browse settings') ||
                $auth_user->can('browse online-application'),

            'system_management' => $auth_user->can('browse users') ||
                $auth_user->can('browse roles') ||
                $auth_user->can('browse permission-titles') ||
                $auth_user->can('browse settings') ||
                $auth_user->can('browse online-application'),

            'system_administrator' => $auth_user->hasRole([$this->SYSTEM_ADMINISTRATOR]),
            'coordinator' => $auth_user->hasRole([$this->COORDINATOR]),
        ];

        return [
            'name' => $this->firstnameLastnameExtFormat($auth_user),
            'email' => $auth_user->email,
            'avatar' => $auth_user->avatar,
            'roles' => $roles,
            'programs' => $auth_user->hasRole([$this->SYSTEM_ADMINISTRATOR])
                ? ['SPICT', 'STE']
                : $auth_user->programCodes(),
            'view' => $view,
            'must_change_password' => (bool) $auth_user->must_change_password,
        ];
    }

    public function getProfile()
    {
        $auth_user = $this->callLoggedUser();
        $auth_user->refresh(); // Refresh to get latest permissions and roles
        
        return response()->json([
            'id' => $auth_user->id,
            'lastname' => $auth_user->lastname,
            'firstname' => $auth_user->firstname,
            'middlename' => $auth_user->middlename,
            'extname' => $auth_user->extname,
            'email' => $auth_user->email,
            'avatar' => $auth_user->avatar,
            'roles' => $auth_user->roles->pluck('name')->toArray(),
            'created_at' => date("Y-m-d H:i:s", strtotime($auth_user->created_at)),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $auth_user = $this->callLoggedUser();
        $user = User::findOrFail($auth_user->id);

        $validated = $request->validate([
            'lastname' => 'required|string|max:255',
            'firstname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'extname' => 'nullable|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)->whereNull('deleted_at')],
        ]);

        $old_values = $user->getOriginal();

        $user->update([
            'lastname' => $validated['lastname'],
            'firstname' => $validated['firstname'],
            'middlename' => $validated['middlename'],
            'extname' => $validated['extname'],
            'email' => $validated['email'],
        ]);

        $action = ActionType::Updated->value;
        $module = 'Profile';
        $description = 'Updated profile information';
        $this->setAuditLog($action, $module, $description, $old_values, $user->toArray());

        return response()->json([
            'message' => 'Profile updated successfully!',
        ]);
    }

    public function changePassword(Request $request)
    {
        $auth_user = $this->callLoggedUser();
        $user = User::findOrFail($auth_user->id);

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect!',
            ], 422);
        }

        $user->update([
            'password' => bcrypt($validated['new_password']),
            'must_change_password' => false,
        ]);

        $action = ActionType::Updated->value;
        $module = 'Profile';
        $description = 'Changed password';
        $this->setAuditLog($action, $module, $description, null, null);

        return response()->json([
            'message' => 'Password changed successfully!',
        ]);
    }

    public function uploadAvatar(Request $request)
    {
        $auth_user = $this->callLoggedUser();
        $user = User::findOrFail($auth_user->id);

        $request->validate([
            'avatar' => 'required|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        // Delete old avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update([
            'avatar' => $path,
        ]);

        $this->logAdminActivity('updated_avatar', 'Updated profile avatar.');

        return response()->json([
            'message' => 'Avatar updated successfully!',
            'avatar' => $path,
        ]);
    }

    public function removeAvatar(Request $request)
    {
        $auth_user = $this->callLoggedUser();
        $user = User::findOrFail($auth_user->id);

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update([
            'avatar' => null,
        ]);

        $this->logAdminActivity('removed_avatar', 'Removed profile avatar.');

        return response()->json([
            'message' => 'Avatar removed successfully!',
        ]);
    }
}
