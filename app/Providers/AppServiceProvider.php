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

            return $user_permissions->contains($ability);
        });
    }
}
