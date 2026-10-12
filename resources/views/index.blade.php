<!DOCTYPE html>
<?php
    // Load landing page settings from JSON (falls back to hardcoded defaults)
    $settingsFile = storage_path('app/landing_settings.json');
    $ls = file_exists($settingsFile)
        ? array_merge([
            'school_name'  => 'CNHS Online Application',
            'title'        => 'Welcome to CNHS Online Application System',
            'description'  => 'Apply for admission easily and conveniently from anywhere, anytime.',
            'announcement' => '',
            'contact_phone'=> '(+63) 44-291-2340',
            'contact_email'=> 'cnhs.edu.ph@gmail.com',
            'office_hours' => "Monday – Friday: 7:30 AM – 4:30 PM\nSaturday: 7:30 AM – 12:00 PM\nSunday: Closed",
            'logo_path'    => null,
            'banner_path'  => null,
            'hero_steps_title' => 'Quick Process',
            'hero_steps'   => [
                'Register and choose your program',
                'Verify your email address',
                'Fill out the application form',
                'Wait for admission review',
            ],
        ], json_decode(file_get_contents($settingsFile), true) ?? [])
        : [
            'school_name'  => 'CNHS Online Application',
            'title'        => 'Welcome to CNHS Online Application System',
            'description'  => 'Apply for admission easily and conveniently from anywhere, anytime.',
            'announcement' => '',
            'contact_phone'=> '(+63) 44-291-2340',
            'contact_email'=> 'cnhs.edu.ph@gmail.com',
            'office_hours' => "Monday – Friday: 7:30 AM – 4:30 PM\nSaturday: 7:30 AM – 12:00 PM\nSunday: Closed",
            'logo_path'    => null,
            'banner_path'  => null,
            'hero_steps_title' => 'Quick Process',
            'hero_steps'   => [
                'Register and choose your program',
                'Verify your email address',
                'Fill out the application form',
                'Wait for admission review',
            ],
        ];

    $logoUrl   = $ls['logo_path']   ? asset('storage/' . $ls['logo_path'])   : asset('images/cnhs_logo.png');
    $bannerUrl = $ls['banner_path'] ? asset('storage/' . $ls['banner_path']) : null;

    // Split office hours string into display lines
    $officeLines = array_filter(array_map('trim', explode("\n", $ls['office_hours'])));

    // Hero "Quick Process" steps (editable from Admin → Settings → Landing Page)
    $heroStepsTitle = trim((string) ($ls['hero_steps_title'] ?? '')) !== '' ? $ls['hero_steps_title'] : 'Quick Process';
    $heroSteps = array_values(array_filter(array_map('trim', (array) ($ls['hero_steps'] ?? []))));
    if (!$heroSteps) {
        $heroSteps = [
            'Register and choose your program',
            'Verify your email address',
            'Fill out the application form',
            'Wait for admission review',
        ];
    }
