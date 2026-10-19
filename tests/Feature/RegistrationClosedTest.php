<?php

namespace Tests\Feature;

use App\Models\Applicant\Applicant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_form_is_blocked_when_registration_is_closed(): void
    {
        $this->setRegistrationOpen(false);

        $this->get('/applicant/register')
            ->assertRedirect(route('applicant.login'));
    }

    public function test_registration_submission_is_blocked_when_registration_is_closed(): void
    {
        $this->setRegistrationOpen(false);

        $email = Str::uuid().'@example.com';

        $this->post('/applicant/register', [
            'fullname' => 'Juan Dela Cruz',
            'email' => $email,
        ])->assertRedirect(route('applicant.login'));

        $this->assertNull(Applicant::where('email', $email)->first());
        $this->assertGuest('applicant');
    }

    public function test_registration_form_is_available_when_registration_is_open(): void
    {
        $this->setRegistrationOpen(true);

        $this->get('/applicant/register')->assertStatus(200);
    }
}
