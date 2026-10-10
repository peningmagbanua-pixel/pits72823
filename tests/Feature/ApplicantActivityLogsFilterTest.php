<?php

namespace Tests\Feature;

use App\Models\Admin\Program;
use App\Models\Applicant\ActivityLog;
use App\Models\Applicant\Applicant;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplicantActivityLogsFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(ProgramsSeeder::class);
    }

    private function makeApplicant(): Applicant
    {
        $program = Program::where('initials', 'STE')->firstOrFail();

        return Applicant::create([
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'program_id' => $program->id,
        ]);
    }

    public function test_only_the_logged_in_applicants_own_logs_are_returned(): void
    {
        $me    = $this->makeApplicant();
        $other = $this->makeApplicant();

        $myLogin = ActivityLog::logActivity($me->id, 'logged_in', 'Logged in.');
        $mySubmit = ActivityLog::logActivity($me->id, 'application_submitted', 'Application submitted.');
        $theirLogin = ActivityLog::logActivity($other->id, 'logged_in', 'Other applicant logged in.');

        $response = $this->actingAs($me, 'applicant')
            ->getJson('/api/applicant/activity-logs')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total', 'from', 'to']);

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($myLogin->id));
        $this->assertTrue($ids->contains($mySubmit->id));
        $this->assertFalse($ids->contains($theirLogin->id), 'Another applicant\'s log leaked into the response.');
        $this->assertSame(2, $response->json('total'));
    }

    public function test_action_filter_returns_only_matching_own_logs(): void
    {
        $me = $this->makeApplicant();
        $other = $this->makeApplicant();

        $myLogin = ActivityLog::logActivity($me->id, 'logged_in', 'Logged in.');
        ActivityLog::logActivity($me->id, 'application_submitted', 'Application submitted.');
        ActivityLog::logActivity($other->id, 'logged_in', 'Other applicant logged in.');

        $response = $this->actingAs($me, 'applicant')
            ->getJson('/api/applicant/activity-logs?action=logged_in')
            ->assertOk();

        $rows = $response->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame($myLogin->id, $rows[0]['id']);
        $this->assertSame('logged_in', $rows[0]['action']);
        $this->assertSame(1, $response->json('total'));
    }

    public function test_action_filter_with_no_matches_returns_an_empty_page(): void
    {
        $me = $this->makeApplicant();
        ActivityLog::logActivity($me->id, 'logged_in', 'Logged in.');

        $response = $this->actingAs($me, 'applicant')
            ->getJson('/api/applicant/activity-logs?action=profile_updated')
            ->assertOk();

        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('total'));
    }
}
