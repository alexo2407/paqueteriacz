
<?php include("vista/includes/header_materialize.php") ?>

<?php
$usaDataTables = true;
$listarUsuarios = new UsuariosController();
$resultadoUsuarios = $listarUsuarios->mostrarUsuariosController();

// Obtener roles de cada usuario
require_once __DIR__ . '/../../../modelo/usuario.php';
$um = new UsuarioModel();

// Cargar países para mostrar nombres
require_once __DIR__ . '/../../../controlador/pais.php';
$paisesCtrl = new PaisesController();
$paisesLista = $paisesCtrl->listar();
$paisesMap = [];
foreach ($paisesLista as $p) {
    $paisesMap[$p['id']] = $p['nombre'];
}

// Contar estadísticas
$totalUsuarios = count($resultadoUsuarios);
$usuariosActivos = 0;

// Filtrado por rol si viene en la URL
$rolFiltro = $_GET['rol'] ?? null;
if ($rolFiltro) {
    $resultadoUsuarios = array_filter($resultadoUsuarios, function($u) use ($rolFiltro, $um) {
        $roles = $um->obtenerRolesDeUsuario($u['id']);
        return in_array($rolFiltro, $roles['nombres'] ?? []);
    });
}

foreach ($resultadoUsuarios as $u) {
    if (isset($u['activo']) && $u['activo']) $usuariosActivos++;
}

// Colores para los badges de roles
$roleColors = [
    'Administrador' => 'danger',
    'Proveedor' => 'primary',
    'Repartidor' => 'success',
    'Usuario' => 'secondary'
];
?>

<style>
.user-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.user-card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
}
.user-card-header h3 {
    margin: 0;
    font-weight: 600;
}
.stat-box {
    background: rgba(255,255,255,0.15);
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
    backdrop-filter: blur(10px);
}
.stat-box .stat-number {
    font-size: 1.75rem;
    font-weight: 700;
}
.stat-box .stat-label {
    font-size: 0.8rem;
    opacity: 0.9;
}
.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.8rem;
    color: white;
    flex-shrink: 0;
}
.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-name {
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 1px;
    font-size: 0.9rem;
}
.user-email {
    font-size: 0.8rem;
    color: #666;
}
.badge-role {
    font-size: 0.7rem;
    padding: 0.35em 0.65em;
    font-weight: 500;
}
.status-badge {
    font-size: 0.75rem;
    padding: 0.4em 0.8em;
}
.btn-icon {
    width: 34px;
    height: 34px;
    padding: 0 !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
    color: #ffffff !important;
    border: none;
}
.btn-icon i {
    font-size: 14px !important;
    line-height: 1;
    color: #ffffff !important;
}
.btn-icon:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    color: #ffffff !important;
}
#tblUsuarios tbody tr {
    transition: all 0.2s ease;
}
#tblUsuarios tbody tr:hover {
    background-color: #f8f9ff;
}
.table > :not(caption) > * > * {
    padding: 0.65rem 0.5rem;
    vertical-align: middle;
}
</style>

