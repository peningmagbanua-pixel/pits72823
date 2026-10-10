<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaintenanceMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Admin pages always bypass — never block admin access
        if ($request->is('admin/*')) {
            return $next($request);
        }

        // Authenticated admins always bypass (covers admin API calls under auth:web)
        if (Auth::guard('web')->check()) {
            return $next($request);
        }

        // Read maintenance_mode from the settings JSON
        $path     = storage_path('app/landing_settings.json');
        $settings = file_exists($path)
            ? (json_decode(file_get_contents($path), true) ?? [])
            : [];

        $maintenanceOn = $settings['app_settings']['maintenance_mode'] ?? false;

        if ($maintenanceOn) {
            // Return JSON 503 for applicant API calls
            if ($request->expectsJson() || $request->is('api/applicant/*')) {
                return response()->json([
                    'message' => 'The system is currently under maintenance. Please try again later.',
                ], 503)->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                        ->header('Pragma', 'no-cache');
            }
            // Return HTML maintenance page — no-cache so browsers don't store it
            return response()->view('maintenance', [], 503)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Retry-After', '60');
        }

        return $next($request);
    }
}
