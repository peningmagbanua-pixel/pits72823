<?php

namespace Tests\Feature;

use App\Mail\ApplicantAccountCreatedMail;
use App\Models\Applicant\Applicant;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class TempPasswordValidityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->setRegistrationOpen(true);
    }

    public function test_emailed_temp_password_stays_valid_until_it_is_changed(): void
    {
        Mail::fake();

        $email = Str::uuid().'@example.com';

        $this->post('/applicant/register', [
            'fullname' => 'Juan Dela Cruz',
            'email' => $email,
        ])->assertRedirect(route('applicant.dashboard'));

        $applicant = Applicant::where('email', $email)->firstOrFail();
        $this->assertTrue((bool) $applicant->must_change_password);

        Mail::assertSent(ApplicantAccountCreatedMail::class, function (ApplicantAccountCreatedMail $mail) use ($email) {
            return $mail->hasTo($email) && $mail->plainPassword !== '';
        });

        $emailedPassword = Mail::sent(ApplicantAccountCreatedMail::class)->first()->plainPassword;

        // Registration auto-login ends; the user returns much later without
        // having changed the temporary password.
        $this->post('/applicant/logout');
        $this->travel(3)->hours();

        $this->post('/applicant/login', [
            'email' => $email,
            'password' => $emailedPassword,
        ])->assertRedirect(route('applicant.dashboard'));

        $this->assertAuthenticatedAs($applicant, 'applicant');

        // The temporary password is still the valid credential: changing it
        // must accept it and persist the replacement.
        $this->putJson('/api/applicant/profile/change-password', [
            'current_password' => $emailedPassword,
            'new_password' => 'brand-new-secret-1',
            'new_password_confirmation' => 'brand-new-secret-1',
        ])->assertStatus(200);

        $applicant->refresh();
        $this->assertFalse((bool) $applicant->must_change_password);

        $this->post('/applicant/logout');

        // Old temporary password no longer works; the new one does.
        $this->post('/applicant/login', [
            'email' => $email,
            'password' => $emailedPassword,
        ])->assertSessionHas('error');

        $this->assertGuest('applicant');

        $this->post('/applicant/login', [
            'email' => $email,
            'password' => 'brand-new-secret-1',
        ])->assertRedirect(route('applicant.dashboard'));

        $this->travelBack();
    }

    public function test_temp_password_rejected_only_when_it_is_actually_wrong(): void
    {
        $applicant = Applicant::create([
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'email' => Str::uuid().'@example.com',
            'password' => 'temporary-pass-1',
            'must_change_password' => true,
            'is_active' => true,
            'application_status' => 'pending',
        ]);

        $this->travel(2)->days();

        $this->post('/applicant/login', [
            'email' => $applicant->email,
            'password' => 'temporary-pass-1',
        ])->assertRedirect(route('applicant.dashboard'));

        $this->assertAuthenticatedAs($applicant, 'applicant');

        $this->post('/applicant/logout');

        $this->post('/applicant/login', [
            'email' => $applicant->email,
            'password' => 'wrong-password',
        ])->assertSessionHas('error');

        $this->assertGuest('applicant');
    }
}
