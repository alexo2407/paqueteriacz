<?php include("vista/includes/header_materialize.php"); ?>

<?php
$params = isset($parametros) ? $parametros : [];
$idUsuario = isset($params[0]) ? (int) $params[0] : 0;

$usuarioCtrl = new UsuariosController();
$usuario = $idUsuario > 0 ? $usuarioCtrl->verUsuario($idUsuario) : null;
$rolesDisponibles = UsuariosController::obtenerRolesDisponibles();

// Obtener roles actuales del usuario (multi-rol)
require_once __DIR__ . '/../../../modelo/usuario.php';
$um = new UsuarioModel();
$rolesUsuario = $idUsuario > 0 ? $um->obtenerRolesDeUsuario($idUsuario) : ['ids' => [], 'nombres' => []];
$rolesUsuarioIds = $rolesUsuario['ids'] ?? [];

// Cargar países para el select
require_once __DIR__ . '/../../../controlador/pais.php';
$paisesCtrl = new PaisesController();
$paises = $paisesCtrl->listar();

// Colores e iconos para los roles
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

// Fecha de registro
$fechaRegistro = isset($usuario['created_at']) ? date('d/m/Y H:i', strtotime($usuario['created_at'])) : '—';
?>

<style>
.edit-user-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.edit-user-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
}
.user-avatar-lg {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.75rem;
    color: white;
    border: 4px solid rgba(255,255,255,0.3);
}
.user-header-info h3 {
    margin: 0 0 5px 0;
    font-weight: 600;
}
.user-header-info p {
    margin: 0;
    opacity: 0.85;
}
.user-meta {
    display: flex;
    gap: 1.5rem;
    margin-top: 0.75rem;
}
.user-meta-item {
    font-size: 0.85rem;
    opacity: 0.9;
}
.user-meta-item i {
    margin-right: 5px;
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
.input-icon-wrapper input {
    padding-left: 40px;
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 0.75rem 2rem;
    font-weight: 600;
    border-radius: 10px;
}
.btn-submit-user:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <?php if (!$usuario) : ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>Usuario no encontrado.
                </div>
                <a href="<?= RUTA_URL ?>usuarios/listar" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Volver al listado
                </a>
            <?php else : ?>
            <div class="card edit-user-card">
                <div class="edit-user-header">
                    <div class="d-flex align-items-center gap-4">
                        <div class="user-avatar-lg" style="background: <?= $avatarColor ?>">
                            <?= $iniciales ?>
                        </div>
                        <div class="user-header-info">
                            <h3><?= htmlspecialchars($usuario['nombre'] ?? '') ?></h3>
                            <p><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($usuario['email'] ?? '') ?></p>
                            <div class="user-meta">
                                <div class="user-meta-item">
                                    <i class="bi bi-calendar3"></i>Registrado: <?= $fechaRegistro ?>
                                </div>
                                <div class="user-meta-item">
                                    <i class="bi bi-hash"></i>ID: <?= $idUsuario ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <form method="POST" action="<?= RUTA_URL ?>usuarios/actualizar/<?= $idUsuario; ?>">
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
                                               value="<?= htmlspecialchars($usuario['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
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
                        
                        <!-- Roles -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-person-gear"></i>
                                Roles y Permisos
                            </div>
                            <p class="text-muted small mb-3">Selecciona uno o más roles para el usuario</p>
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
                            <div class="form-text mt-2">El primer rol marcado se usará como rol principal.</div>
                        </div>
                        
                        <!-- Seguridad -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-shield-lock"></i>
                                Seguridad
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nueva Contraseña</label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-lock input-icon"></i>
                                        <input type="password" class="form-control" name="contrasena" 
                                               placeholder="Dejar vacío para mantener la actual">
                                    </div>
                                    <div class="form-text">Solo ingresa una contraseña si deseas cambiarla</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botones -->
                        <div class="d-flex justify-content-between align-items-center pt-3">
                            <a href="<?= RUTA_URL ?>usuarios/listar" class="btn btn-outline-secondary px-4">
                                <i class="bi bi-arrow-left me-1"></i>Volver al listado
                            </a>
                            <button type="submit" class="btn btn-primary btn-submit-user">
                                <i class="bi bi-check-lg me-1"></i>Guardar Cambios
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
