<?php

namespace App\Http\Controllers\Applicant\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ApplicantAccountCreatedMail;
use App\Models\Applicant\Applicant;
use App\Models\Applicant\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->redirectUrl(config('services.google_applicant.redirect'))
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(config('services.google_applicant.redirect'))
                ->stateless()
                ->user();

            // Check if an active applicant account exists for this email.
            // Soft-deleted accounts are ignored so the email can register again.
            $applicant = Applicant::where('email', $googleUser->getEmail())->first();

            if ($applicant) {

                // Applicant account found — link Google SSO if not already linked
                $applicant->update([
                    'google_id' => $applicant->google_id ?? $googleUser->getId(),
                    'profile'   => $applicant->profile ?? $googleUser->getAvatar(),
                ]);

                if (!$applicant->is_active) {
                    return redirect()->route('applicant.login')
                        ->with('error', 'Your account is not yet activated. Please wait for admin approval.');
                }

                // Block repeat logins while the password change is still pending.
                // last_login_at is null only before the very first login, which is
                // the one session in which the applicant can change the password.
                if ($applicant->must_change_password && $applicant->last_login_at !== null) {
                    return redirect()->route('applicant.login')
                        ->with('error', 'You must change your password before logging in. Please contact the administrator for assistance.');
                }

                Auth::guard('applicant')->login($applicant);

                $applicant->update([
                    'last_login_at' => now(),
                    'last_login_ip' => request()->ip(),
                ]);

                ActivityLog::logActivity($applicant->id, 'logged_in', 'Logged in via Google SSO');

                return redirect()->route('applicant.dashboard');
            }

            // Email doesn't exist — create new applicant account and log them in
            $path     = storage_path('app/landing_settings.json');
            $settings = file_exists($path)
                ? (json_decode(file_get_contents($path), true) ?? [])
                : [];

            if (!($settings['app_settings']['registration_open'] ?? true)) {
                return redirect()->route('applicant.login')
                    ->with('error', 'New registration is currently disabled. Please contact the administrator.');
            }

            $names      = explode(' ', trim($googleUser->getName()));
            $firstname  = $names[0] ?? '';
            $lastname   = count($names) > 1 ? $names[count($names) - 1] : '';
            $middlename = count($names) > 2 ? $names[1] : null;

            $plainPassword = Str::random(10);

            $applicant = Applicant::create([
                'firstname'  => $firstname,
                'lastname'   => $lastname,
                'middlename' => $middlename,
                'email'      => $googleUser->getEmail(),
                'google_id'  => $googleUser->getId(),
                'password'   => bcrypt($plainPassword),
                'must_change_password' => true,
                'is_active'  => true,
                'profile'    => $googleUser->getAvatar(),
            ]);

            ActivityLog::logActivity(
                $applicant->id,
                'registered_via_google',
                'Account created via Google Sign-In.',
                ['email' => $applicant->email]
            );

            Auth::guard('applicant')->login($applicant);
            request()->session()->regenerate();

            $applicant->update([
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
            ]);

            // Send login credentials to the applicant's Gmail
            try {
                Mail::to($applicant->email)
                    ->send(new ApplicantAccountCreatedMail($applicant, $plainPassword));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Registration email failed for ' . $applicant->email . ': ' . $e->getMessage());
            }

            return redirect()->route('applicant.dashboard');

        } catch (\Throwable $e) {
            \Log::error('Google OAuth callback error: ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->route('applicant.login')
                ->with('error', 'Unable to sign in with Google. Please try again. [' . class_basename($e) . ']');
        }
    }
}
