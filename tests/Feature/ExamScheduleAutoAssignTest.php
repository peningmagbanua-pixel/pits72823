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

class ExamScheduleAutoAssignTest extends TestCase
{
    use RefreshDatabase;

    private Program $spict;

    private SchoolYear $schoolYear;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(ProgramsSeeder::class);

        $this->spict = Program::where('initials', 'SPICT')->firstOrFail();

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
    }

    private function makeSchedule(array $overrides = []): ExamSchedule
    {
        return ExamSchedule::create(array_merge([
            'school_year_id' => $this->schoolYear->id,
            'program_id' => $this->spict->id,
            'date' => now()->addDays(7)->toDateString(),
            'time_slot' => '8:00 am - 10:00 am',
            'room' => 'Room 32',
            'capacity' => 10,
        ], $overrides));
    }

    private function makeApplicant(array $overrides = []): Applicant
    {
        return Applicant::create(array_merge([
            'firstname' => 'Approved',
            'lastname' => 'Applicant',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'program_id' => $this->spict->id,
            'school_year_id' => $this->schoolYear->id,
            'application_status' => 'approved',
        ], $overrides));
    }

    private function autoAssign(?string $programId = null)
    {
        return $this->actingAs($this->admin)
            ->postJson('/api/exam-schedules/auto-assign', [
                'program_id' => $programId ?? $this->spict->id,
                'school_year_id' => $this->schoolYear->id,
            ]);
    }

    public function test_auto_assign_assigns_approved_unscheduled_applicants(): void
    {
        $applicant = $this->makeApplicant();
        $schedule = $this->makeSchedule();

        $this->autoAssign()
            ->assertStatus(200)
            ->assertJson(['message' => 'Successfully assigned 1 applicants.']);

        $fresh = $applicant->fresh();
        $this->assertSame($schedule->id, $fresh->schedule_id);
        $this->assertSame('scheduled', $fresh->exam_status);
    }

    public function test_applicants_of_other_programs_and_statuses_are_skipped(): void
    {
        $otherProgram = Program::where('initials', 'STE')->firstOrFail();
        $this->makeSchedule();
        $this->makeApplicant(['program_id' => $otherProgram->id]);
        $this->makeApplicant(['application_status' => 'pending']);

        $this->autoAssign()
            ->assertStatus(422)
            ->assertJson(['message' => 'No applicants to assign.']);
    }

    public function test_applicants_stale_schedule_link_blocks_auto_assign(): void
    {
        $schedule = $this->makeSchedule();
        $applicant = $this->makeApplicant([
            'schedule_id' => $schedule->id,
            'exam_status' => 'scheduled',
        ]);

        $this->autoAssign()
            ->assertStatus(422)
            ->assertJson(['message' => 'No applicants to assign.']);

        $this->assertSame($schedule->id, $applicant->fresh()->schedule_id);
    }

    public function test_deleting_a_schedule_releases_its_applicants_for_reassignment(): void
    {
        $schedule = $this->makeSchedule();
        $replacement = $this->makeSchedule(['room' => 'Room 99']);
        $applicant = $this->makeApplicant([
            'schedule_id' => $schedule->id,
            'exam_status' => 'scheduled',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson('/api/exam-schedules/'.$schedule->id)
            ->assertStatus(200);

        $fresh = $applicant->fresh();
        $this->assertNull($fresh->schedule_id);
        $this->assertNull($fresh->exam_status);

        $this->autoAssign()
            ->assertStatus(200)
            ->assertJson(['message' => 'Successfully assigned 1 applicants.']);

        $fresh = $applicant->fresh();
        $this->assertSame($replacement->id, $fresh->schedule_id);
        $this->assertSame('scheduled', $fresh->exam_status);
    }

    public function test_data_migration_releases_applicants_linked_to_deleted_schedules(): void
    {
        $schedule = $this->makeSchedule();
        $orphan = $this->makeApplicant([
            'schedule_id' => $schedule->id,
            'exam_status' => 'scheduled',
        ]);

        $schedule->delete();

        $migration = include database_path(
            'migrations/2026_10_15_000000_release_applicants_from_deleted_exam_schedules.php'
        );
        $migration->up();

        $fresh = $orphan->fresh();
        $this->assertNull($fresh->schedule_id);
        $this->assertNull($fresh->exam_status);
    }
}
