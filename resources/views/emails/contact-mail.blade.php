<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>New Contact Message</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .email-wrapper {
            width: 100%;
            padding: 40px 0;
            background-color: #f4f6f9;
        }

        .email-content {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .email-header {
            background: #3CA9FF;
            padding: 20px;
            text-align: center;
            color: #fff;
        }

        .email-header h1 {
            margin: 0;
            font-size: 22px;
        }

        .email-body {
            padding: 30px;
        }

        .email-body h2 {
            margin-top: 0;
            font-size: 20px;
            color: #3CA9FF;
        }

        .email-body p {
            line-height: 1.6;
            margin: 10px 0;
        }

        .info-box {
            margin: 20px 0;
            padding: 15px;
            background: #f9fafc;
            border-left: 4px solid #3CA9FF;
        }

        .email-footer {
            text-align: center;
            padding: 15px;
            font-size: 13px;
            color: #999;
            background: #fafafa;
        }
    </style>
</head>

<body>
    <div class="email-wrapper">
        <div class="email-content">
            <!-- Header -->
            <div class="email-header">
                <h1>📩 New Contact Message</h1>
            </div>

            <!-- Body -->
            <div class="email-body">
                <h2>Hello Admin,</h2>
                <p>You have received a new contact form submission. Here are the details:</p>

                <div class="info-box">
                    <p><strong>Name:</strong> {{ $name }}</p>
                    <p><strong>Email:</strong> {{ $email }}</p>
                    <p><strong>Subject:</strong> {{ $subject }}</p>
                </div>

                <p><strong>Message:</strong></p>
                <p>{{ $usermessage }}</p>
            </div>

            <!-- Footer -->
            <div class="email-footer">
                <p>This email was generated automatically from your website contact form.</p>
            </div>
        </div>
    </div>
</body>

</html>
