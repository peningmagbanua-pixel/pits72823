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
        .reason-box { background: #f9f9f9; border: 1px solid #e9ecef; border-left: 4px solid #8B0000; border-radius: 8px; padding: 18px 24px; margin: 20px 0; }
        .reason-box h5 { color: #333333; margin: 0 0 8px; font-size: 14px; font-weight: bold; }
        .reason-box .reason-text { margin: 0; font-size: 14px; color: #555555; line-height: 1.7; white-space: pre-wrap; word-break: break-word; }
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
                Thank you for applying to the <strong>{{ $applicant->program?->initials }}</strong> program at
                        <strong>Carranglan National High School (CNHS)</strong>.
                We appreciate the time and effort you invested in your application.
            </p>

            <div class="status-box">
                <div class="status-label">Your Application Status</div>
                <div class="status-value">Rejected</div>
                <p class="status-note">
                    After careful review of your submitted requirements, your application for the
                    <strong>{{ $applicant->program?->initials }}</strong> program has not been approved at this time.
                </p>
            </div>

            <div class="reason-box">
                <h5>Reason for Rejection</h5>
                <p class="reason-text">{{ $applicant->rejection_reason ?: 'No specific reason was recorded. Please contact the CNHS Admissions Office for details.' }}</p>
                <p class="status-note">
                    Please read the reason above carefully. Correct or complete the requirements mentioned,
                    then submit them again so your application can be reviewed.
                </p>
            </div>

            <p>
                We understand this may be disappointing news. The reason above explains exactly what needs to be
                fixed or submitted, and our admissions team is ready to help if anything is unclear.
            </p>

            <div class="next-steps">
                <h5>What You Can Do Next:</h5>
                <ul>
                    <li>Review the reason for rejection above and note the requirements that need attention.</li>
                    <li>Correct, complete, or re-submit the required documents.</li>
                    <li>Contact the CNHS Admissions Office if you need clarification on any item.</li>
                </ul>
            </div>

            <p>
                If you have any questions or would like to discuss your application, please contact us
                at <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> or call
                <strong>{{ $contactPhone }}</strong>.
            </p>

            <p>We wish you the best in your academic journey.</p>
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
