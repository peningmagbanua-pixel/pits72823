<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CNHS Online Application System</title>
    <link rel="icon" href="{{ asset('images/cnhs_logo.png') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('css/spinner.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">

    <div class="container d-flex align-items-center justify-content-center min-vh-100">

        <div class="card shadow border-0" style="max-width: 420px; width:100%;">

            <!-- Logo -->
            <div class="text-center mb-4">
                <img src="{{ asset('images/cnhs_logo.png') }}" width="70" class="mb-2" alt="CNHS Logo">
                <h6 class="fw-bold text-dark mb-0">Carranglan National High School</h6>
                <p class="text-muted small mb-1">Online Application System</p>
                <span class="badge bg-primary-subtle text-primary">
                    Applicant Portal
                </span>
            </div>

            <div class="bg-light border-start border-4 border-primary rounded px-3 py-2 mb-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-plus-fill me-2" style="color: #0d6efd;"></i>Create Applicant Account</h6>
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
                    <label class="form-label fw-semibold">Complete Name <span class="text-danger">*</span></label>
                    <input type="text" name="fullname" class="form-control form-control-sm @error('fullname') is-invalid @enderror"
                        value="{{ old('fullname') }}" placeholder="Juan Santos Dela Cruz" required>
                    @error('fullname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Format: Firstname [Middlename] Lastname</small>
                </div>

                <!-- Email Field -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control form-control-sm @error('email') is-invalid @enderror"
                        value="{{ old('email') }}" placeholder="you@gmail.com" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Your generated password will be sent here.</small>
                </div>

                <!-- Divider -->
                <div class="d-flex align-items-center my-4">
                    <hr class="flex-grow-1 opacity-25">
                    <small class="px-2 text-muted">or register with Google</small>
                    <hr class="flex-grow-1 opacity-25">
                </div>

                <!-- Google SSO Button -->
                <div class="mb-4">
                    <a id="googleRegisterBtn" href="{{ route('applicant.google.redirect') }}"
                        class="btn btn-outline-danger btn-sm d-flex align-items-center justify-content-center gap-2 fw-semibold shadow-sm">

                        <i class="bi bi-google btn-icon"></i>

                        <span class="btn-text">Register with Google</span>

                        <span class="btn-loading d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Redirecting…
                        </span>
                    </a>
                </div>

                <!-- Submit Button -->
                <div class="d-grid mb-3">
                    <button type="submit" id="submitBtn" class="btn btn-primary btn-sm fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <span id="submitIcon"><i class="bi bi-person-plus-fill"></i></span>
                        <span id="submitText">Register and Continue</span>
                        <span id="submitSpinner" style="display:none;">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                            Registering…
                        </span>
                    </button>
                </div>
            </form>

            <div class="text-center mt-4">
                <small class="text-muted">Already have an account?
                    <a href="{{ route('applicant.login') }}" class="text-decoration-none fw-semibold">Login here</a>
                </small>
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
