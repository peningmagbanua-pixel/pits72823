<!DOCTYPE html>
<html>
<head>
    <title>Account Created</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <h2 style="color: #2c3e50;">Welcome to CNHS Online Application System</h2>

        <p>Hello <strong>{{ $applicant->firstname }} {{ $applicant->lastname }}</strong>,</p>

        <p>Your account has been successfully created. You can now log in and continue with your application.</p>

        <h3 style="color: #2c3e50; margin-top: 25px;">Account Credentials</h3>
        <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #3498db; margin: 15px 0;">
            <p><strong>Email:</strong> {{ $applicant->email }}</p>
            <p><strong>Temporary Password:</strong> {{ $plainPassword }}</p>
        </div>

        <h3 style="color: #2c3e50; margin-top: 25px;">Next Steps</h3>
        <p>Click the button below to access the login page:</p>
        <div style="text-align: center; margin: 25px 0;">
            <a href="{{ $loginUrl }}" style="display: inline-block; background-color: #3498db; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">Login to System</a>
        </div>
        <p style="text-align: center; color: #666; font-size: 12px;">Or copy this link: <a href="{{ $loginUrl }}" style="color: #3498db;">{{ $loginUrl }}</a></p>

        <h3 style="color: #2c3e50; margin-top: 25px;">Important Security Notes</h3>
        <ul>
            <li>Keep your credentials confidential</li>
            <li>Never share your password with anyone</li>
            <li>Change your password on first login</li>
            <li>If you didn't request this account, please contact the administrator</li>
        </ul>

        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">

        <p style="color: #666; font-size: 12px;">
            If you have any questions or need assistance, please contact the CNHS Support Team.<br><br>
            Best regards,<br>
            <strong>CNHS Online Application System</strong>
        </p>
    </div>
</body>
</html>
