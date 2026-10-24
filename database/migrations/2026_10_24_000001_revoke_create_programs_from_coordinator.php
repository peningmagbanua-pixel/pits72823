<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Creating a new program is a System Administrator task. Coordinators keep
     * browse/read/update/delete on Programs but lose the "create programs"
     * action, which hides the New Program button and blocks the POST route.
     */
    public function up(): void
    {
        $coordinatorRoleId = DB::table('roles')->where('name', 'Coordinator')->value('id');
        $createProgramsId = DB::table('permissions')->where('name', 'create programs')->value('id');

        if (! $coordinatorRoleId || ! $createProgramsId) {
            return;
        }

        DB::table('role_permissions')
            ->where('role_id', $coordinatorRoleId)
            ->where('permission_id', $createProgramsId)
            ->delete();
    }

    public function down(): void
    {
        $coordinatorRoleId = DB::table('roles')->where('name', 'Coordinator')->value('id');
        $createProgramsId = DB::table('permissions')->where('name', 'create programs')->value('id');

        if (! $coordinatorRoleId || ! $createProgramsId) {
            return;
        }

        $exists = DB::table('role_permissions')
            ->where('role_id', $coordinatorRoleId)
            ->where('permission_id', $createProgramsId)
            ->exists();

        if (! $exists) {
            DB::table('role_permissions')->insert([
                'role_id' => $coordinatorRoleId,
                'permission_id' => $createProgramsId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
