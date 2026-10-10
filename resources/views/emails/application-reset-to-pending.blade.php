<!DOCTYPE html>
<?php
    $settingsPath = storage_path('app/landing_settings.json');
    $ls = file_exists($settingsPath) ? (json_decode(file_get_contents($settingsPath), true) ?? []) : [];
    $schoolName     = $ls['school_name']     ?? 'CNHS Online Application';
    $contactEmail   = $ls['contact_email']   ?? 'cnhs.edu.ph@gmail.com';
    $contactPhone   = $ls['contact_phone']   ?? '(+63) 44-291-2340';
    $contactAddress = $ls['contact_address'] ?? 'Quezon Street, F.C. Otic Poblacion (Brgy. II), Carranglan, Nueva Ecija';
?>
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
        .header p { color: rgba(255,255,255,0.85); margin: 6px 0 0; font-size: 14px; }
        .body { padding: 30px 40px; color: #333333; }
        .body p { line-height: 1.7; margin-bottom: 14px; }
        .status-box { background: #f9f9f9; border: 1px solid #e9ecef; border-left: 4px solid #8B0000; border-radius: 8px; padding: 18px 24px; margin: 20px 0; }
        .status-box .status-label { font-size: 12px; color: #777; text-transform: uppercase; font-weight: bold; letter-spacing: 0.05em; }
        .status-box .status-value { font-size: 18px; font-weight: bold; color: #333333; margin-top: 6px; }
        .status-box .status-note { margin: 8px 0 0; font-size: 14px; color: #555555; line-height: 1.6; }
        .next-steps { background: #f8f9fa; border-radius: 8px; padding: 18px 24px; margin: 20px 0; }
        .next-steps h5 { color: #333; margin: 0 0 10px; font-size: 14px; font-weight: bold; }
        .next-steps li { font-size: 13px; color: #555; margin-bottom: 6px; line-height: 1.6; }
        .footer { background: #f4f4f4; padding: 20px 40px; text-align: center; color: #888888; font-size: 12px; }
        .info-text { font-size: 13px; color: #777; font-style: italic; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CNHS Online Application System</h1>
            <p>Application Status Update</p>
        </div>
        <div class="body">
            <p>Dear <strong>{{ $applicant->firstname }} {{ $applicant->lastname }}</strong>,</p>

            <p>
                Thank you for your patience regarding your application for the
                <strong>{{ $applicant->program?->initials }}</strong> program at
                <strong>Carranglan National High School (CNHS)</strong>.
            </p>

            <div class="status-box">
                <div class="status-label">Your Application Status</div>
                <div class="status-value">Pending</div>
                <p class="status-note">
                    Your application status has been reset to <strong>pending</strong>.
                    Please wait for further updates from the admissions team.
                </p>
            </div>

            <p>
                We sincerely apologise for any inconvenience this may have caused.
                Rest assured that your application is still under review and we will notify you
                once a final decision has been made.
            </p>

            <div class="next-steps">
                <h5>What You Can Do:</h5>
                <ul>
                    <li>Keep an eye on your email for further announcements.</li>
                    <li>Contact the CNHS Admissions Office if you have any questions.</li>
                    <li>Ensure your contact information is up to date.</li>
                </ul>
            </div>

            <p>
                If you have any questions, please contact us at
                <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> or call
                <strong>{{ $contactPhone }}</strong>.
            </p>

            <p>We appreciate your understanding.</p>
            <p>Respectfully,<br><strong>CNHS Admissions Team</strong><br>Carranglan National High School</p>

            <p class="info-text">This is an automated notification. Please do not reply directly to this email.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ $schoolName }}. All rights reserved.<br>
            {{ $contactAddress }}
        </div>
    </div>
</body>
</html>
