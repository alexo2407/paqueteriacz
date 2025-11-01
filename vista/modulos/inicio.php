<?php include "vista/includes/headerlogin.php"; ?>

<div class="container login">

<div class="login-card text-center">
    <i class="fas fa-user-circle mb-3"></i>
    <h3 class="mb-4">Inicio de Sesión</h3>
    <form id="loginForm" method="POST" action="<?= RUTA_URL ?>?enlace=login">
        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fas fa-envelope"></i>
                </span>
                <input id="email" name="email" type="email" class="form-control border-start-0" placeholder="Correo Electrónico" required>
            </div>
        </div>
        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fas fa-lock"></i>
                </span>
                <input id="password" name="password" type="password" class="form-control border-start-0" placeholder="Contraseña" required>
            </div>
        </div>
        <button type="submit" class="btn btn-login w-100">Iniciar Sesión</button>
    </form>
    <div class="mt-3 text-danger">
        <?php
        if (session_status() == PHP_SESSION_NONE) session_start();
        if (isset($_SESSION['login_error'])) {
            echo htmlspecialchars($_SESSION['login_error']);
            unset($_SESSION['login_error']);
        }
        ?>
    </div>
    <div class="mt-3">
        <small><a href="#" class="text-decoration-none">¿Olvidaste tu contraseña?</a></small>
    </div>
</div>
</div>

<?php include "vista/includes/footer.php" ?>