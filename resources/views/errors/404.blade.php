<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>404 - Halaman Tidak Ditemukan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>

    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: #f8fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }

        .wrapper {
            max-width: 600px;
            padding: 20px;
        }

        h1 {
            font-size: 42px;
            margin-top: 20px;
        }

        p {
            opacity: 0.7;
            margin-top: 10px;
        }

        .btn {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            border-radius: 10px;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>

<div class="wrapper">

    <lottie-player
        src="https://assets9.lottiefiles.com/packages/lf20_tno6cg2w.json"
        background="transparent"
        speed="1"
        style="width:300px;height:300px;margin:auto;"
        loop
        autoplay>
    </lottie-player>

    <h1>404 - Page Not Found</h1>
    <p>Halaman yang lo cari gak ada.<br>Mungkin salah ketik atau sudah dihapus.</p>

    <a href="/" class="btn">Kembali ke Home</a>

</div>

</body>
</html>
