<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Traits\ConstantsTrait;

class AppServiceProvider extends ServiceProvider
{
    use ConstantsTrait;
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {

            if ($user->roles->contains('name', $this->SYSTEM_ADMINISTRATOR)) {
                return true;
            }

            $user_permissions = $user->roles->flatMap(fn($role) => $role->permissions->pluck('name'))->unique();

            if ($user_permissions->contains($ability)) {
                return true;
            }

            if (! str_starts_with($ability, 'browse ')) {
                return null;
            }

            $group = match (true) {
                str_ends_with($ability, ' school-years'),
                str_ends_with($ability, ' programs') => 'browse academic-setup',
                str_ends_with($ability, ' users'),
                str_ends_with($ability, ' roles'),
                str_ends_with($ability, ' permission-titles'),
                str_ends_with($ability, ' online-application'),
                str_ends_with($ability, ' settings') => 'browse system-management',
                default => null,
            };

            return $group !== null && $user_permissions->contains($group);
        });
    }
}
