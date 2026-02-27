<?php include "vista/includes/headerlogin.php"; ?>

<div class="container login">

<div class="login-card text-center">
    <i class="bi bi-envelope-check mb-3" style="font-size: 4rem; color: var(--bs-primary);"></i>
    <h3 class="mb-4">Recuperar Contraseña</h3>
    <p class="text-muted mb-4">Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
    
    <form method="POST" action="<?= RUTA_URL ?>recuperar-password">
        <?php 
        require_once __DIR__ . '/../../utils/csrf.php';
        echo csrf_field(); 
        ?>
        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-envelope-fill text-primary"></i>
                </span>
                <input id="email" name="email" type="email" class="form-control border-start-0" placeholder="Correo Electrónico" required autofocus>
            </div>
        </div>
        <button type="submit" class="btn btn-login w-100 mb-3">Enviar Enlace de Recuperación</button>
    </form>
    
    <div class="mt-3">
        <small><a href="<?= RUTA_URL ?>login" class="text-decoration-none">← Volver al inicio de sesión</a></small>
    </div>
</div>
</div>

<?php include "vista/includes/footer_materialize.php" ?>
