<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SessionTimeoutMiddleware;
use App\Models\Setting;
use Illuminate\Http\Request;

class SessionTimeoutController extends Controller
{
    /**
     * Read-only session deadline for the SPA warning countdown.
     *
     * Synchronizing against this value (instead of local timers alone) keeps
     * the countdown accurate when a background tab is throttled or resumed.
     */
    public function status(Request $request)
    {
        return response()->json($this->payload($request));
    }

    /**
     * Record real user activity and return the refreshed session deadline.
     *
     * Writes are throttled server-side so mousemove storms or several open
     * tabs do not hammer the session storage.
     */
    public function activity(Request $request)
    {
        $session = $request->session();
        $lastActivity = (int) $session->get(SessionTimeoutMiddleware::LAST_ACTIVITY_KEY, 0);

        if (time() - $lastActivity >= 30) {
            $session->put(SessionTimeoutMiddleware::LAST_ACTIVITY_KEY, time());
        }

        return response()->json($this->payload($request));
    }

    private function payload(Request $request): array
    {
        $lastActivity = (int) $request->session()
            ->get(SessionTimeoutMiddleware::LAST_ACTIVITY_KEY, time());

        $timeoutMinutes = (int) Setting::get(
            'session_timeout_minutes',
            (int) config('session.timeout_minutes')
        );
        $warningMinutes = (int) Setting::get(
            'session_warning_minutes',
            (int) config('session.warning_minutes')
        );

        return [
            'status' => 'active',
            'remaining_seconds' => max(
                0,
                SessionTimeoutMiddleware::limitSeconds() - (time() - $lastActivity)
            ),
            'warning_seconds' => $warningMinutes * 60,
            'timeout_minutes' => $timeoutMinutes,
            'warning_minutes' => $warningMinutes,
        ];
    }
}
