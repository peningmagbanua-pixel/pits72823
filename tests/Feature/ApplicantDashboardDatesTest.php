<?php

namespace Tests\Feature;

use App\Models\Admin\ExamSchedule;
use App\Models\Admin\Program;
use App\Models\Admin\Role;
use App\Models\Admin\SchoolYear;
use App\Models\Admin\User;
use App\Models\Applicant\Applicant;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplicantDashboardDatesTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $schoolYear;

    private Applicant $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(ProgramsSeeder::class);

        $this->schoolYear = SchoolYear::create([
            'start' => '2026',
            'end' => '2027',
            'name' => 'SY 2026 - 2027',
            'is_active' => true,
            'application_start' => '2026-06-01',
            'application_deadline' => '2026-07-15',
        ]);

        $this->applicant = Applicant::create([
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'school_year_id' => $this->schoolYear->id,
            'application_status' => 'pending',
        ]);
    }

    public function test_dashboard_returns_the_stored_school_year_application_dates(): void
    {
        $response = $this->actingAs($this->applicant, 'applicant')
            ->getJson('/api/applicant/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('school_year.application_start', '2026-06-01');
        $response->assertJsonPath('school_year.application_deadline', '2026-07-15');
        $this->assertArrayNotHasKey('exam_date', $response->json('school_year'));
    }

    public function test_dashboard_returns_null_dates_when_the_dates_are_not_set(): void
    {
        $this->schoolYear->update([
            'application_start' => null,
            'application_deadline' => null,
        ]);

        $response = $this->actingAs($this->applicant, 'applicant')
            ->getJson('/api/applicant/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('school_year.application_start', null);
        $response->assertJsonPath('school_year.application_deadline', null);
    }

    public function test_dashboard_exposes_the_scheduled_exam_date(): void
    {
        $program = Program::where('initials', 'SPICT')->firstOrFail();

        $schedule = ExamSchedule::create([
            'school_year_id' => $this->schoolYear->id,
            'program_id' => $program->id,
            'date' => '2026-10-09',
            'time_slot' => '8:00 am - 10:00 am',
            'room' => 'Room 32',
            'capacity' => 50,
        ]);

        $this->applicant->update(['schedule_id' => $schedule->id]);

        $response = $this->actingAs($this->applicant, 'applicant')
            ->getJson('/api/applicant/dashboard');

        $response->assertStatus(200);
        $this->assertSame('2026-10-09', $response->json('schedule.date'));
    }

    public function test_school_year_settings_only_accept_full_dates(): void
    {
        $role = Role::where('name', 'System Administrator')->firstOrFail();
        $admin = User::create([
            'firstname' => 'Test',
            'lastname' => 'Admin',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
        ]);
        $admin->roles()->attach($role->id);

        $this->actingAs($admin)->postJson('/api/school-years', [
            'school_year_start' => '2027',
            'school_year_end' => '2028',
            'application_start' => '06-01',
            'application_deadline' => '07-15',
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/api/school-years', [
            'school_year_start' => '2027',
            'school_year_end' => '2028',
            'application_start' => '2027-06-01',
            'application_deadline' => '2027-07-15',
        ])->assertStatus(200);

        $created = SchoolYear::where('start', '2027')->whereNull('deleted_at')->firstOrFail();
        $this->assertSame('2027-06-01', $created->application_start);
        $this->assertSame('2027-07-15', $created->application_deadline);
    }
}
