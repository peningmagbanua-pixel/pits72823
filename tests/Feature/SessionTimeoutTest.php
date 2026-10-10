<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Admin\User;
use App\Models\Applicant\Applicant;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    /** Inactivity limit in seconds: SESSION_TIMEOUT_MINUTES (15) + SESSION_WARNING_MINUTES (2). */
    private const LIMIT = 1020;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
    }

    private function admin(bool $mustChange = false): User
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

    private function staleSession(): array
    {
        return ['last_activity_at' => now()->subMinutes(18)->getTimestamp()];
    }

    // ── Staff ──────────────────────────────────────────────────────────────

    public function test_staff_status_returns_the_remaining_session_time(): void
    {
        $response = $this->actingAs($this->admin())->getJson('/api/session/status');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'active');
        $response->assertJsonPath('warning_seconds', 120);
        $this->assertGreaterThan(0, $response->json('remaining_seconds'));
        $this->assertLessThanOrEqual(self::LIMIT, $response->json('remaining_seconds'));
    }

    public function test_staff_activity_refreshes_the_inactivity_timestamp(): void
    {
        $admin = $this->admin();

        // One minute of inactivity recorded, still within the limit.
        $response = $this->actingAs($admin)
            ->withSession(['last_activity_at' => now()->subMinute()->getTimestamp()])
            ->postJson('/api/session/activity');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'active');
        $this->assertGreaterThanOrEqual(self::LIMIT - 5, $response->json('remaining_seconds'));
    }

    public function test_staff_activity_is_throttled_on_the_server(): void
    {
        $admin = $this->admin();

        // Activity happened 5 seconds ago — the ping must not rewrite it.
        $response = $this->actingAs($admin)
            ->withSession(['last_activity_at' => now()->subSeconds(5)->getTimestamp()])
            ->postJson('/api/session/activity');

        $response->assertStatus(200);
        $this->assertGreaterThan(1010, $response->json('remaining_seconds'));
        $this->assertLessThan(1019, $response->json('remaining_seconds'));
    }

    public function test_staff_api_is_rejected_when_the_session_went_inactive(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession($this->staleSession())
            ->getJson('/api/profile')
            ->assertStatus(401)
            ->assertJson(['code' => 'session_expired']);
    }

    public function test_staff_page_redirects_to_login_when_the_session_went_inactive(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession($this->staleSession())
            ->get('/admin/dashboard')
            ->assertStatus(302)
            ->assertRedirect(route('admin.login'));
    }

    public function test_expired_staff_session_is_fully_invalidated(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession($this->staleSession())
            ->getJson('/api/profile')
            ->assertStatus(401);

        $this->assertGuest('web');
        $this->assertNull(session()->get('last_activity_at'));
    }

    public function test_staff_logout_still_works_when_the_session_went_inactive(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession($this->staleSession())
            ->post('/admin/logout')
            ->assertStatus(302)
            ->assertRedirect(route('admin.login'));
    }

    public function test_staff_timestamp_is_seeded_on_the_first_protected_request(): void
    {
        $this->actingAs($this->admin())->getJson('/api/profile')->assertStatus(200);

        $this->assertNotNull(session()->get('last_activity_at'));
    }

    public function test_staff_regular_api_requests_do_not_refresh_inactivity(): void
    {
        $admin = $this->admin();
        $fiveMinutesAgo = now()->subMinutes(5)->getTimestamp();

        $this->actingAs($admin)
            ->withSession(['last_activity_at' => $fiveMinutesAgo])
            ->getJson('/api/profile')
            ->assertStatus(200);

        // A plain data fetch must not restart the inactivity clock.
        $this->assertSame($fiveMinutesAgo, session()->get('last_activity_at'));
    }

    // ── Applicant ──────────────────────────────────────────────────────────

    public function test_applicant_status_returns_the_remaining_session_time(): void
    {
        $response = $this->actingAs($this->applicant(), 'applicant')
            ->getJson('/api/applicant/session/status');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'active');
        $this->assertGreaterThan(0, $response->json('remaining_seconds'));
        $this->assertLessThanOrEqual(self::LIMIT, $response->json('remaining_seconds'));
    }

    public function test_applicant_activity_refreshes_the_inactivity_timestamp(): void
    {
        $applicant = $this->applicant();

        $response = $this->actingAs($applicant, 'applicant')
            ->withSession(['last_activity_at' => now()->subMinute()->getTimestamp()])
            ->postJson('/api/applicant/session/activity');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(self::LIMIT - 5, $response->json('remaining_seconds'));
    }

    public function test_applicant_api_is_rejected_when_the_session_went_inactive(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant, 'applicant')
            ->withSession($this->staleSession())
            ->getJson('/api/applicant/dashboard')
            ->assertStatus(401)
            ->assertJson(['code' => 'session_expired']);
    }

    public function test_applicant_page_redirects_to_login_when_the_session_went_inactive(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant, 'applicant')
            ->withSession($this->staleSession())
            ->get('/applicant/dashboard')
            ->assertStatus(302)
            ->assertRedirect(route('applicant.login'));
    }

    public function test_expired_applicant_session_is_fully_invalidated(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant, 'applicant')
            ->withSession($this->staleSession())
            ->getJson('/api/applicant/profile')
            ->assertStatus(401);

        $this->assertGuest('applicant');
        $this->assertNull(session()->get('last_activity_at'));
    }

    public function test_applicant_logout_still_works_when_the_session_went_inactive(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant, 'applicant')
            ->withSession($this->staleSession())
            ->post('/applicant/logout')
            ->assertStatus(302);
    }

    // ── Interplay with the forced change-password lock ─────────────────────

    public function test_session_endpoints_work_while_a_password_change_is_pending(): void
    {
        $admin = $this->admin(mustChange: true);

        $this->actingAs($admin)->getJson('/api/session/status')->assertStatus(200);
        $this->actingAs($admin)->postJson('/api/session/activity')->assertStatus(200);
    }

    public function test_session_expiration_takes_precedence_over_a_pending_password_change(): void
    {
        $admin = $this->admin(mustChange: true);

        $this->actingAs($admin)
            ->withSession($this->staleSession())
            ->getJson('/api/profile')
            ->assertStatus(401)
            ->assertJson(['code' => 'session_expired']);
    }
}