?>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $ls['school_name'] }} — Online Application System</title>
    <meta name="description" content="{{ $ls['description'] }}">
    <link rel="icon" href="{{ $logoUrl }}" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/spinner.css') }}">

    <style>
        :root {
            --primary: {{ $ls['primary_color'] ?? '#8B0000' }};
            --primary-light: {{ $ls['primary_color'] ?? '#8B0000' }};
            --secondary: #6c757d;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            scroll-behavior: smooth;
        }

        /* ── Navbar ── */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0,0,0,.1);
            border-bottom: 2px solid var(--primary);
        }
        .navbar-brand { color: var(--primary) !important; font-weight: bold; }
        .logo-img { height: 48px; width: auto; }
        .nav-link { font-weight: 500; transition: color .2s; }
        .nav-link:hover { color: var(--primary) !important; }

        /* ── Hero ── */
        .hero-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8f8f8 100%);
            color: #212529;
            padding: 110px 0 80px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            border-bottom: 5px solid var(--primary);
        }
        .hero-title {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 800;
            margin-bottom: 1.25rem;
            color: var(--primary);
            line-height: 1.2;
        }
        .hero-subtitle {
            font-size: 1.2rem;
            margin-bottom: 1.75rem;
            color: #555;
            line-height: 1.6;
        }
        .hero-steps {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 1.5rem;
            line-height: 1.9;
        }
        .btn-custom {
            padding: 12px 32px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all .3s ease;
        }
        .btn-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(0,0,0,.18);
        }

        /* ── Feature Cards ── */
        .feature-card {
            border: none;
            border-radius: 15px;
            padding: 28px;
            height: 100%;
            transition: all .3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
            border-top: 4px solid var(--primary);
        }
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(139,0,0,.18);
        }
        .feature-icon {
            font-size: 2.8rem;
            color: var(--primary);
            margin-bottom: .85rem;
        }

        /* ── Section Titles ── */
        .section-title {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 800;
            margin-bottom: 2.5rem;
            text-align: center;
            color: var(--primary);
        }

        /* ── About ── */
        #about { border-top: 3px solid var(--primary); }

        /* ── Contact / Address ── */
        #contact { border-top: 3px solid var(--primary); }

        .address-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .address-card-link .feature-card {
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .address-card-link .feature-card::after {
            content: '\F44D'; /* bi-box-arrow-up-right */
            font-family: 'bootstrap-icons';
            position: absolute;
            top: 10px;
            right: 14px;
            font-size: 1rem;
            color: var(--primary);
            opacity: 0;
            transition: opacity .25s;
        }
        .address-card-link:hover .feature-card::after { opacity: 1; }
        .address-card-link:hover .feature-card {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139,0,0,.15), 0 15px 30px rgba(139,0,0,.18);
        }

        /* Map highlight animation */
        @keyframes mapHighlight {
            0%   { box-shadow: 0 0 0 0 rgba(139,0,0,.5); }
            70%  { box-shadow: 0 0 0 12px rgba(139,0,0,0); }
            100% { box-shadow: 0 0 0 0 rgba(139,0,0,0); }
        }
        .map-pulse {
            animation: mapHighlight 1s ease 2;
        }

        /* ── Map Container ── */
        .map-container {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,.12);
            border: 2px solid #eee;
            transition: box-shadow .3s;
        }
        .map-container iframe {
            display: block;
            width: 100%;
            height: 380px;
            border: 0;
        }

        /* ── Footer ── */
        footer {
            background: var(--primary);
            color: white;
            padding: 40px 0 20px;
        }
        footer a:hover { opacity: .8; }

        /* ── Responsive navbar: logo, title and hamburger share one row ── */
        @media (max-width: 991.98px) {
            .navbar-brand {
                flex: 1 1 auto;
                min-width: 0;
                font-size: .95rem;
                padding-right: .5rem;
            }
            .navbar-brand span {
                min-width: 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .navbar-toggler {
                flex: 0 0 auto;
                margin-left: auto;
                padding: .4rem .6rem;
            }
            .logo-img { height: 34px; }
        }

        /* ── Responsive hero logo ── */
        @media (max-width: 768px) {
            .hero-section { padding: 96px 0 60px; min-height: auto; }
            .hero-logo-col { display: none; }
        }

        /* ── Banner image (optional) ── */
        .hero-section.has-banner {
            background-image: var(--banner-url);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .hero-section.has-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0.88);
            z-index: 0;
        }
        .hero-section.has-banner { position: relative; }
        .hero-section.has-banner .container { position: relative; z-index: 1; }

        /* ── Announcement banner ── */
        .announcement-bar {
            background: var(--primary);
            color: #fff;
            text-align: center;
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 500;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
        }
        .announcement-bar + #mainNav { top: var(--announcement-h, 38px); }
        .announcement-bar ~ .hero-section { padding-top: calc(110px + var(--announcement-h, 38px)); }
    </style>
</head>

