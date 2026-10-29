<?php

namespace Tests\Feature;

use App\Models\Admin\PermissionTitle;
use App\Models\Admin\Program;
use App\Models\Admin\Requirement;
use App\Models\Admin\Role;
use App\Models\Admin\User;
use Database\Seeders\PermissionTitlesSeeder;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RequirementsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * start-container.sh runs `php artisan db:seed --force` on every deploy.
 * Soft-deleted rows still occupy their unique indexes (and shadow the
 * firstOrCreate lookups), so each seeder must consider withTrashed() rows
 * or a later deploy crashes with SQLSTATE 23000 duplicate-key errors —
 * or silently inserts duplicate rows on tables without unique indexes.
 */
class SeederSoftDeleteIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_seeder_skips_soft_deleted_roles(): void
    {
        $this->seed(RolesTableSeeder::class);

        Role::where('name', 'Encoder')->firstOrFail()->delete();

        $this->seed(RolesTableSeeder::class);

        $this->assertSame(1, Role::withTrashed()->where('name', 'Encoder')->count());
        $this->assertFalse(Role::where('name', 'Encoder')->exists());
    }

    public function test_users_seeder_skips_soft_deleted_users(): void
    {
        $this->seed(RolesTableSeeder::class);
        $this->seed(UsersTableSeeder::class);

        $email = 'peningmagbanua@gmail.com';
        User::where('email', $email)->firstOrFail()->delete();

        $this->seed(UsersTableSeeder::class);

        $this->assertSame(1, User::withTrashed()->where('email', $email)->count());
        $this->assertFalse(User::where('email', $email)->exists());
    }

    public function test_permission_titles_seeder_skips_soft_deleted_titles(): void
    {
        $this->seed(PermissionTitlesSeeder::class);

        PermissionTitle::where('name', 'Dashboard')->firstOrFail()->delete();

        $this->seed(PermissionTitlesSeeder::class);

        $this->assertSame(1, PermissionTitle::withTrashed()->where('name', 'Dashboard')->count());
        $this->assertFalse(PermissionTitle::where('name', 'Dashboard')->exists());
    }

    public function test_programs_seeder_skips_soft_deleted_programs(): void
    {
        $this->seed(ProgramsSeeder::class);

        Program::where('initials', 'STE')->firstOrFail()->delete();

        $this->seed(ProgramsSeeder::class);

        $this->assertSame(1, Program::withTrashed()->where('initials', 'STE')->count());
        $this->assertFalse(Program::where('initials', 'STE')->exists());
    }

    public function test_requirements_seeder_skips_soft_deleted_requirements(): void
    {
        $this->seed(RequirementsTableSeeder::class);

        Requirement::where('name', 'SF9 / Report Card')->firstOrFail()->delete();

        $this->seed(RequirementsTableSeeder::class);

        $this->assertSame(1, Requirement::withTrashed()->where('name', 'SF9 / Report Card')->count());
        $this->assertFalse(Requirement::where('name', 'SF9 / Report Card')->exists());
    }
}
