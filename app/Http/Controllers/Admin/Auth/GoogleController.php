<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Traits\AdminActivityLogsTrait;

class GoogleController extends Controller
{
    use AdminActivityLogsTrait;
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->with([
                'redirect_uri' => config('services.google.redirect'),
                'prompt' => 'select_account',
            ])
            ->stateless()
            ->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')
                ->with(['redirect_uri' => config('services.google.redirect')])
                ->stateless()
                ->user();
        } catch (\Throwable $e) {
            \Log::error('Admin Google OAuth callback error: ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->route('admin.login')
                ->with('error', 'Unable to sign in with Google. Please try again. [' . class_basename($e) . ']');
        }

        // Enforce an email-domain allowlist when GOOGLE_ALLOWED_DOMAINS is configured
        // (comma-separated, e.g. "deped.edu.ph,gmail.com"). Blank = any registered email.
        $allowedDomains = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GOOGLE_ALLOWED_DOMAINS', ''))
        )));

        if ($allowedDomains && ! Str::endsWith($googleUser->email, $allowedDomains)) {
            return redirect()->route('admin.login')->with('error', 'Unauthorized email domain.');
        }

        $user = User::where('email', $googleUser->email)->first();

        if (! $user) {
            return redirect()->route('admin.login')->with('error', 'Your email is not registered.');
        }

        if (! $user->is_active) {
            return redirect()->route('admin.login')->with('error', 'Account is inactive.');
        }

        if ($user) {
            $user->update([
                'google_id' => $user->google_id ?? $googleUser->id,
                'avatar' => $user->getRawOriginal('avatar') ?? $googleUser->avatar,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);
        }

        Auth::login($user);

        $this->logAdminActivity('logged_in_via_google', 'Admin logged in via Google SSO.', ['email' => $user->email]);

        return redirect('/admin/dashboard');
    }
}
