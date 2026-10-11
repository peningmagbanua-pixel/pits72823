<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// TEMPORARY DIAGNOSTIC — remove after the Google SSO 500 is diagnosed.
// Probes outbound HTTPS reachability to the Google hosts Socialite uses.
Route::get('/diag/google-hosts', function () {
    set_time_limit(60);
    $out = 'max_execution_time=' . ini_get('max_execution_time') . "\n\n";

    $hosts = [
        'oauth2.googleapis.com',
        'www.googleapis.com',
        'openidconnect.googleapis.com',
        'accounts.google.com',
    ];

    foreach ($hosts as $host) {
        $t = microtime(true);
        $sock = @fsockopen($host, 443, $errno, $errstr, 8);
        $ms = round((microtime(true) - $t) * 1000);
        if ($sock) {
            $out .= "TCP 443 {$host}: OPEN ({$ms}ms)\n";
            fclose($sock);
        } else {
            $out .= "TCP 443 {$host}: FAIL ({$ms}ms) {$errstr}\n";
        }
    }

    $out .= "\n";

    $urls = [
        'token endpoint (expect HTTP 400)' => 'https://oauth2.googleapis.com/tokeninfo?id_token=invalid',
        'userinfo host (expect HTTP 401)' => 'https://www.googleapis.com/oauth2/v3/userinfo',
        'openid userinfo (expect HTTP 401)' => 'https://openidconnect.googleapis.com/v1/userinfo',
    ];

    foreach ($urls as $label => $url) {
        $t = microtime(true);
        try {
            $response = Http::timeout(10)->get($url);
            $ms = round((microtime(true) - $t) * 1000);
            $out .= "{$label}: HTTP {$response->status()} ({$ms}ms)\n";
        } catch (\Throwable $e) {
            $ms = round((microtime(true) - $t) * 1000);
            $out .= "{$label}: FAILED ({$ms}ms) " . $e::class . ': ' . substr($e->getMessage(), 0, 200) . "\n";
        }
    }

    return response($out, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
});

// TEMPORARY DIAGNOSTIC — remove after the applicant registration 500 is diagnosed.
// Replays the exact DB writes of POST /applicant/register against production.
Route::get('/diag/applicant-create', function () {
    set_time_limit(60);
    try {
        $applicant = \App\Models\Applicant\Applicant::create([
            'lastname' => 'Diag',
            'firstname' => 'Test',
            'middlename' => null,
            'extname' => null,
            'email' => 'diag-create-' . uniqid() . '@example.com',
            'password' => 'x',
            'must_change_password' => true,
            'is_active' => true,
        ]);

        \App\Models\Applicant\ActivityLog::logActivity(
            $applicant->id,
            'diag',
            'diagnostic create',
            null,
            '127.0.0.1',
            'diagnostic'
        );

        $applicant->update(['last_login_at' => now(), 'last_login_ip' => '127.0.0.1']);

        $id = $applicant->id;
        $applicant->delete();

        return response("OK: create+log+update+softdelete succeeded, id={$id}\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    } catch (\Throwable $e) {
        return response(
            $e::class . ': ' . $e->getMessage() . "\n\n" . $e->getTraceAsString(),
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
});

// TEMPORARY DIAGNOSTIC — remove after mail delivery is verified.
// Sends a real test email through the currently configured mailer.
Route::get('/diag/send-mail', function () {
    set_time_limit(60);
    try {
        Mail::raw(
            "Mail verification test from the Online Application System at " . now()->toDateTimeString(),
            function ($message) {
                $message->to('peningmagbanua@gmail.com')
                    ->subject('CNHS Admissions - mail verification');
            }
        );
        return response("SEND OK — mailer=" . config('mail.default') . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    } catch (\Throwable $e) {
        return response(
            "SEND FAILED — mailer=" . config('mail.default') . "\n" . $e::class . ': ' . substr($e->getMessage(), 0, 400) . "\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
});

// Index
Route::get('/', function () {
    return view('index');
})->name('home')->middleware('maintenance');

// Applicant Authentication Routes
Route::middleware('maintenance')->group(function () {
    Route::get('/applicant/register', [Applicant\Auth\RegisterController::class, 'showRegistrationForm'])->name('applicant.register');
    Route::post('/applicant/register', [Applicant\Auth\RegisterController::class, 'register'])->name('applicant.register.submit');

    Route::get('/applicant/login', function () {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('applicant.dashboard');
        }

        return view('applicant.auth.login');
    })->name('applicant.login');

    Route::post('/applicant/login', [Applicant\Auth\LoginController::class, 'login'])->name('applicant.login.submit');
    Route::get('applicant/auth/google', [Applicant\Auth\GoogleController::class, 'redirectToGoogle'])->name('applicant.google.redirect');
    Route::get('applicant/auth/google/callback', [Applicant\Auth\GoogleController::class, 'handleGoogleCallback']);
});

// Admin Authentication Routes
Route::get('/admin/login', function () {
    if (Auth::guard('web')->check()) {
        return redirect()->route('admin.dashboard');
    }

    return view('admin.auth.login');
})->name('admin.login');

Route::post('/admin/login', [Admin\Auth\LoginController::class, 'login'])->name('admin.login.submit');
Route::get('auth/google', [Admin\Auth\GoogleController::class, 'redirectToGoogle'])->name('admin.google.redirect');
Route::get('auth/google/callback', [Admin\Auth\GoogleController::class, 'handleGoogleCallback']);

// One-time OAuth flow for the Gmail API mailer (see App\Console\Commands\AuthorizeGmail).
// /gmail-authorize sends the visitor to Google's consent screen; /gmail-auth-callback
// captures the refresh token. Both are safe to leave in place: the callback requires
// the short-lived random state minted by /gmail-authorize and only ever stores a
// token for whichever Google account approves it.
Route::get('/gmail-authorize', function () {
    $state = \Illuminate\Support\Str::random(40);
    Cache::put('gmail_auth.state', $state, now()->addMinutes(15));

    $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => config('services.gmail_api.client_id'),
        'redirect_uri' => config('services.gmail_api.redirect'),
        'response_type' => 'code',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'scope' => 'https://www.googleapis.com/auth/gmail.send',
        'state' => $state,
    ]);

    return redirect()->away($url);
});

// One-time OAuth callback used to capture a Gmail API refresh token. Gated by a
// short-lived random state value so it cannot be abused.
Route::get('/gmail-auth-callback', function (\Illuminate\Http\Request $request) {
    $expected = Cache::pull('gmail_auth.state');

    if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
        return response('Invalid or expired state. Visit /gmail-authorize to start again.', 403);
    }

    if ($error = $request->query('error')) {
        return response('Google returned an error: ' . e($error), 400);
    }

    $response = Http::asForm()
        ->timeout(30)
        ->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.gmail_api.client_id'),
            'client_secret' => config('services.gmail_api.client_secret'),
            'redirect_uri' => config('services.gmail_api.redirect'),
            'code' => $request->query('code'),
            'grant_type' => 'authorization_code',
        ]);

    if ($response->failed()) {
        return response('Token exchange failed [' . $response->status() . ']: ' . $response->body(), 500);
    }

    $refreshToken = $response->json('refresh_token');

    if (! $refreshToken) {
        return response('No refresh token in the response. Make sure the account is listed as a test user on the OAuth consent screen, then visit /gmail-authorize again.', 500);
    }

    Storage::put('gmail-oauth-token.txt', $refreshToken . "\n");

    return response(
        'Gmail authorized successfully. Copy the refresh token below into the Railway variable GMAIL_REFRESH_TOKEN, then set MAIL_MAILER=gmail-api.' .
        "\n\n" . $refreshToken,
        200,
        ['Content-Type' => 'text/plain; charset=UTF-8']
    );
});

// Applicant Protected Routes
Route::middleware(['auth:applicant', 'session-timeout:applicant', 'maintenance', 'must-change-password:applicant'])->group(function () {
    Route::post('/applicant/logout', [Applicant\Auth\LoginController::class, 'logout'])->name('applicant.logout');

    // Applicant Protected Pages
    Route::get('/applicant/dashboard', fn () => view('applicant.dashboard'))->name('applicant.dashboard');

    // Serve the applicant's own uploaded files through PHP (private disk, ownership-checked)
    Route::get('/applicant/files/{path}', function (string $path) {
        $applicant = Auth::guard('applicant')->user();

        if (! str_starts_with($path, 'applications/'.$applicant->id.'/')) {
            abort(403, 'You do not have access to this file.');
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->file(Storage::disk('local')->path($path));
    })->where('path', '.*')->name('applicant.file.serve');

    Route::get('/applicant/{any}', fn () => view('applicant.dashboard'))->where('any', '.*');

    // Applicant API Routes
    Route::prefix('api/applicant')->group(function () {
        Route::get('/profile', [Applicant\ProfileController::class, 'profile']);
        Route::get('/profile/details', [Applicant\ProfileController::class, 'getProfile']);
        Route::put('/profile/update', [Applicant\ProfileController::class, 'updateProfile']);
        Route::put('/profile/change-password', [Applicant\ProfileController::class, 'changePassword']);

        // Session Inactivity Timeout
        Route::get('/session/status', [SessionTimeoutController::class, 'status']);
        Route::post('/session/activity', [SessionTimeoutController::class, 'activity']);

        // Programs
        Route::get('/programs', [Applicant\ProgramsController::class, 'index']);

        // Requirements
        Route::get('/requirements', [Applicant\RequirementsController::class, 'index']);

        // Application Form
        Route::get('/dashboard', [Applicant\DashboardController::class, 'index']);
        Route::get('/activity-logs', [Applicant\DashboardController::class, 'activityLogs']);
        Route::get('/application', [Applicant\ApplicationController::class, 'show']);
        Route::post('/application', [Applicant\ApplicationController::class, 'submit']);
        Route::post('/application/select-program', [Applicant\ApplicationController::class, 'selectProgram']);

        // FAQ
        Route::get('/faqs', [Applicant\FaqController::class, 'index']);
    });
});

// Admin Protected Routes
Route::middleware(['auth:web', 'session-timeout:web', 'must-change-password:web'])->group(function () {
    Route::post('/admin/logout', [Admin\Auth\LoginController::class, 'logout'])->name('admin.logout');

    // Admin Protected Pages
    Route::get('/admin/dashboard', fn () => view('admin.dashboard'))->name('admin.dashboard');

    // Serve uploaded applicant files through PHP (bypasses Windows symlink limitation in php artisan serve).
    // Files live on the private disk and are only readable by authenticated staff with permission
    // who are allowed to access the owning applicant's program.
    Route::get('/admin/files/{path}', [Admin\ApplicantsController::class, 'serveFile'])->where('path', '.*')->middleware('permission:browse applications')->name('admin.file.serve');

    Route::get('/admin/{any}', fn () => view('admin.dashboard'))->where('any', '.*');

    // Protected API Routes
    Route::prefix('api')->group(function () {

        // Applicant Accounts
        Route::get('/applicant-accounts', [Admin\ApplicantAccountsController::class, 'index'])->middleware('permission:browse applicant-accounts');
        Route::put('/applicant-accounts/{id}', [Admin\ApplicantAccountsController::class, 'update'])->middleware('permission:update applicant-accounts');
        Route::delete('/applicant-accounts/{id}', [Admin\ApplicantAccountsController::class, 'delete'])->middleware('permission:delete applicant-accounts');
        Route::post('/applicant-accounts/{id}/restore', [Admin\ApplicantAccountsController::class, 'restore'])->middleware('permission:delete applicant-accounts');

        // Applicant Applications
        Route::get('/applicants', [Admin\ApplicantsController::class, 'index'])->middleware('permission:browse applications');
        Route::get('/applicants/print-approved', [Admin\ApplicantsController::class, 'printApproved'])->middleware('permission:browse applications');
        Route::get('/applicants/{id}', [Admin\ApplicantsController::class, 'show'])->middleware('permission:read applicants');
        Route::put('/applicants/{id}/status', [Admin\ApplicantsController::class, 'updateStatus'])->middleware('permission:update applications');
        Route::get('/applicants/{id}/activity-logs', [Admin\ApplicantsController::class, 'activityLogs'])->middleware('permission:read applicants');

        // Exam Scheduling
        Route::get('/exam-schedules', [Admin\ExamScheduleController::class, 'index'])->middleware('permission:browse exam-scheduling');
        Route::post('/exam-schedules', [Admin\ExamScheduleController::class, 'store'])->middleware('permission:create exam-scheduling');
        Route::put('/exam-schedules/{examSchedule}', [Admin\ExamScheduleController::class, 'update'])->middleware('permission:update exam-scheduling');
        Route::delete('/exam-schedules/{examSchedule}', [Admin\ExamScheduleController::class, 'destroy'])->middleware('permission:delete exam-scheduling');
        Route::post('/exam-schedules/{id}/restore', [Admin\ExamScheduleController::class, 'restore'])->middleware('permission:delete exam-scheduling');
        Route::get('/unscheduled-applicants', [Admin\ExamScheduleController::class, 'getUnscheduledApplicants'])->middleware('permission:browse exam-scheduling');
        Route::post('/exam-schedules/auto-assign', [Admin\ExamScheduleController::class, 'autoAssign'])->middleware('permission:create exam-scheduling');
        Route::post('/exam-schedules/manual-assign', [Admin\ExamScheduleController::class, 'manualAssign'])->middleware('permission:create exam-scheduling');
        Route::get('/exam-schedules/{examSchedule}/applicants', [Admin\ExamScheduleController::class, 'getScheduleApplicants'])->middleware('permission:browse exam-scheduling');

        // Dashboard
        Route::get('/dashboard/stats', [Admin\DashboardController::class, 'stats'])->middleware('permission:browse dashboard');

        // Reports & Monitoring
        Route::get('/reports', [Admin\ReportsController::class, 'index'])->middleware('permission:browse reports');
        Route::get('/reports/print', [Admin\ReportsController::class, 'printReport'])->middleware('permission:browse reports');
        Route::get('/reports/print-approved', [Admin\ReportsController::class, 'printApproved'])->middleware('permission:browse reports');
        Route::get('/reports/print-exam-schedule', [Admin\ReportsController::class, 'printExamSchedule'])->middleware('permission:browse reports');
        Route::get('/audit-logs', [Admin\AuditLogsController::class, 'index'])->middleware('permission:browse audit-logs');

        // System Administration
        // Academic Management
        Route::get('/school-years', [Admin\SchoolYearsController::class, 'index'])->middleware('permission:browse school-years|browse academic-setup|browse reports|browse applications|browse exam-scheduling|browse settings');
        Route::post('/school-years', [Admin\SchoolYearsController::class, 'store'])->middleware('permission:create school-years');
        Route::put('/school-years/{id}', [Admin\SchoolYearsController::class, 'update'])->middleware('permission:update school-years');
        Route::delete('/school-years/{id}', [Admin\SchoolYearsController::class, 'delete'])->middleware('permission:delete school-years');
        Route::post('/school-years/{id}/restore', [Admin\SchoolYearsController::class, 'restore'])->middleware('permission:delete school-years');

        Route::get('/programs', [Admin\ProgramsController::class, 'index'])->middleware('permission:browse programs|browse academic-setup|browse reports|browse applications|browse exam-scheduling|browse settings');
        Route::post('/programs', [Admin\ProgramsController::class, 'store'])->middleware('permission:create programs');
        Route::put('/programs/{id}', [Admin\ProgramsController::class, 'update'])->middleware('permission:update programs');
        Route::delete('/programs/{id}', [Admin\ProgramsController::class, 'delete'])->middleware('permission:delete programs');
        Route::post('/programs/{id}/restore', [Admin\ProgramsController::class, 'restore'])->middleware('permission:delete programs');

        // System Management
        Route::get('/users', [Admin\UsersController::class, 'index'])->middleware('permission:browse users|browse system-management');
        Route::get('/list/users', [Admin\UsersController::class, 'list'])->middleware('permission:read audit-logs');
        Route::post('/users', [Admin\UsersController::class, 'store'])->middleware('permission:create users');
        Route::put('/users/{id}', [Admin\UsersController::class, 'update'])->middleware('permission:update users');
        Route::delete('/users/{id}', [Admin\UsersController::class, 'delete'])->middleware('permission:delete users');
        Route::post('/users/{id}/restore', [Admin\UsersController::class, 'restore'])->middleware('permission:delete users');

        Route::get('/roles', [Admin\RolesController::class, 'index'])->middleware('permission:browse roles|browse system-management');
        Route::get('/list/roles', [Admin\RolesController::class, 'list'])->middleware('permission:browse users|browse system-management');
        Route::get('/all/roles', [Admin\RolesController::class, 'allRoles'])->middleware('permission:browse users|browse system-management');
        Route::get('/roles/permission-matrix', [Admin\RolesController::class, 'getPermissionMatrix'])->middleware('permission:browse roles|browse system-management');
        Route::post('/roles', [Admin\RolesController::class, 'store'])->middleware('permission:create roles');
        Route::put('/roles/{id}', [Admin\RolesController::class, 'update'])->middleware('permission:update roles');
        Route::delete('/roles/{id}', [Admin\RolesController::class, 'delete'])->middleware('permission:delete roles');
        Route::post('/roles/{id}/restore', [Admin\RolesController::class, 'restore'])->middleware('permission:delete roles');
        Route::get('/roles/{id}/users', [Admin\RolesController::class, 'getRoleUsers'])->middleware('permission:browse roles|browse system-management');

        Route::get('/roles/{id}/permissions', [Admin\PermissionsController::class, 'getRolePermissions'])->middleware('permission:browse roles|browse system-management');
        Route::put('/roles/{id}/permissions', [Admin\PermissionsController::class, 'updateRolePermissions'])->middleware('permission:update roles');
        Route::get('/check/{category}/permissions', [Admin\PermissionsController::class, 'checkPermissions']);

        Route::get('/permission-titles', [Admin\PermissionTitlesController::class, 'index'])->middleware('permission:browse permission-titles|browse system-management');
        Route::post('/permission-titles', [Admin\PermissionTitlesController::class, 'store'])->middleware('permission:create permission-titles');
        Route::put('/permission-titles/{id}', [Admin\PermissionTitlesController::class, 'update'])->middleware('permission:update permission-titles');
        Route::delete('/permission-titles/{id}', [Admin\PermissionTitlesController::class, 'delete'])->middleware('permission:delete permission-titles');
        Route::post('/permission-titles/{id}/restore', [Admin\PermissionTitlesController::class, 'restore'])->middleware('permission:delete permission-titles');

        // Landing Page Settings
        Route::get('/landing-settings', [Admin\LandingSettingsController::class, 'index'])->middleware('permission:browse settings|browse system-management');
        Route::get('/landing-settings/app-settings', [Admin\LandingSettingsController::class, 'getAppSettings'])->middleware('permission:browse settings|browse system-management');
        Route::post('/landing-settings', [Admin\LandingSettingsController::class, 'update'])->middleware('permission:update settings');
        Route::post('/landing-settings/app-settings', [Admin\LandingSettingsController::class, 'updateAppSettings'])->middleware('permission:update settings');
        Route::post('/landing-settings/notifications', [Admin\LandingSettingsController::class, 'updateNotifications'])->middleware('permission:update settings');

        // Applicant Requirements (Settings)
        Route::get('/requirements', [Admin\RequirementsController::class, 'index'])->middleware('permission:browse settings|browse system-management');
        Route::post('/requirements', [Admin\RequirementsController::class, 'store'])->middleware('permission:create settings');
        Route::put('/requirements/{id}', [Admin\RequirementsController::class, 'update'])->middleware('permission:update settings');
        Route::delete('/requirements/{id}', [Admin\RequirementsController::class, 'delete'])->middleware('permission:delete settings');

        // Online Application Period (Settings)
        Route::get('/application-period', [Admin\ApplicationPeriodController::class, 'show'])->middleware('permission:browse online-application|browse system-management');
        Route::post('/application-period', [Admin\ApplicationPeriodController::class, 'update'])->middleware('permission:update online-application');

        // Session Timeout Settings
        Route::get('/session-timeout-settings', [Admin\SessionTimeoutSettingsController::class, 'show'])->middleware('permission:browse settings|browse system-management');
        Route::post('/session-timeout-settings', [Admin\SessionTimeoutSettingsController::class, 'update'])->middleware('permission:update settings');

        Route::get('/profile', [Admin\ProfileController::class, 'profile']);
        Route::get('/profile/details', [Admin\ProfileController::class, 'getProfile']);
        Route::put('/profile/update', [Admin\ProfileController::class, 'updateProfile']);
        Route::put('/profile/change-password', [Admin\ProfileController::class, 'changePassword']);
        Route::post('/profile/avatar', [Admin\ProfileController::class, 'uploadAvatar']);
        Route::delete('/profile/avatar', [Admin\ProfileController::class, 'removeAvatar']);

        // Session Inactivity Timeout
        Route::get('/session/status', [SessionTimeoutController::class, 'status']);
        Route::post('/session/activity', [SessionTimeoutController::class, 'activity']);
    });
});

Route::fallback(function () {
    return redirect()->route('home');
});
