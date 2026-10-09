<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Admin\User;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsersEmailReuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
    }

    private function makeUser(?string $email = null): User
    {
        return User::create([
            'firstname' => 'Test',
            'lastname' => 'Staff',
            'email' => $email ?? Str::uuid().'@example.com',
            'password' => 'password',
        ]);
    }

    private function makeSysAdmin(): User
    {
        $admin = $this->makeUser();
        $admin->roles()->attach(Role::where('name', 'System Administrator')->firstOrFail()->id);

        return $admin;
    }

    private function payload(string $email): array
    {
        return [
            'lastname' => 'Dela Cruz',
            'firstname' => 'Maria',
            'middlename' => null,
            'extname' => null,
            'email' => $email,
            'admin' => 'Yes',
        ];
    }

    public function test_deleted_user_email_can_be_reused_for_a_new_account(): void
    {
        $old = $this->makeUser();
        $old->delete();

        $this->assertTrue($old->trashed());

        $admin = $this->makeSysAdmin();

        $this->actingAs($admin)
            ->postJson('/api/users', $this->payload($old->email))
            ->assertStatus(200)
            ->assertJson(['message' => 'User created successfully!']);

        $new = User::where('email', $old->email)->first();
        $this->assertNotNull($new);
        $this->assertNotSame($old->id, $new->id);

        // The old record is preserved as a soft-deleted row
        $this->assertSame(2, User::withTrashed()->where('email', $old->email)->count());
        $this->assertSame(1, User::onlyTrashed()->where('email', $old->email)->count());
    }

    public function test_active_user_email_cannot_be_reused(): void
    {
        $active = $this->makeUser();
        $admin = $this->makeSysAdmin();

        $this->actingAs($admin)
            ->postJson('/api/users', $this->payload($active->email))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, User::withTrashed()->where('email', $active->email)->count());
    }

    public function test_restoring_deleted_account_is_blocked_when_active_account_uses_the_email(): void
    {
        $old = $this->makeUser();
        $old->delete();

        $admin = $this->makeSysAdmin();

        $this->actingAs($admin)
            ->postJson('/api/users', $this->payload($old->email))
            ->assertStatus(200);

        $this->actingAs($admin)
            ->postJson('/api/users/'.$old->id.'/restore')
            ->assertStatus(400);

        $this->assertTrue(User::onlyTrashed()->where('id', $old->id)->exists());
    }

    public function test_new_account_can_be_updated_after_email_reuse(): void
    {
        $old = $this->makeUser();
        $old->delete();

        $admin = $this->makeSysAdmin();

        $this->actingAs($admin)
            ->postJson('/api/users', $this->payload($old->email))
            ->assertStatus(200);

        $new = User::where('email', $old->email)->firstOrFail();

        $this->actingAs($admin)
            ->putJson('/api/users/'.$new->id, $this->payload($old->email) + ['status' => true])
            ->assertStatus(200)
            ->assertJson(['message' => 'User updated successfully!']);
    }

    public function test_profile_can_be_updated_after_email_reuse(): void
    {
        $old = $this->makeUser();
        $old->delete();

        $admin = $this->makeSysAdmin();

        $this->actingAs($admin)
            ->postJson('/api/users', $this->payload($old->email))
            ->assertStatus(200);

        $new = User::where('email', $old->email)->firstOrFail();
        $new->update(['must_change_password' => false]);

        $this->actingAs($new)
            ->putJson('/api/profile/update', [
                'lastname' => 'Dela Cruz',
                'firstname' => 'Maria',
                'middlename' => null,
                'extname' => null,
                'email' => $old->email,
            ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Profile updated successfully!']);
    }
}
