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
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .email-header {
            background: linear-gradient(135deg, #3CA9FF, #006eff);
            padding: 25px;
            text-align: center;
            color: #fff;
        }

        .email-header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .email-body {
            padding: 30px;
        }

        .email-body h2 {
            margin-top: 0;
            font-size: 20px;
            font-weight: 600;
            color: #006eff;
        }

        .email-body p {
            line-height: 1.6;
            margin: 10px 0;
        }

        .info-box {
            margin: 20px 0;
            padding: 15px 20px;
            background: #f9fafc;
            border-left: 4px solid #3CA9FF;
            border-radius: 4px;
        }

        .info-box p {
            margin: 8px 0;
            font-size: 15px;
        }

        .email-footer {
            text-align: center;
            padding: 18px;
            font-size: 13px;
            color: #777;
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
                <p>You’ve received a new contact form submission. Details are below:</p>

                <div class="info-box">
                    <p><strong>Name:</strong> {{ $name }}</p>
                    <p><strong>Email:</strong> {{ $email }}</p>
                    <p><strong>Subject:</strong> {{ $sub }}</p>
                </div>

                <p><strong>Message:</strong></p>
                <p>{{ $mes }}</p>
            </div>

            <!-- Footer -->
            <div class="email-footer">
                <p>This email was generated automatically from your website contact form.</p>
            </div>
        </div>
    </div>
</body>

</html>
