<?php

namespace App\Http\Controllers\Applicant\Auth;

use App\Http\Controllers\Controller;
use App\Models\Applicant\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::guard('applicant')->attempt($credentials)) {

            $request->session()->regenerate();

            /** @var \App\Models\Applicant\Applicant $applicant */
            $applicant = Auth::guard('applicant')->user();

            if (!$applicant->is_active) {
                Auth::guard('applicant')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                ActivityLog::logActivity(
                    $applicant->id,
                    'login_failed_inactive',
                    'Login attempt with inactive account',
                    [],
                    $request->ip(),
                    $request->userAgent()
                );

                return redirect()->route('applicant.login')
                    ->with('error', 'Your account is not yet activated. Please wait for admin approval.');
            }

            // Block repeat logins while the password change is still pending.
            // last_login_at is null only before the very first login, which is
            // the one session in which the applicant can change the password.
            if ($applicant->must_change_password && $applicant->last_login_at !== null) {
                Auth::guard('applicant')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                ActivityLog::logActivity(
                    $applicant->id,
                    'login_blocked_must_change_password',
                    'Login attempt blocked: password must be changed before logging in again',
                    [],
                    $request->ip(),
                    $request->userAgent()
                );

                return redirect()->route('applicant.login')
                    ->with('error', 'You must change your password before logging in. Please contact the administrator for assistance.');
            }

            $applicant->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            // Log successful login
            ActivityLog::logActivity(
                $applicant->id,
                'logged_in',
                'User logged in successfully',
                [],
                $request->ip(),
                $request->userAgent()
            );

            return redirect()->route('applicant.dashboard');
        }

        $failedApplicant = \App\Models\Applicant\Applicant::where('email', $request->input('email'))->first();
        if ($failedApplicant) {
            ActivityLog::logActivity(
                $failedApplicant->id,
                'login_failed',
                'Failed login attempt (incorrect password)',
                [],
                $request->ip(),
                $request->userAgent()
            );
        } else {
            \Illuminate\Support\Facades\Log::warning('Failed login attempt with non-existent email: ' . $request->input('email'), [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
        }

        return back()->withInput($request->only('email'))
            ->with('error', 'Invalid email or password.');
    }

    public function logout(Request $request)
    {
        $applicant = Auth::guard('applicant')->user();
        
        if ($applicant) {
            ActivityLog::logActivity(
                $applicant->id,
                'logged_out',
                'User logged out',
                [],
                $request->ip(),
                $request->userAgent()
            );
        }

        Auth::guard('applicant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirectTo = $request->input('redirect_to');
        if ($redirectTo && str_starts_with($redirectTo, '/') && !str_starts_with($redirectTo, '//')) {
            return redirect($redirectTo);
        }

        return redirect()->route('applicant.login')->with('message', 'Logged out successfully');
    }
}
