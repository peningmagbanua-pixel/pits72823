<?php

namespace Tests\Feature;

use App\Models\Admin\Permission;
use App\Models\Admin\Program;
use App\Models\Admin\Requirement;
use App\Models\Admin\Role;
use App\Models\Admin\SchoolYear;
use App\Models\Admin\User;
use App\Models\Applicant\Applicant;
use App\Models\Applicant\ApplicantDocument;
use Database\Seeders\PermissionTitlesSeeder;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RequirementsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequirementSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $sysadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(PermissionTitlesSeeder::class);
        $this->seed(ProgramsSeeder::class);
        $this->seed(RequirementsTableSeeder::class);

        $role = Role::where('name', 'System Administrator')->firstOrFail();
        $role->permissions()->attach(
            Permission::where('name', 'browse applications')->firstOrFail()->id
        );

        $this->sysadmin = User::create([
            'firstname' => 'Test',
            'lastname' => 'Admin',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
        ]);
        $this->sysadmin->roles()->attach($role->id);

        // Submissions are only accepted while the online application period
        // of the active school year is open.
        SchoolYear::create([
            'start' => '2026',
            'end' => '2027',
            'name' => 'SY 2026 - 2027',
            'is_active' => true,
            'application_start' => today()->subDays(30)->toDateString(),
            'application_deadline' => today()->addDays(30)->toDateString(),
        ]);
    }

    private function makeApplicant(array $attributes = []): Applicant
    {
        $program = Program::where('initials', 'SPICT')->firstOrFail();

        return Applicant::create(array_merge([
            'firstname' => 'Test',
            'lastname' => 'Applicant',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'program_id' => $program->id,
        ], $attributes));
    }

    /**
     * Base application payload; pass extra keys for files and overrides.
     * The program is never part of the payload — it is resolved server-side
     * from the selection step.
     *
     * @return array<string, mixed>
     */
    private function submissionPayload(array $extra = []): array
    {
        return array_merge([
            'lastname' => 'Dela Cruz',
            'firstname' => 'Juan',
            'date_of_birth' => '2015-01-01',
            'place_of_birth' => 'Carranglan',
            'citizenship' => 'Filipino',
            'sex' => 'Male',
            'complete_address' => 'Poblacion, Carranglan, Nueva Ecija',
            'parent_guardian_name' => 'Maria Dela Cruz',
            'contact_number' => '09171234567',
            'elementary_school' => 'Carranglan Central School',
            'elementary_school_address' => 'Poblacion, Carranglan, Nueva Ecija',
        ], $extra);
    }

    /**
     * Pick the program the way the selection page does (public initials in,
     * resolved id kept server-side in the session).
     */
    private function selectProgram(Applicant $applicant, string $initials): void
    {
        $this->actingAs($applicant, 'applicant')
            ->postJson('/api/applicant/application/select-program', ['program' => $initials])
            ->assertOk();
    }

    /**
     * Documents shown on the admin Applicants page for an applicant.
     *
     * @return array<int, array<string, mixed>>
     */
    private function adminDocuments(Applicant $applicant): array
    {
        $response = $this->actingAs($this->sysadmin, 'web')
            ->getJson('/api/applicants/'.$applicant->id)
            ->assertOk();

        return $response->json('data.documents') ?? [];
    }

    public function test_applicant_requirements_expose_the_keys_the_form_and_server_agree_on(): void
    {
        $generic = Requirement::create([
            'name' => 'Birth Certificate Copy',
            'field_key' => null,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $unknown = Requirement::create([
            'name' => 'Insurance Card',
            'field_key' => 'insurance_card',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $response = $this->actingAs($this->makeApplicant(), 'applicant')
            ->getJson('/api/applicant/requirements')
            ->assertOk();

        $byName = collect($response->json())->keyBy('name');

        $sf9 = $byName->get('SF9 / Report Card');
        $this->assertSame('file', $sf9['type']);
        $this->assertSame('sf9_file', $sf9['input_key']);
        $this->assertSame('sf9_file', $sf9['error_key']);

        $declaration = $byName->get('Health Declaration Form');
        $this->assertSame('declaration', $declaration['type']);
        $this->assertSame('ste_declaration', $declaration['input_key']);
        $this->assertSame('ste_declaration', $declaration['error_key']);

        $this->assertSame('files.'.$generic->id, $byName->get('Birth Certificate Copy')['error_key']);
        $this->assertSame('files['.$generic->id.']', $byName->get('Birth Certificate Copy')['input_key']);

        // An unrecognized field_key must not become the upload key, otherwise
        // the file would be posted under a key the server never stores.
        $this->assertSame('files['.$unknown->id.']', $byName->get('Insurance Card')['input_key']);
        $this->assertSame('files.'.$unknown->id, $byName->get('Insurance Card')['error_key']);
    }

    public function test_requirement_with_unknown_field_key_is_uploaded_and_shown_to_reviewers(): void
    {
        Storage::fake('local');

        // Isolate the requirement under test.
        Requirement::query()->update(['is_active' => false]);

        $requirement = Requirement::create([
            'name' => 'Birth Certificate Copy',
            'field_key' => 'birth_cert',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $applicant = $this->makeApplicant();
        $this->selectProgram($applicant, 'SPICT');

        $this->actingAs($applicant, 'applicant')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/applicant/application', $this->submissionPayload([
                'files' => [$requirement->id => UploadedFile::fake()->create('birth.pdf', 64)],
            ]))
            ->assertOk();

        $this->assertNotNull($applicant->fresh()->submitted_at);

        $document = ApplicantDocument::where('applicant_id', $applicant->id)
            ->where('requirement_id', $requirement->id)
            ->first();

        $this->assertNotNull($document, 'The upload was silently dropped.');
        $this->assertNotNull($document->file_path);
        $this->assertSame('Birth Certificate Copy', $document->requirement_name);

        $reviewerView = collect($this->adminDocuments($applicant))->firstWhere('name', 'Birth Certificate Copy');

        $this->assertNotNull($reviewerView);
        $this->assertNotNull($reviewerView['url']);
        $this->assertFalse($reviewerView['is_orphan']);
    }

    public function test_deleting_a_requirement_keeps_existing_uploads_visible_under_their_original_name(): void
    {
        $applicant = $this->makeApplicant();

        $requirement = Requirement::create([
            'name' => 'Old Requirement',
            'field_key' => null,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'requirement_id' => $requirement->id,
            'requirement_name' => 'Old Requirement',
            'file_path' => 'applications/'.$applicant->id.'/old.pdf',
        ]);

        // A legacy row without a name snapshot still resolves via soft delete.
        ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'requirement_id' => $requirement->id,
            'file_path' => 'applications/'.$applicant->id.'/legacy.pdf',
        ]);

        $requirement->delete();

        $documents = collect($this->adminDocuments($applicant));

        $this->assertCount(2, $documents->where('is_orphan'));

        foreach ($documents->where('is_orphan') as $document) {
            $this->assertSame('Old Requirement', $document['name']);
            $this->assertNotNull($document['url']);
            $this->assertFalse($document['completed']);
        }
    }

    public function test_health_declaration_stays_visible_after_its_requirement_is_deleted(): void
    {
        $program = Program::where('initials', 'STE')->firstOrFail();
        $applicant = $this->makeApplicant([
            'program_id' => $program->id,
            'ste_declaration' => ['learner_name' => 'Juan Dela Cruz'],
        ]);

        Requirement::where('field_key', 'ste_declaration')->firstOrFail()->delete();

        $document = collect($this->adminDocuments($applicant))
            ->firstWhere('name', 'Health Declaration Form');

        $this->assertNotNull($document, 'The declaration disappeared from the Applicants page.');
        $this->assertSame('declaration', $document['type']);
        $this->assertTrue($document['completed']);
        $this->assertTrue($document['is_orphan']);
    }

    public function test_deleting_the_sf9_requirement_does_not_break_submitted_status(): void
    {
        $applicant = $this->makeApplicant(['submitted_at' => now()]);

        Requirement::where('field_key', 'sf9_file')->firstOrFail()->delete();

        $this->actingAs($applicant, 'applicant')
            ->getJson('/api/applicant/application')
            ->assertOk()
            ->assertJson(['submitted' => true]);

        $this->actingAs($applicant, 'applicant')
            ->postJson('/api/applicant/application', [])
            ->assertStatus(422)
            ->assertJson(['message' => 'You have already submitted an application.']);
    }

    public function test_deactivating_a_requirement_removes_it_from_the_form_and_the_applicants_page(): void
    {
        $applicant = $this->makeApplicant();

        $requirement = Requirement::create([
            'name' => 'Proof of Residency',
            'field_key' => null,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $listedOnForm = fn () => collect(
            $this->actingAs($applicant, 'applicant')
                ->getJson('/api/applicant/requirements')
                ->assertOk()
                ->json()
        )->contains('name', 'Proof of Residency');

        $listedForReviewer = fn () => collect($this->adminDocuments($applicant))
            ->contains('name', 'Proof of Residency');

        $this->assertTrue($listedOnForm(), 'Missing from the application form.');
        $this->assertTrue($listedForReviewer(), 'Missing from the Applicants page.');

        $reviewerEntry = collect($this->adminDocuments($applicant))
            ->firstWhere('name', 'Proof of Residency');
        $this->assertNull($reviewerEntry['url'], 'Should show as not submitted.');

        $requirement->update(['is_active' => false]);

        $this->assertFalse($listedOnForm(), 'Still shown on the application form.');
        $this->assertFalse($listedForReviewer(), 'Still shown on the Applicants page.');
    }

    public function test_a_requirement_assigned_to_one_program_is_only_required_for_that_program(): void
    {
        Storage::fake('local');

        // Isolate: exactly one active requirement, assigned to STE only.
        Requirement::query()->update(['is_active' => false]);

        $ste = Program::where('initials', 'STE')->firstOrFail();

        $steOnly = Requirement::create([
            'name' => 'STE Only Document',
            'field_key' => null,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $steOnly->programs()->attach($ste->id);

        // A SPICT applicant is not asked for it, so submitting without it passes.
        $spictApplicant = $this->makeApplicant();
        $this->selectProgram($spictApplicant, 'SPICT');

        $this->actingAs($spictApplicant, 'applicant')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/applicant/application', $this->submissionPayload())
            ->assertOk();

        $this->assertNotNull($spictApplicant->fresh()->submitted_at);

        // An STE applicant must provide it — the server enforces the assignment.
        $steApplicant = $this->makeApplicant(['program_id' => $ste->id]);
        $this->selectProgram($steApplicant, 'STE');

        $response = $this->actingAs($steApplicant, 'applicant')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/applicant/application', $this->submissionPayload());

        $response->assertStatus(422);
        $this->assertArrayHasKey('files.'.$steOnly->id, $response->json('errors'));
        $this->assertNull($steApplicant->fresh()->submitted_at);

        // Both applicants see the same list; the ids are what tell the form apart.
        foreach ([$spictApplicant, $steApplicant] as $applicant) {
            $listed = collect(
                $this->actingAs($applicant, 'applicant')
                    ->getJson('/api/applicant/requirements')
                    ->assertOk()
                    ->json()
            )->firstWhere('name', 'STE Only Document');

            $this->assertSame([$ste->id], $listed['program_ids']);
        }
    }
}
