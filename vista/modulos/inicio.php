<?php include "vista/includes/headerlogin.php"; ?>

<div class="login-wrapper">
    <div class="login-card">

        <!-- Logo -->
        <div class="login-logo">
            <div class="login-logo-icon">
                <i class="bi bi-box-seam"></i>
            </div>
            <h1 class="login-title">RutaEx-Latam</h1>
            <p class="login-subtitle">Gestión de paquetería y logística</p>
        </div>

        <!-- Formulario -->
        <form id="loginForm" method="POST" action="<?= RUTA_URL ?>?enlace=login">
            <?php
            require_once __DIR__ . '/../../utils/csrf.php';
            echo csrf_field();
            ?>

            <div class="mb-3">
                <label class="login-label" for="email">Correo Electrónico</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text">
                        <i class="bi bi-envelope-fill"></i>
                    </span>
                    <input id="email" name="email" type="email"
                           class="form-control"
                           placeholder="usuario@ejemplo.com" required autocomplete="email">
                </div>
            </div>

            <div class="mb-4">
                <label class="login-label" for="password">Contraseña</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock-fill"></i>
                    </span>
                    <input id="password" name="password" type="password"
                           class="form-control"
                           placeholder="••••••••" required autocomplete="current-password">
                </div>
            </div>

            <?php
            if (session_status() == PHP_SESSION_NONE) session_start();
            if (isset($_SESSION['login_error'])): ?>
            <div class="login-error mb-3">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?= htmlspecialchars($_SESSION['login_error']) ?>
                <?php unset($_SESSION['login_error']); ?>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-login w-100 mb-3">
                <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
            </button>

            <div class="text-center">
                <a href="<?= RUTA_URL ?>recuperar-password" class="login-link">
                    <i class="bi bi-key me-1"></i>¿Olvidaste tu contraseña?
                </a>
            </div>
        </form>

    </div>
</div>

<!-- Scripts mínimos -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>