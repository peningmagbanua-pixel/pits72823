<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Schedule Notification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .schedule-details { background-color: white; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Exam Schedule Assigned</h1>
        </div>
        <div class="content">
            <p>Dear {{ $applicant->firstname }} {{ $applicant->lastname }},</p>
            
            <p>Your exam schedule has been assigned. Here are the details:</p>
            
            <div class="schedule-details">
                <h3>Exam Details</h3>
                <p><strong>Program:</strong> {{ $schedule->program }}</p>
                <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($schedule->date)->format('F d, Y') }}</p>
                <p><strong>Time:</strong> {{ $schedule->time_slot }}</p>
                <p><strong>Room:</strong> {{ $schedule->room }}</p>
            </div>
            
            <p>Please arrive at least 15 minutes before your scheduled time. Bring your admission slip and any required documents.</p>
            
            <p>If you have any questions, please contact the admissions office.</p>
            
            <p>Best regards,<br>
            CNHS Admissions Team</p>
        </div>
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>