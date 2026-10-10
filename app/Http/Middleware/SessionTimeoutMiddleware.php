<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeoutMiddleware
{
    /**
     * Session key holding the unix timestamp of the account's last real activity.
     * Shared by every tab of the same browser session.
     */
    public const LAST_ACTIVITY_KEY = 'last_activity_at';

    /**
     * Enforce server-side inactivity expiration for authenticated sessions.
     *
     * The timestamp is only refreshed by actual user activity (the throttled
     * session/activity endpoint and page navigation), never by background
     * API requests, so the server remains the authority on session validity.
     *
     * The logout routes are exempt so ending a session always works cleanly.
     */
    public function handle(Request $request, Closure $next, string $guard = 'web'): Response
    {
        $user = Auth::guard($guard)->user();

        if (! $user) {
            return $next($request);
        }

        if ($request->is('admin/logout') || $request->is('applicant/logout')) {
            return $next($request);
        }

        $session = $request->session();
        $lastActivity = $session->get(self::LAST_ACTIVITY_KEY);

        // First protected request after login starts the inactivity clock.
        if (! is_numeric($lastActivity)) {
            $session->put(self::LAST_ACTIVITY_KEY, time());

            return $next($request);
        }

        if ((time() - (int) $lastActivity) <= self::limitSeconds()) {
            return $next($request);
        }

        // Inactivity limit reached — invalidate everything, like a normal logout.
        Auth::guard($guard)->logout();
        $session->invalidate();
        $session->regenerateToken();

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Your session has expired. Please log in again.',
                'code' => 'session_expired',
            ], 401);
        }

        // Mirror the app's Authenticate middleware redirect logic.
        if ($request->is('applicant/*')) {
            return redirect()->route('applicant.login');
        }

        return redirect()->route('admin.login');
    }

    /**
     * Inactivity limit in seconds: the warning appears after the configured
     * idle minutes (session_timeout_minutes) and the countdown then runs for
     * the configured logout minutes (session_warning_minutes) before the
     * session is closed (15 + 2 minutes by default). Both values are stored
     * in the settings table and editable from Admin Settings.
     */
    public static function limitSeconds(): int
    {
        $timeout = (int) Setting::get(
            'session_timeout_minutes',
            (int) config('session.timeout_minutes')
        );
        $warning = (int) Setting::get(
            'session_warning_minutes',
            (int) config('session.warning_minutes')
        );

        return ($timeout + $warning) * 60;
    }
}
