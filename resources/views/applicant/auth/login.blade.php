<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Login – CNHS Online Application System</title>
    <link rel="icon" href="{{ asset('images/cnhs_logo.png') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('css/spinner.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <style>
        body {
            background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,.28);
            padding: 2rem 2.25rem;
            max-width: 440px;
            width: 100%;
        }

        .form-control:focus {
            border-color: #8B0000;
            box-shadow: 0 0 0 3px rgba(139,0,0,.12);
        }

        .google-btn {
            background: white;
            color: #444;
            border: 1px solid #ddd;
            transition: all .25s;
        }
        .google-btn:hover {
            background: #f8f8f8;
            box-shadow: 0 4px 12px rgba(0,0,0,.12);
            transform: translateY(-1px);
        }

        .back-link {
            color: rgba(255,255,255,.75);
            font-size: .85rem;
            text-decoration: none;
            transition: color .2s;
        }
        .back-link:hover { color: white; }
    </style>
</head>

<body class="d-flex flex-column align-items-center justify-content-center min-vh-100 p-3">

    <a href="{{ route('home') }}" class="back-link mb-3">
        <i class="bi bi-arrow-left me-1"></i>Back to Home
    </a>

    <div class="card login-card">

        <div class="text-center mb-4">
            <img src="{{ asset('images/cnhs_logo.png') }}" width="70" class="mb-2" alt="CNHS Logo">
            <h6 class="fw-bold text-dark mb-0">Carranglan National High School</h6>
            <p class="text-muted small mb-1">Online Application System</p>
            <span class="badge" style="background: #8B0000; color: white;">
                Applicant Portal
            </span>
        </div>

        @if(session('error'))
        <div class="alert alert-danger text-center py-2 small mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span>{{ session('error') }}</span>
        </div>
        @endif

        @if(session('success'))
        <div class="alert alert-success text-center py-2 small mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill"></i>
            <span>{{ session('success') }}</span>
        </div>
        @endif

        @if(session('warning'))
        <div class="alert alert-warning text-center py-2 small mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>{{ session('warning') }}</span>
        </div>
        @endif

        <form method="POST" action="{{ route('applicant.login.submit') }}" id="loginForm">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">Email</label>
                <input type="email" name="email" id="email" class="form-control form-control-sm"
                       value="{{ old('email') }}" placeholder="your@gmail.com" required autofocus>
                @error('email')
                <small class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</small>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">Password</label>
                <div class="input-group input-group-sm">
                    <input type="password" name="password" id="password" class="form-control form-control-sm"
                           placeholder="Enter your password" required>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="togglePassword"
                        title="Show/hide password" onclick="
                            const p = document.getElementById('password');
                            p.type = p.type === 'password' ? 'text' : 'password';
                            this.innerHTML = p.type === 'password'
                                ? '<i class=\'bi bi-eye\'></i>'
                                : '<i class=\'bi bi-eye-slash\'></i>';
                        ">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" id="loginBtn"
                    class="btn btn-sm fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-2"
                    style="background: #8B0000; color: white; border: none;">
                    <span id="loginBtnText"><i class="bi bi-box-arrow-in-right me-1"></i>Log In</span>
                    <span id="loginBtnSpinner" style="display:none; align-items:center; gap:6px;">
                        <span class="cnhs-spinner cnhs-spinner-sm white">
                            <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                            <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                            <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                            <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                        </span>
                        Logging in…
                    </span>
                </button>
            </div>

            <div class="d-flex align-items-center my-3">
                <hr class="flex-grow-1 opacity-25">
                <small class="px-2 text-muted">or continue with</small>
                <hr class="flex-grow-1 opacity-25">
            </div>

            <a id="googleLoginBtn" href="{{ route('applicant.google.redirect') }}"
                class="btn btn-sm google-btn d-flex align-items-center justify-content-center gap-2 fw-semibold shadow-sm w-100">
                <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
                    <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z" fill="#34A853"/>
                    <path d="M3.964 10.71c-.18-.54-.282-1.117-.282-1.71s.102-1.17.282-1.71V4.958H.957C.347 6.173 0 7.548 0 9s.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
                    <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
                </svg>
                <span class="btn-text">Sign in with Google</span>
                <span class="btn-loading" style="display:none; align-items:center; gap:6px;">
                    <span class="cnhs-spinner cnhs-spinner-sm">
                        <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                        <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                        <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                        <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                    </span>
                    Redirecting…
                </span>
            </a>
        </form>

        <div class="text-center mt-4">
            <small class="text-muted">
                Don't have an account?
                <a href="{{ route('applicant.register') }}" class="text-decoration-none fw-semibold"
                    style="color: #8B0000;">Register here</a>
            </small>
        </div>
    </div>

    <script src="{{ asset('js/spinner.js') }}"></script>
    <script src="{{ asset('js/animated.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Login form spinner
        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('loginBtn');
            const text = document.getElementById('loginBtnText');
            const spinner = document.getElementById('loginBtnSpinner');
            btn.disabled = true;
            text.style.display = 'none';
            spinner.style.display = 'inline-flex';
        });

        // Google login spinner
        document.getElementById('googleLoginBtn').addEventListener('click', function () {
            this.querySelector('.btn-text').style.display = 'none';
            const loading = this.querySelector('.btn-loading');
            loading.style.display = 'inline-flex';
            loading.style.alignItems = 'center';
        });
    </script>
</body>

</html>