<div class="container-fluid py-3">
    <div class="card user-card">
        <div class="user-card-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-people-fill fs-3"></i>
                        </div>
                        <div>
                            <h3><?= $rolFiltro ? "Gestión de " . htmlspecialchars($rolFiltro) . "s" : "Gestión de Usuarios" ?></h3>
                            <p class="mb-0 opacity-75"><?= $rolFiltro ? "Listado filtrado por rol: " . htmlspecialchars($rolFiltro) : "Administra los usuarios del sistema" ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <div class="stat-number"><?= $totalUsuarios ?></div>
                        <div class="stat-label">Total Usuarios</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <div class="stat-number"><?= $usuariosActivos ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <span class="text-muted">Mostrando <?= count($resultadoUsuarios) ?> <?= $rolFiltro ? htmlspecialchars($rolFiltro) . "s" : "usuarios" ?> registrados</span>
                    <?php if ($rolFiltro): ?>
                        <a href="<?= RUTA_URL ?>usuarios/listar" class="btn btn-sm btn-outline-secondary ms-2">
                            <i class="bi bi-x-circle"></i> Quitar Filtro
                        </a>
                    <?php endif; ?>
                </div>
                <a href="<?=RUTA_URL?>usuarios/crear" class="btn btn-primary px-4">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Usuario
                </a>
            </div>
            
            <div class="table-responsive">
                <table id="tblUsuarios" class="table table-hover" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>País</th>
                            <th>Roles</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($resultadoUsuarios as $usuario) :
                            $timestampCreacion = !empty($usuario['fecha_creacion']) ? strtotime($usuario['fecha_creacion']) : (!empty($usuario['created_at']) ? strtotime($usuario['created_at']) : null);
                            $fechaCreacion = $timestampCreacion ? date('d/m/Y', $timestampCreacion) : '—';
                            
                            // Obtener roles del usuario
                            $rolesUsuario = $um->obtenerRolesDeUsuario($usuario['id']);
                            $nombresRoles = $rolesUsuario['nombres'] ?? [];
                            
                            // Generar iniciales y color del avatar
                            $nombre = $usuario['nombre'] ?? 'U';
                            $partes = explode(' ', $nombre);
                            $iniciales = strtoupper(substr($partes[0], 0, 1) . (isset($partes[1]) ? substr($partes[1], 0, 1) : ''));
                            
                            // Color basado en el hash del nombre
                            $colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe', '#43e97b', '#38f9d7', '#fa709a', '#fee140'];
                            $colorIndex = crc32($nombre) % count($colors);
                            $avatarColor = $colors[$colorIndex];
                            
                            // Estado
                            $activo = isset($usuario['activo']) ? $usuario['activo'] : true;
                            
                            // País
                            $paisNombre = isset($usuario['id_pais']) && isset($paisesMap[$usuario['id_pais']]) ? $paisesMap[$usuario['id_pais']] : '—';
                        ?>
                        <tr>
                            <td class="text-muted small fw-semibold">#<?= $usuario['id'] ?></td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar" style="background: <?= $avatarColor ?>">
                                        <?= $iniciales ?>
                                    </div>
                                    <div>
                                        <div class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                                        <div class="user-email"><?= htmlspecialchars($usuario['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($paisNombre !== '—'): ?>
                                    <i class="bi bi-geo-alt text-muted me-1"></i>
                                    <?= htmlspecialchars($paisNombre) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($nombresRoles)): ?>
                                    <?php foreach ($nombresRoles as $rol): 
                                        $badgeColor = $roleColors[$rol] ?? 'secondary';
                                    ?>
                                        <span class="badge bg-<?= $badgeColor ?> badge-role me-1"><?= htmlspecialchars($rol) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary badge-role">Sin rol</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($activo): ?>
                                    <span class="badge rounded-pill bg-success p-1">
                                        <i class="bi bi-check-lg fs-6"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-danger p-1">
                                        <i class="bi bi-x-lg fs-6"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="<?= RUTA_URL ?>usuarios/editar/<?= $usuario['id'] ?>" 
                                       class="btn btn-primary btn-icon" 
                                       title="Editar usuario">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <?php 
                                    // Solo mostrar eliminar si es admin y no es el usuario actual
                                    $rolesSession = $_SESSION['roles_nombres'] ?? [];
                                    $isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesSession, true);
                                    $esUsuarioActual = ($_SESSION['user_id'] ?? 0) == $usuario['id'];
                                    if ($isAdmin && !$esUsuarioActual): 
                                    ?>
                                    <button type="button" 
                                            class="btn btn-danger btn-icon btn-eliminar" 
                                            data-id="<?= $usuario['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($usuario['nombre']) ?>"
                                            title="Eliminar usuario">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>       
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>Confirmar eliminación
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="bi bi-person-x text-danger" style="font-size: 3rem;"></i>
                </div>
                <p class="mb-1">¿Estás seguro de eliminar al usuario</p>
                <p class="fw-bold fs-5" id="nombreUsuarioEliminar"></p>
                <p class="text-muted small">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                <form id="formEliminar" method="POST" action="" style="display: inline;">
                    <?php 
                    require_once __DIR__ . '/../../../utils/csrf.php';
                    echo csrf_field(); 
                    ?>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-trash me-1"></i>Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer_materialize.php") ?>

<script>
$(document).ready(function () {
    $('#tblUsuarios').DataTable({
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
        },
        columnDefs: [
            { orderable: false, targets: -1 }
        ],
        order: [[0, 'desc']]
    });
    
    // Modal de eliminación
    $('.btn-eliminar').on('click', function() {
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');
        $('#nombreUsuarioEliminar').text(nombre);
        $('#formEliminar').attr('action', '<?= RUTA_URL ?>usuarios/eliminar/' + id);
        $('#modalEliminar').modal('show');
    });
});
</script>