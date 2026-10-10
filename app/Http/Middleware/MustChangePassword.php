<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MustChangePassword
{
    /**
     * Block API access for accounts flagged with must_change_password until
     * they change their password through the forced change-password modal.
     *
     * Allow-listed so the SPA can still boot (page shell, profile boot reads,
     * the change-password endpoints themselves) and so logout keeps working
     * while the account is locked.
     */
    public function handle(Request $request, Closure $next, string $guard = 'web'): Response
    {
        $user = Auth::guard($guard)->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        // Page shells and non-API routes (e.g. logout) always pass.
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $allowed = [
            'api/profile',
            'api/profile/change-password',
            'api/applicant/profile',
            'api/applicant/profile/change-password',
            'api/session/*',
            'api/applicant/session/*',
        ];

        if ($request->is($allowed)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'You must change your password before continuing.',
            'code' => 'must_change_password',
        ], 403);
    }
}
