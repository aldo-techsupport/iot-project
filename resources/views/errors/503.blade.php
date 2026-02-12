<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Mode</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Font --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    {{-- dotLottie Player --}}
    <script type="module" src="https://unpkg.com/@dotlottie/player-component@latest/dist/dotlottie-player.mjs"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: #f8fafc;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            overflow: hidden;
        }

        .wrapper {
            max-width: 600px;
            padding: 20px;
        }

        h1 {
            font-size: 36px;
            font-weight: 700;
            margin-top: 20px;
        }

        p {
            margin-top: 10px;
            opacity: 0.7;
        }

        .badge {
            margin-top: 20px;
            display: inline-block;
            padding: 8px 16px;
            background: #1e293b;
            border-radius: 999px;
            font-size: 14px;
            letter-spacing: 1px;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
    </style>
</head>
<body>

    <div class="wrapper">

        <dotlottie-player
            src="https://lottie.host/4fbfb311-40bc-4893-8657-776b45eacbf6/2cyQZmbyb4.lottie"
            background="transparent"
            speed="1"
            style="width: 320px; height: 320px; margin:auto;"
            loop
            autoplay>
        </dotlottie-player>

        <h1>System Upgrade in Progress</h1>

        <p>
            Lagi maintenance bentar.<br>
            Server lagi dipoles biar makin stabil.
        </p>

        @if(isset($exception) && $exception->getMessage())
            <div class="badge pulse">
                {{ $exception->getMessage() }}
            </div>
        @endif

    </div>

</body>
</html>
