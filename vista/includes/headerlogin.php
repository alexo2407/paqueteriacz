<?php
/**
 * headerlogin.php
 * Header standalone para la página de login / recuperación de contraseña.
 * Paleta oficial: #061C4C | #0B4EA2 | #FF8A00
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión · RutaEx Latam</title>

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?= RUTA_URL ?>apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= RUTA_URL ?>favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= RUTA_URL ?>favicon-16x16.png">
    <link rel="manifest" href="<?= RUTA_URL ?>site.webmanifest">

    <!-- Google Fonts: Montserrat + Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,700;0,900;1,900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <style>
        :root {
            --brand-navy:   #061C4C;
            --brand-blue:   #0B4EA2;
            --brand-orange: #FF8A00;
            --brand-gray:   #EEF2F6;
        }

        * { box-sizing: border-box; }

        body.login-page {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--brand-navy);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Capa Vanta NET */
        #vanta-bg {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 0;
        }

        body.login-page::before,
        body.login-page::after { display: none; }

        /* Wrapper de la card */
        .login-wrapper {
            width: 100%;
            max-width: 440px;
            padding: 1.25rem;
            z-index: 1;
        }

        /* Card con glassmorphism sobre el navy */
        .login-card {
            background: rgba(255, 255, 255, .06);
            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
            border: 1px solid rgba(255, 255, 255, .13);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            box-shadow:
                0 25px 60px rgba(6, 28, 76, .55),
                0 0 0 1px rgba(255, 255, 255, .04),
                inset 0 1px 0 rgba(255,255,255,.08);
        }

        /* Logo / Header de la card */
        .login-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }

        .login-logo-img {
            height: 90px;
            width: auto;
            object-fit: contain;
            margin-bottom: .75rem;
            /* El logo tiene fondo blanco en el png, se usa con drop-shadow */
            filter: drop-shadow(0 4px 12px rgba(255,138,0,.35));
        }

        .login-tagline {
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .35em;
            color: var(--brand-orange);
            margin: 0;
        }

        /* Inputs */
        .login-input-group .input-group-text {
            background: rgba(255, 255, 255, .07);
            border: 1px solid rgba(255, 255, 255, .15);
            border-right: none;
            color: rgba(255, 255, 255, .6);
            border-radius: 10px 0 0 10px;
        }
        .login-input-group .form-control {
            background: rgba(255, 255, 255, .07);
            border: 1px solid rgba(255, 255, 255, .15);
            border-left: none;
            color: #fff;
            border-radius: 0 10px 10px 0;
            transition: background .2s, border-color .2s;
        }
        .login-input-group .form-control::placeholder { color: rgba(255, 255, 255, .35); }
        .login-input-group .form-control:focus {
            background: rgba(255, 255, 255, .12);
            border-color: rgba(255, 138, 0, .6);
            box-shadow: 0 0 0 3px rgba(255, 138, 0, .15);
            outline: none;
            color: #fff;
        }
        .login-input-group .input-group-text:has(+ .form-control:focus) {
            border-color: rgba(255, 138, 0, .6);
        }

        /* Botón de login */
        .btn-login {
            background: linear-gradient(135deg, var(--brand-orange) 0%, #d96d00 100%);
            border: none;
            color: #fff;
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: .95rem;
            padding: .75rem;
            border-radius: 10px;
            letter-spacing: .5px;
            text-transform: uppercase;
            transition: opacity .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 6px 20px rgba(255, 138, 0, .4);
        }
        .btn-login:hover {
            opacity: .92;
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(255, 138, 0, .5);
            color: #fff;
        }
        .btn-login:active { transform: translateY(0); }

        /* Links */
        .login-link {
            color: rgba(255, 255, 255, .5);
            font-size: .82rem;
            text-decoration: none;
            transition: color .2s;
        }
        .login-link:hover { color: var(--brand-orange); }

        /* Error */
        .login-error {
            background: rgba(244, 67, 54, .15);
            border: 1px solid rgba(244, 67, 54, .3);
            border-radius: 8px;
            color: #ff6b6b;
            font-size: .85rem;
            padding: .65rem 1rem;
        }

        /* Label */
        .login-label {
            display: block;
            font-size: .78rem;
            font-weight: 500;
            color: rgba(255, 255, 255, .55);
            margin-bottom: .35rem;
            letter-spacing: .3px;
        }

        /* Separador decorativo */
        .login-divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,.1);
            margin: 1.5rem 0;
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
            color: 0xFF8A00,         /* naranja acento */
            color2: 0x0B4EA2,        /* azul profundo */
            backgroundColor: 0x061C4C, /* navy profundo */
            points: 8.00,
            maxDistance: 22.00,
            spacing: 19.00
        });
    }
});
</script>

<!-- Detección automática de timezone -->
<script>
(function() {
    try {
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (tz) {
            fetch(RUTA_URL + 'api/utils/set_timezone.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ timezone: tz }),
                credentials: 'same-origin'
            });
        }
    } catch(e) { /* silencioso */ }
})();
</script>
