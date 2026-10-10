<?php

namespace Tests\Feature;

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

class ApplicationPeriodTest extends TestCase
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
            'application_start' => today()->subDays(10)->toDateString(),
            'application_deadline' => today()->addDays(10)->toDateString(),
        ]);

        $this->applicant = Applicant::create([
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'application_status' => 'pending',
        ]);
    }

    private function admin(): User
    {
        $role = Role::where('name', 'System Administrator')->firstOrFail();
        $admin = User::create([
            'firstname' => 'Test',
            'lastname' => 'Admin',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
        ]);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_settings_return_the_active_school_year_period(): void
    {
        $response = $this->actingAs($this->admin())->getJson('/api/application-period');

        $response->assertStatus(200);
        $response->assertJsonPath('school_year', 'SY 2026 - 2027');
        $response->assertJsonPath('application_start', today()->subDays(10)->toDateString());
        $response->assertJsonPath('application_deadline', today()->addDays(10)->toDateString());
    }

    public function test_saving_the_period_updates_only_the_active_school_year(): void
    {
        $inactive = SchoolYear::create([
            'start' => '2025',
            'end' => '2026',
            'name' => 'SY 2025 - 2026',
            'is_active' => false,
            'application_start' => '2025-06-01',
            'application_deadline' => '2025-07-15',
        ]);

        $response = $this->actingAs($this->admin())->postJson('/api/application-period', [
            'application_start' => '2026-12-01',
            'application_deadline' => '2027-01-15',
        ]);

        $response->assertStatus(200);

        $this->schoolYear->refresh();
        $this->assertSame('2026-12-01', $this->schoolYear->application_start);
        $this->assertSame('2027-01-15', $this->schoolYear->application_deadline);

        $inactive->refresh();
        $this->assertSame('2025-06-01', $inactive->application_start);
        $this->assertSame('2025-07-15', $inactive->application_deadline);
    }

    public function test_saving_the_period_requires_an_active_school_year(): void
    {
        $this->schoolYear->update(['is_active' => false]);

        $this->actingAs($this->admin())->postJson('/api/application-period', [
            'application_start' => '2026-12-01',
            'application_deadline' => '2027-01-15',
        ])->assertStatus(400);
    }

    public function test_saving_the_period_validates_the_dates(): void
    {
        $this->actingAs($this->admin())->postJson('/api/application-period', [
            'application_start' => '06-01',
            'application_deadline' => '07-15',
        ])->assertStatus(422);

        $this->actingAs($this->admin())->postJson('/api/application-period', [
            'application_start' => '2027-01-15',
            'application_deadline' => '2026-12-01',
        ])->assertStatus(422);
    }

    public function test_dashboard_exposes_the_application_period_status(): void
    {
        $response = $this->actingAs($this->applicant, 'applicant')
            ->getJson('/api/applicant/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('application_period.status', 'open');
    }

    public function test_application_period_is_closed_when_dates_are_not_configured(): void
    {
        $this->schoolYear->update([
            'application_start' => null,
            'application_deadline' => null,
        ]);

        $response = $this->actingAs($this->applicant, 'applicant')
            ->getJson('/api/applicant/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('application_period.status', 'closed');
    }

    public function test_submit_is_rejected_before_the_period_opens(): void
    {
        $this->schoolYear->update([
            'application_start' => today()->addDays(7)->toDateString(),
            'application_deadline' => today()->addDays(30)->toDateString(),
        ]);

        $response = $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application', []);

        $response->assertStatus(422);
        $this->assertStringContainsString('not yet open', $response->json('message'));
        $this->assertNull($this->applicant->fresh()->submitted_at);
    }

    public function test_submit_is_rejected_after_the_period_closes(): void
    {
        $this->schoolYear->update([
            'application_start' => today()->subDays(30)->toDateString(),
            'application_deadline' => today()->subDays(7)->toDateString(),
        ]);

        $response = $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application', []);

        $response->assertStatus(422);
        $this->assertStringContainsString('period has ended', $response->json('message'));
        $this->assertNull($this->applicant->fresh()->submitted_at);
    }

    public function test_submit_is_accepted_while_the_period_is_open(): void
    {
        $program = Program::where('initials', 'SPICT')->firstOrFail();

        // The program is selected up front; the submission itself carries no id.
        $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application/select-program', ['program' => 'SPICT'])
            ->assertStatus(200);

        $response = $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application', [
                'lastname' => 'Dela Cruz',
                'firstname' => 'Juan',
                'date_of_birth' => '2015-06-15',
                'place_of_birth' => 'Carranglan',
                'citizenship' => 'Filipino',
                'sex' => 'Male',
                'complete_address' => 'Barangay Poblacion, Carranglan, Nueva Ecija',
                'parent_guardian_name' => 'Maria Dela Cruz',
                'contact_number' => '09171234567',
                'elementary_school' => 'Carranglan National High School',
                'elementary_school_address' => 'Carranglan, Nueva Ecija',
            ]);

        $response->assertStatus(200);

        $submitted = $this->applicant->fresh();
        $this->assertNotNull($submitted->submitted_at);
        $this->assertSame($program->id, $submitted->program_id);
        $this->assertSame($this->schoolYear->id, $submitted->school_year_id);
    }
}
