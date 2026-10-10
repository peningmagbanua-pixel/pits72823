<?php

namespace Tests\Feature;

use App\Models\Admin\Program;
use App\Models\Admin\Requirement;
use App\Models\Admin\SchoolYear;
use App\Models\Applicant\Applicant;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HealthDeclarationSignatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tiny 1x1 PNG — enough to satisfy the data-URL format rule.
     */
    private const SIGNATURE = 'data:image/png;base64,'
        .'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(ProgramsSeeder::class);

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

        // Isolate the declaration requirement: no other requirements are
        // seeded, so no file uploads are needed alongside the payload.
        Requirement::create([
            'name' => 'Health Declaration Form',
            'field_key' => 'ste_declaration',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 3,
        ]);
    }

    private function makeSteApplicant(): Applicant
    {
        $program = Program::where('initials', 'STE')->firstOrFail();

        $applicant = Applicant::create([
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'program_id' => $program->id,
        ]);

        $this->actingAs($applicant, 'applicant')
            ->postJson('/api/applicant/application/select-program', ['program' => 'STE'])
            ->assertOk();

        return $applicant;
    }

    /**
     * Base application payload plus a complete Health Declaration Form.
     * The program is never part of the payload — it comes from the session.
     *
     * @return array<string, mixed>
     */
    private function payloadWithSignature(?string $signature): array
    {
        return [
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
            'ste_declaration' => array_filter([
                'learner_name' => 'Juan Dela Cruz',
                'health_agreed' => '1',
                'conditions' => ['None'],
                'others_text' => '',
                'guardian_name' => 'Maria Dela Cruz',
                'date_signed' => today()->toDateString(),
                'signature' => $signature,
                'cert_agreed' => '1',
            ], fn ($value) => $value !== null),
        ];
    }

    public function test_drawn_signature_is_required_and_stored_with_the_application(): void
    {
        $applicant = $this->makeSteApplicant();

        $this->actingAs($applicant, 'applicant')
            ->postJson('/api/applicant/application', $this->payloadWithSignature(self::SIGNATURE))
            ->assertOk();

        $declaration = $applicant->fresh()->ste_declaration;

        $this->assertNotNull($applicant->fresh()->submitted_at);
        $this->assertArrayHasKey('signature', $declaration);
        $this->assertSame(self::SIGNATURE, $declaration['signature']);
        $this->assertSame(today()->toDateString(), $declaration['date_signed']);
        $this->assertSame('Maria Dela Cruz', $declaration['guardian_name']);
    }

    public function test_submission_is_rejected_when_the_signature_is_missing(): void
    {
        $applicant = $this->makeSteApplicant();

        $this->actingAs($applicant, 'applicant')
            ->postJson('/api/applicant/application', $this->payloadWithSignature(null))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ste_declaration.signature']);

        $this->assertNull($applicant->fresh()->submitted_at);
        $this->assertNull($applicant->fresh()->ste_declaration);
    }

    public function test_submission_is_rejected_when_the_signature_is_not_a_data_url(): void
    {
        $applicant = $this->makeSteApplicant();

        $this->actingAs($applicant, 'applicant')
            ->postJson('/api/applicant/application', $this->payloadWithSignature('not-a-signature'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ste_declaration.signature']);
    }
}
