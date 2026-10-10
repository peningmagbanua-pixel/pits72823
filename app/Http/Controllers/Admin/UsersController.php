<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\UserRolesTrait;
use Illuminate\Http\Request;
use App\Models\Admin\User;
use App\Models\Admin\Role;
use App\Http\Resources\Admin\UsersResource;
use App\Traits\AuditLogsTrait;
use App\Traits\ConstantsTrait;
use App\Enums\ActionType;
use App\Mail\UserAccountCreatedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    use AuditLogsTrait, ConstantsTrait;
    public function index()
    {
        $paginate = request('paginate');
        $search = request('search', '');
        $role = request('role');
        $status = request('status');
        $sort_order = request('sort_order', 'desc');
        $sort_field = request('sort_field', 'created_at');

        if (!in_array($sort_order, ['asc', 'desc'])) {
            $sort_order = 'desc';
        }

        if (!in_array($sort_field, ['id', 'email', 'created_at'])) {
            $sort_field = 'created_at';
        }

        $users = User::with(['roles', 'program_assignments'])
            ->when($role, function ($query) use ($role) {
                 $query->whereHas('roles', function ($query) use ($role) {
                    $query->where('role_id', $role); 
                });
            })
            ->when($status !== null && $status !== '', function ($query) use ($status) {
                $query->where('is_active', $status);
            })
            ->when(request('deleted') == '1', fn ($query) => $query->onlyTrashed())
            ->search(trim($search))
            ->orderBy($sort_field, $sort_order)
            ->paginate($paginate);

        return UsersResource::collection($users);
    }

    public function list()
    {
        $users = User::orderBy('lastname')
            ->orderBy('firstname')->get();

        return $users;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lastname' => 'required|string|max:255',
            'firstname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'extname' => 'nullable|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'admin' => 'nullable|string|in:Yes,No',
            'roles' => 'required_if:admin,No|array',
            'roles.*' => 'exists:roles,id',
            'program_access' => 'nullable|array',
            'program_access.*' => 'in:SPICT,STE',
        ]);

        // Generate a random temporary password
        $plainPassword = Str::password(length: 12, symbols: true);

        $user = User::create([
            'lastname' => $validated['lastname'],
            'firstname' => $validated['firstname'],
            'middlename' => $validated['middlename'],
            'extname' => $validated['extname'],
            'email' => $validated['email'],
            'password' => bcrypt($plainPassword),
            'must_change_password' => true,
        ]);

        if (isset($validated['admin']) && $validated['admin'] === $this->ADMIN_YES) {
            $system_administrator = Role::where('name', $this->SYSTEM_ADMINISTRATOR)->first();
            if ($system_administrator) {
                $user->roles()->attach($system_administrator->id);
            }
        } else {
            if (isset($validated['roles']) && count($validated['roles']) > 0) {
                $user->roles()->attach($validated['roles']);
            }
        }

        // Save program assignments (not applied to System Administrators)
        if (!(isset($validated['admin']) && $validated['admin'] === $this->ADMIN_YES)) {
            foreach (array_unique($validated['program_access'] ?? []) as $program) {
                $user->program_assignments()->create(['program' => $program]);
            }
        }

        // Send the account created email with credentials
        Mail::to($user->email)->send(new UserAccountCreatedMail($user, $plainPassword));

        $action = ActionType::Created->value;
        $module = 'Users';
        $description = 'Created user ' . $user->email;
        $this->setAuditLog($action, $module, $description, null, $user->toArray());

        return response()->json([
            'message' => 'User created successfully!',
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found!',
            ], 404);
        }

        $validated = $request->validate([
            'lastname' => 'required|string|max:255',
            'firstname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'extname' => 'nullable|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($id)->whereNull('deleted_at')],
            'status' => 'required|boolean',
            'admin' => 'nullable|string|in:Yes,No',
            'roles' => 'required_if:admin,No|array',
            'roles.*' => 'exists:roles,id',
            'program_access' => 'nullable|array',
            'program_access.*' => 'in:SPICT,STE',
        ]);

        $old_values = $user->getOriginal();

        $user->update([
            'lastname' => $validated['lastname'],
            'firstname' => $validated['firstname'],
            'middlename' => $validated['middlename'],
            'extname' => $validated['extname'],
            'email' => $validated['email'],
            'is_active' => $validated['status'],
        ]);

        $user->roles()->detach();

        if (isset($validated['admin']) && $validated['admin'] === $this->ADMIN_YES) {
            $system_administrator = Role::where('name', $this->SYSTEM_ADMINISTRATOR)->first();
            if ($system_administrator) {
                $user->roles()->attach($system_administrator->id);
            }
        } else {
            if (isset($validated['roles']) && count($validated['roles']) > 0) {
                $user->roles()->attach($validated['roles']);
            }
        }

        // Replace program assignments (System Administrators keep none)
        $user->program_assignments()->delete();

        if (!(isset($validated['admin']) && $validated['admin'] === $this->ADMIN_YES)) {
            foreach (array_unique($validated['program_access'] ?? []) as $program) {
                $user->program_assignments()->create(['program' => $program]);
            }
        }

        $action = ActionType::Updated->value;
        $module = 'Users';
        $description = 'Updated user ' . $user->email;
        $this->setAuditLog($action, $module, $description, $old_values, $user->toArray());

        return response()->json([
            'message' => 'User updated successfully!',
        ]);
    }

    public function delete($id)
    {
        $user = User::findOrFail($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found!',
            ], 404);
        }

        $current_user = $this->callLoggedUser();
        if ($user->id === $current_user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account!',
            ], 403);
        }

        $action = ActionType::Deleted->value;
        $module = 'Users';
        $description = 'Deleted user ' . $user->email;
        $old_values = $user->getOriginal();

        if ($user->delete()) {
            $this->setAuditLog($action, $module, $description, $old_values, null);
            return response()->json([
                'message' => 'User deleted successfully!',
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to delete user!',
            ], 500);
        }
    }

    public function restore($id)
    {
        $user = User::withTrashed()->findOrFail($id);

        if (!$user->trashed()) {
            return response()->json([
                'message' => 'User is not deleted!',
            ], 400);
        }

        if ($user->email !== null && User::where('email', $user->email)->exists()) {
            return response()->json([
                'message' => 'An active account with this email already exists. The deleted account cannot be restored.',
            ], 400);
        }

        $user->restore();

        $action = ActionType::Updated->value;
        $module = 'Users';
        $description = 'Restored user ' . $user->email;
        $this->setAuditLog($action, $module, $description, null, $user->toArray());

        return response()->json([
            'message' => 'User restored successfully!',
        ]);
    }
}
