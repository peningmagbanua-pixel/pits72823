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
</head>

<body class="bg-light">

    <div class="container d-flex align-items-center justify-content-center min-vh-100">

        <div class="card shadow border-0" style="max-width: 420px; width:100%;">

        <div class="text-center mb-4">
            <img src="{{ asset('images/cnhs_logo.png') }}" width="70" class="mb-2" alt="CNHS Logo">
            <h6 class="fw-bold text-dark mb-0">Carranglan National High School</h6>
            <p class="text-muted small mb-1">Online Application System</p>
            <span class="badge bg-primary-subtle text-primary">
                Applicant Portal
            </span>
        </div>

        @if(session('error'))
        <div class="alert alert-danger text-center py-2 small mb-3">
            {{ session('error') }}
        </div>
        @endif

        @if(session('success'))
        <div class="alert alert-success text-center py-2 small mb-3">
            {{ session('success') }}
        </div>
        @endif

        @if(session('warning'))
        <div class="alert alert-warning text-center py-2 small mb-3">
            {{ session('warning') }}
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
                    class="btn btn-primary btn-sm fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-2">
                    <span id="loginBtnText"><i class="bi bi-box-arrow-in-right me-1"></i>Log In</span>
                </button>
            </div>

            <div class="d-flex align-items-center my-4">
                <hr class="flex-grow-1 opacity-25">
                <small class="px-2 text-muted">or continue with</small>
                <hr class="flex-grow-1 opacity-25">
            </div>

            <a id="googleLoginBtn" href="{{ route('applicant.google.redirect') }}"
                class="btn btn-outline-danger btn-sm d-flex align-items-center justify-content-center gap-2 fw-semibold shadow-sm">

                <i class="bi bi-google btn-icon"></i>

                <span class="btn-text">Sign in with Google</span>

                <span class="btn-loading d-none">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
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

    </div>
    <script src="{{ asset('js/spinner.js') }}"></script>
    <script src="{{ asset('js/animated.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Login form submit: prevent duplicate submissions while redirecting
        document.getElementById('loginForm').addEventListener('submit', function () {
            document.getElementById('loginBtn').disabled = true;
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