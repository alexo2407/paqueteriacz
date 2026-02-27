<?php
include("vista/includes/header_materialize.php");
require_once __DIR__ . '/../../../controlador/usuario.php';
require_once __DIR__ . '/../../../controlador/pais.php';

$paisesCtrl = new PaisesController();
$paises = $paisesCtrl->listar();

$roles = UsuariosController::obtenerRolesDisponibles();

// Colores para los roles
$roleColors = [
    'Administrador' => 'danger',
    'Proveedor' => 'primary',
    'Repartidor' => 'success',
    'Usuario' => 'secondary'
];

// Iconos para los roles
$roleIcons = [
    'Administrador' => 'shield-lock',
    'Proveedor' => 'building',
    'Repartidor' => 'truck',
    'Usuario' => 'person'
];
?>

<style>
.create-user-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.create-user-header {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    padding: 1.5rem 2rem;
}
.create-user-header h3 {
    margin: 0;
    font-weight: 600;
}
.form-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}
.form-section-title {
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.form-section-title i {
    color: #667eea;
}
.input-icon-wrapper {
    position: relative;
}
.input-icon-wrapper .input-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    z-index: 4;
}
.input-icon-wrapper input,
.input-icon-wrapper select {
    padding-left: 40px;
}
.password-wrapper {
    position: relative;
}
.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    z-index: 5;
    padding: 0;
}
.password-toggle:hover {
    color: #495057;
}
.password-strength {
    height: 4px;
    border-radius: 2px;
    margin-top: 8px;
    transition: all 0.3s ease;
}
.password-strength-text {
    font-size: 0.75rem;
    margin-top: 4px;
}
.role-card {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    background: white;
}
.role-card:hover {
    border-color: #667eea;
    background: #f8f9ff;
}
.role-card.selected {
    border-color: #667eea;
    background: #f0f3ff;
}
.role-card .role-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.5rem;
}
.role-card .role-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
}
.role-card .role-desc {
    font-size: 0.8rem;
    color: #6c757d;
}
.role-card input[type="checkbox"] {
    display: none;
}
.btn-submit-user {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    border: none;
    padding: 0.75rem 2rem;
    font-weight: 600;
    border-radius: 10px;
}
.btn-submit-user:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(17, 153, 142, 0.4);
}
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card create-user-card">
                <div class="create-user-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-person-plus-fill fs-3"></i>
                        </div>
                        <div>
                            <h3>Crear Nuevo Usuario</h3>
                            <p class="mb-0 opacity-75">Completa los datos para registrar un nuevo usuario</p>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <form method="post" id="formCrearUsuario">
                        <?php 
                        require_once __DIR__ . '/../../../utils/csrf.php';
                        echo csrf_field(); 
                        ?>
                        
                        <!-- Información Personal -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-person-badge"></i>
                                Información Personal
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-person input-icon"></i>
                                        <input type="text" class="form-control" name="nombre" required 
                                               placeholder="Ingrese nombre completo">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-envelope input-icon"></i>
                                        <input type="email" class="form-control" name="email" required 
                                               placeholder="ejemplo@correo.com">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Teléfono</label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-telephone input-icon"></i>
                                        <input type="text" class="form-control" name="telefono" 
                                               placeholder="Opcional">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">País</label>
                                    <select class="form-control select2-searchable" name="id_pais" data-placeholder="Buscar país...">
                                        <option value="">-- Seleccione País --</option>
                                        <?php foreach ($paises as $pais): ?>
                                            <option value="<?= $pais['id'] ?>"><?= htmlspecialchars($pais['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Seguridad -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-shield-lock"></i>
                                Seguridad
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                                    <div class="input-icon-wrapper password-wrapper">
                                        <i class="bi bi-lock input-icon"></i>
                                        <input type="password" class="form-control" name="password" id="password" 
                                               required placeholder="Ingrese contraseña" style="padding-right: 40px;">
                                        <button type="button" class="password-toggle" onclick="togglePassword()">
                                            <i class="bi bi-eye" id="toggleIcon"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength bg-secondary" id="passwordStrength"></div>
                                    <div class="password-strength-text text-muted" id="passwordStrengthText">
                                        Ingresa una contraseña segura
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Estado del Usuario</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" checked 
                                               style="width: 50px; height: 26px;">
                                        <label class="form-check-label ms-2" for="activo" style="padding-top: 3px;">
                                            <span class="badge bg-success" id="estadoLabel">Activo</span>
                                        </label>
                                    </div>
                                    <div class="form-text">Define si el usuario puede acceder al sistema</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Roles -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-person-gear"></i>
                                Roles y Permisos
                            </div>
                            <p class="text-muted small mb-3">Selecciona uno o más roles para el usuario</p>
                            <div class="row g-3">
                                <?php foreach ($roles as $idRol => $nombreRol): 
                                    $color = $roleColors[$nombreRol] ?? 'secondary';
                                    $icon = $roleIcons[$nombreRol] ?? 'person';
                                ?>
                                <div class="col-md-6 col-lg-3">
                                    <label class="role-card d-block" for="rol_<?= $idRol ?>">
                                        <input type="checkbox" name="roles[]" value="<?= $idRol ?>" id="rol_<?= $idRol ?>">
                                        <div class="role-icon bg-<?= $color ?>-subtle text-<?= $color ?>">
                                            <i class="bi bi-<?= $icon ?>"></i>
                                        </div>
                                        <div class="role-name"><?= htmlspecialchars($nombreRol) ?></div>
                                        <div class="role-desc">
                                            <?php
                                            switch($nombreRol) {
                                                case 'Administrador': echo 'Acceso completo'; break;
                                                case 'Proveedor': echo 'Gestión de productos'; break;
                                                case 'Repartidor': echo 'Gestión de entregas'; break;
                                                default: echo 'Acceso básico';
                                            }
                                            ?>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Botones -->
                        <div class="d-flex justify-content-end gap-3 pt-3">
                            <a href="<?= RUTA_URL ?>usuarios/listar" class="btn btn-outline-secondary px-4">
                                <i class="bi bi-x-lg me-1"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-success btn-submit-user">
                                <i class="bi bi-check-lg me-1"></i>Guardar Usuario
                            </button>
                        </div>
                        
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer_materialize.php"); ?>

<?php
// Procesar POST aquí, DESPUÉS del footer (donde ya cargó SweetAlert2)
$crearUsuario = new UsuariosController();
$crearUsuario->crearUsuarioController();
?>

<script>
// Toggle password visibility
function togglePassword() {
    const password = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    if (password.type === 'password') {
        password.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        password.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// Password strength indicator
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('passwordStrengthText');
    
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    const colors = ['bg-danger', 'bg-warning', 'bg-info', 'bg-primary', 'bg-success'];
    const texts = ['Muy débil', 'Débil', 'Regular', 'Fuerte', 'Muy fuerte'];
    const widths = ['20%', '40%', '60%', '80%', '100%'];
    
    const index = Math.min(strength, 4);
    strengthBar.className = 'password-strength ' + colors[index];
    strengthBar.style.width = widths[index];
    strengthText.textContent = password ? texts[index] : 'Ingresa una contraseña segura';
    strengthText.className = 'password-strength-text ' + (strength >= 3 ? 'text-success' : strength >= 2 ? 'text-warning' : 'text-danger');
});

// Estado toggle label
document.getElementById('activo').addEventListener('change', function() {
    const label = document.getElementById('estadoLabel');
    if (this.checked) {
        label.className = 'badge bg-success';
        label.textContent = 'Activo';
    } else {
        label.className = 'badge bg-danger';
        label.textContent = 'Inactivo';
    }
});

// Role card selection visual
document.querySelectorAll('.role-card input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            this.closest('.role-card').classList.add('selected');
        } else {
            this.closest('.role-card').classList.remove('selected');
        }
    });
});
</script>
