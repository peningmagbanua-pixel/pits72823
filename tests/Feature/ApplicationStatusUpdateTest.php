<?php

namespace Tests\Feature;

use App\Mail\ApplicationRejectedMail;
use App\Mail\ApplicationResetToPendingMail;
use App\Mail\ApplicationStatusNotificationEmail;
use App\Models\Admin\Program;
use App\Models\Admin\Role;
use App\Models\Admin\SchoolYear;
use App\Models\Admin\User;
use App\Models\Applicant\Applicant;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplicationStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Program $program;

    private SchoolYear $schoolYear;

    private User $admin;

    private Applicant $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(ProgramsSeeder::class);

        $this->program = Program::where('initials', 'SPICT')->firstOrFail();

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

        $this->applicant = Applicant::create([
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'program_id' => $this->program->id,
            'school_year_id' => $this->schoolYear->id,
            'application_status' => 'pending',
        ]);
    }

    private function updateStatus(array $payload)
    {
        return $this->actingAs($this->admin)
            ->putJson("/api/applicants/{$this->applicant->id}/status", $payload);
    }

    public function test_rejection_requires_a_manual_reason(): void
    {
        Mail::fake();

        $response = $this->updateStatus(['status' => 'rejected']);

        $response->assertStatus(422);
        $this->assertSame(
            'A rejection reason is required to reject an application.',
            $response->json('message')
        );
        $this->assertSame('pending', $this->applicant->fresh()->application_status);

        $response = $this->updateStatus(['status' => 'rejected', 'rejection_reason' => '   ']);

        $response->assertStatus(422);
        $this->assertSame('pending', $this->applicant->fresh()->application_status);

        Mail::assertNothingSent();
    }

    public function test_rejection_stores_the_reason_and_sends_it_escaped_in_the_email(): void
    {
        Mail::fake();

        $reason = "Missing birth certificate.\nSecond line <script>alert(1)</script>";

        $response = $this->updateStatus([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Application rejected.']);

        $fresh = $this->applicant->fresh();
        $this->assertSame('rejected', $fresh->application_status);
        $this->assertSame($reason, $fresh->rejection_reason);

        Mail::assertSent(ApplicationRejectedMail::class, function ($mail) use ($reason) {
            $this->assertTrue($mail->hasTo($this->applicant->email));

            $html = $mail->render();

            return str_contains($html, 'Reason for Rejection')
                && str_contains($html, e($reason))
                && ! str_contains($html, '<script>alert(1)</script>');
        });
    }

    public function test_approving_clears_the_rejection_reason(): void
    {
        Mail::fake();

        $this->updateStatus([
            'status' => 'rejected',
            'rejection_reason' => 'Incomplete requirements.',
        ])->assertStatus(200);

        Mail::fake();

        $response = $this->updateStatus(['status' => 'approved']);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Application approved.']);

        $fresh = $this->applicant->fresh();
        $this->assertSame('approved', $fresh->application_status);
        $this->assertNull($fresh->rejection_reason);

        Mail::assertSent(ApplicationStatusNotificationEmail::class);
    }

    public function test_reset_to_pending_clears_the_reason_and_reports_a_simple_message(): void
    {
        Mail::fake();

        $this->updateStatus([
            'status' => 'rejected',
            'rejection_reason' => 'Wrong document uploaded.',
        ])->assertStatus(200);

        Mail::fake();

        $response = $this->updateStatus(['status' => 'pending']);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Application status reset to pending.']);

        $fresh = $this->applicant->fresh();
        $this->assertSame('pending', $fresh->application_status);
        $this->assertNull($fresh->rejection_reason);

        Mail::assertSent(ApplicationResetToPendingMail::class);
    }
}
