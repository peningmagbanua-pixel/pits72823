<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Portal | Exam Scheduling</title>
    <link rel="icon" href="{{ asset('images/cnhs_logo.png') }}" type="image/x-icon">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .app-main { padding: 2rem; background: #f4f6f9; min-height: 100vh; }
        .sidebar-brand { background: #403434; padding: 1rem; }
        .sidebar-brand .brand-text { color: #fff; font-weight: bold; }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper d-flex">
        <!-- Replicating minimal sidebar for Blade pages -->
        <aside class="app-sidebar border-end shadow-sm bg-dark" style="width: 250px; min-height: 100vh;">
            <div class="sidebar-brand text-center">
                <a href="/admin/dashboard" class="text-decoration-none">
                    <span class="brand-text">ADMIN PORTAL</span>
                </a>
            </div>
            <div class="sidebar-wrapper mt-3">
                <nav class="nav flex-column px-2">
                    <a href="/admin/dashboard" class="nav-link text-white py-2">
                        <i class="bi bi-grid-fill me-2"></i> Dashboard
                    </a>
                    <hr class="bg-secondary">
                    <div class="px-3 small text-uppercase text-secondary mb-2">Admissions</div>
                    <a href="/admin/exam-scheduling" class="nav-link text-white py-2 active bg-primary rounded">
                        <i class="bi bi-calendar-check me-2"></i> Exam Scheduling
                    </a>
                </nav>
            </div>
        </aside>

        <div class="flex-grow-1">
            <nav class="navbar navbar-expand bg-white border-bottom shadow-sm px-3">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/dashboard">Return to SPA</a>
                    </li>
                </ul>
            </nav>
            <main class="app-main">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
