<?php
/**
 * headerlogin.php
 * Header standalone para la página de login / recuperación de contraseña.
 * No incluye navbar ni sidebar — página full-screen con fondo gradiente.
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión · RutaEx-Latam</title>

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?= RUTA_URL ?>apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= RUTA_URL ?>favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= RUTA_URL ?>favicon-16x16.png">
    <link rel="manifest" href="<?= RUTA_URL ?>site.webmanifest">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <style>
        * { box-sizing: border-box; }

        body.login-page {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #1a1a2e;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Capa Vanta NET fija detrás de todo */
        #vanta-bg {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 0;
        }

        /* Quitar pseudo-elementos decorativos — Vanta los reemplaza */
        body.login-page::before,
        body.login-page::after { display: none; }

        /* Card principal */
        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 1.25rem;
            z-index: 1;
        }

        .login-card {
            background: rgba(255,255,255,.06);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 60px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.04);
        }

        /* Logo / Header de la card */
        .login-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }
        .login-logo-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #00d084, #0d6efd);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: #fff;
            margin-bottom: 1rem;
            box-shadow: 0 8px 24px rgba(0,208,132,.35);
        }
        .login-title {
            font-size: 1.45rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 4px;
            letter-spacing: -.3px;
        }
        .login-subtitle {
            font-size: .85rem;
            color: rgba(255,255,255,.5);
            margin: 0;
        }

        /* Inputs */
        .login-input-group .input-group-text {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.15);
            border-right: none;
            color: rgba(255,255,255,.6);
        }
        .login-input-group .form-control {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.15);
            border-left: none;
            color: #fff;
            transition: background .2s, border-color .2s;
        }
        .login-input-group .form-control::placeholder { color: rgba(255,255,255,.35); }
        .login-input-group .form-control:focus {
            background: rgba(255,255,255,.12);
            border-color: rgba(0,208,132,.5);
            box-shadow: 0 0 0 3px rgba(0,208,132,.15);
            outline: none;
            color: #fff;
        }
        .login-input-group .input-group-text {
            border-radius: 10px 0 0 10px;
        }
        .login-input-group .form-control {
            border-radius: 0 10px 10px 0;
        }

        /* Botón de login */
        .btn-login {
            background: linear-gradient(135deg, #00d084 0%, #0d6efd 100%);
            border: none;
            color: #fff;
            font-weight: 600;
            font-size: .95rem;
            padding: .75rem;
            border-radius: 10px;
            letter-spacing: .3px;
            transition: opacity .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 6px 20px rgba(0,208,132,.3);
        }
        .btn-login:hover {
            opacity: .9;
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(0,208,132,.4);
            color: #fff;
        }
        .btn-login:active { transform: translateY(0); }

        /* Links */
        .login-link {
            color: rgba(255,255,255,.5);
            font-size: .82rem;
            text-decoration: none;
            transition: color .2s;
        }
        .login-link:hover { color: #00d084; }

        /* Error */
        .login-error {
            background: rgba(244,67,54,.15);
            border: 1px solid rgba(244,67,54,.3);
            border-radius: 8px;
            color: #ff6b6b;
            font-size: .85rem;
            padding: .65rem 1rem;
        }

        /* Label flotante encima del input */
        .login-label {
            display: block;
            font-size: .78rem;
            font-weight: 500;
            color: rgba(255,255,255,.55);
            margin-bottom: .35rem;
            letter-spacing: .3px;
        }
    </style>
</head>
<body class="login-page">
<div id="vanta-bg"></div>
<script>const RUTA_URL = '<?= RUTA_URL ?>';</script>

<!-- Three.js + Vanta NET -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.net.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.VANTA) {
        VANTA.NET({
            el: '#vanta-bg',
            mouseControls: true,
            touchControls: true,
            gyroControls: false,
            minHeight: 200.00,
            minWidth: 200.00,
            scale: 1.00,
            scaleMobile: 1.00,
            color: 0x00d084,        /* verde acento */
            color2: 0x0d6efd,       /* azul secundario */
            backgroundColor: 0x1a1a2e, /* fondo navy */
            points: 9.00,
            maxDistance: 22.00,
            spacing: 18.00
        });
    }
});
</script>
