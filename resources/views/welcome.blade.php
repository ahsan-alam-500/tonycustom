<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forbidden Area</title>
    <link href="https://fonts.googleapis.com/css2?family=Anton&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: radial-gradient(circle at center, #0f0c29, #302b63, #24243e);
            color: #ff0000;
            font-family: 'Anton', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden;
        }

        .container {
            text-align: center;
            animation: pulse 2s infinite;
        }

        h1 {
            font-size: 6rem;
            text-shadow: 0 0 20px #ff0000, 0 0 40px #ff0000, 0 0 60px #ff0000;
            margin: 0;
        }

        p {
            font-size: 1.5rem;
            color: #fff;
            margin-top: 20px;
            text-shadow: 0 0 10px #000;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        /* Optional dramatic background animation */
        .background {
            position: absolute;
            width: 200%;
            height: 200%;
            background: repeating-radial-gradient(circle, rgba(255, 0, 0, 0.05) 0 2px, transparent 2px 4px);
            animation: rotate 10s linear infinite;
            z-index: 0;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .container {
            position: relative;
            z-index: 1;
        }
    </style>
</head>

<body>
    <div class="background"></div>
    <div class="container">
        <h1>FORBIDDEN AREA</h1>
        <p>Proceed at your own risk! Your system can be hacked</p>
    </div>
</body>

</html>
