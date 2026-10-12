<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LandingSettingsController extends Controller
{
    private string $settingsFile = 'landing_settings.json';

    // Default values — matches the current hardcoded index.blade.php content
    private array $defaults = [
        'school_name' => 'CNHS Online Application',
        'title' => 'Welcome to CNHS Online Application System',
        'description' => 'Apply for admission easily and conveniently from anywhere, anytime.',
        'announcement' => '',
        'contact_phone' => '(+63) 44-291-2340',
        'contact_email' => 'cnhs.edu.ph@gmail.com',
        'contact_address' => 'Quezon Street, F.C. Otic Poblacion (Brgy. II), Carranglan, Nueva Ecija',
        'office_hours' => "Monday – Friday: 7:30 AM – 4:30 PM\nSaturday: 7:30 AM – 12:00 PM\nSunday: Closed",
        'primary_color' => '#8B0000',
        'sidebar_color' => '#8B0000',
        'logo_path' => null,
        'banner_path' => null,
        // Hero "Quick Process" steps
        'hero_steps_title' => 'Quick Process',
        'hero_steps' => [
            'Register and choose your program',
            'Verify your email address',
            'Fill out the application form',
            'Wait for admission review',
        ],
        // Application Settings
        'app_settings' => [
            'maintenance_mode' => false,
            'registration_open' => true,
            'allow_google_login' => true,
            'auto_send_email' => true,
        ],
        // Notification Settings
        'notification_settings' => [
            ['id' => 1, 'event' => 'Application Submitted',   'email' => true,  'description' => 'Notify applicant when their application is received.'],
            ['id' => 2, 'event' => 'Application Approved',    'email' => true,  'description' => 'Notify applicant when their application is approved.'],
            ['id' => 3, 'event' => 'Application Rejected',    'email' => true,  'description' => 'Notify applicant when their application is rejected.'],
            ['id' => 4, 'event' => 'Status Reset to Pending', 'email' => true,  'description' => 'Notify applicant when their status is reset to pending.'],
            ['id' => 5, 'event' => 'Exam Schedule Assigned',  'email' => true,  'description' => 'Notify applicant when assigned to an exam schedule.'],
            ['id' => 6, 'event' => 'Account Created',         'email' => true,  'description' => 'Send credentials when a new account is created.'],
        ],
    ];

    public function getAppSettings()
    {
        $settings = $this->load();

        return response()->json([
            'app_settings' => $settings['app_settings'],
        ]);
    }

    public function updateAppSettings(Request $request)
    {
        $request->validate([
            'maintenance_mode' => 'required|boolean',
            'registration_open' => 'required|boolean',
            'allow_google_login' => 'required|boolean',
            'auto_send_email' => 'required|boolean',
        ]);

        $settings = $this->load();
        $settings['app_settings'] = [
            'maintenance_mode' => (bool) $request->maintenance_mode,
            'registration_open' => (bool) $request->registration_open,
            'allow_google_login' => (bool) $request->allow_google_login,
            'auto_send_email' => (bool) $request->auto_send_email,
        ];
        $this->save($settings);

        return response()->json([
            'message' => 'Application settings saved successfully.',
            'app_settings' => $settings['app_settings'],
        ]);
    }

    public function updateNotifications(Request $request)
    {
        $request->validate([
            'notifications' => 'required|array',
            'notifications.*.id' => 'required|integer',
            'notifications.*.email' => 'required|boolean',
        ]);

        $settings = $this->load();

        // Merge saved email flags back into the full notification list
        $emailMap = collect($request->notifications)->keyBy('id');
        $settings['notification_settings'] = collect($settings['notification_settings'])
            ->map(function ($notif) use ($emailMap) {
                if ($emailMap->has($notif['id'])) {
                    $notif['email'] = (bool) $emailMap[$notif['id']]['email'];
                }

                return $notif;
            })->values()->toArray();

        $this->save($settings);

        return response()->json([
            'message' => 'Notification settings saved successfully.',
            'notification_settings' => $settings['notification_settings'],
        ]);
    }

    public function index()
    {
        $settings = $this->load();

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $request->validate([
            'school_name' => 'required|string|max:255',
            'title' => 'required|string|max:500',
            'description' => 'required|string|max:1000',
            'announcement' => 'nullable|string|max:1000',
            'contact_phone' => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:255',
            'contact_address' => 'nullable|string|max:500',
            'office_hours' => 'nullable|string|max:500',
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'hero_steps_title' => 'nullable|string|max:100',
            'hero_steps' => 'nullable|array|max:10',
            'hero_steps.*' => 'nullable|string|max:200',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'banner' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
        ]);

        $settings = $this->load();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            if ($settings['logo_path'] && Storage::disk('public')->exists($settings['logo_path'])) {
                Storage::disk('public')->delete($settings['logo_path']);
            }
            $settings['logo_path'] = $request->file('logo')->store('landing', 'public');
        }

        // Handle banner upload
        if ($request->hasFile('banner')) {
            if ($settings['banner_path'] && Storage::disk('public')->exists($settings['banner_path'])) {
                Storage::disk('public')->delete($settings['banner_path']);
            }
            $settings['banner_path'] = $request->file('banner')->store('landing', 'public');
        }

        // Remove logo
        if ($request->input('remove_logo') === '1') {
            if ($settings['logo_path'] && Storage::disk('public')->exists($settings['logo_path'])) {
                Storage::disk('public')->delete($settings['logo_path']);
            }
            $settings['logo_path'] = null;
        }

        // Remove banner
        if ($request->input('remove_banner') === '1') {
            if ($settings['banner_path'] && Storage::disk('public')->exists($settings['banner_path'])) {
                Storage::disk('public')->delete($settings['banner_path']);
            }
            $settings['banner_path'] = null;
        }

        // Scalar fields
        foreach (['school_name', 'title', 'description', 'announcement', 'contact_phone', 'contact_email', 'contact_address', 'office_hours', 'primary_color', 'sidebar_color', 'hero_steps_title'] as $field) {
            $settings[$field] = $request->input($field, $settings[$field] ?? '');
        }

        // Hero "Quick Process" steps — trim, drop blanks, cap at 10
        if (is_array($request->input('hero_steps'))) {
            $steps = collect($request->input('hero_steps'))
                ->map(fn ($step) => trim((string) $step))
                ->filter()
                ->take(10)
                ->values()
                ->all();
            $settings['hero_steps'] = $steps ?: $this->defaults['hero_steps'];
        }

        $this->save($settings);

        return response()->json([
            'message' => 'Landing page settings saved successfully.',
            'settings' => $settings,
        ]);
    }

    private function load(): array
    {
        $path = storage_path('app/landing_settings.json');
        if (file_exists($path)) {
            $saved = json_decode(file_get_contents($path), true) ?? [];
            $merged = array_merge($this->defaults, $saved);
            // Deep-merge nested arrays so app_settings and notification_settings
            // are never lost when only scalar fields are saved
            if (empty($saved['app_settings'])) {
                $merged['app_settings'] = $this->defaults['app_settings'];
            }
            if (empty($saved['notification_settings'])) {
                $merged['notification_settings'] = $this->defaults['notification_settings'];
            }
            if (empty($saved['hero_steps']) || !is_array($saved['hero_steps'])) {
                $merged['hero_steps'] = $this->defaults['hero_steps'];
            }
            if (trim((string) ($saved['hero_steps_title'] ?? '')) === '') {
                $merged['hero_steps_title'] = $this->defaults['hero_steps_title'];
            }
            // Requirements are now managed from the database (see RequirementsController)
            unset($merged['requirements']);
            if (empty($saved['primary_color'])) {
                $merged['primary_color'] = $this->defaults['primary_color'];
            }
            if (empty($saved['sidebar_color'])) {
                $merged['sidebar_color'] = $this->defaults['sidebar_color'];
            }

            return $merged;
        }

        return $this->defaults;
    }

    private function save(array $settings): void
    {
        file_put_contents(
            storage_path('app/landing_settings.json'),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
