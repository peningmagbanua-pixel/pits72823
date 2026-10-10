<?php

namespace Tests\Feature;

use App\Models\Admin\Program;
use App\Models\Admin\SchoolYear;
use App\Models\Applicant\Applicant;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReapplicationTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $schoolYear;

    private Program $program;

    private Applicant $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(ProgramsSeeder::class);

        $this->program = Program::where('initials', 'SPICT')->firstOrFail();

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
            'program_id' => $this->program->id,
            'school_year_id' => $this->schoolYear->id,
            'application_status' => 'pending',
            'submitted_at' => now()->subDay(),
        ]);
    }

    private function submissionPayload(): array
    {
        return [
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
        ];
    }

    private function rejectApplicant(string $reason = 'Missing birth certificate.'): void
    {
        $this->applicant->update([
            'application_status' => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Pick the program the way the selection page does; the resolved id is
     * kept server-side in the session and never sent by the client.
     */
    private function selectProgram(): void
    {
        $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application/select-program', [
                'program' => $this->program->initials,
            ])
            ->assertStatus(200);
    }

    public function test_rejected_applicant_can_resubmit_and_status_resets_to_pending(): void
    {
        $this->rejectApplicant();
        $this->applicant->update(['exam_status' => 'Failed']);

        $this->selectProgram();

        $response = $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application', $this->submissionPayload());

        $response->assertStatus(200);

        $fresh = $this->applicant->fresh();
        $this->assertSame('pending', $fresh->application_status);
        $this->assertNull($fresh->rejection_reason);
        $this->assertNull($fresh->exam_status);
        $this->assertNull($fresh->schedule_id);
        $this->assertNotNull($fresh->submitted_at);
        $this->assertSame($this->program->id, $fresh->program_id);
        $this->assertSame($this->applicant->email, $fresh->email);
    }

    public function test_pending_applicant_cannot_resubmit(): void
    {
        $response = $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application', $this->submissionPayload());

        $response->assertStatus(422);
        $this->assertSame('You have already submitted an application.', $response->json('message'));
        $this->assertSame('pending', $this->applicant->fresh()->application_status);
    }

    public function test_approved_applicant_cannot_resubmit(): void
    {
        $this->applicant->update(['application_status' => 'approved']);

        $response = $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application', $this->submissionPayload());

        $response->assertStatus(422);
        $this->assertSame('You have already submitted an application.', $response->json('message'));
        $this->assertSame('approved', $this->applicant->fresh()->application_status);
    }

    public function test_application_show_exposes_the_rejected_status_and_reason(): void
    {
        $this->rejectApplicant('Incomplete requirements.');

        $response = $this->actingAs($this->applicant, 'applicant')
            ->getJson('/api/applicant/application');

        $response->assertStatus(200);
        $response->assertJsonPath('submitted', true);
        $response->assertJsonPath('application_status', 'rejected');
        $response->assertJsonPath('rejection_reason', 'Incomplete requirements.');
    }

    public function test_profile_exposes_the_application_status(): void
    {
        $this->rejectApplicant();

        $response = $this->actingAs($this->applicant, 'applicant')
            ->getJson('/api/applicant/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('application_status', 'rejected');
        $response->assertJsonPath('email', $this->applicant->email);
    }

    public function test_status_hides_the_form_again_after_resubmission(): void
    {
        $this->rejectApplicant();
        $this->selectProgram();

        $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application', $this->submissionPayload())
            ->assertStatus(200);

        // Now pending: the form must no longer be accessible.
        $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application', $this->submissionPayload())
            ->assertStatus(422);

        $profile = $this->actingAs($this->applicant, 'applicant')
            ->getJson('/api/applicant/profile');

        $profile->assertJsonPath('application_status', 'pending');
    }
}
