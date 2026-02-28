<?php include "vista/includes/headerlogin.php"; ?>

<div class="login-wrapper">
    <div class="login-card">

        <!-- Logo -->
        <div class="login-logo text-center d-flex align-items-center justify-content-center gap-3 mb-4">
            <!-- Icono (Sin círculo) -->
            <div style="flex-shrink: 0;">
                <img src="<?= RUTA_URL ?>vista/img/icono-logo.png" alt="Icono" class="img-fluid" style="height: 65px; width: auto; filter: brightness(0) invert(1); object-fit: contain;">
            </div>
            <!-- Textos -->
            <div class="text-start d-flex flex-column" style="line-height: 1;">
                <span style="font-size: 2rem; font-weight: 900; text-transform: uppercase; font-style: italic; letter-spacing: -1px; color: #fff;">RutaEx-Latam</span>
                <span style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.3em; color: rgba(255,255,255,0.9); margin-top: 2px;">Pan-Latam Logistics</span>
            </div>
        </div>

        <p style="color:rgba(255,255,255,.5); font-size:.87rem; text-align:center; margin-bottom:1.5rem;">
            Ingresa tu correo electrónico y recibirás un enlace para restablecer tu contraseña.
        </p>

        <!-- Formulario -->
        <form method="POST" action="<?= RUTA_URL ?>recuperar-password">
            <?php
            require_once __DIR__ . '/../../utils/csrf.php';
            echo csrf_field();
            ?>

            <div class="mb-4">
                <label class="login-label" for="email">Correo Electrónico</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text">
                        <i class="bi bi-envelope-fill"></i>
                    </span>
                    <input id="email" name="email" type="email"
                           class="form-control"
                           placeholder="usuario@ejemplo.com"
                           required autofocus autocomplete="email">
                </div>
            </div>

            <button type="submit" class="btn btn-login w-100 mb-3"
                    style="background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%); box-shadow: 0 6px 20px rgba(245,158,11,.3);">
                <i class="bi bi-send-fill me-2"></i>Enviar Enlace
            </button>

            <div class="text-center">
                <a href="<?= RUTA_URL ?>login" class="login-link">
                    <i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión
                </a>
            </div>
        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
