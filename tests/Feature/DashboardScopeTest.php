<?php

namespace Tests\Feature;

use App\Models\Admin\ExamSchedule;
use App\Models\Admin\Permission;
use App\Models\Admin\Program;
use App\Models\Admin\Role;
use App\Models\Admin\User;
use App\Models\Applicant\Applicant;
use Database\Seeders\PermissionTitlesSeeder;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $sysadmin;

    private User $spictStaff;

    private User $steStaff;

    private User $unassignedStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(PermissionTitlesSeeder::class);
        $this->seed(ProgramsSeeder::class);

        $systemAdministrator = Role::where('name', 'System Administrator')->firstOrFail();
        $encoder = Role::where('name', 'Encoder')->firstOrFail();
        $encoder->permissions()->attach(
            Permission::where('name', 'browse applications')->firstOrFail()->id
        );
        $encoder->permissions()->attach(
            Permission::where('name', 'browse dashboard')->firstOrFail()->id
        );

        $this->sysadmin = $this->makeUser();
        $this->sysadmin->roles()->attach($systemAdministrator->id);

        $this->spictStaff = $this->makeUser();
        $this->spictStaff->roles()->attach($encoder->id);
        $this->spictStaff->program_assignments()->create(['program' => 'SPICT']);

        $this->steStaff = $this->makeUser();
        $this->steStaff->roles()->attach($encoder->id);
        $this->steStaff->program_assignments()->create(['program' => 'STE']);

        $this->unassignedStaff = $this->makeUser();
        $this->unassignedStaff->roles()->attach($encoder->id);

        $spict = Program::where('initials', 'SPICT')->firstOrFail();
        $ste = Program::where('initials', 'STE')->firstOrFail();

        $this->makeApplicant($spict, 'approved');
        $this->makeApplicant($spict, 'pending');
        $this->makeApplicant($ste, 'pending');

        $this->makeSchedule($spict);
        $this->makeSchedule($ste);
    }

    private function makeUser(): User
    {
        return User::create([
            'firstname' => 'Test',
            'lastname' => 'Staff',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
        ]);
    }

    private function makeApplicant(Program $program, string $status): Applicant
    {
        return Applicant::create([
            'firstname' => 'Applicant',
            'lastname' => 'Test',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'program_id' => $program->id,
            'application_status' => $status,
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

    private function stats(User $user): array
    {
        $response = $this->actingAs($user)->getJson('/api/dashboard/stats');

        $response->assertStatus(200);

        return $response->json();
    }

    public function test_system_administrator_sees_all_programs(): void
    {
        $stats = $this->stats($this->sysadmin);

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['pending']);
        $this->assertSame(1, $stats['approved']);
        $this->assertSame(0, $stats['rejected']);
        $this->assertSame(2, $stats['spict_count']);
        $this->assertSame(1, $stats['ste_count']);

        $this->assertEqualsCanonicalizing(
            ['SPICT', 'STE'],
            collect($stats['recent'])->pluck('program')->unique()->sort()->values()->all()
        );
        $this->assertEqualsCanonicalizing(
            ['SPICT', 'STE'],
            collect($stats['upcoming_exams'])->pluck('program')->unique()->sort()->values()->all()
        );
    }

    public function test_spict_staff_sees_only_spict_data(): void
    {
        $stats = $this->stats($this->spictStaff);

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['approved']);
        $this->assertSame(0, $stats['rejected']);

        $this->assertSame(2, $stats['spict_count']);
        $this->assertSame(0, $stats['ste_count']);

        $this->assertSame(2, $stats['today_count']);
        $this->assertSame(2, $stats['week_count']);
        $this->assertSame(2, $stats['month_count']);
        $this->assertSame(
            2,
            collect($stats['monthly'])->sum(fn ($r) => (int) $r['count'])
        );

        $this->assertSame(
            ['SPICT'],
            collect($stats['recent'])->pluck('program')->unique()->values()->all()
        );
        $this->assertSame(
            ['SPICT'],
            collect($stats['upcoming_exams'])->pluck('program')->unique()->values()->all()
        );
    }

    public function test_ste_staff_sees_only_ste_data(): void
    {
        $stats = $this->stats($this->steStaff);

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(0, $stats['approved']);
        $this->assertSame(0, $stats['rejected']);

        $this->assertSame(1, $stats['ste_count']);
        $this->assertSame(0, $stats['spict_count']);

        $this->assertSame(1, $stats['today_count']);
        $this->assertSame(1, $stats['week_count']);
        $this->assertSame(1, $stats['month_count']);
        $this->assertSame(
            1,
            collect($stats['monthly'])->sum(fn ($r) => (int) $r['count'])
        );

        $this->assertSame(
            ['STE'],
            collect($stats['recent'])->pluck('program')->unique()->values()->all()
        );
        $this->assertSame(
            ['STE'],
            collect($stats['upcoming_exams'])->pluck('program')->unique()->values()->all()
        );
    }

    public function test_staff_without_program_assignment_sees_no_data(): void
    {
        $stats = $this->stats($this->unassignedStaff);

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['pending']);
        $this->assertSame(0, $stats['approved']);
        $this->assertSame(0, $stats['rejected']);
        $this->assertSame(0, $stats['spict_count']);
        $this->assertSame(0, $stats['ste_count']);
        $this->assertSame([], $stats['recent']);
        $this->assertSame([], $stats['upcoming_exams']);
    }
}
