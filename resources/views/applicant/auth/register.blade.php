<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CNHS Online Application System</title>
    <link rel="icon" href="{{ asset('images/cnhs_logo.png') }}" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/spinner.css') }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            overflow: hidden;
            background: #ffffff;
        }

        .form-label {
            font-weight: 600;
            font-size: 13px;
        }

        .section-header {
            background: #f8f9fa;
            border-left: 4px solid #8B0000;
            padding: .6rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.2rem;
        }

        .btn-primary-custom {
            background: #8B0000;
            color: white;
            border: none;
            transition: background 0.2s ease;
        }

        .btn-primary-custom:hover {
            background: #700000;
            color: white;
        }

        .google-btn {
            border: 1px solid #dadce0 !important;
            background-color: #fff !important;
            color: #3c4043 !important;
            transition: background-color .2s, border-color .2s;
            height: 38px;
        }
        .google-btn:hover {
            background-color: #f7f8f8 !important;
            border-color: #d2e3fc !important;
        }
    </style>
</head>
<body>
<div class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
    <div style="width:100%;max-width:550px;">

        <div class="mb-3 text-center">
            <a href="{{ route('home') }}" class="text-white text-decoration-none small">
                <i class="bi bi-arrow-left me-1"></i>Back to Home
            </a>
        </div>

        <div class="card main-card">
            <div class="card-body p-4 p-md-5">

                <!-- Logo -->
                <div class="text-center mb-4">
                    <img src="{{ asset('images/cnhs_logo.png') }}" width="70" class="mb-2" alt="CNHS Logo">
                    <h5 class="fw-bold text-dark mb-0">Carranglan National High School</h5>
                    <p class="text-muted small mb-0">Online Application System</p>
                </div>

                <div class="section-header">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-person-plus-fill me-2" style="color: #8B0000;"></i>Create Applicant Account</h6>
                </div>

                @if($errors->any())
                <div class="alert alert-danger py-2 small mb-3">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form method="POST" action="{{ route('applicant.register.submit') }}" id="registerForm">
                    @csrf

                    <!-- Complete Name Field -->
                    <div class="mb-3">
                        <label class="form-label">Complete Name <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" class="form-control form-control-sm @error('fullname') is-invalid @enderror"
                            value="{{ old('fullname') }}" placeholder="Juan Santos Dela Cruz" required>
                        @error('fullname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Format: Firstname [Middlename] Lastname</small>
                    </div>

                    <!-- Email Field -->
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control form-control-sm @error('email') is-invalid @enderror"
                            value="{{ old('email') }}" placeholder="you@gmail.com" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Your generated password will be sent here.</small>
                    </div>

                    <!-- Divider -->
                    <div class="d-flex align-items-center mb-4">
                        <hr class="flex-grow-1 my-0 text-muted opacity-25">
                        <span class="px-3 text-muted small fw-semibold text-uppercase">or register with Google</span>
                        <hr class="flex-grow-1 my-0 text-muted opacity-25">
                    </div>

                    <!-- Google SSO Button -->
                    <div class="mb-4">
                        <a id="googleRegisterBtn" href="{{ route('applicant.google.redirect') }}"
                            class="btn btn-sm google-btn d-flex align-items-center justify-content-center gap-2 fw-semibold shadow-sm w-100">
                            <svg class="btn-icon" width="18" height="18" viewBox="0 0 18 18">
                                <path fill="#4285F4" d="M17.64 9.2c0-.63-.06-1.25-.16-1.84H9v3.47h4.84c-.21 1.12-.84 2.07-1.8 2.71v2.24h2.91c1.7-1.56 2.69-3.86 2.69-6.58z"/>
                                <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.91-2.24c-.8.54-1.84.87-3.05.87-2.35 0-4.33-1.58-5.04-3.71H.95v2.3A9 9 0 009 18z"/>
                                <path fill="#FBBC05" d="M3.96 10.74A5.4 5.4 0 013.6 9c0-.6.1-1.18.27-1.74l-2.3-1.8A8.99 8.99 0 00.95 9c0 1.91.6 3.67 1.62 5.09l1.39-3.35z"/>
                                <path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.47.89 11.43 0 9 0 5.48 0 2.44 2.07.95 5.09l2.3 1.8c.71-2.13 2.69-3.71 5.04-3.71z"/>
                            </svg>
                            <span class="btn-loading d-none">
                                <span class="cnhs-spinner cnhs-spinner-sm text-secondary">
                                    <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                                    <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                                    <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                                    <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                                </span>
                                Redirecting...
                            </span>
                            <span class="btn-text">Register with Google</span>
                        </a>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid mb-3">
                        <button type="submit" id="submitBtn" class="btn btn-primary-custom btn-sm fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-2">
                            <span id="submitIcon"><i class="bi bi-person-plus-fill"></i></span>
                            <span id="submitText">Register and Continue</span>
                            <span id="submitSpinner" style="display:none;">
                                <span class="cnhs-spinner cnhs-spinner-sm white">
                                    <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                                    <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                                    <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                                    <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                                </span>
                                Registering…
                            </span>
                        </button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <small class="text-muted">Already have an account?
                        <a href="{{ route('applicant.login') }}" class="text-decoration-none fw-semibold">Login here</a>
                    </small>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/spinner.js') }}"></script>
<script src="{{ asset('js/register.js') }}"></script>
<script>
    document.getElementById('registerForm') && document.getElementById('registerForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        const icon = document.getElementById('submitIcon');
        const text = document.getElementById('submitText');
        const spinner = document.getElementById('submitSpinner');
        if (btn && icon && text && spinner) {
            btn.disabled = true;
            icon.style.display = 'none';
            text.style.display = 'none';
            spinner.style.display = 'inline-flex';
            spinner.style.alignItems = 'center';
            spinner.style.gap = '8px';
        }
    });
</script>
</body>
</html>