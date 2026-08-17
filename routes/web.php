<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Index
Route::get('/', function () {
    return view('index');
})->name('home');

// Applicant Authentication Routes
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

// Applicant Protected Routes
Route::middleware('auth:applicant')->group(function () {
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

        // Application Form
        Route::get('/dashboard', [Applicant\DashboardController::class, 'index']);
        Route::get('/application', [Applicant\ApplicationController::class, 'show']);
        Route::post('/application', [Applicant\ApplicationController::class, 'submit']);

        // FAQ
        Route::get('/faqs', [Applicant\FaqController::class, 'index']);
    });
});

// Admin Protected Routes
Route::middleware('auth:web')->group(function () {
    Route::post('/admin/logout', [Admin\Auth\LoginController::class, 'logout'])->name('admin.logout');

    // Admin Protected Pages
    Route::get('/admin/dashboard', fn () => view('admin.dashboard'))->name('admin.dashboard');

    // Serve uploaded applicant files through PHP (bypasses Windows symlink limitation in php artisan serve).
    // Files live on the private disk and are only readable by authenticated staff with permission.
    Route::get('/admin/files/{path}', function (string $path) {
        if (! str_starts_with($path, 'applications/') || str_contains($path, '..')) {
            abort(403, 'You do not have access to this file.');
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File not found.');
        }
        $fullPath = Storage::disk('local')->path($path);

        return response()->file($fullPath);
    })->where('path', '.*')->middleware('permission:browse applications')->name('admin.file.serve');

    Route::get('/admin/{any}', fn () => view('admin.dashboard'))->where('any', '.*');

    // Protected API Routes
    Route::prefix('api')->group(function () {

        // Applicant Accounts
        Route::get('/applicant-accounts', [Admin\ApplicantAccountsController::class, 'index'])->middleware('permission:browse applicant-accounts');
        Route::put('/applicant-accounts/{id}', [Admin\ApplicantAccountsController::class, 'update'])->middleware('permission:update applicant-accounts');
        Route::delete('/applicant-accounts/{id}', [Admin\ApplicantAccountsController::class, 'delete'])->middleware('permission:delete applicant-accounts');

        // Applicant Applications
        Route::get('/applicants', [Admin\ApplicantsController::class, 'index'])->middleware('permission:browse applications');
        Route::get('/applicants/print-approved', [Admin\ApplicantsController::class, 'printApproved'])->middleware('permission:browse applications');
        Route::get('/applicants/{id}', [Admin\ApplicantsController::class, 'show'])->middleware('permission:browse applications');
        Route::put('/applicants/{id}/status', [Admin\ApplicantsController::class, 'updateStatus'])->middleware('permission:update applications');
        Route::get('/applicants/{id}/activity-logs', [Admin\ApplicantsController::class, 'activityLogs'])->middleware('permission:browse applications');

        // Exam Scheduling
        Route::get('/exam-schedules', [Admin\ExamScheduleController::class, 'index'])->middleware('permission:browse exam-scheduling');
        Route::post('/exam-schedules', [Admin\ExamScheduleController::class, 'store'])->middleware('permission:create exam-scheduling');
        Route::put('/exam-schedules/{examSchedule}', [Admin\ExamScheduleController::class, 'update'])->middleware('permission:update exam-scheduling');
        Route::delete('/exam-schedules/{examSchedule}', [Admin\ExamScheduleController::class, 'destroy'])->middleware('permission:delete exam-scheduling');
        Route::get('/unscheduled-applicants', [Admin\ExamScheduleController::class, 'getUnscheduledApplicants'])->middleware('permission:browse exam-scheduling');
        Route::post('/exam-schedules/auto-assign', [Admin\ExamScheduleController::class, 'autoAssign'])->middleware('permission:create exam-scheduling');
        Route::post('/exam-schedules/manual-assign', [Admin\ExamScheduleController::class, 'manualAssign'])->middleware('permission:create exam-scheduling');
        Route::get('/exam-schedules/{examSchedule}/applicants', [Admin\ExamScheduleController::class, 'getScheduleApplicants'])->middleware('permission:browse exam-scheduling');

        // Dashboard
        Route::get('/dashboard/stats', [Admin\DashboardController::class, 'stats'])->middleware('permission:browse applications');

        // Reports & Monitoring
        Route::get('/reports', [Admin\ReportsController::class, 'index'])->middleware('permission:browse reports');
        Route::get('/audit-logs', [Admin\AuditLogsController::class, 'index'])->middleware('permission:browse audit-logs');
        Route::get('/activity-logs', [Admin\ActivityLogsController::class, 'index'])->middleware('permission:browse applications');

        // System Administration
        // Academic Management
        Route::get('/school-years', [Admin\SchoolYearsController::class, 'index'])->middleware('permission:browse school-years');
        Route::post('/school-years', [Admin\SchoolYearsController::class, 'store'])->middleware('permission:create school-years');
        Route::put('/school-years/{id}', [Admin\SchoolYearsController::class, 'update'])->middleware('permission:update school-years');
        Route::delete('/school-years/{id}', [Admin\SchoolYearsController::class, 'delete'])->middleware('permission:delete school-years');

        Route::get('/programs', [Admin\ProgramsController::class, 'index'])->middleware('permission:browse programs');
        Route::post('/programs', [Admin\ProgramsController::class, 'store'])->middleware('permission:create programs');
        Route::put('/programs/{id}', [Admin\ProgramsController::class, 'update'])->middleware('permission:update programs');
        Route::delete('/programs/{id}', [Admin\ProgramsController::class, 'delete'])->middleware('permission:delete programs');

        // System Management
        Route::get('/users', [Admin\UsersController::class, 'index'])->middleware('role:System Administrator');
        Route::get('/list/users', [Admin\UsersController::class, 'list'])->middleware('role:System Administrator');
        Route::post('/users', [Admin\UsersController::class, 'store'])->middleware('role:System Administrator');
        Route::put('/users/{id}', [Admin\UsersController::class, 'update'])->middleware('role:System Administrator');
        Route::delete('/users/{id}', [Admin\UsersController::class, 'delete'])->middleware('role:System Administrator');

        Route::get('/roles', [Admin\RolesController::class, 'index'])->middleware('role:System Administrator');
        Route::get('/list/roles', [Admin\RolesController::class, 'list'])->middleware('role:System Administrator');
        Route::get('/all/roles', [Admin\RolesController::class, 'allRoles'])->middleware('role:System Administrator');
        Route::get('/roles/permission-matrix', [Admin\RolesController::class, 'getPermissionMatrix'])->middleware('role:System Administrator');
        Route::post('/roles', [Admin\RolesController::class, 'store'])->middleware('role:System Administrator');
        Route::put('/roles/{id}', [Admin\RolesController::class, 'update'])->middleware('role:System Administrator');
        Route::delete('/roles/{id}', [Admin\RolesController::class, 'delete'])->middleware('role:System Administrator');
        Route::get('/roles/{id}/users', [Admin\RolesController::class, 'getRoleUsers'])->middleware('role:System Administrator');

        Route::get('/roles/{id}/permissions', [Admin\PermissionsController::class, 'getRolePermissions'])->middleware('role:System Administrator');
        Route::put('/roles/{id}/permissions', [Admin\PermissionsController::class, 'updateRolePermissions'])->middleware('role:System Administrator');
        Route::get('/check/{category}/permissions', [Admin\PermissionsController::class, 'checkPermissions']);

        Route::get('/permission-titles', [Admin\PermissionTitlesController::class, 'index'])->middleware('role:System Administrator');
        Route::post('/permission-titles', [Admin\PermissionTitlesController::class, 'store'])->middleware('role:System Administrator');
        Route::put('/permission-titles/{id}', [Admin\PermissionTitlesController::class, 'update'])->middleware('role:System Administrator');
        Route::delete('/permission-titles/{id}', [Admin\PermissionTitlesController::class, 'delete'])->middleware('role:System Administrator');

        Route::get('/profile', [Admin\ProfileController::class, 'profile']);
        Route::get('/profile/details', [Admin\ProfileController::class, 'getProfile']);
        Route::put('/profile/update', [Admin\ProfileController::class, 'updateProfile']);
        Route::put('/profile/change-password', [Admin\ProfileController::class, 'changePassword']);
    });
});

Route::fallback(function () {
    return redirect()->route('home');
});
