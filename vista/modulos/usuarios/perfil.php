<?php include("vista/includes/header_materialize.php"); ?>

<?php
// Obtener ID del usuario actual desde la sesión
$idUsuario = $_SESSION['user_id'] ?? 0;
$rolesNombresSession = $_SESSION['roles_nombres'] ?? [];
$isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombresSession, true);

$usuarioCtrl = new UsuariosController();
$usuario = $idUsuario > 0 ? $usuarioCtrl->verUsuario($idUsuario) : null;
$rolesDisponibles = UsuariosController::obtenerRolesDisponibles();

// Obtener roles actuales del usuario (multi-rol)
require_once __DIR__ . '/../../../modelo/usuario.php';
$um = new UsuarioModel();
$rolesUsuario = $idUsuario > 0 ? $um->obtenerRolesDeUsuario($idUsuario) : ['ids' => [], 'nombres' => []];
$rolesUsuarioIds = $rolesUsuario['ids'] ?? [];
$nombresRoles = $rolesUsuario['nombres'] ?? [];

// Cargar países para el select
require_once __DIR__ . '/../../../controlador/pais.php';
$paisesCtrl = new PaisesController();
$paises = $paisesCtrl->listar();

// Colores para los roles
$roleColors = [
    'Administrador' => 'danger',
    'Proveedor' => 'primary',
    'Repartidor' => 'success',
    'Usuario' => 'secondary'
];
$roleIcons = [
    'Administrador' => 'shield-lock',
    'Proveedor' => 'building',
    'Repartidor' => 'truck',
    'Usuario' => 'person'
];

// Generar iniciales y color del avatar
$nombre = $usuario['nombre'] ?? 'U';
$partes = explode(' ', $nombre);
$iniciales = strtoupper(substr($partes[0], 0, 1) . (isset($partes[1]) ? substr($partes[1], 0, 1) : ''));
$colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe', '#43e97b', '#38f9d7', '#fa709a', '#fee140'];
$colorIndex = crc32($nombre) % count($colors);
$avatarColor = $colors[$colorIndex];

// Fechas
$fechaRegistro = isset($usuario['created_at']) ? date('d/m/Y', strtotime($usuario['created_at'])) : '—';
$diasRegistrado = isset($usuario['created_at']) ? floor((time() - strtotime($usuario['created_at'])) / 86400) : 0;
?>

