<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminGoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    private function googleAuthQuery(TestResponse $response): array
    {
        $response->assertStatus(302);

        $location = $response->headers->get('Location');
        $this->assertSame('accounts.google.com', parse_url($location, PHP_URL_HOST));

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    public function test_sign_in_redirect_forces_google_account_chooser(): void
    {
        $query = $this->googleAuthQuery($this->get(route('admin.google.redirect')));

        $this->assertSame('select_account', $query['prompt'] ?? null);
        $this->assertSame(config('services.google.redirect'), $query['redirect_uri'] ?? null);
        $this->assertSame('code', $query['response_type'] ?? null);
        $this->assertArrayNotHasKey('state', $query);
        $this->assertFalse(session()->has('state'));
    }

    public function test_sign_in_redirect_still_forces_chooser_after_logout(): void
    {
        $user = User::create([
            'firstname' => 'Test',
            'lastname' => 'Staff',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($user)
            ->post('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $query = $this->googleAuthQuery($this->get(route('admin.google.redirect')));

        $this->assertSame('select_account', $query['prompt'] ?? null);
        $this->assertArrayNotHasKey('state', $query);
        $this->assertFalse(session()->has('state'));
    }
}
