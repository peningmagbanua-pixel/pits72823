<!DOCTYPE html>
<?php
    $settingsPath = storage_path('app/landing_settings.json');
    $appSettings  = ['maintenance_mode' => false, 'registration_open' => true, 'allow_google_login' => true, 'auto_send_email' => true];
    if (file_exists($settingsPath)) {
        $json = json_decode(file_get_contents($settingsPath), true) ?? [];
        if (!empty($json['app_settings'])) {
            $appSettings = array_merge($appSettings, $json['app_settings']);
        }
    }
?>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" href="{{ asset('images/cnhs_logo.png') }}" type="image/x-icon">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/spinner.css') }}">
    <script>
        window.__APP_SETTINGS__ = @json($appSettings);
    </script>
    <script>
        window.__SESSION_TIMEOUT__ = {
            timeoutMinutes: {{ (int) \App\Models\Setting::get('session_timeout_minutes', (int) config('session.timeout_minutes')) }},
            warningMinutes: {{ (int) \App\Models\Setting::get('session_warning_minutes', (int) config('session.warning_minutes')) }},
        };
    </script>
</head>

<body class="layout-fixed fixed-header fixed-footer app-loaded sidebar-expand-lg sidebar-mini bg-body-tertiary">
    <!-- Page loading bar (router navigation) -->
    <div id="cnhs-page-loader"></div>

    <!-- Initial boot overlay (shown until Vue mounts) -->
    <div id="cnhs-boot-overlay">
        <span class="cnhs-spinner cnhs-spinner-lg maroon" role="status" aria-label="Loading">
            <div class="bar"></div><div class="bar"></div><div class="bar"></div>
            <div class="bar"></div><div class="bar"></div><div class="bar"></div>
            <div class="bar"></div><div class="bar"></div><div class="bar"></div>
            <div class="bar"></div><div class="bar"></div><div class="bar"></div>
        </span>
        <p>Loading, please wait…</p>
    </div>

    <div id="admin-app"></div>
    <script src="{{ asset('../js/admin-lte.js') }}" defer></script>
    <script src="{{ asset('../js/darkmode.js') }}" defer></script>
    <script src="{{ asset('../js/eye-protection.js') }}" defer></script>
</body>

</html>