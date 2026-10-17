<?php

namespace Tests\Feature;

use App\Models\Admin\ExamSchedule;
use App\Models\Admin\Permission;
use App\Models\Admin\PermissionTitle;
use App\Models\Admin\Program;
use App\Models\Admin\Requirement;
use App\Models\Admin\Role;
use App\Models\Admin\User;
use App\Models\Applicant\Applicant;
use Database\Seeders\CoordinatorPermissionsSeeder;
use Database\Seeders\PermissionTitlesSeeder;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoordinatorRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $sysadmin;

    private User $spictCoordinator;

    private User $steCoordinator;

    private User $dualCoordinator;

    private User $spictStaff;

    private User $steStaff;

    private Program $spict;

    private Program $ste;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->seed(RolesTableSeeder::class);
        $this->seed(PermissionTitlesSeeder::class);
        $this->seed(ProgramsSeeder::class);
        $this->seed(CoordinatorPermissionsSeeder::class);

        $systemAdministrator = Role::where('name', 'System Administrator')->firstOrFail();
        $coordinator = Role::where('name', 'Coordinator')->firstOrFail();
        $encoder = Role::where('name', 'Encoder')->firstOrFail();

        $this->sysadmin = $this->makeUser();
        $this->sysadmin->roles()->attach($systemAdministrator->id);

        $this->spictCoordinator = $this->makeUser();
        $this->spictCoordinator->roles()->attach($coordinator->id);
        $this->spictCoordinator->program_assignments()->create(['program' => 'SPICT']);

        $this->steCoordinator = $this->makeUser();
        $this->steCoordinator->roles()->attach($coordinator->id);
        $this->steCoordinator->program_assignments()->create(['program' => 'STE']);

        $this->dualCoordinator = $this->makeUser();
        $this->dualCoordinator->roles()->attach($coordinator->id);
        $this->dualCoordinator->program_assignments()->create(['program' => 'SPICT']);
        $this->dualCoordinator->program_assignments()->create(['program' => 'STE']);

        $this->spictStaff = $this->makeUser();
        $this->spictStaff->roles()->attach($encoder->id);
        $this->spictStaff->program_assignments()->create(['program' => 'SPICT']);

        $this->steStaff = $this->makeUser();
        $this->steStaff->roles()->attach($encoder->id);
        $this->steStaff->program_assignments()->create(['program' => 'STE']);

        $this->spict = Program::where('initials', 'SPICT')->firstOrFail();
        $this->ste = Program::where('initials', 'STE')->firstOrFail();

        $this->makeApplicant($this->spict);
        $this->makeApplicant($this->spict);
        $this->makeApplicant($this->ste);

        $this->makeSchedule($this->spict);
        $this->makeSchedule($this->ste);
    }

    private function makeUser(): User
    {
        return User::create([
            'firstname' => 'Coordinator',
            'lastname' => 'Test',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'must_change_password' => false,
        ]);
    }

    private function makeApplicant(Program $program): Applicant
    {
        return Applicant::create([
            'firstname' => 'Applicant',
            'lastname' => 'Test',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'program_id' => $program->id,
            'application_status' => 'pending',
        ]);
    }

    private function makeSchedule(Program $program): ExamSchedule
    {
        return ExamSchedule::create([
            'program_id' => $program->id,
            'date' => now()->addDays(7)->toDateString(),
            'time_slot' => '8:00 am - 10:00 am',
            'room' => 'Room 32',
            'capacity' => 50,
        ]);
    }

    private function makeRequirement(string $name, array $programIds): Requirement
    {
        $requirement = Requirement::create([
            'name' => $name,
            'is_required' => true,
            'is_active' => true,
        ]);
        $requirement->programs()->sync($programIds);

        return $requirement;
    }

    private function requirementPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Requirement '.Str::uuid(),
            'is_required' => 1,
            'is_active' => 1,
        ], $overrides);
    }

    // ── Profile & page access ─────────────────────────────────────────────

    public function test_coordinator_profile_exposes_coordinator_flag_and_own_programs(): void
    {
        $profile = $this->actingAs($this->spictCoordinator)
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->json();

        $this->assertTrue($profile['view']['coordinator']);
        $this->assertFalse($profile['view']['system_administrator']);
        $this->assertSame(['SPICT'], $profile['programs']);
        $this->assertTrue($profile['view']['users']);
        $this->assertTrue($profile['view']['settings']);

        // The Roles page is now reachable (Staff roles only); Permission Titles
        // stays System Administrator only.
        $this->assertTrue($profile['view']['roles']);
        $this->assertFalse($profile['view']['permission_titles']);

        $adminProfile = $this->actingAs($this->sysadmin)
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->json();

        $this->assertTrue($adminProfile['view']['system_administrator']);
        $this->assertFalse($adminProfile['view']['coordinator']);
        $this->assertEqualsCanonicalizing(['SPICT', 'STE'], $adminProfile['programs']);
    }

    public function test_revoked_school_years_and_programs_permissions_remove_coordinator_access(): void
    {
        $coordinatorRole = Role::where('name', 'Coordinator')->firstOrFail();

        // Simulate the RBAC matrix: uncheck every School Years and Programs
        // action while leaving the rest of the Coordinator grant intact.
        $revoked = [
            'browse school-years', 'read school-years', 'create school-years',
            'update school-years', 'delete school-years',
            'browse programs', 'read programs', 'create programs',
            'update programs', 'delete programs',
        ];

        $remaining = $coordinatorRole->permissions()
            ->whereNotIn('name', $revoked)
            ->pluck('id')
            ->all();

        $this->actingAs($this->sysadmin)
            ->putJson('/api/roles/'.$coordinatorRole->id.'/permissions', [
                'permissions' => $remaining,
            ])
            ->assertStatus(200);

        // Frontend gates: sidebar children, the parent menu, and page
        // routes all close once no Academic Setup child remains.
        $profile = $this->actingAs($this->spictCoordinator)
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->json();

        $this->assertFalse($profile['view']['school_years']);
        $this->assertFalse($profile['view']['programs']);
        $this->assertFalse($profile['view']['academic_setup']);

        // Other sidebar sections the Coordinator still holds stay visible.
        $this->assertTrue($profile['view']['system_management']);
        $this->assertTrue($profile['view']['users']);
        $this->assertTrue($profile['view']['settings']);

        $schoolYearPermissions = $this->actingAs($this->spictCoordinator)
            ->getJson('/api/check/school-years/permissions')
            ->assertStatus(200)
            ->json();
        $programPermissions = $this->actingAs($this->spictCoordinator)
            ->getJson('/api/check/programs/permissions')
            ->assertStatus(200)
            ->json();

        $this->assertFalse($schoolYearPermissions['browse']);
        $this->assertFalse($programPermissions['browse']);

        // Backend writes are rejected outright.
        $this->actingAs($this->spictCoordinator)
            ->postJson('/api/school-years', [
                'school_year_start' => '2027-06-01',
                'school_year_end' => '2028-04-01',
            ])
            ->assertStatus(403);
        $this->actingAs($this->spictCoordinator)
            ->postJson('/api/programs', ['initials' => 'NEW', 'name' => 'New Program'])
            ->assertStatus(403);
    }

    public function test_coordinator_is_forbidden_from_admin_only_settings_endpoints(): void
    {
        $coordinator = $this->spictCoordinator;

        $this->actingAs($coordinator)->getJson('/api/landing-settings')->assertStatus(403);
        $this->actingAs($coordinator)->getJson('/api/landing-settings/app-settings')->assertStatus(403);
        $this->actingAs($coordinator)->postJson('/api/landing-settings', [])->assertStatus(403);
        $this->actingAs($coordinator)
            ->postJson('/api/landing-settings/app-settings', ['maintenance_mode' => 1])
            ->assertStatus(403);
        $this->actingAs($coordinator)
            ->postJson('/api/landing-settings/notifications', ['notifications' => []])
            ->assertStatus(403);
        $this->actingAs($coordinator)->getJson('/api/session-timeout-settings')->assertStatus(403);
        $this->actingAs($coordinator)
            ->postJson('/api/session-timeout-settings', ['timeout_minutes' => 5, 'warning_minutes' => 1])
            ->assertStatus(403);

        $this->actingAs($this->sysadmin)
            ->getJson('/api/landing-settings')
            ->assertStatus(200);
        $this->actingAs($this->sysadmin)
            ->getJson('/api/session-timeout-settings')
            ->assertStatus(200);
    }

    public function test_coordinator_cannot_create_or_delete_roles_or_reach_permission_titles(): void
    {
        $coordinator = $this->spictCoordinator;
        $encoder = Role::where('name', 'Encoder')->firstOrFail();
        $permissionTitle = PermissionTitle::firstOrFail();

        // Permission Titles remains a pure System Administrator surface.
        $this->actingAs($coordinator)->getJson('/api/permission-titles')->assertStatus(403);
        $this->actingAs($coordinator)
            ->postJson('/api/permission-titles', ['name' => 'Leaked'])
            ->assertStatus(403);
        $this->actingAs($coordinator)
            ->putJson('/api/permission-titles/'.$permissionTitle->id, ['name' => 'Renamed'])
            ->assertStatus(403);

        // Create / delete / restore of roles stay System Administrator only.
        $this->actingAs($coordinator)
            ->postJson('/api/roles', ['name' => 'Another Role'])
            ->assertStatus(403);
        $this->actingAs($coordinator)
            ->deleteJson('/api/roles/'.$encoder->id)
            ->assertStatus(403);
        $this->assertDatabaseMissing('roles', ['name' => 'Another Role']);
        $this->assertDatabaseHas('roles', ['name' => 'Encoder']);
    }

    public function test_coordinator_is_limited_to_staff_roles_on_the_roles_page(): void
    {
        $coordinator = $this->spictCoordinator;
        $encoder = Role::where('name', 'Encoder')->firstOrFail();
        $coordinatorRole = Role::where('name', 'Coordinator')->firstOrFail();
        $systemAdministrator = Role::where('name', 'System Administrator')->firstOrFail();

        // The roles list and the permission matrix only expose Staff roles.
        $names = collect(
            $this->actingAs($coordinator)
                ->getJson('/api/roles')
                ->assertStatus(200)
                ->json('data')
        )->pluck('name');
        $this->assertTrue($names->contains('Encoder'));
        $this->assertFalse($names->contains('System Administrator'));
        $this->assertFalse($names->contains('Coordinator'));

        $matrixNames = collect(
            $this->actingAs($coordinator)
                ->getJson('/api/roles/permission-matrix')
                ->assertStatus(200)
                ->json('roles')
        )->pluck('name');
        $this->assertTrue($matrixNames->contains('Encoder'));
        $this->assertFalse($matrixNames->contains('System Administrator'));
        $this->assertFalse($matrixNames->contains('Coordinator'));

        // The System Administrator still sees everything.
        $adminNames = collect(
            $this->actingAs($this->sysadmin)
                ->getJson('/api/roles')
                ->assertStatus(200)
                ->json('data')
        )->pluck('name');
        $this->assertTrue($adminNames->contains('System Administrator'));
        $this->assertTrue($adminNames->contains('Coordinator'));
        $this->assertTrue($adminNames->contains('Encoder'));

        // Privileged role details are off limits in both directions.
        $this->actingAs($coordinator)
            ->getJson('/api/roles/'.$coordinatorRole->id.'/permissions')
            ->assertStatus(403);
        $this->actingAs($coordinator)
            ->getJson('/api/roles/'.$systemAdministrator->id.'/permissions')
            ->assertStatus(403);
        $this->actingAs($coordinator)
            ->putJson('/api/roles/'.$coordinatorRole->id.'/permissions', ['permissions' => []])
            ->assertStatus(403);
        $this->actingAs($coordinator)
            ->getJson('/api/roles/'.$coordinatorRole->id.'/users')
            ->assertStatus(403);
        $this->actingAs($coordinator)
            ->getJson('/api/roles/'.$systemAdministrator->id.'/users')
            ->assertStatus(403);

        // Only the Coordinator's own permissions are offered for a Staff role.
        $offered = collect(
            $this->actingAs($coordinator)
                ->getJson('/api/roles/'.$encoder->id.'/permissions')
                ->assertStatus(200)
                ->json('permissions')
        )->flatMap(fn ($group) => collect($group['permissions'])->pluck('name'));

        $this->assertTrue($offered->contains('browse users'));
        $this->assertFalse($offered->contains('browse system-management'));
        $this->assertFalse($offered->contains('create roles'));
        $this->assertFalse($offered->contains('browse permission-titles'));

        // Editing the Staff role works with permissions the Coordinator holds.
        $this->actingAs($coordinator)
            ->putJson('/api/roles/'.$encoder->id.'/permissions', [
                'permissions' => Permission::whereIn('name', ['browse users', 'read users'])
                    ->pluck('id')->all(),
            ])
            ->assertStatus(200);
        $this->assertEqualsCanonicalizing(
            ['browse users', 'read users'],
            $encoder->fresh()->permissions->pluck('name')->all()
        );

        // Minting a capability the Coordinator does not hold is rejected.
        $this->actingAs($coordinator)
            ->putJson('/api/roles/'.$encoder->id.'/permissions', [
                'permissions' => Permission::whereIn('name', [
                    'browse users', 'browse system-management', 'create roles',
                ])->pluck('id')->all(),
            ])
            ->assertStatus(403);
        $this->assertEqualsCanonicalizing(
            ['browse users', 'read users'],
            $encoder->fresh()->permissions->pluck('name')->all()
        );

        // Renaming privileged roles is forbidden; Staff roles can be renamed.
        $this->actingAs($coordinator)
            ->putJson('/api/roles/'.$coordinatorRole->id, ['name' => 'Renamed Coordinator'])
            ->assertStatus(403);
        $this->actingAs($coordinator)
            ->putJson('/api/roles/'.$systemAdministrator->id, ['name' => 'Renamed Admin'])
            ->assertStatus(403);

        $staffRole = Role::create(['name' => 'Staff Assistant']);
        $this->actingAs($coordinator)
            ->putJson('/api/roles/'.$staffRole->id, ['name' => 'Staff Associate'])
            ->assertStatus(200);
        $this->assertSame('Staff Associate', $staffRole->fresh()->name);

        // Role member lists stay scoped to the Coordinator's own programs.
        $emails = collect(
            $this->actingAs($coordinator)
                ->getJson('/api/roles/'.$encoder->id.'/users')
                ->assertStatus(200)
                ->json('users')
        )->pluck('email');
        $this->assertTrue($emails->contains($this->spictStaff->email));
        $this->assertFalse($emails->contains($this->steStaff->email));
        $this->assertFalse($emails->contains($this->sysadmin->email));
    }

    public function test_granting_applications_access_also_allows_approving_or_declining(): void
    {
        $encoder = Role::where('name', 'Encoder')->firstOrFail();

        // A Staff role granted only access to the Applications page.
        $encoder->permissions()->sync(
            Permission::where('name', 'browse applications')->pluck('id')->all()
        );

        $this->actingAs($this->spictCoordinator)
            ->putJson('/api/roles/'.$encoder->id.'/permissions', [
                'permissions' => Permission::where('name', 'browse applications')
                    ->pluck('id')->all(),
            ])
            ->assertStatus(200);

        $names = $encoder->fresh()->permissions->pluck('name');
        $this->assertTrue($names->contains('browse applications'));
        $this->assertTrue($names->contains('update applications'));
        $this->assertTrue($names->contains('read applicants'));

        // The permissions modal reports the implied permissions as checked too,
        // and every group serializes as a JSON array (not an object with key
        // gaps, which crashes the modal's group.permissions.forEach).
        $groups = collect(
            $this->actingAs($this->spictCoordinator)
                ->getJson('/api/roles/'.$encoder->id.'/permissions')
                ->assertStatus(200)
                ->json('permissions')
        );

        foreach ($groups as $group) {
            $this->assertIsArray($group['permissions']);
            $this->assertTrue(
                array_is_list($group['permissions']),
                "Permission group '{$group['title']}' must serialize as a JSON array."
            );
        }

        $checked = $groups->flatMap(fn ($group) => collect($group['permissions']))
            ->filter(fn ($permission) => $permission['checked'])
            ->pluck('name');

        $this->assertTrue($checked->contains('update applications'));
        $this->assertTrue($checked->contains('read applicants'));
    }

    public function test_coordinator_role_lists_hide_privileged_roles(): void
    {
        $listed = $this->actingAs($this->spictCoordinator)
            ->getJson('/api/list/roles')
            ->assertStatus(200)
            ->json();

        $names = collect($listed)->pluck('name');
        $this->assertTrue($names->contains('Encoder'));
        $this->assertFalse($names->contains('System Administrator'));
        $this->assertFalse($names->contains('Coordinator'));

        $all = $this->actingAs($this->spictCoordinator)
            ->getJson('/api/all/roles')
            ->assertStatus(200)
            ->json();

        $allNames = collect($all)->pluck('name');
        $this->assertTrue($allNames->contains('Encoder'));
        $this->assertFalse($allNames->contains('System Administrator'));
        $this->assertFalse($allNames->contains('Coordinator'));

        $adminNames = collect(
            $this->actingAs($this->sysadmin)->getJson('/api/all/roles')->json()
        )->pluck('name');
        $this->assertTrue($adminNames->contains('System Administrator'));
        $this->assertTrue($adminNames->contains('Coordinator'));
    }

    // ── Data scoping ──────────────────────────────────────────────────────

    public function test_coordinator_dashboard_is_scoped_to_the_assigned_program(): void
    {
        $stats = $this->actingAs($this->spictCoordinator)
            ->getJson('/api/dashboard/stats')
            ->assertStatus(200)
            ->json();

        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['spict_count']);
        $this->assertSame(0, $stats['ste_count']);

        $steStats = $this->actingAs($this->steCoordinator)
            ->getJson('/api/dashboard/stats')
            ->assertStatus(200)
            ->json();

        $this->assertSame(1, $steStats['total']);
        $this->assertSame(0, $steStats['spict_count']);
        $this->assertSame(1, $steStats['ste_count']);
    }

    public function test_dual_program_coordinator_sees_both_programs(): void
    {
        $stats = $this->actingAs($this->dualCoordinator)
            ->getJson('/api/dashboard/stats')
            ->assertStatus(200)
            ->json();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['spict_count']);
        $this->assertSame(1, $stats['ste_count']);
    }

    public function test_coordinator_sees_only_users_of_their_programs(): void
    {
        $emails = collect(
            $this->actingAs($this->spictCoordinator)
                ->getJson('/api/users')
                ->assertStatus(200)
                ->json('data')
        )->pluck('email');

        $this->assertTrue($emails->contains($this->spictStaff->email));
        $this->assertFalse($emails->contains($this->steStaff->email));
        $this->assertFalse($emails->contains($this->sysadmin->email));
        $this->assertFalse($emails->contains($this->steCoordinator->email));
        $this->assertFalse($emails->contains($this->dualCoordinator->email));

        $dualEmails = collect(
            $this->actingAs($this->dualCoordinator)
                ->getJson('/api/users')
                ->assertStatus(200)
                ->json('data')
        )->pluck('email');

        $this->assertTrue($dualEmails->contains($this->spictStaff->email));
        $this->assertTrue($dualEmails->contains($this->steStaff->email));
        $this->assertFalse($dualEmails->contains($this->sysadmin->email));

        $adminEmails = collect(
            $this->actingAs($this->sysadmin)
                ->getJson('/api/users')
                ->assertStatus(200)
                ->json('data')
        )->pluck('email');

        $this->assertTrue($adminEmails->contains($this->spictStaff->email));
        $this->assertTrue($adminEmails->contains($this->steStaff->email));
    }

    // ── User creation / management restrictions ───────────────────────────

    public function test_coordinator_creates_users_limited_to_their_program_and_roles(): void
    {
        $encoder = Role::where('name', 'Encoder')->firstOrFail();
        $coordinatorRole = Role::where('name', 'Coordinator')->firstOrFail();
        $systemAdministrator = Role::where('name', 'System Administrator')->firstOrFail();

        $this->actingAs($this->spictCoordinator)
            ->postJson('/api/users', [
                'lastname' => 'New',
                'firstname' => 'Staff',
                'middlename' => null,
                'extname' => null,
                'email' => 'spict-staff@example.com',
                'roles' => [$encoder->id],
                'program_access' => ['SPICT'],
            ])
            ->assertStatus(200);

        $created = User::where('email', 'spict-staff@example.com')->firstOrFail();
        $this->assertTrue($created->hasRole(['Encoder']));
        $this->assertSame(['SPICT'], $created->programCodes());

        // A SPICT-only Coordinator may not create STE accounts.
        $this->actingAs($this->spictCoordinator)
            ->postJson('/api/users', [
                'lastname' => 'Cross',
                'firstname' => 'Program',
                'middlename' => null,
                'extname' => null,
                'email' => 'ste-staff@example.com',
                'roles' => [$encoder->id],
                'program_access' => ['STE'],
            ])
            ->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'ste-staff@example.com']);

        // An account must be assigned to at least one managed program.
        $this->actingAs($this->spictCoordinator)
            ->postJson('/api/users', [
                'lastname' => 'No',
                'firstname' => 'Program',
                'middlename' => null,
                'extname' => null,
                'email' => 'no-program@example.com',
                'roles' => [$encoder->id],
                'program_access' => [],
            ])
            ->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'no-program@example.com']);

        // Privileged roles may never be assigned by a Coordinator.
        $this->actingAs($this->spictCoordinator)
            ->postJson('/api/users', [
                'lastname' => 'Fake',
                'firstname' => 'Admin',
                'middlename' => null,
                'extname' => null,
                'email' => 'fake-admin@example.com',
                'roles' => [$systemAdministrator->id],
                'program_access' => ['SPICT'],
            ])
            ->assertStatus(403);

        $this->actingAs($this->spictCoordinator)
            ->postJson('/api/users', [
                'lastname' => 'Fake',
                'firstname' => 'Coordinator',
                'middlename' => null,
                'extname' => null,
                'email' => 'fake-coordinator@example.com',
                'roles' => [$coordinatorRole->id],
                'program_access' => ['SPICT'],
            ])
            ->assertStatus(403);

        // The System Administrator shortcut is closed for Coordinators.
        $this->actingAs($this->spictCoordinator)
            ->postJson('/api/users', [
                'lastname' => 'Fake',
                'firstname' => 'Admin',
                'email' => 'admin-toggle@example.com',
                'admin' => 'Yes',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('users', ['email' => 'fake-admin@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'fake-coordinator@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'admin-toggle@example.com']);
    }

    public function test_dual_program_coordinator_can_assign_both_programs(): void
    {
        $encoder = Role::where('name', 'Encoder')->firstOrFail();

        $this->actingAs($this->dualCoordinator)
            ->postJson('/api/users', [
                'lastname' => 'Both',
                'firstname' => 'Programs',
                'middlename' => null,
                'extname' => null,
                'email' => 'dual-staff@example.com',
                'roles' => [$encoder->id],
                'program_access' => ['SPICT', 'STE'],
            ])
            ->assertStatus(200);

        $created = User::where('email', 'dual-staff@example.com')->firstOrFail();
        $this->assertEqualsCanonicalizing(['SPICT', 'STE'], $created->programCodes());
    }

    public function test_coordinator_cannot_modify_out_of_scope_or_privileged_accounts(): void
    {
        $encoder = Role::where('name', 'Encoder')->firstOrFail();

        $payload = [
            'lastname' => 'Updated',
            'firstname' => 'Name',
            'middlename' => null,
            'extname' => null,
            'email' => $this->steStaff->email,
            'status' => 0,
            'admin' => 'No',
            'roles' => [$encoder->id],
            'program_access' => ['STE'],
        ];

        // Cross-program user is out of scope.
        $this->actingAs($this->spictCoordinator)
            ->putJson('/api/users/'.$this->steStaff->id, $payload)
            ->assertStatus(403);
        $this->actingAs($this->spictCoordinator)
            ->deleteJson('/api/users/'.$this->steStaff->id)
            ->assertStatus(403);

        // Privileged accounts are never manageable.
        $this->actingAs($this->spictCoordinator)
            ->putJson('/api/users/'.$this->sysadmin->id, array_merge($payload, [
                'email' => $this->sysadmin->email,
                'program_access' => ['SPICT'],
            ]))
            ->assertStatus(403);
        $this->actingAs($this->spictCoordinator)
            ->deleteJson('/api/users/'.$this->sysadmin->id)
            ->assertStatus(403);

        // In-scope updates still work.
        $this->actingAs($this->spictCoordinator)
            ->putJson('/api/users/'.$this->spictStaff->id, array_merge($payload, [
                'email' => $this->spictStaff->email,
                'program_access' => ['SPICT'],
            ]))
            ->assertStatus(200);

        $this->spictStaff->refresh();
        $this->assertSame(0, (int) $this->spictStaff->is_active);
    }

    // ── Requirements (per-program settings) ───────────────────────────────

    public function test_coordinator_sees_only_requirements_relevant_to_their_program(): void
    {
        $this->makeRequirement('SPICT Requirement', [$this->spict->id]);
        $this->makeRequirement('STE Requirement', [$this->ste->id]);
        $this->makeRequirement('Shared Requirement', []);

        $names = collect(
            $this->actingAs($this->spictCoordinator)
                ->getJson('/api/requirements')
                ->assertStatus(200)
                ->json('data')
        )->pluck('name');

        $this->assertTrue($names->contains('SPICT Requirement'));
        $this->assertTrue($names->contains('Shared Requirement'));
        $this->assertFalse($names->contains('STE Requirement'));

        $adminNames = collect(
            $this->actingAs($this->sysadmin)
                ->getJson('/api/requirements')
                ->assertStatus(200)
                ->json('data')
        )->pluck('name');

        $this->assertTrue($adminNames->contains('STE Requirement'));
    }

    public function test_coordinator_can_only_create_requirements_for_their_program(): void
    {
        $this->actingAs($this->spictCoordinator)
            ->postJson('/api/requirements', $this->requirementPayload([
                'program_ids' => [$this->spict->id],
            ]))
            ->assertStatus(200);

        // Cross-program assignment is rejected.
        $this->actingAs($this->spictCoordinator)
            ->postJson('/api/requirements', $this->requirementPayload([
                'program_ids' => [$this->ste->id],
            ]))
            ->assertStatus(403);

        // "All Programs" would leak into the other program.
        $this->actingAs($this->spictCoordinator)
            ->postJson('/api/requirements', $this->requirementPayload([
                'program_ids' => [],
            ]))
            ->assertStatus(403);
    }

    public function test_coordinator_cannot_touch_requirements_outside_their_program(): void
    {
        $steRequirement = $this->makeRequirement('STE Only', [$this->ste->id]);
        $sharedRequirement = $this->makeRequirement('Shared All', []);
        $spictRequirement = $this->makeRequirement('SPICT Only', [$this->spict->id]);

        // Updating / deleting an STE requirement is forbidden.
        $this->actingAs($this->spictCoordinator)
            ->putJson('/api/requirements/'.$steRequirement->id, [
                'name' => 'Renamed By Coordinator',
                'is_required' => 1,
                'is_active' => 1,
                'program_ids' => [$this->ste->id],
            ])
            ->assertStatus(403);
        $this->actingAs($this->spictCoordinator)
            ->deleteJson('/api/requirements/'.$steRequirement->id)
            ->assertStatus(403);

        // "All Programs" requirements are System Administrator territory.
        $this->actingAs($this->spictCoordinator)
            ->putJson('/api/requirements/'.$sharedRequirement->id, [
                'name' => 'Renamed Shared',
                'is_required' => 1,
                'is_active' => 1,
                'program_ids' => [$this->spict->id],
            ])
            ->assertStatus(403);
        $this->actingAs($this->spictCoordinator)
            ->deleteJson('/api/requirements/'.$sharedRequirement->id)
            ->assertStatus(403);

        // Renaming an STE requirement to a SPICT one is still an STE change.
        $this->actingAs($this->steCoordinator)
            ->putJson('/api/requirements/'.$spictRequirement->id, [
                'name' => 'Taken Over',
                'is_required' => 1,
                'is_active' => 1,
                'program_ids' => [$this->spict->id],
            ])
            ->assertStatus(403);

        $this->assertSame('STE Only', $steRequirement->fresh()->name);
        $this->assertSame('Shared All', $sharedRequirement->fresh()->name);
        $this->assertSame('SPICT Only', $spictRequirement->fresh()->name);
    }

    public function test_coordinator_can_update_and_toggle_their_own_requirement(): void
    {
        $spictRequirement = $this->makeRequirement('SPICT Docs', [$this->spict->id]);

        $this->actingAs($this->spictCoordinator)
            ->putJson('/api/requirements/'.$spictRequirement->id, [
                'name' => 'SPICT Docs Updated',
                'is_required' => 1,
                'is_active' => 1,
                'program_ids' => [$this->spict->id],
            ])
            ->assertStatus(200);

        $this->assertSame('SPICT Docs Updated', $spictRequirement->fresh()->name);

        // The activate/deactivate toggle sends no program_ids key.
        $this->actingAs($this->spictCoordinator)
            ->putJson('/api/requirements/'.$spictRequirement->id, [
                'name' => 'SPICT Docs Updated',
                'is_required' => 1,
                'is_active' => 0,
            ])
            ->assertStatus(200);

        $this->assertFalse($spictRequirement->fresh()->is_active);

        $this->actingAs($this->spictCoordinator)
            ->deleteJson('/api/requirements/'.$spictRequirement->id)
            ->assertStatus(200);
    }

    public function test_coordinator_can_access_the_application_period_settings(): void
    {
        $this->actingAs($this->spictCoordinator)
            ->getJson('/api/application-period')
            ->assertStatus(200);

        $this->actingAs($this->sysadmin)
            ->getJson('/api/application-period')
            ->assertStatus(200);
    }
}
