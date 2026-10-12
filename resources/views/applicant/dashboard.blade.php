<!DOCTYPE html>
<?php
    $settingsPath = storage_path('app/landing_settings.json');
    $sidebarColor = '#8B0000';
    if (file_exists($settingsPath)) {
        $json = json_decode(file_get_contents($settingsPath), true) ?? [];
        if (!empty($json['sidebar_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $json['sidebar_color'])) {
            $sidebarColor = $json['sidebar_color'];
        }
    }
    $sidebarLight = \App\Support\SidebarColor::isLight($sidebarColor);
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
    <style>
        :root { --sidebar-color: {{ $sidebarColor }}; }
        @if($sidebarLight)
        /* Light side menu background: keep the existing menu text/icons readable */
        .app-sidebar {
            --bs-color-sidebar: {{ \App\Support\SidebarColor::DARK_TEXT }};
            --lte-sidebar-color: {{ \App\Support\SidebarColor::DARK_TEXT }};
            --lte-sidebar-hover-color: {{ \App\Support\SidebarColor::DARK_TEXT }};
            --lte-sidebar-active-color: {{ \App\Support\SidebarColor::DARK_TEXT }};
            --lte-sidebar-menu-active-color: {{ \App\Support\SidebarColor::DARK_TEXT }};
            --lte-sidebar-submenu-color: #2b3440;
            --lte-sidebar-submenu-hover-color: {{ \App\Support\SidebarColor::DARK_TEXT }};
            --lte-sidebar-submenu-active-color: {{ \App\Support\SidebarColor::DARK_TEXT }};
            --lte-sidebar-header-color: rgba(31, 41, 55, .6);
            --lte-sidebar-hover-bg: rgba(31, 41, 55, .08);
            --lte-sidebar-menu-active-bg: rgba(31, 41, 55, .10);
            --lte-sidebar-submenu-hover-bg: rgba(31, 41, 55, .08);
            --lte-sidebar-submenu-active-bg: rgba(31, 41, 55, .10);
        }
        .sidebar-brand .brand-link,
        .sidebar-brand .brand-text,
        .sidebar-brand .brand-text:hover {
            color: {{ \App\Support\SidebarColor::DARK_TEXT }} !important;
        }
        @endif
    </style>
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

    <div id="applicant-app"></div>
    <script src="{{ asset('../js/admin-lte.js') }}" defer></script>
    <script src="{{ asset('../js/darkmode.js') }}" defer></script>
    <script src="{{ asset('../js/eye-protection.js') }}" defer></script>
</body>

</html>