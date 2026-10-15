<?php

namespace Tests\Feature;

use App\Models\Admin\AuditLog;
use App\Models\Admin\Permission;
use App\Models\Admin\PermissionTitle;
use App\Models\Admin\Program;
use App\Models\Admin\Role;
use App\Models\Admin\User;
use App\Models\Applicant\Applicant;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EncoderPermissionsSeeder;
use Database\Seeders\PermissionTitlesSeeder;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_group_browse_permission_does_not_grant_child_module_pages_or_writes(): void
    {
        $user = $this->makeUser(['browse academic-setup']);

        $this->actingAs($user)
            ->postJson('/api/school-years', [])
            ->assertStatus(403);

        $permissions = $this->actingAs($user)
            ->getJson('/api/check/school-years/permissions')
            ->assertStatus(200)
            ->json();

        $this->assertFalse($permissions['browse']);
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

        // The section title alone no longer opens the sidebar menu.
        $this->assertFalse($view['academic_setup']);
        $this->assertFalse($view['school_years']);
        $this->assertFalse($view['programs']);
        $this->assertFalse($view['dashboard']);
        $this->assertFalse($view['users']);
        $this->assertFalse($view['system_management']);
        $this->assertFalse($view['system_administrator']);

        // Child grants light up the child page and its section header.
        $childView = $this->actingAs($this->makeUser(['browse school-years']))
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->json('view');

        $this->assertTrue($childView['academic_setup']);
        $this->assertTrue($childView['school_years']);
        $this->assertFalse($childView['programs']);

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

    public function test_sidebar_sections_hide_when_no_child_page_is_accessible(): void
    {
        // Admissions section stays visible for an exam-scheduling-only user,
        // even without the other Admissions child pages.
        $examOnlyView = $this->actingAs($this->makeUser(['browse exam-scheduling']))
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->json('view');

        $this->assertTrue($examOnlyView['admissions']);
        $this->assertTrue($examOnlyView['exam_scheduling']);
        $this->assertFalse($examOnlyView['applicant_accounts']);
        $this->assertFalse($examOnlyView['applications']);

        // A section title with no child pages opens no sidebar section.
        $sectionOnlyView = $this->actingAs($this->makeUser([
            'browse academic-setup',
            'browse system-management',
        ]))
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->json('view');

        $this->assertFalse($sectionOnlyView['admissions']);
        $this->assertFalse($sectionOnlyView['academic_setup']);
        $this->assertFalse($sectionOnlyView['system_management']);
        $this->assertFalse($sectionOnlyView['reports_monitoring']);

        // One child page is enough to open its parent menu and section.
        $settingsOnlyView = $this->actingAs($this->makeUser(['browse settings']))
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->json('view');

        $this->assertTrue($settingsOnlyView['system_management']);
        $this->assertTrue($settingsOnlyView['settings']);
        $this->assertFalse($settingsOnlyView['users']);
        $this->assertFalse($settingsOnlyView['roles']);
        $this->assertFalse($settingsOnlyView['permission_titles']);
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

    public function test_fresh_install_seeder_produces_expected_permission_state(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $encoder = Role::where('name', 'Encoder')->firstOrFail();
        $expected = [
            'browse academic-setup',
            'browse applicant-accounts',
            'browse applications',
            'browse audit-logs',
            'browse dashboard',
            'browse exam-scheduling',
            'browse programs',
            'browse reports',
            'browse school-years',
            'create exam-scheduling',
            'read applicant-accounts',
            'read applicants',
            'read applications',
            'update applicant-accounts',
            'update applications',
            'update exam-scheduling',
        ];

        $this->assertEqualsCanonicalizing(
            $expected,
            $encoder->permissions()->pluck('name')->all()
        );

        $this->assertFalse(
            PermissionTitle::where('name', 'Special Programs')->exists()
        );

        $this->assertSame(
            1,
            Role::where('name', 'System Administrator')->count()
        );
        $this->assertSame(
            1,
            Role::where('name', 'Encoder')->count()
        );
    }

    public function test_check_permissions_rejects_unknown_categories(): void
    {
        $user = $this->makeUser(['browse users']);

        $this->actingAs($user)
            ->getJson('/api/check/users/permissions')
            ->assertStatus(200);

        $this->actingAs($user)
            ->getJson('/api/check/bogus-category/permissions')
            ->assertStatus(404);

        $this->actingAs($user)
            ->getJson('/api/check/users%20roles/permissions')
            ->assertStatus(404);
    }

    public function test_lookup_routes_accept_page_scoped_roles(): void
    {
        $reportsUser = $this->makeUser(['browse reports']);
        $applicationsUser = $this->makeUser(['browse applications']);

        $this->actingAs($reportsUser)
            ->getJson('/api/school-years')
            ->assertStatus(200);
        $this->actingAs($reportsUser)
            ->getJson('/api/programs')
            ->assertStatus(200);
        $this->actingAs($applicationsUser)
            ->getJson('/api/programs')
            ->assertStatus(200);

        $this->actingAs($this->makeUser())
            ->getJson('/api/programs')
            ->assertStatus(403);
    }

    public function test_reports_are_scoped_to_the_staff_program(): void
    {
        $this->seed(ProgramsSeeder::class);

        $spict = Program::where('initials', 'SPICT')->firstOrFail();
        $ste = Program::where('initials', 'STE')->firstOrFail();

        foreach ([[$spict, 'approved'], [$spict, 'approved'], [$ste, 'approved']] as [$program, $status]) {
            Applicant::create([
                'firstname' => 'Report',
                'lastname' => 'Scoped',
                'email' => Str::uuid().'@example.com',
                'password' => 'password',
                'program_id' => $program->id,
                'application_status' => $status,
            ]);
        }

        $spictStaff = $this->makeUser(['browse reports']);
        $spictStaff->program_assignments()->create(['program' => 'SPICT']);

        $summary = $this->actingAs($spictStaff)
            ->getJson('/api/reports')
            ->assertStatus(200)
            ->json('summary');

        $this->assertSame(2, $summary['total']);
        $this->assertSame(2, $summary['approved']);

        $adminSummary = $this->actingAs($this->makeSystemAdministrator())
            ->getJson('/api/reports')
            ->assertStatus(200)
            ->json('summary');

        $this->assertSame(3, $adminSummary['total']);

        $approved = $this->actingAs($spictStaff)
            ->getJson('/api/reports/print-approved')
            ->assertStatus(200)
            ->json('applicants');

        $this->assertCount(2, $approved);
    }

    public function test_file_access_is_scoped_to_the_staff_program(): void
    {
        $this->seed(ProgramsSeeder::class);

        Storage::fake('local');

        $spict = Program::where('initials', 'SPICT')->firstOrFail();
        $ste = Program::where('initials', 'STE')->firstOrFail();

        $spictApplicant = Applicant::create([
            'firstname' => 'File',
            'lastname' => 'Owner',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'program_id' => $spict->id,
            'application_status' => 'pending',
        ]);
        $steApplicant = Applicant::create([
            'firstname' => 'Other',
            'lastname' => 'Owner',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'program_id' => $ste->id,
            'application_status' => 'pending',
        ]);

        Storage::disk('local')->put("applications/{$spictApplicant->id}/doc.pdf", 'spict');
        Storage::disk('local')->put("applications/{$steApplicant->id}/doc.pdf", 'ste');

        $steStaff = $this->makeUser(['browse applications']);
        $steStaff->program_assignments()->create(['program' => 'STE']);

        $this->actingAs($steStaff)
            ->get('/admin/files/applications/'.$steApplicant->id.'/doc.pdf')
            ->assertStatus(200);

        $this->actingAs($steStaff)
            ->get('/admin/files/applications/'.$spictApplicant->id.'/doc.pdf')
            ->assertStatus(403);

        $this->actingAs($steStaff)
            ->get('/admin/files/other/path.pdf')
            ->assertStatus(403);

        $this->actingAs($this->makeSystemAdministrator())
            ->get('/admin/files/applications/'.$spictApplicant->id.'/doc.pdf')
            ->assertStatus(200);

        $this->actingAs($this->makeUser())
            ->get('/admin/files/applications/'.$steApplicant->id.'/doc.pdf')
            ->assertStatus(403);
    }

    public function test_zero_permission_user_is_forbidden_from_all_read_apis(): void
    {
        $user = $this->makeUser();

        $endpoints = [
            '/api/dashboard/stats',
            '/api/applicants',
            '/api/applicant-accounts',
            '/api/exam-schedules',
            '/api/unscheduled-applicants',
            '/api/reports',
            '/api/reports/print',
            '/api/reports/print-approved',
            '/api/reports/print-exam-schedule',
            '/api/audit-logs',
            '/api/school-years',
            '/api/programs',
            '/api/users',
            '/api/list/users',
            '/api/roles',
            '/api/list/roles',
            '/api/all/roles',
            '/api/roles/permission-matrix',
            '/api/permission-titles',
            '/api/landing-settings',
            '/api/landing-settings/app-settings',
            '/api/requirements',
            '/api/application-period',
            '/api/session-timeout-settings',
            '/admin/files/applications/some-id/doc.pdf',
        ];

        foreach ($endpoints as $endpoint) {
            $this->actingAs($user)
                ->getJson($endpoint)
                ->assertStatus(403, "Expected 403 for {$endpoint}");
        }
    }
}
