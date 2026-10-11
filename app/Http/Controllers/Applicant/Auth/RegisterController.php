<?php

namespace App\Http\Controllers\Applicant\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ApplicantAccountCreatedMail;
use App\Models\Admin\User;
use App\Models\Applicant\ActivityLog;
use App\Models\Applicant\Applicant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('applicant.dashboard');
        }

        return view('applicant.auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'fullname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('applicants', 'email')->whereNull('deleted_at')],
        ]);

        // Check if email is already registered as an active admin/teacher
        $adminUser = User::where('email', $validated['email'])->first();
        if ($adminUser) {
            return back()->withInput()->withErrors([
                'email' => 'This email is registered as staff/admin. Please use another email.',
            ]);
        }

        // Parse fullname
        $fullName = trim($validated['fullname']);
        $parts = preg_split('/\s+/', $fullName);

        $firstname = '';
        $lastname = '';
        $middlename = null;
        $extname = null;

        // Check if there is an suffix/extension name at the end (Jr., Sr., III, II, etc.)
        $extCandidates = ['jr', 'jr.', 'sr', 'sr.', 'ii', 'iii', 'iv', 'v'];
        if (count($parts) > 1) {
            $lastWord = strtolower(end($parts));
            if (in_array($lastWord, $extCandidates)) {
                $extname = array_pop($parts);
            }
        }

        $count = count($parts);
        if ($count === 1) {
            $firstname = $parts[0];
            $lastname = 'N/A'; // DB requirement
        } elseif ($count === 2) {
            $firstname = $parts[0];
            $lastname = $parts[1];
        } else {
            $firstname = $parts[0];
            $lastname = array_pop($parts);
            $middlename = implode(' ', $parts);
        }

        // Generate password
        $generatedPassword = Str::random(10);

        $data = [
            'lastname' => $lastname,
            'firstname' => $firstname,
            'middlename' => $middlename,
            'extname' => $extname,
            'email' => $validated['email'],
            'password' => Hash::make($generatedPassword),
            'must_change_password' => true,
            'is_active' => true,
            // application_status intentionally omitted — uses DB column default
        ];

        $applicant = Applicant::create($data);

        // Log registration activity
        ActivityLog::logActivity(
            $applicant->id,
            'registered',
            'User registered a new account.',
            ['email' => $validated['email']],
            $request->ip(),
            $request->userAgent()
        );

        // Send login credentials to the applicant's email
        try {
            Mail::to($applicant->email)
                ->send(new ApplicantAccountCreatedMail($applicant, $generatedPassword));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Registration email failed for ' . $applicant->email . ': ' . $e->getMessage());
        }

        // Auto-login and go straight to the dashboard
        Auth::guard('applicant')->login($applicant);
        $request->session()->regenerate();

        $applicant->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        ActivityLog::logActivity(
            $applicant->id,
            'logged_in',
            'User logged in successfully after registration',
            [],
            $request->ip(),
            $request->userAgent()
        );

        return redirect()->route('applicant.dashboard');
    }
}
