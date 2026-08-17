<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOGIN</title>
    <link rel="icon" href="{{ asset('images/cnhs_logo.png') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
</head>

<body class="bg-light">

    <div class="container d-flex align-items-center justify-content-center min-vh-100">

        <div class="card shadow border-0" style="max-width: 420px; width:100%;">

            <div class="text-center mb-4">
                <img src="{{ asset('images/cnhs_logo.png') }}" width="70" class="mb-2" alt="CNHS Logo">

                <h6 class="fw-bold text-dark mb-0">Carranglan National High School</h6>
                <p class="text-muted small mb-1">Online Application System</p>

                <span class="badge bg-primary-subtle text-primary">
                    Admin Portal
                </span>
            </div>

            @if(session('error'))
            <div class="alert alert-danger text-center py-2 small mb-3">
                {{ session('error') }}
            </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" id="email" class="form-control form-control-sm" placeholder="your@example.com" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <input type="password" name="password" id="password" class="form-control form-control-sm" placeholder="Enter your password" required>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" id="loginBtn" class="btn btn-primary btn-sm fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-2" disabled>
                        <span class="btn-text">Log in</span>
                        <span class="btn-loading d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Loading…
                        </span>
                    </button>
                </div>

                <div class="d-flex align-items-center my-4">
                    <hr class="flex-grow-1 opacity-25">
                    <small class="px-2 text-muted">or continue with</small>
                    <hr class="flex-grow-1 opacity-25">
                </div>

                <a id="googleLoginBtn" href="{{ route('admin.google.redirect') }}"
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
                <small class="text-muted">Authorized Personnel Only</small>
            </div>

        </div>

    </div>
    <script src="{{ asset('js/animated.js') }}"></script>
    <script src="{{ asset('js/login.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>
</body>

</html>