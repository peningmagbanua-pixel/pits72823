<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Admin\User;
use App\Models\Applicant\Applicant;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionTimeoutSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $user = User::create([
            'firstname' => 'Test',
            'lastname' => $roleName,
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'must_change_password' => false,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function applicant(): Applicant
    {
        return Applicant::create([
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'application_status' => 'pending',
        ]);
    }

    // ── System Administrator ──────────────────────────────────────────────

    public function test_system_administrator_can_view_the_timeout_settings(): void
    {
        $response = $this->actingAs($this->userWithRole('System Administrator'))
            ->getJson('/api/session-timeout-settings');

        $response->assertStatus(200);
        $response->assertJsonPath('timeout_minutes', 15);
        $response->assertJsonPath('warning_minutes', 2);
    }

    public function test_system_administrator_can_save_the_timeout_settings(): void
    {
        $response = $this->actingAs($this->userWithRole('System Administrator'))
            ->postJson('/api/session-timeout-settings', [
                'timeout_minutes' => 5,
                'warning_minutes' => 1,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('timeout_minutes', 5);
        $response->assertJsonPath('warning_minutes', 1);

        $this->assertDatabaseHas('settings', ['key' => 'session_timeout_minutes', 'value' => '5']);
        $this->assertDatabaseHas('settings', ['key' => 'session_warning_minutes', 'value' => '1']);
    }

    public function test_saved_values_are_used_by_the_staff_automatic_logout_system(): void
    {
        $admin = $this->userWithRole('System Administrator');

        $this->actingAs($admin)->postJson('/api/session-timeout-settings', [
            'timeout_minutes' => 5,
            'warning_minutes' => 1,
        ])->assertStatus(200);

        $response = $this->actingAs($admin)->getJson('/api/session/status');

        $response->assertStatus(200);
        $response->assertJsonPath('timeout_minutes', 5);
        $response->assertJsonPath('warning_minutes', 1);
        $response->assertJsonPath('warning_seconds', 60);
        $this->assertGreaterThan(0, $response->json('remaining_seconds'));
        $this->assertLessThanOrEqual(360, $response->json('remaining_seconds'));
    }

    public function test_saved_values_are_used_by_the_applicant_automatic_logout_system(): void
    {
        $admin = $this->userWithRole('System Administrator');

        $this->actingAs($admin)->postJson('/api/session-timeout-settings', [
            'timeout_minutes' => 5,
            'warning_minutes' => 1,
        ])->assertStatus(200);

        $response = $this->actingAs($this->applicant(), 'applicant')
            ->getJson('/api/applicant/session/status');

        $response->assertStatus(200);
        $response->assertJsonPath('timeout_minutes', 5);
        $response->assertJsonPath('warning_minutes', 1);
        $response->assertJsonPath('warning_seconds', 60);
        $this->assertLessThanOrEqual(360, $response->json('remaining_seconds'));
    }

    public function test_expired_staff_session_uses_the_saved_timeout_value(): void
    {
        $admin = $this->userWithRole('System Administrator');

        $this->actingAs($admin)->postJson('/api/session-timeout-settings', [
            'timeout_minutes' => 5,
            'warning_minutes' => 1,
        ])->assertStatus(200);

        // 7 minutes idle exceeds the configured 5 + 1 minute limit.
        $this->actingAs($admin)
            ->withSession(['last_activity_at' => now()->subMinutes(7)->getTimestamp()])
            ->getJson('/api/profile')
            ->assertStatus(401)
            ->assertJson(['code' => 'session_expired']);
    }

    public function test_invalid_timeout_values_are_rejected(): void
    {
        $this->actingAs($this->userWithRole('System Administrator'))
            ->postJson('/api/session-timeout-settings', [
                'timeout_minutes' => 0,
                'warning_minutes' => 1,
            ])
            ->assertStatus(422);

        $this->actingAs($this->userWithRole('System Administrator'))
            ->postJson('/api/session-timeout-settings', [
                'timeout_minutes' => 5,
                'warning_minutes' => 'invalid',
            ])
            ->assertStatus(422);
    }

    // ── Staff (Encoder) ───────────────────────────────────────────────────

    public function test_staff_cannot_view_or_edit_the_timeout_settings(): void
    {
        $encoder = $this->userWithRole('Encoder');

        $this->actingAs($encoder)
            ->getJson('/api/session-timeout-settings')
            ->assertStatus(403);

        $this->actingAs($encoder)
            ->postJson('/api/session-timeout-settings', [
                'timeout_minutes' => 5,
                'warning_minutes' => 1,
            ])
            ->assertStatus(403);
    }

    // ── Applicant ─────────────────────────────────────────────────────────

    public function test_applicant_cannot_access_the_timeout_settings(): void
    {
        $this->actingAs($this->applicant(), 'applicant')
            ->getJson('/api/session-timeout-settings')
            ->assertStatus(401);

        $this->actingAs($this->applicant(), 'applicant')
            ->postJson('/api/session-timeout-settings', [
                'timeout_minutes' => 5,
                'warning_minutes' => 1,
            ])
            ->assertStatus(401);
    }

    public function test_guests_cannot_access_the_timeout_settings(): void
    {
        $this->getJson('/api/session-timeout-settings')->assertStatus(401);

        $this->postJson('/api/session-timeout-settings', [
            'timeout_minutes' => 5,
            'warning_minutes' => 1,
        ])->assertStatus(401);
    }
}
