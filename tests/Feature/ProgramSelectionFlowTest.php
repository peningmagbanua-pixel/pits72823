<?php

namespace Tests\Feature;

use App\Models\Admin\Program;
use App\Models\Admin\Requirement;
use App\Models\Admin\SchoolYear;
use App\Models\Applicant\Applicant;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RequirementsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProgramSelectionFlowTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $schoolYear;

    private Applicant $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(ProgramsSeeder::class);
        $this->seed(RequirementsTableSeeder::class);

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
        ]);
    }

    private function selectProgram(string $initials)
    {
        return $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application/select-program', ['program' => $initials]);
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationPayload(): array
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

    public function test_selection_returns_public_initials_and_never_a_program_id(): void
    {
        $response = $this->selectProgram('SPICT')->assertStatus(200);

        $response->assertJsonPath('selected_program.initials', 'SPICT');
        $this->assertArrayNotHasKey('id', $response->json('selected_program'));

        // The generic form URL is backed by the show endpoint, which reports
        // the same id-free payload so the frontend never sees a program id.
        $show = $this->actingAs($this->applicant, 'applicant')
            ->getJson('/api/applicant/application')
            ->assertOk();

        $show->assertJsonPath('selected_program.initials', 'SPICT');
        $this->assertArrayNotHasKey('id', $show->json('selected_program'));
    }

    public function test_unknown_missing_and_inactive_programs_are_rejected(): void
    {
        $this->selectProgram('NOPE')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['program']);

        $this->selectProgram('')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['program']);

        Program::where('initials', 'STE')->firstOrFail()->update(['is_active' => false]);

        $this->selectProgram('STE')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['program']);
    }

    public function test_selection_is_blocked_once_an_application_is_submitted(): void
    {
        $this->applicant->update([
            'submitted_at' => now(),
            'application_status' => 'pending',
        ]);

        $this->selectProgram('SPICT')
            ->assertStatus(422)
            ->assertJson(['message' => 'You have already submitted an application.']);

        // A rejected applicant may pick a program again (reapplication).
        $this->applicant->update(['application_status' => 'rejected']);

        $this->selectProgram('STE')->assertStatus(200);
    }

    public function test_submit_requires_a_server_side_selection(): void
    {
        $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application', $this->applicationPayload())
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The selected program is invalid. Please select a program and try again.',
            ]);

        $this->assertNull($this->applicant->fresh()->submitted_at);
    }

    public function test_submit_ignores_any_client_supplied_program_id(): void
    {
        // Isolate: no file requirements, so the payload passes on its own.
        Requirement::query()->update(['is_active' => false]);

        $spict = Program::where('initials', 'SPICT')->firstOrFail();
        $ste = Program::where('initials', 'STE')->firstOrFail();

        $this->selectProgram('SPICT')->assertStatus(200);

        // Attempt to manipulate the program through the request body.
        $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application', array_merge($this->applicationPayload(), [
                'program_id' => $ste->id,
            ]))
            ->assertStatus(200);

        $this->assertSame($spict->id, $this->applicant->fresh()->program_id);
    }

    public function test_deactivating_the_selected_program_blocks_the_form(): void
    {
        Requirement::query()->update(['is_active' => false]);

        $this->selectProgram('SPICT')->assertStatus(200);

        Program::where('initials', 'SPICT')->firstOrFail()->update(['is_active' => false]);

        $show = $this->actingAs($this->applicant, 'applicant')
            ->getJson('/api/applicant/application')
            ->assertOk();
        $this->assertNull($show->json('selected_program'));

        $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application', $this->applicationPayload())
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The selected program is invalid. Please select a program and try again.',
            ]);
    }

    public function test_requirements_are_filtered_by_public_program_initials(): void
    {
        $requirementName = 'Certificate of Good Moral Character';

        $all = collect(
            $this->actingAs($this->applicant, 'applicant')
                ->getJson('/api/applicant/requirements')
                ->assertOk()
                ->json()
        );
        $this->assertTrue($all->contains('name', $requirementName));

        // The STE-only requirement must not be listed for SPICT ...
        $spict = collect(
            $this->actingAs($this->applicant, 'applicant')
                ->getJson('/api/applicant/requirements?program=SPICT')
                ->assertOk()
                ->json()
        );
        $this->assertFalse($spict->contains('name', $requirementName));

        // ... but must be listed for STE, resolved server-side from initials.
        $ste = collect(
            $this->actingAs($this->applicant, 'applicant')
                ->getJson('/api/applicant/requirements?program=STE')
                ->assertOk()
                ->json()
        );
        $this->assertTrue($ste->contains('name', $requirementName));

        // Unknown or inactive programs are rejected server-side.
        $this->actingAs($this->applicant, 'applicant')
            ->getJson('/api/applicant/requirements?program=NOPE')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['program']);
    }
}
