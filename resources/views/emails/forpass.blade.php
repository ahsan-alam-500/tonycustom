<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Password Reset OTP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f5f7fa;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #333333;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .header {
            background: linear-gradient(135deg, #3CA9FF, #0077CC);
            color: #ffffff;
            text-align: center;
            padding: 30px 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
        }

        .content {
            padding: 30px 20px;
            text-align: center;
        }

        .content p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .otp-box {
            background: #f0f9ff;
            border: 2px dashed #3CA9FF;
            border-radius: 10px;
            padding: 20px;
            margin: 20px auto;
            max-width: 280px;
        }

        .otp-code {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #0077CC;
        }

        .btn {
            display: inline-block;
            background: #3CA9FF;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            margin-top: 20px;
        }

        .btn:hover {
            background: #0077CC;
        }

        .footer {
            text-align: center;
            font-size: 13px;
            color: #777777;
            padding: 20px;
            border-top: 1px solid #e6e6e6;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Password Reset Request</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <p>Hello 👋,<br>
                We received a request to reset your password. Use the OTP code below to proceed:</p>

            <!-- OTP Box -->
            <div class="otp-box">
                <div class="otp-code">{{ $otp }}</div>
            </div>

            <p>This OTP is valid for <strong>5 minutes</strong>. Do not share this code with anyone.</p>
        </div>

        <!-- Footer -->
        <div class="footer">
            If you didn’t request this, please ignore this email.<br>
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>
</body>

</html>
