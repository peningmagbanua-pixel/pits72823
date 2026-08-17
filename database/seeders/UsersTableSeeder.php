<?php

namespace Database\Seeders;

use App\Models\Admin\User;
use App\Traits\ConstantsTrait;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin\Role;
use App\Models\Admin\RoleUser;

class UsersTableSeeder extends Seeder
{
    use ConstantsTrait;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = File::get("database/data/admin/users.json");
        $users = json_decode($file);

        $role = Role::where('name', $this->SYSTEM_ADMINISTRATOR)->first();

        foreach ($users as $user) {
            // Fixed development password (dev convenience; change after first login)
            $plainPassword = 'password';

            $new = User::firstOrCreate(
                ['email' => $user->email],
                [
                    'firstname' => $user->firstname,
                    'middlename' => $user->middlename,
                    'lastname' => $user->lastname,
                    'extname' => $user->extname,
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'password' => Hash::make($plainPassword),
                ]
            );

            if ($new && $role) {
                RoleUser::firstOrCreate(
                    [
                        'role_id' => $role->id,
                        'user_id' => $new->id,
                    ]
                );
            }

            if ($new->wasRecentlyCreated) {
                $this->command->warn("Created admin account: {$user->email} — password: {$plainPassword} (change after first login)");
            }
        }
    }
}
