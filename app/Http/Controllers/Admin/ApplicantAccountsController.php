<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\AuditLogsTrait;
use App\Traits\AdminActivityLogsTrait;
use App\Traits\ProgramScopeTrait;
use App\Enums\ActionType;
use Illuminate\Http\Request;
use App\Models\Applicant\Applicant;
use App\Http\Resources\Admin\ApplicantAccountsResource;

class ApplicantAccountsController extends Controller
{
    use AuditLogsTrait, AdminActivityLogsTrait, ProgramScopeTrait;

    public function index()
    {
        $paginate = request('paginate', 10);
        $search = request('search', '');
        $status = request('status');
        $sort_order = request('sort_order', 'desc');
        $sort_field = request('sort_field', 'created_at');

        if (!in_array($sort_order, ['asc', 'desc'])) {
            $sort_order = 'desc';
        }

        if (!in_array($sort_field, ['id', 'email', 'created_at'])) {
            $sort_field = 'created_at';
        }

        $applicants = $this->applyProgramScope(Applicant::query())
            ->when($status !== null && $status !== '', function ($query) use ($status) {
                $query->where('is_active', $status);
            })
            ->when(request('deleted') == '1', fn ($query) => $query->onlyTrashed())
            ->search(trim($search))
            ->orderBy($sort_field, $sort_order)
            ->paginate($paginate);

        return ApplicantAccountsResource::collection($applicants);
    }

    public function update(Request $request, $id)
    {
        $applicant = Applicant::findOrFail($id);

        $this->assertProgramAllowed($applicant->program_id);

        $validated = $request->validate([
            'status' => 'required|boolean',
        ]);

        $old_values = $applicant->getOriginal();

        $applicant->update([
            'is_active' => $validated['status'],
        ]);

        $action = ActionType::Updated->value;
        $module = 'Applicant Accounts';
        $description = 'Updated applicant account status: ' . $applicant->email;
        $this->setAuditLog($action, $module, $description, $old_values, $applicant->toArray());

        $this->logAdminActivity('updated_applicant_account', 'Updated applicant account status (' . ($validated['status'] ? 'active' : 'inactive') . '): ' . $applicant->email);

        return response()->json([
            'message' => 'Applicant account updated successfully!',
        ]);
    }

    public function delete($id)
    {
        $applicant = Applicant::findOrFail($id);

        $this->assertProgramAllowed($applicant->program_id);

        $action = ActionType::Deleted->value;
        $module = 'Applicant Accounts';
        $description = 'Deleted applicant account: ' . $applicant->email;
        $old_values = $applicant->getOriginal();

        if ($applicant->delete()) {
            $this->setAuditLog($action, $module, $description, $old_values, null);
            $this->logAdminActivity('deleted_applicant_account', 'Deleted applicant account: ' . $applicant->email);
            return response()->json([
                'message' => 'Applicant account deleted successfully!',
            ]);
        }

        return response()->json([
            'message' => 'Failed to delete applicant account!',
        ], 500);
    }

    public function restore($id)
    {
        $applicant = Applicant::withTrashed()->findOrFail($id);

        $this->assertProgramAllowed($applicant->program_id);

        if (!$applicant->trashed()) {
            return response()->json([
                'message' => 'Applicant account is not deleted!',
            ], 400);
        }

        if (Applicant::where('email', $applicant->email)->exists()) {
            return response()->json([
                'message' => 'An active account with this email already exists. The deleted account cannot be restored.',
            ], 400);
        }

        $applicant->restore();

        $action = ActionType::Updated->value;
        $module = 'Applicant Accounts';
        $description = 'Restored applicant account: ' . $applicant->email;
        $this->setAuditLog($action, $module, $description, null, $applicant->toArray());
        $this->logAdminActivity('updated_applicant_account', 'Restored applicant account: ' . $applicant->email);

        return response()->json([
            'message' => 'Applicant account restored successfully!',
        ]);
    }
}