<body>
    @if(!empty($ls['announcement']))
    <div class="announcement-bar">
        <i class="bi bi-megaphone-fill me-2"></i>{{ $ls['announcement'] }}
    </div>
    @endif

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top navbar-custom" id="mainNav">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="/">
                <img src="{{ $logoUrl }}" alt="{{ $ls['school_name'] }} Logo" class="logo-img me-2">
                <span class="fw-bold">{{ $ls['school_name'] }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section{{ $bannerUrl ? ' has-banner' : '' }}"
        @if($bannerUrl) style="--banner-url: url('{{ $bannerUrl }}')" @endif>
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <h1 class="hero-title">{{ $ls['title'] }}</h1>
                    <p class="hero-subtitle">{{ $ls['description'] }}</p>
                    <div class="hero-steps">
                        <strong>{{ $heroStepsTitle }}:</strong><br>
                        @foreach($heroSteps as $i => $step)
                            <i class="bi bi-{{ ($i + 1) <= 9 ? ($i + 1) . '-circle-fill' : 'circle-fill' }} me-2" style="color: var(--primary);"></i>{{ $step }}@if(!$loop->last)<br>@endif
                        @endforeach
                    </div>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="{{ route('applicant.register') }}" class="btn btn-custom"
                            style="background: var(--primary); color: white; border: none;">
                            <i class="bi bi-person-plus me-2"></i>Start Your Application
                        </a>
                        <a href="{{ route('applicant.login') }}" class="btn btn-custom"
                            style="background: white; color: var(--primary); border: 2px solid var(--primary);">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center hero-logo-col">
                    <img src="{{ $logoUrl }}" alt="{{ $ls['school_name'] }}" class="img-fluid"
                        style="max-width: 380px; filter: drop-shadow(0 10px 30px rgba(139,0,0,.25));">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5" style="background: white;">
        <div class="container">
            <h2 class="section-title">Why Choose Our System?</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="bi bi-lightning-charge-fill feature-icon"></i>
                            <h4 class="card-title">Fast &amp; Easy</h4>
                            <p class="card-text text-muted">Complete your application in minutes with our user-friendly interface.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="bi bi-shield-check feature-icon"></i>
                            <h4 class="card-title">Secure</h4>
                            <p class="card-text text-muted">Your data is protected with industry-standard security measures.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="bi bi-clock-history feature-icon"></i>
                            <h4 class="card-title">24/7 Access</h4>
                            <p class="card-text text-muted">Apply anytime, anywhere with our always-available online system.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="bi bi-file-earmark-check feature-icon"></i>
                            <h4 class="card-title">Track Status</h4>
                            <p class="card-text text-muted">Monitor your application status in real-time.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="bi bi-envelope-check feature-icon"></i>
                            <h4 class="card-title">Email Updates</h4>
                            <p class="card-text text-muted">Receive instant email notifications about your application status.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="bi bi-phone feature-icon"></i>
                            <h4 class="card-title">Mobile Friendly</h4>
                            <p class="card-text text-muted">Access the system from any device — desktop, tablet, or mobile.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5" style="background: white;">
        <div class="container">
            <h2 class="section-title">About CNHS</h2>
            <div class="row align-items-center g-5">
                <div class="col-lg-5 text-center">
                    <img src="{{ $logoUrl }}" alt="{{ $ls['school_name'] }}"
                        class="img-fluid rounded shadow" style="max-width: min(320px, 100%);">
                </div>
                <div class="col-lg-7">
                    <h3 class="mb-3" style="color: var(--primary);">Excellence in Education</h3>
                    <p class="lead mb-3">CNHS is committed to providing quality education and nurturing future leaders.</p>
                    <p class="text-muted mb-4">Our online application system makes it easier for students to apply and join our community. We believe in making education accessible to everyone.</p>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color: var(--primary);"></i>Accredited Programs (SPICT &amp; STE)</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color: var(--primary);"></i>Experienced Faculty</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color: var(--primary);"></i>Modern Facilities</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color: var(--primary);"></i>Student Support Services</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact & Location Section -->
    <section id="contact" class="py-5" style="background: #fafafa;">
        <div class="container">
            <h2 class="section-title">Contact Us &amp; Location</h2>

            <div class="row g-4 mb-4">
                <!-- Address Card – clicking toggles Google Maps below -->
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-geo-alt-fill feature-icon"></i>
                            <h5 class="fw-bold">Address</h5>
                            <p class="card-text text-muted">
                                Carranglan National High School<br>
                                {{ $ls['contact_address'] ?? 'Quezon Street, F.C. Otic Poblacion (Brgy. II), Carranglan, Nueva Ecija' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-telephone-fill feature-icon"></i>
                            <h5 class="fw-bold">Contact</h5>
                            <p class="card-text text-muted">
                                @if(!empty($ls['contact_phone']))
                                <strong>Phone:</strong> {{ $ls['contact_phone'] }}<br>
                                @endif
                                @if(!empty($ls['contact_email']))
                                <strong>Email:</strong><br>
                                <a href="mailto:{{ $ls['contact_email'] }}" class="text-decoration-none"
                                    style="color: var(--primary);">{{ $ls['contact_email'] }}</a>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Office Hours -->
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-clock feature-icon"></i>
                            <h5 class="fw-bold">Office Hours</h5>
                            <p class="card-text text-muted">
                                @foreach($officeLines as $line)
                                    {!! nl2br(e($line)) !!}<br>
                                @endforeach
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-2">
                        <img src="{{ $logoUrl }}" alt="{{ $ls['school_name'] }}" width="36" class="me-2">
                        <h5 class="mb-0">{{ $ls['school_name'] }}</h5>
                    </div>
                    <p class="text-white-50 small">Making education accessible through technology.</p>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-1"><a href="#home"     class="text-white text-decoration-none">Home</a></li>
                        <li class="mb-1"><a href="#features" class="text-white text-decoration-none">Features</a></li>
                        <li class="mb-1"><a href="#about"    class="text-white text-decoration-none">About</a></li>
                        <li class="mb-1"><a href="#contact"  class="text-white text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Apply Now</h5>
                    <a href="{{ route('applicant.register') }}" class="btn btn-sm btn-outline-light mb-2 d-block">
                        <i class="bi bi-person-plus me-1"></i>New Application
                    </a>
                    <a href="{{ route('applicant.login') }}" class="btn btn-sm btn-light d-block"
                        style="color: var(--primary); font-weight: 600;">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Applicant Sign In
                    </a>
                </div>
            </div>
            <hr class="border-white opacity-25 mt-4">
            <div class="text-center">
                <p class="mb-0 text-white-50 small">&copy; {{ date('Y') }} CNHS Online Application System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/spinner.js') }}"></script>

    <script>
        // Keep the fixed nav and hero clear of the announcement bar when its text wraps
        const announcementBar = document.querySelector('.announcement-bar');
        if (announcementBar) {
            const setAnnouncementHeight = () => {
                document.documentElement.style.setProperty('--announcement-h', announcementBar.offsetHeight + 'px');
            };
            setAnnouncementHeight();
            window.addEventListener('resize', setAnnouncementHeight);
            if (document.fonts && document.fonts.ready) document.fonts.ready.then(setAnnouncementHeight);
        }

        // Smooth scroll for nav links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Active nav link on scroll
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-link');
        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const top = section.offsetTop - 80;
                if (window.scrollY >= top) current = section.getAttribute('id');
            });
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) link.classList.add('active');
            });
        });
    </script>
</body>

</html>