<?php

namespace Tests\Feature;

use App\Models\Admin\AuditLog;
use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Models\Admin\User;
use Database\Seeders\EncoderPermissionsSeeder;
use Database\Seeders\PermissionTitlesSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RbacModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(PermissionTitlesSeeder::class);
    }

    private function makeUser(array $permissionNames = []): User
    {
        $user = User::create([
            'firstname' => 'RBAC',
            'lastname' => 'Tester',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
        ]);

        $role = Role::create(['name' => 'Role '.Str::uuid()]);

        foreach ($permissionNames as $name) {
            $role->permissions()->attach(
                Permission::where('name', $name)->firstOrFail()->id
            );
        }

        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeSystemAdministrator(): User
    {
        $user = $this->makeUser();
        $user->roles()->attach(
            Role::where('name', 'System Administrator')->firstOrFail()->id
        );

        return $user;
    }

    public function test_system_administrator_bypasses_permission_checks(): void
    {
        $this->actingAs($this->makeSystemAdministrator())
            ->getJson('/api/users')
            ->assertStatus(200);
    }

    public function test_user_with_browse_users_can_access_users_index(): void
    {
        $this->actingAs($this->makeUser(['browse users']))
            ->getJson('/api/users')
            ->assertStatus(200);
    }

    public function test_user_without_permissions_is_forbidden_from_users_index(): void
    {
        $this->actingAs($this->makeUser())
            ->getJson('/api/users')
            ->assertStatus(403);
    }

    public function test_group_browse_permission_grants_access_to_child_module_routes(): void
    {
        $user = $this->makeUser(['browse academic-setup']);

        $this->actingAs($user)
            ->getJson('/api/school-years')
            ->assertStatus(200);

        $this->actingAs($user)
            ->getJson('/api/users')
            ->assertStatus(403);
    }

    public function test_group_browse_permission_does_not_grant_child_module_write_actions(): void
    {
        $user = $this->makeUser(['browse academic-setup']);

        $this->actingAs($user)
            ->postJson('/api/school-years', [])
            ->assertStatus(403);

        $permissions = $this->actingAs($user)
            ->getJson('/api/check/school-years/permissions')
            ->assertStatus(200)
            ->json();

        $this->assertFalse($permissions['create']);
        $this->assertFalse($permissions['update']);
        $this->assertFalse($permissions['delete']);
    }

    public function test_system_management_browse_does_not_grant_role_permission_edits(): void
    {
        $user = $this->makeUser(['browse system-management']);
        $role = Role::create(['name' => 'Managed '.Str::uuid()]);

        $this->actingAs($user)
            ->getJson('/api/roles')
            ->assertStatus(200);

        $permissions = $this->actingAs($user)
            ->getJson('/api/check/roles/permissions')
            ->assertStatus(200)
            ->json();

        $this->assertFalse($permissions['create']);
        $this->assertFalse($permissions['update']);
        $this->assertFalse($permissions['delete']);

        $this->actingAs($user)
            ->putJson('/api/roles/'.$role->id.'/permissions', ['permissions' => []])
            ->assertStatus(403);
    }

    public function test_dashboard_access_uses_its_own_permission(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson('/api/dashboard/stats')
            ->assertStatus(403);

        $user->roles()->first()->permissions()->attach(
            Permission::where('name', 'browse dashboard')->firstOrFail()->id
        );
        $user->unsetRelation('roles');

        $this->actingAs($user)
            ->getJson('/api/dashboard/stats')
            ->assertStatus(200);
    }

    public function test_applicant_details_require_applicants_read_permission(): void
    {
        $user = $this->makeUser(['browse applications']);

        $this->actingAs($user)
            ->getJson('/api/applicants/not-a-uuid')
            ->assertStatus(403);

        $this->actingAs($user)
            ->getJson('/api/applicants/not-a-uuid/activity-logs')
            ->assertStatus(403);

        $user->roles()->first()->permissions()->attach(
            Permission::where('name', 'read applicants')->firstOrFail()->id
        );
        $user->unsetRelation('roles');

        $this->actingAs($user)
            ->getJson('/api/applicants/not-a-uuid')
            ->assertStatus(404);

        $this->actingAs($user)
            ->getJson('/api/applicants/not-a-uuid/activity-logs')
            ->assertStatus(404);
    }

    public function test_settings_api_requires_settings_permission(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson('/api/requirements')
            ->assertStatus(403);

        $user->roles()->first()->permissions()->attach(
            Permission::where('name', 'browse settings')->firstOrFail()->id
        );
        $user->unsetRelation('roles');

        $this->actingAs($user)
            ->getJson('/api/requirements')
            ->assertStatus(200);
    }

    public function test_online_application_period_uses_its_own_permission(): void
    {
        $user = $this->makeUser(['browse settings']);

        $this->actingAs($user)
            ->getJson('/api/application-period')
            ->assertStatus(403);

        $user->roles()->first()->permissions()->attach(
            Permission::where('name', 'browse online-application')->firstOrFail()->id
        );
        $user->unsetRelation('roles');

        $this->actingAs($user)
            ->getJson('/api/application-period')
            ->assertStatus(200);

        $this->actingAs($user)
            ->postJson('/api/application-period', [])
            ->assertStatus(403);
    }

    public function test_online_application_permission_exposes_only_its_settings_section(): void
    {
        $user = $this->makeUser(['browse online-application']);

        $view = $this->actingAs($user)
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->json('view');

        $this->assertTrue($view['online_application']);
        $this->assertTrue($view['settings']);
        $this->assertFalse($view['system_administrator']);

        $this->actingAs($user)
            ->getJson('/api/landing-settings')
            ->assertStatus(403);
    }

    public function test_permission_migration_preserves_existing_staff_role_access(): void
    {
        $user = $this->makeUser(['browse applications', 'browse settings', 'update settings']);
        $role = $user->roles()->firstOrFail();

        $migration = require database_path(
            'migrations/2026_10_09_000001_add_dashboard_applicants_and_online_application_permissions.php'
        );
        $migration->up();
        $migration->up();

        $this->assertTrue($role->permissions()->where('name', 'browse dashboard')->exists());
        $this->assertTrue($role->permissions()->where('name', 'read applicants')->exists());
        $this->assertTrue($role->permissions()->where('name', 'browse online-application')->exists());
        $this->assertTrue($role->permissions()->where('name', 'update online-application')->exists());
        $this->assertSame(
            1,
            $role->permissions()->where('name', 'read applicants')->count()
        );
    }

    public function test_view_flags_follow_permissions(): void
    {
        $user = $this->makeUser(['browse academic-setup']);

        $view = $this->actingAs($user)
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->json('view');

        $this->assertTrue($view['academic_setup']);
        $this->assertTrue($view['school_years']);
        $this->assertFalse($view['dashboard']);
        $this->assertFalse($view['users']);
        $this->assertFalse($view['system_management']);
        $this->assertFalse($view['system_administrator']);

        $adminView = $this->actingAs($this->makeSystemAdministrator())
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->json('view');

        $this->assertTrue($adminView['academic_setup']);
        $this->assertTrue($adminView['dashboard']);
        $this->assertTrue($adminView['online_application']);
        $this->assertTrue($adminView['system_management']);
        $this->assertTrue($adminView['system_administrator']);
    }

    public function test_audit_logs_browse_shows_only_own_logs_without_details(): void
    {
        $viewer = $this->makeUser(['browse audit-logs']);
        $other = $this->makeUser();

        AuditLog::create([
            'user_id' => $viewer->id,
            'action' => 'Updated',
            'module' => 'Users',
            'description' => 'Own entry',
            'old_values' => json_encode(['name' => 'old']),
            'new_values' => json_encode(['name' => 'new']),
        ]);
        AuditLog::create([
            'user_id' => $other->id,
            'action' => 'Updated',
            'module' => 'Roles',
            'description' => 'Other entry',
            'old_values' => json_encode(['name' => 'old']),
            'new_values' => json_encode(['name' => 'new']),
        ]);

        $data = $this->actingAs($viewer)
            ->getJson('/api/audit-logs')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Own entry', $data[0]['description']);
        $this->assertEmpty($data[0]['old_values']);
        $this->assertEmpty($data[0]['new_values']);

        $this->actingAs($viewer)
            ->getJson('/api/list/users')
            ->assertStatus(403);
    }

    public function test_audit_logs_read_grants_all_logs_details_and_user_filter(): void
    {
        $viewer = $this->makeUser(['browse audit-logs', 'read audit-logs']);
        $other = $this->makeUser();

        AuditLog::create([
            'user_id' => $viewer->id,
            'action' => 'Updated',
            'module' => 'Users',
            'description' => 'Own entry',
            'old_values' => json_encode(['name' => 'old']),
            'new_values' => json_encode(['name' => 'new']),
        ]);
        AuditLog::create([
            'user_id' => $other->id,
            'action' => 'Updated',
            'module' => 'Roles',
            'description' => 'Other entry',
            'old_values' => json_encode(['name' => 'old']),
            'new_values' => json_encode(['name' => 'new']),
        ]);

        $data = $this->actingAs($viewer)
            ->getJson('/api/audit-logs')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $data);

        $own = collect($data)->firstWhere('description', 'Own entry');
        $this->assertNotEmpty($own['old_values']);
        $this->assertNotEmpty($own['new_values']);

        $this->actingAs($viewer)
            ->getJson('/api/list/users')
            ->assertStatus(200);
    }

    public function test_audit_logs_read_is_scoped_to_permission_not_role(): void
    {
        $user = $this->makeUser(['browse audit-logs']);

        $permissions = $this->actingAs($user)
            ->getJson('/api/check/audit-logs/permissions')
            ->assertStatus(200)
            ->json();

        $this->assertTrue($permissions['browse']);
        $this->assertFalse($permissions['read']);

        $user->roles()->first()->permissions()->attach(
            Permission::where('name', 'read audit-logs')->firstOrFail()->id
        );
        $user->unsetRelation('roles');

        $permissions = $this->actingAs($user)
            ->getJson('/api/check/audit-logs/permissions')
            ->assertStatus(200)
            ->json();

        $this->assertTrue($permissions['read']);
    }

    public function test_encoder_default_permissions_are_seeded(): void
    {
        $this->seed(EncoderPermissionsSeeder::class);
        $this->seed(EncoderPermissionsSeeder::class);

        $encoder = Role::where('name', 'Encoder')->firstOrFail();

        $this->assertTrue($encoder->permissions()->where('name', 'browse dashboard')->exists());
        $this->assertTrue($encoder->permissions()->where('name', 'browse applications')->exists());
        $this->assertTrue($encoder->permissions()->where('name', 'read applicants')->exists());
        $this->assertTrue($encoder->permissions()->where('name', 'update exam-scheduling')->exists());
        $this->assertTrue($encoder->permissions()->where('name', 'browse academic-setup')->exists());

        $this->assertFalse($encoder->permissions()->where('name', 'browse system-management')->exists());
        $this->assertFalse($encoder->permissions()->where('name', 'create users')->exists());
        $this->assertFalse($encoder->permissions()->where('name', 'delete programs')->exists());
        $this->assertFalse($encoder->permissions()->where('name', 'read audit-logs')->exists());

        $this->assertSame(16, $encoder->permissions()->count());
    }
}
