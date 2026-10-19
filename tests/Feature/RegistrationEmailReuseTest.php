<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Admin\User;
use App\Models\Applicant\Applicant;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RegistrationEmailReuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->setRegistrationOpen(true);
    }

    private function makeApplicant(?string $email = null): Applicant
    {
        return Applicant::create([
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'email' => $email ?? Str::uuid().'@example.com',
            'password' => 'password',
            'application_status' => 'pending',
        ]);
    }

    private function makeStaff(): User
    {
        return User::create([
            'firstname' => 'Test',
            'lastname' => 'Staff',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
        ]);
    }

    private function register(string $email): TestResponse
    {
        return $this->post('/applicant/register', [
            'fullname' => 'Maria Santos',
            'email' => $email,
        ]);
    }

    public function test_active_applicant_email_cannot_be_registered_again(): void
    {
        $applicant = $this->makeApplicant();

        $this->register($applicant->email)
            ->assertStatus(302)
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Applicant::withTrashed()->where('email', $applicant->email)->count());
    }

    public function test_deleted_applicant_can_register_again_with_the_same_email(): void
    {
        $old = $this->makeApplicant();
        $old->delete();

        $this->assertTrue($old->trashed());

        $this->register($old->email)
            ->assertRedirect(route('applicant.dashboard'));

        $new = Applicant::where('email', $old->email)->first();
        $this->assertNotNull($new);
        $this->assertNotSame($old->id, $new->id);
        $this->assertTrue((bool) $new->must_change_password);

        // The old record is preserved as a soft-deleted row
        $this->assertSame(2, Applicant::withTrashed()->where('email', $old->email)->count());
        $this->assertSame(1, Applicant::onlyTrashed()->where('email', $old->email)->count());

        $this->assertAuthenticatedAs($new, 'applicant');
    }

    public function test_new_email_registers_normally(): void
    {
        $email = Str::uuid().'@example.com';

        $this->register($email)
            ->assertRedirect(route('applicant.dashboard'));

        $this->assertNotNull(Applicant::where('email', $email)->first());
    }

    public function test_active_staff_email_is_blocked(): void
    {
        $staff = $this->makeStaff();

        $this->register($staff->email)
            ->assertStatus(302)
            ->assertSessionHasErrors('email');

        $this->assertNull(Applicant::where('email', $staff->email)->first());
    }

    public function test_deleted_staff_email_can_be_registered(): void
    {
        $staff = $this->makeStaff();
        $staff->delete();

        $this->register($staff->email)
            ->assertRedirect(route('applicant.dashboard'));

        $this->assertNotNull(Applicant::where('email', $staff->email)->first());
    }

    public function test_restoring_deleted_account_is_blocked_when_active_account_uses_the_email(): void
    {
        $old = $this->makeApplicant();
        $old->delete();
        $this->makeApplicant($old->email);

        $sysadmin = $this->makeStaff();
        $sysadmin->roles()->attach(Role::where('name', 'System Administrator')->firstOrFail()->id);

        $this->actingAs($sysadmin)
            ->post('/api/applicant-accounts/'.$old->id.'/restore')
            ->assertStatus(400);

        $this->assertTrue(Applicant::onlyTrashed()->where('id', $old->id)->exists());
    }
}