<style>
.profile-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    overflow: hidden;
}
.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 3rem 2rem 5rem 2rem;
    text-align: center;
    color: white;
    position: relative;
}
.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 2.5rem;
    color: white;
    border: 5px solid rgba(255,255,255,0.3);
    margin: 0 auto 1rem auto;
    position: relative;
}
.profile-avatar-badge {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #38ef7d;
    border: 3px solid white;
    display: flex;
    align-items: center;
    justify-content: center;
}
.profile-name {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}
.profile-email {
    opacity: 0.9;
    font-size: 1rem;
}
.profile-roles {
    margin-top: 1rem;
}
.profile-roles .badge {
    font-size: 0.8rem;
    padding: 0.5em 1em;
    margin: 0 0.25rem;
}
.profile-stats {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.2);
}
.profile-stat {
    text-align: center;
}
.profile-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
}
.profile-stat-label {
    font-size: 0.8rem;
    opacity: 0.8;
}
.profile-body {
    margin-top: -3rem;
    padding: 0 2rem 2rem 2rem;
}
.form-section {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border: 1px solid #eee;
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
.input-icon-wrapper input {
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
}
.btn-save-profile {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 0.875rem 2.5rem;
    font-weight: 600;
    border-radius: 12px;
    font-size: 1rem;
}
.btn-save-profile:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
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
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if (!$usuario) : ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>Usuario no encontrado.
                </div>
            <?php else : ?>
            <div class="card profile-card">
                <div class="profile-header">
                    <div class="profile-avatar" style="background: <?= $avatarColor ?>">
                        <?= $iniciales ?>
                        <div class="profile-avatar-badge">
                            <i class="bi bi-check2 text-white" style="font-size: 0.8rem;"></i>
                        </div>
                    </div>
                    <div class="profile-name"><?= htmlspecialchars($usuario['nombre'] ?? '') ?></div>
                    <div class="profile-email">
                        <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($usuario['email'] ?? '') ?>
                    </div>
                    <div class="profile-roles">
                        <?php if (!empty($nombresRoles)): ?>
                            <?php foreach ($nombresRoles as $rol): 
                                $badgeColor = $roleColors[$rol] ?? 'secondary';
                                $badgeIcon = $roleIcons[$rol] ?? 'person';
                            ?>
                                <span class="badge bg-white text-<?= $badgeColor ?>">
                                    <i class="bi bi-<?= $badgeIcon ?> me-1"></i><?= htmlspecialchars($rol) ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="badge bg-white text-secondary">Sin rol asignado</span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-stats">
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?= $diasRegistrado ?></div>
                            <div class="profile-stat-label">Días registrado</div>
                        </div>
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?= $fechaRegistro ?></div>
                            <div class="profile-stat-label">Fecha de registro</div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-body">
                    <form method="POST" action="<?= RUTA_URL ?>usuarios/actualizarPerfil">
                        <?php 
                        require_once __DIR__ . '/../../../utils/csrf.php';
                        echo csrf_field(); 
                        ?>
                        
                        <!-- Información Personal -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-person-circle"></i>
                                Información Personal
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombre Completo</label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-person input-icon"></i>
                                        <input type="text" class="form-control" name="nombre" required 
                                               value="<?= htmlspecialchars($usuario['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Correo Electrónico</label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-envelope input-icon"></i>
                                        <input type="email" class="form-control" name="email" required 
                                               value="<?= htmlspecialchars($usuario['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Teléfono</label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-telephone input-icon"></i>
                                        <input type="text" class="form-control" name="telefono" 
                                               value="<?= htmlspecialchars($usuario['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">País</label>
                                    <select name="id_pais" class="form-control select2-searchable" data-placeholder="Seleccionar país...">
                                        <option value="">-- Seleccione País --</option>
                                        <?php foreach ($paises as $pais): ?>
                                            <option value="<?= $pais['id'] ?>" <?= (isset($usuario['id_pais']) && $usuario['id_pais'] == $pais['id']) ? 'selected' : '' ?>><?= htmlspecialchars($pais['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($isAdmin): ?>
                        <!-- Roles (solo admin) -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-person-gear"></i>
                                Mis Roles
                            </div>
                            <p class="text-muted small mb-3">Selecciona uno o más roles</p>
                            <div class="row g-3">
                                <?php foreach ($rolesDisponibles as $rolId => $nombreRol): 
                                    $color = $roleColors[$nombreRol] ?? 'secondary';
                                    $icon = $roleIcons[$nombreRol] ?? 'person';
                                    $isSelected = in_array((int)$rolId, $rolesUsuarioIds, true) || ((int)($usuario['id_rol'] ?? 0) === (int)$rolId && empty($rolesUsuarioIds));
                                ?>
                                <div class="col-md-6 col-lg-3">
                                    <label class="role-card d-block <?= $isSelected ? 'selected' : '' ?>" for="rol_<?= $rolId ?>">
                                        <input type="checkbox" name="roles[]" value="<?= (int)$rolId ?>" id="rol_<?= $rolId ?>" <?= $isSelected ? 'checked' : '' ?>>
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
                        <?php endif; ?>
                        
                        <!-- Seguridad -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-shield-lock"></i>
                                Cambiar Contraseña
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Nueva Contraseña</label>
                                    <div class="input-icon-wrapper password-wrapper">
                                        <i class="bi bi-lock input-icon"></i>
                                        <input type="password" class="form-control" name="contrasena" id="contrasena"
                                               placeholder="Dejar vacío para no cambiar" style="padding-right: 40px;">
                                        <button type="button" class="password-toggle" onclick="togglePassword()">
                                            <i class="bi bi-eye" id="toggleIcon"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Ingresa una nueva contraseña solo si deseas cambiarla</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botones -->
                        <div class="text-center pt-2">
                            <button type="submit" class="btn btn-primary btn-save-profile">
                                <i class="bi bi-check-lg me-2"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include("vista/includes/footer_materialize.php"); ?>

<script>
function togglePassword() {
    const password = document.getElementById('contrasena');
    const icon = document.getElementById('toggleIcon');
    if (password.type === 'password') {
        password.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        password.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

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
