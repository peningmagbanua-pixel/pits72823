<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Admin\User;
use App\Models\Applicant\Applicant;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MustChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
    }

    private function admin(bool $mustChange = true): User
    {
        $role = Role::where('name', 'System Administrator')->firstOrFail();
        $admin = User::create([
            'firstname' => 'Test',
            'lastname' => 'Admin',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'must_change_password' => $mustChange,
        ]);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function applicant(bool $mustChange = true): Applicant
    {
        return Applicant::create([
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'must_change_password' => $mustChange,
            'application_status' => 'pending',
        ]);
    }

    // ── Staff ─────────────────────────────────────────────────────────────

    public function test_staff_profile_boot_is_allowed_while_locked_and_exposes_the_flag(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->getJson('/api/profile')
            ->assertStatus(200)
            ->assertJsonPath('must_change_password', true);
    }

    public function test_staff_api_calls_are_blocked_while_locked(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->getJson('/api/profile/details')
            ->assertStatus(403)
            ->assertJson(['code' => 'must_change_password']);

        $this->actingAs($admin)->getJson('/api/dashboard/stats')
            ->assertStatus(403)
            ->assertJson(['code' => 'must_change_password']);
    }

    public function test_staff_page_and_logout_stay_available_while_locked(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/dashboard')->assertStatus(200);

        $this->actingAs($admin)->post('/admin/logout')
            ->assertStatus(302)
            ->assertRedirect(route('admin.login'));
    }

    public function test_staff_change_password_rejects_the_wrong_current_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson('/api/profile/change-password', [
            'current_password' => 'wrong-password',
            'new_password' => 'new-secret-123',
            'new_password_confirmation' => 'new-secret-123',
        ])->assertStatus(422);

        $this->assertTrue($admin->fresh()->must_change_password);

        $this->actingAs($admin)->getJson('/api/profile/details')->assertStatus(403);
    }

    public function test_staff_change_password_clears_the_flag_and_unblocks_api(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson('/api/profile/change-password', [
            'current_password' => 'password',
            'new_password' => 'new-secret-123',
            'new_password_confirmation' => 'new-secret-123',
        ])->assertStatus(200);

        $this->assertFalse($admin->fresh()->must_change_password);

        $this->actingAs($admin)->getJson('/api/profile')
            ->assertStatus(200)
            ->assertJsonPath('must_change_password', false);

        $this->actingAs($admin)->getJson('/api/profile/details')->assertStatus(200);
    }

    public function test_staff_without_the_flag_is_not_blocked(): void
    {
        $admin = $this->admin(false);

        $this->actingAs($admin)->getJson('/api/profile/details')
            ->assertStatus(200);

        $this->assertFalse($admin->fresh()->must_change_password);
    }

    // ── Applicant ─────────────────────────────────────────────────────────

    public function test_applicant_profile_boot_is_allowed_while_locked_and_exposes_the_flag(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant, 'applicant')->getJson('/api/applicant/profile')
            ->assertStatus(200)
            ->assertJsonPath('must_change_password', true);
    }

    public function test_applicant_api_calls_are_blocked_while_locked(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant, 'applicant')->getJson('/api/applicant/dashboard')
            ->assertStatus(403)
            ->assertJson(['code' => 'must_change_password']);
    }

    public function test_applicant_page_and_logout_stay_available_while_locked(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant, 'applicant')->get('/applicant/dashboard')->assertStatus(200);

        $this->actingAs($applicant, 'applicant')->post('/applicant/logout')
            ->assertStatus(302);
    }

    public function test_applicant_change_password_rejects_the_wrong_current_password(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant, 'applicant')->putJson('/api/applicant/profile/change-password', [
            'current_password' => 'wrong-password',
            'new_password' => 'new-secret-123',
            'new_password_confirmation' => 'new-secret-123',
        ])->assertStatus(422);

        $this->assertTrue($applicant->fresh()->must_change_password);
    }

    public function test_applicant_change_password_clears_the_flag_and_unblocks_api(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant, 'applicant')->putJson('/api/applicant/profile/change-password', [
            'current_password' => 'password',
            'new_password' => 'new-secret-123',
            'new_password_confirmation' => 'new-secret-123',
        ])->assertStatus(200);

        $this->assertFalse($applicant->fresh()->must_change_password);

        $this->actingAs($applicant, 'applicant')->getJson('/api/applicant/dashboard')
            ->assertStatus(200);
    }

    public function test_registration_flags_the_new_applicant_for_a_password_change(): void
    {
        $email = Str::uuid().'@example.com';

        $this->post('/applicant/register', [
            'fullname' => 'Juan Dela Cruz',
            'email' => $email,
        ])->assertStatus(302);

        $this->assertTrue(
            Applicant::where('email', $email)->firstOrFail()->must_change_password
        );
    }
}
