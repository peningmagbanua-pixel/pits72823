<?php

namespace App\Providers;

use App\Mail\Transports\GmailApiTransport;
use App\Models\Setting;
use App\Traits\ConstantsTrait;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

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
        // The Admin Settings session-timeout values must outlive Laravel's
        // own SESSION_LIFETIME GC window, or an idle logout configured above
        // ~120 minutes silently underperforms. Only ever raised, so small
        // timeouts never shrink guest/CSRF sessions; guarded because the
        // settings table may not exist yet during install or migrations.
        try {
            $idleMinutes = (int) Setting::get(
                'session_timeout_minutes',
                (int) config('session.timeout_minutes')
            ) + (int) Setting::get(
                'session_warning_minutes',
                (int) config('session.warning_minutes')
            );

            if ($idleMinutes > 0) {
                config(['session.lifetime' => max((int) config('session.lifetime'), $idleMinutes)]);
            }
        } catch (\Throwable $e) {
            // Settings table unavailable — keep the configured default.
        }

        Mail::extend('gmail-api', function () {
            $config = config('services.gmail_api');

            return new GmailApiTransport(
                (string) ($config['client_id'] ?? ''),
                (string) ($config['client_secret'] ?? ''),
                (string) ($config['refresh_token'] ?? ''),
            );
        });

        Mail::extend('brevo', function () {
            return (new BrevoTransportFactory)->create(
                new Dsn(
                    'brevo+api',
                    'default',
                    (string) config('services.brevo.key')
                )
            );
        });

        Gate::before(function ($user, $ability) {

            if ($user->roles->contains('name', $this->SYSTEM_ADMINISTRATOR)) {
                return true;
            }

            $user_permissions = $user->roles->flatMap(fn ($role) => $role->permissions->pluck('name'))->unique();

            // Permissions are exact-match: unchecking a title in the RBAC
            // matrix always revokes it. Section titles (Academic Setup,
            // System Management) never implicitly grant their child pages.
            return $user_permissions->contains($ability) ? true : null;
        });
    }
}
