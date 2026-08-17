<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" href="{{ asset('images/cnhs_logo.png') }}" type="image/x-icon">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="layout-fixed fixed-header fixed-footer app-loaded sidebar-expand-lg sidebar-mini bg-body-tertiary">
    <div id="applicant-app"></div>
    <script src="{{ asset('../js/admin-lte.js') }}" defer></script>
    <script src="{{ asset('../js/darkmode.js') }}" defer></script>
    <script src="{{ asset('../js/eye-protection.js') }}" defer></script>
</body>

</html>