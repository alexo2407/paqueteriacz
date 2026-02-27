<?php 
include "vista/includes/headerlogin.php";
require_once __DIR__ . '/../../controlador/password_reset.php';

// Obtener token de la URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// Validar token
$controller = new PasswordResetController();
$data = $controller->mostrarFormularioReset($token);

// Si llegamos aquí, el token es válido
?>

<div class="container login">

<div class="login-card text-center">
    <i class="bi bi-shield-lock mb-3" style="font-size: 4rem; color: var(--bs-primary);"></i>
    <h3 class="mb-4">Nueva Contraseña</h3>
    <p class="text-muted mb-4">Ingresa tu nueva contraseña para <strong><?= htmlspecialchars($data['email']) ?></strong></p>
    
    <form method="POST" action="<?= RUTA_URL ?>reset-password" onsubmit="return validarPassword()">
        <?php 
        require_once __DIR__ . '/../../utils/csrf.php';
        echo csrf_field(); 
        ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($data['token']) ?>">
        
        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-lock-fill text-primary"></i>
                </span>
                <input id="password" name="password" type="password" class="form-control border-start-0" placeholder="Nueva Contraseña" required minlength="6" autofocus>
            </div>
            <small class="form-text text-muted">Mínimo 6 caracteres</small>
        </div>
        
        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-lock-check-fill text-primary"></i>
                </span>
                <input id="password_confirm" name="password_confirm" type="password" class="form-control border-start-0" placeholder="Confirmar Contraseña" required minlength="6">
            </div>
            <div id="password-match-message" class="form-text"></div>
        </div>
        
        <button type="submit" class="btn btn-login w-100" id="submit-btn">Restablecer Contraseña</button>
    </form>
    
    <div class="mt-3">
        <small><a href="<?= RUTA_URL ?>login" class="text-decoration-none">← Volver al inicio de sesión</a></small>
    </div>
</div>
</div>

<script>
// Validación en tiempo real de coincidencia de contraseñas
const password = document.getElementById('password');
const passwordConfirm = document.getElementById('password_confirm');
const message = document.getElementById('password-match-message');
const submitBtn = document.getElementById('submit-btn');

function checkPasswordMatch() {
    if (passwordConfirm.value === '') {
        message.textContent = '';
        message.className = 'form-text';
        return;
    }
    
    if (password.value === passwordConfirm.value) {
        message.textContent = '✓ Las contraseñas coinciden';
        message.className = 'form-text text-success';
    } else {
        message.textContent = '✗ Las contraseñas no coinciden';
        message.className = 'form-text text-danger';
    }
}

password.addEventListener('input', checkPasswordMatch);
passwordConfirm.addEventListener('input', checkPasswordMatch);

function validarPassword() {
    if (password.value !== passwordConfirm.value) {
        alert('Las contraseñas no coinciden');
        return false;
    }
    if (password.value.length < 6) {
        alert('La contraseña debe tener al menos 6 caracteres');
        return false;
    }
    return true;
}
</script>

<?php include "vista/includes/footer_materialize.php" ?>
