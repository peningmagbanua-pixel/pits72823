<!DOCTYPE html>
<?php
    $settingsFile = storage_path('app/landing_settings.json');
    $ls = file_exists($settingsFile)
        ? array_merge(['school_name' => 'CNHS Online Application', 'primary_color' => '#8B0000', 'logo_path' => null],
            json_decode(file_get_contents($settingsFile), true) ?? [])
        : ['school_name' => 'CNHS Online Application', 'primary_color' => '#8B0000', 'logo_path' => null];
    $logoUrl = $ls['logo_path'] ? asset('storage/' . $ls['logo_path']) : asset('images/cnhs_logo.png');
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance — {{ $ls['school_name'] }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: {{ $ls['primary_color'] ?? '#8B0000' }}; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f8f8;
            font-family: 'Segoe UI', sans-serif;
            padding: 0 12px;
        }
        .maintenance-card {
            max-width: 480px;
            width: 100%;
            text-align: center;
            padding: 48px 36px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            border-top: 5px solid var(--primary);
        }
        .maintenance-icon {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        h1 { font-size: 1.6rem; font-weight: 800; color: #222; }
        p  { color: #666; font-size: 0.95rem; line-height: 1.6; }
        .logo { height: 60px; width: auto; margin-bottom: 1.25rem; }
        @media (max-width: 480px) {
            .maintenance-card { padding: 32px 20px; }
        }
    </style>
</head>
<body>
    <div class="maintenance-card">
        <img src="{{ $logoUrl }}" alt="{{ $ls['school_name'] }}" class="logo">
        <div class="maintenance-icon">
            <i class="bi bi-tools"></i>
        </div>
        <h1>Under Maintenance</h1>
        <p class="mt-2 mb-0">
            The <strong>{{ $ls['school_name'] }}</strong> portal is currently undergoing scheduled maintenance.<br>
            Please check back later. We apologize for the inconvenience.
        </p>
    </div>
</body>
</html>
