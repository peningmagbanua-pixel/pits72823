<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SessionTimeoutSettingsController extends Controller
{
    /**
     * The configured automatic logout timeouts (System Administrators only).
     */
    public function show()
    {
        return response()->json([
            'timeout_minutes' => (int) Setting::get(
                'session_timeout_minutes',
                (int) config('session.timeout_minutes')
            ),
            'warning_minutes' => (int) Setting::get(
                'session_warning_minutes',
                (int) config('session.warning_minutes')
            ),
        ]);
    }

    /**
     * Save the automatic logout timeouts used by every session type
     * (System Administrator, Staff, and Applicant).
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'timeout_minutes' => 'required|integer|min:1|max:1440',
            'warning_minutes' => 'required|integer|min:1|max:1440',
        ]);

        Setting::set('session_timeout_minutes', $validated['timeout_minutes']);
        Setting::set('session_warning_minutes', $validated['warning_minutes']);

        return response()->json([
            'message' => 'Session timeout settings saved successfully.',
            'timeout_minutes' => (int) $validated['timeout_minutes'],
            'warning_minutes' => (int) $validated['warning_minutes'],
        ]);
    }
}
