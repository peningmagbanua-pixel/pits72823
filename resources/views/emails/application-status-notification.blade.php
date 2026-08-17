<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Application Status Update – CNHS</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background-color: #8B0000; padding: 30px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; }
        .header p { color: #fdd4d4; margin: 6px 0 0; font-size: 14px; }
        .body { padding: 30px 40px; color: #333333; }
        .body p { line-height: 1.7; margin-bottom: 14px; }
        .status-box { border-left: 4px solid #8B0000; background: #f9f9f9; padding: 16px; margin: 20px 0; }
        .status-box .status-label { font-size: 12px; color: #999; text-transform: uppercase; font-weight: bold; }
        .status-box .status-value { font-size: 18px; font-weight: bold; color: #8B0000; margin-top: 6px; }
        .btn { display: inline-block; margin: 20px 0 10px; padding: 12px 28px; background-color: #8B0000; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 15px; }
        .footer { background: #f4f4f4; padding: 20px 40px; text-align: center; color: #888888; font-size: 12px; }
        .note { background: #e8f4f8; border: 1px solid #80c7d7; border-radius: 6px; padding: 12px 16px; font-size: 13px; color: #0c5460; margin-top: 16px; }
        .approved { border-left-color: #28a745; }
        .approved .status-value { color: #28a745; }
        .rejected { border-left-color: #dc3545; }
        .rejected .status-value { color: #dc3545; }
        .under-review { border-left-color: #ffc107; }
        .under-review .status-value { color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Application Status Update</h1>
            <p>Your CNHS application has been updated</p>
        </div>
        <div class="body">
            <p>Hi <strong>{{ $applicant->firstname }} {{ $applicant->lastname }}</strong>,</p>
            <p>
                We wanted to inform you about the status of your application for the
                <strong>{{ $applicant->program }}</strong> program at
                <strong>Carranglan National Highschool (CNHS)</strong>.
            </p>

            <div class="status-box {{ str_replace('_', '-', $status) }}">
                <div class="status-label">Your Application Status</div>
                <div class="status-value">
                    @switch($status)
                        @case('approved')
                            ✓ Approved
                            @break
                        @case('rejected')
                            ✗ Not Approved
                            @break
                        @case('under_review')
                            ⊙ Under Review
                            @break
                        @case('exam_scheduled')
                            📅 Exam Scheduled
                            @break
                        @default
                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                    @endswitch
                </div>
            </div>

            @if($adminMessage)
                <p><strong>Message from Admissions Team:</strong></p>
                <p>{{ $adminMessage }}</p>
            @endif

            @switch($status)
                @case('approved')
                    <p>
                        🎉 Congratulations! Your application has been approved. You can now log in to your account
                        to access the next steps in the admissions process.
                    </p>
                    <a href="{{ rtrim(config('app.url'), '/') . '/applicant/login' }}" class="btn">Access Your Account</a>
                    @break

                @case('exam_scheduled')
                    <p>
                        Your entrance examination has been scheduled. Please log in to your account to view the
                        exact date, time, and location of your exam.
                    </p>
                    <a href="{{ rtrim(config('app.url'), '/') . '/applicant/login' }}" class="btn">View Exam Details</a>
                    @break

                @case('under_review')
                    <p>
                        Thank you for submitting your application. We are currently reviewing your documents
                        and information. We will notify you of any updates shortly.
                    </p>
                    <p>Please check back regularly for status updates.</p>
                    @break

                @case('rejected')
                    <p>
                        Thank you for your interest in CNHS. Unfortunately, your application was not approved at this time.
                        If you have any questions or would like to reapply, please contact the Admissions Office.
                    </p>
                    @break
            @endswitch

            <div class="note">
                ℹ️ <strong>Next Steps:</strong> Log in to your account for detailed information and any required actions.
            </div>

            <p style="margin-top: 20px;">
                If you have any questions or concerns, please don't hesitate to contact the CNHS Admissions Office.
            </p>

            <p>Best regards,<br><strong>CNHS Admissions Team</strong></p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Carranglan National Highschool. All rights reserved.<br>
            This is an automated email. Please do not reply directly to this message.
        </div>
    </div>
</body>
</html>
