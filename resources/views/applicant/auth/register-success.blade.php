<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - CNHS Online Application System</title>
    <link rel="icon" href="{{ asset('images/cnhs_logo.png') }}" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .success-card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            overflow: hidden;
        }

        .success-icon {
            font-size: 4rem;
            color: #8B0000;
            animation: slideDown 0.6s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-header {
            background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        .success-body {
            padding: 2.5rem;
            text-align: center;
        }

        .success-message {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #333;
            margin: 1.5rem 0;
        }

        .email-highlight {
            background: #e7f3ff;
            padding: 1rem;
            border-left: 4px solid #667eea;
            border-radius: 6px;
            margin: 1.5rem 0;
            text-align: left;
        }

        .ok-btn {
            font-size: 1.1rem;
            padding: 0.75rem 3rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .ok-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(139, 0, 0, 0.4);
        }

        .checklist {
            text-align: left;
            margin: 1.5rem 0;
        }

        .checklist-item {
            padding: 0.75rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .checklist-item i {
            color: #198754;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .logo-section {
            margin-bottom: 1rem;
        }

        .logo-section img {
            width: 70px;
            margin-bottom: 0.75rem;
        }
    </style>
</head>
<body>
<div class="container" style="display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    <div style="max-width: 600px; width: 100%;">
        <div class="card success-card">
            <!-- Header -->
            <div class="success-header">
                <div class="logo-section">
                    <img src="{{ asset('images/cnhs_logo.png') }}" alt="CNHS Logo">
                    <h5 class="mb-0">Carranglan National High School</h5>
                </div>
            </div>

            <!-- Success Content -->
            <div class="success-body">
                <div class="success-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>

                <h3 class="fw-bold mt-3 mb-1" style="color: #198754;">Registration Successful!</h3>
                <p class="text-muted small">Account created - Email verification required</p>

                <div class="success-message">
                    <p>Thank you for registering for <strong>Carranglan National High School</strong>!</p>
                    <p>Your account has been <strong>successfully created</strong>. The next step is to verify your email address.</p>
                </div>

                <!-- What to do next -->
                <div class="email-highlight" style="border-left-color: #8B0000; background: #fff5f5;">
                    <h6 class="fw-bold mb-2" style="color: #8B0000;">
                        <i class="bi bi-envelope-fill me-2"></i>Verify Your Email
                    </h6>
                    <p class="mb-0 small">
                        A verification code has been sent to your email address. 
                        <br><br>
                        You will be redirected to the verification page shortly. If not, you can log in to enter the code manually.
                    </p>
                </div>

                <!-- What happens next -->
                <div class="checklist">
                    <h6 class="fw-bold mb-2" style="color: #333;">Application Process:</h6>
                    <div class="checklist-item">
                        <i class="bi bi-1-circle-fill"></i>
                        <span><strong>Verify Email</strong> - Check your inbox and enter the verification code</span>
                    </div>
                    <div class="checklist-item">
                        <i class="bi bi-2-circle-fill"></i>
                        <span><strong>Complete Application</strong> - Fill out the full application form on your dashboard</span>
                    </div>
                    <div class="checklist-item">
                        <i class="bi bi-3-circle-fill"></i>
                        <span><strong>Wait for Review</strong> - Our team reviews your application (1-3 business days)</span>
                    </div>
                    <div class="checklist-item">
                        <i class="bi bi-4-circle-fill"></i>
                        <span><strong>Get Notified</strong> - Receive email updates about your application status</span>
                    </div>
                </div>

                <!-- Footer message -->
                <p class="text-muted small mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Important: Please check both your inbox and spam/junk folder for the verification email.
                </p>

                <!-- OK Button -->
                <div class="mt-4">
                    <a href="{{ route('applicant.verify-email') }}" class="btn ok-btn" style="background: #8B0000; color: white; border: none;">
                        <i class="bi bi-arrow-right me-2"></i>Go to Email Verification
                    </a>
                </div>

                <div class="mt-2">
                    <a href="{{ route('home') }}" class="btn ok-btn" style="background: white; color: #8B0000; border: 1px solid #8B0000;">
                        <i class="bi bi-house me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
