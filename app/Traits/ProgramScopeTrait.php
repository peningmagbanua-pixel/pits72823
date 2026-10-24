<?php

namespace App\Traits;

use App\Models\Admin\Program;

/**
 * Restricts data access to the programs assigned to the logged-in user.
 * Requires the using class to also use UserRolesTrait.
 */
trait ProgramScopeTrait
{
    /**
     * Program codes the logged-in user is restricted to.
     * Returns null when unrestricted (System Administrator).
     */
    protected function scopedPrograms(): ?array
    {
        if ($this->checkHasRoleSystemAdministrator()) {
            return null;
        }

        return $this->callLoggedUser()->programCodes();
    }

    /**
     * Get allowed program IDs from program codes.
     */
    protected function allowedProgramIds(): array
    {
        $programs = $this->scopedPrograms();

        if ($programs === null) {
            return Program::withTrashed()->pluck('id')->toArray();
        }

        return Program::withTrashed()->whereIn('initials', $programs)->pluck('id')->toArray();
    }

    /**
     * Restrict a query to the logged-in user's allowed programs.
     * An empty assignment list yields no rows.
     */
    protected function applyProgramScope($query)
    {
        // System Administrators have all program IDs, so no filter needed
        if ($this->checkHasRoleSystemAdministrator()) {
            return $query;
        }

        $allowedIds = $this->allowedProgramIds();

        if (empty($allowedIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('program_id', $allowedIds);
    }

    /**
     * Restrict a query on the programs table itself to the logged-in user's
     * allowed program codes (matched against programs.initials).
     * System Administrators are unrestricted; an empty assignment list
     * yields no rows.
     */
    protected function applyProgramCodeScope($query)
    {
        $codes = $this->scopedPrograms();

        if ($codes === null) {
            return $query;
        }

        if (empty($codes)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('initials', $codes);
    }

    /**
     * Abort with a 403 when the logged-in user may not access the given
     * program (null programs are only visible to System Administrators).
     * The $program parameter should be a program_id (UUID).
     */
    protected function assertProgramAllowed(?string $program): void
    {
        // System Administrators are unrestricted
        if ($this->checkHasRoleSystemAdministrator()) {
            return;
        }

        $allowedIds = $this->allowedProgramIds();

        // No program assignments yields no visible rows, so it must also block writes
        if (empty($allowedIds)) {
            abort(403, 'You do not have access to any program.');
        }

        if (! $program || ! in_array($program, $allowedIds, true)) {
            abort(403, 'You do not have access to this program.');
        }
    }
}
