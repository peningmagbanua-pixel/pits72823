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

class ExamScheduleProgramMatchTest extends TestCase
{
    use RefreshDatabase;

    private Program $spict;

    private Program $ste;

    private SchoolYear $schoolYear;

    private User $admin;

    private ExamSchedule $spictSchedule;

    private ExamSchedule $steSchedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(ProgramsSeeder::class);

        $this->spict = Program::where('initials', 'SPICT')->firstOrFail();
        $this->ste = Program::where('initials', 'STE')->firstOrFail();

        $this->schoolYear = SchoolYear::create([
            'start' => '2026-06-01',
            'end' => '2027-03-31',
            'name' => 'SY 2026 - 2027',
            'is_active' => true,
        ]);

        $role = Role::where('name', 'System Administrator')->firstOrFail();
        $this->admin = User::create([
            'firstname' => 'Test',
            'lastname' => 'Admin',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
        ]);
        $this->admin->roles()->attach($role->id);

        $this->spictSchedule = $this->makeSchedule($this->spict, 'Room 32');
        $this->steSchedule = $this->makeSchedule($this->ste, 'Room 12');
    }

    private function makeSchedule(Program $program, string $room): ExamSchedule
    {
        return ExamSchedule::create([
            'school_year_id' => $this->schoolYear->id,
            'program_id' => $program->id,
            'date' => '2026-10-10',
            'time_slot' => '8:00 am - 10:00 am',
            'room' => $room,
            'capacity' => 50,
        ]);
    }

    private function makeApplicant(Program $program, array $overrides = []): Applicant
    {
        return Applicant::create(array_merge([
            'firstname' => 'Applicant',
            'lastname' => 'Test',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'program_id' => $program->id,
            'school_year_id' => $this->schoolYear->id,
            'application_status' => 'approved',
        ], $overrides));
    }

    public function test_manual_assign_rejects_applicant_from_a_different_program(): void
    {
        $applicant = $this->makeApplicant($this->spict);

        $response = $this->actingAs($this->admin)->postJson('/api/exam-schedules/manual-assign', [
            'applicant_ids' => [$applicant->id],
            'schedule_id' => $this->steSchedule->id,
        ]);

        $response->assertStatus(422);

        $fresh = $applicant->fresh();
        $this->assertNull($fresh->schedule_id);
        $this->assertNull($fresh->exam_status);
    }

    public function test_manual_assign_assigns_applicant_of_the_matching_program(): void
    {
        $applicant = $this->makeApplicant($this->ste);

        $response = $this->actingAs($this->admin)->postJson('/api/exam-schedules/manual-assign', [
            'applicant_ids' => [$applicant->id],
            'schedule_id' => $this->steSchedule->id,
        ]);

        $response->assertStatus(200);

        $fresh = $applicant->fresh();
        $this->assertSame($this->steSchedule->id, $fresh->schedule_id);
        $this->assertSame('scheduled', $fresh->exam_status);
    }

    public function test_unscheduled_applicants_are_filtered_by_schedule_program(): void
    {
        $steApplicant = $this->makeApplicant($this->ste);
        $this->makeApplicant($this->spict);
        $this->makeApplicant($this->ste, ['application_status' => 'pending']);

        $this->actingAs($this->admin)
            ->getJson('/api/unscheduled-applicants?schedule_id='.$this->steSchedule->id)
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['email' => $steApplicant->email])
            ->assertJsonFragment(['program' => 'STE']);

        $this->actingAs($this->admin)
            ->getJson('/api/unscheduled-applicants')
            ->assertStatus(200)
            ->assertJsonCount(2);
    }

    public function test_update_status_rejects_schedule_from_a_different_program(): void
    {
        $applicant = $this->makeApplicant($this->spict, ['application_status' => 'pending']);

        $response = $this->actingAs($this->admin)->putJson("/api/applicants/{$applicant->id}/status", [
            'status' => 'approved',
            'schedule_id' => $this->steSchedule->id,
        ]);

        $response->assertStatus(422);

        $fresh = $applicant->fresh();
        $this->assertNull($fresh->schedule_id);
        $this->assertSame('pending', $fresh->application_status);
    }

    public function test_schedule_applicants_flags_program_mismatches(): void
    {
        $mismatched = $this->makeApplicant($this->spict, [
            'schedule_id' => $this->steSchedule->id,
            'exam_status' => 'scheduled',
        ]);
        $matched = $this->makeApplicant($this->ste, [
            'schedule_id' => $this->steSchedule->id,
            'exam_status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/exam-schedules/{$this->steSchedule->id}/applicants");

        $response->assertStatus(200)
            ->assertJsonPath('mismatched_count', 1);

        $rows = collect($response->json('applicants'))->keyBy('email');

        $this->assertTrue($rows[$mismatched->email]['program_mismatch']);
        $this->assertFalse($rows[$matched->email]['program_mismatch']);
    }
}
