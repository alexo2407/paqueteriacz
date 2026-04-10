<?php
$usaDataTables = true;
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/auditoria.php';

start_secure_session();
require_login();

// Determinar rol del usuario
// NOTA LEGACY: ROL_NOMBRE_PROVEEDOR='Cliente', ROL_NOMBRE_CLIENTE='Proveedor' (invertidos en BD)
// isCliente() = TRUE para quien CREA pedidos (id_cliente en pedidos) = "Proveedor logístico"
// ROL_NOMBRE_PROVEEDOR en sesión = distribuidor (id_proveedor en pedidos)
$esAdmin     = isSuperAdmin();
$esProveedorLogistico = !$esAdmin && isCliente(); // Quien crea pedidos (id_cliente)
$esDistribuidor       = !$esAdmin && !$esProveedorLogistico && in_array(ROL_NOMBRE_PROVEEDOR, $_SESSION['roles_nombres'] ?? [], true);

// Si no es admin, ni proveedor logístico, ni distribuidor → denegar
if (!$esAdmin && !$esProveedorLogistico && !$esDistribuidor) {
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

// Para no-admins: configurar filtro por propietario
$userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
$filtroPropietario = null; // null = sin restricción (admin)

if ($esProveedorLogistico) {
    // isCliente()=TRUE = rol "Proveedor" en BD = distribuidor = id_proveedor en pedidos
    $filtroPropietario = ['campo' => 'id_proveedor', 'uid' => (int)$userId];
} elseif ($esDistribuidor) {
    $filtroPropietario = ['campo' => 'id_cliente', 'uid' => (int)$userId];
}

// Obtener filtros
$tablaFiltro = $_GET['tabla'] ?? '';
$accionFiltro = $_GET['accion'] ?? '';
$usuarioFiltro = $_GET['usuario'] ?? '';
$pedidoFiltro = isset($_GET['pedido']) ? trim($_GET['pedido']) : '';

// Resolver numero_orden -> id interno del pedido
$pedidoIdInterno = null;
$pedidoNoEncontrado = false;
if ($pedidoFiltro !== '') {
    try {
        require_once __DIR__ . '/../../../modelo/conexion.php';
        $dbRes = (new Conexion())->conectar();
        
        // Buscar pedido verificando que pertenece al usuario
        if ($filtroPropietario) {
            $campo = $filtroPropietario['campo'];
            $stmtPed = $dbRes->prepare("SELECT id FROM pedidos WHERE numero_orden = :n AND $campo = :uid LIMIT 1");
            $stmtPed->execute([':n' => (int)$pedidoFiltro, ':uid' => $filtroPropietario['uid']]);
        } else {
            $stmtPed = $dbRes->prepare('SELECT id FROM pedidos WHERE numero_orden = :n LIMIT 1');
            $stmtPed->execute([':n' => (int)$pedidoFiltro]);
        }
        
        $rowPed = $stmtPed->fetch(PDO::FETCH_ASSOC);
        if ($rowPed) {
            $pedidoIdInterno = (int)$rowPed['id'];
        } else {
            $pedidoNoEncontrado = true;
        }
    } catch (Exception $e) {
        $pedidoNoEncontrado = true;
    }
}

// Por defecto: últimos 7 días
$fechaHoy = date('Y-m-d');
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fechaFin = $_GET['fecha_fin'] ?? $fechaHoy;

// Construir filtros
$filtros = [];

// Si se filtra por pedido: buscar en todas las tablas y sin restricción de fechas
if ($pedidoFiltro !== '' && $pedidoIdInterno !== null) {
    $filtros['id_registro'] = $pedidoIdInterno;
    $filtros['tabla'] = 'pedidos';
    // Aplicar fechas solo si el usuario las cambió manualmente
    if (isset($_GET['fecha_inicio'])) $filtros['fecha_inicio'] = $fechaInicio;
    if (isset($_GET['fecha_fin']))   $filtros['fecha_fin']    = $fechaFin;
} else {
    $filtros['fecha_inicio'] = $fechaInicio;
    $filtros['fecha_fin']    = $fechaFin;
}

if ($tablaFiltro && $pedidoFiltro === '') $filtros['tabla'] = $tablaFiltro;
if ($accionFiltro) $filtros['accion'] = $accionFiltro;
if ($usuarioFiltro) $filtros['id_usuario'] = (int)$usuarioFiltro;

// Para no-admins: forzar tabla=pedidos y filtrar solo sus pedidos via subquery
if ($filtroPropietario !== null) {
    $filtros['tabla'] = 'pedidos';
    $filtros['propietario'] = $filtroPropietario;
}

// Obtener registros de auditoría
$registros = AuditoriaModel::listar($filtros, 500);

// Obtener tablas y usuarios únicos para filtros
$tablasUnicas = AuditoriaModel::obtenerTablasUnicas();
$usuarios = AuditoriaModel::obtenerUsuariosConAuditoria();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Auditoría - App RutaEx-Latam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        .json-viewer {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
        .badge-crear { background-color: #198754; }
        .badge-actualizar { background-color: #0d6efd; }
        .badge-eliminar { background-color: #dc3545; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-clock-history"></i> Historial de Auditoría</h2>
            <p class="text-muted mb-0">
                <?php if ($esAdmin): ?>
                    Registro de cambios realizados en el sistema
                <?php else: ?>
                    Registro de cambios en tus pedidos
                <?php endif; ?>
            </p>
        </div>
        <a href="<?php echo RUTA_URL; ?><?= $esAdmin ? 'dashboard' : 'logistica/dashboard' ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <?php if ($pedidoNoEncontrado): ?>
    <div class="alert alert-warning d-flex align-items-center mb-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <span>No se encontró ningún pedido con número de orden <strong><?php echo htmlspecialchars($pedidoFiltro); ?></strong>. Verifica el número e intenta de nuevo.</span>
    </div>
    <?php elseif ($pedidoFiltro !== '' && $pedidoIdInterno !== null): ?>
    <div class="alert alert-info d-flex align-items-center mb-3">
        <i class="bi bi-info-circle-fill me-2"></i>
        <span>Mostrando historial del pedido <strong>#<?php echo htmlspecialchars($pedidoFiltro); ?></strong> &nbsp;<span class="badge bg-secondary">ID interno: <?php echo $pedidoIdInterno; ?></span></span>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="row g-3">

                    <!-- Búsqueda rápida por pedido -->
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-primary"><i class="bi bi-box-seam"></i> Pedido #</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-hash"></i></span>
                            <input type="number" name="pedido" class="form-control" 
                                   placeholder="ej: 1234"
                                   value="<?php echo htmlspecialchars($pedidoFiltro); ?>"
                                   min="1">
                        </div>
                        <div class="form-text">Filtra por número de pedido</div>
                    </div>

                    <?php if ($esAdmin): ?>
                    <div class="col-md-2">
                        <label class="form-label small">Tabla</label>
                        <select name="tabla" class="form-select select2-searchable" data-placeholder="Seleccionar tabla...">
                            <option value="">Todas las tablas</option>
                            <?php foreach ($tablasUnicas as $tabla): ?>
                                <option value="<?php echo htmlspecialchars($tabla); ?>" <?php echo $tablaFiltro === $tabla ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tabla); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-2">
                        <label class="form-label small">Acción</label>
                        <select name="accion" class="form-select select2-searchable" data-placeholder="Seleccionar acción...">
                            <option value="">Todas las acciones</option>
                            <option value="crear" <?php echo $accionFiltro === 'crear' ? 'selected' : ''; ?>>Crear</option>
                            <option value="actualizar" <?php echo $accionFiltro === 'actualizar' ? 'selected' : ''; ?>>Actualizar</option>
                            <option value="eliminar" <?php echo $accionFiltro === 'eliminar' ? 'selected' : ''; ?>>Eliminar</option>
                        </select>
                    </div>

                    <?php if ($esAdmin): ?>
                    <div class="col-md-2">
                        <label class="form-label small">Usuario</label>
                        <select name="usuario" class="form-select select2-searchable" data-placeholder="Buscar usuario...">
                            <option value="">Todos los usuarios</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $usuarioFiltro == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-2">
                        <label class="form-label small">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?php echo htmlspecialchars($fechaInicio); ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?php echo htmlspecialchars($fechaFin); ?>">
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        <a href="<?php echo RUTA_URL; ?>auditoria/historial" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Estadísticas rápidas -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h3 class="text-success"><?php echo count(array_filter($registros, fn($r) => $r['accion'] === 'crear')); ?></h3>
                    <small>Creaciones</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h3 class="text-primary"><?php echo count(array_filter($registros, fn($r) => $r['accion'] === 'actualizar')); ?></h3>
                    <small>Actualizaciones</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h3 class="text-danger"><?php echo count(array_filter($registros, fn($r) => $r['accion'] === 'eliminar')); ?></h3>
                    <small>Eliminaciones</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de registros -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaAuditoria" class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Usuario</th>
                            <?php if ($esAdmin): ?><th>Tabla</th><?php endif; ?>
                            <th>ID Registro</th>
                            <th>Acción</th>
                            <th>Detalles</th>
                            <?php if ($esAdmin): ?><th>IP</th><th>País</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $reg): 
                            $badgeClass = match($reg['accion']) {
                                'crear' => 'badge-crear',
                                'actualizar' => 'badge-actualizar',
                                'eliminar' => 'badge-eliminar',
                                default => 'bg-secondary'
                            };
                            $iconClass = match($reg['accion']) {
                                'crear' => 'bi-plus-circle',
                                'actualizar' => 'bi-pencil',
                                'eliminar' => 'bi-trash',
                                default => 'bi-circle'
                            };
                        ?>
                            <tr>
                                <td>
                                    <small><?php echo date('d/m/Y H:i:s', strtotime($reg['created_at'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($reg['usuario_nombre'] ?? 'Sistema'); ?></strong>
                                </td>
                                <?php if ($esAdmin): ?>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($reg['tabla']); ?></span>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <code>#<?php echo $reg['id_registro']; ?></code>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <i class="bi <?php echo $iconClass; ?>"></i> 
                                        <?php echo ucfirst($reg['accion']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-info" type="button" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalDetalles<?php echo $reg['id']; ?>">
                                        <i class="bi bi-eye"></i> Ver
                                    </button>
                                </td>
                                <?php if ($esAdmin): ?>
                                <td>
                                    <small class="text-muted"><?php echo htmlspecialchars($reg['ip_address'] ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    <?php
                                        $pais = $reg['pais_origen'] ?? null;
                                        if ($pais === 'Local') {
                                            echo '<span class="badge bg-secondary">🏠 Local</span>';
                                        } elseif ($pais) {
                                            echo '<small>' . htmlspecialchars($pais) . '</small>';
                                        } else {
                                            echo '<small class="text-muted">—</small>';
                                        }
                                    ?>
                                </td>
                                <?php endif; ?>
                            </tr>

                            <!-- Modal de detalles -->
                            <div class="modal fade" id="modalDetalles<?php echo $reg['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="bi bi-info-circle"></i> Detalles de Auditoría #<?php echo $reg['id']; ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Tabla:</strong> <?php echo htmlspecialchars($reg['tabla']); ?><br>
                                                    <strong>ID Registro:</strong> #<?php echo $reg['id_registro']; ?><br>
                                                    <strong>Acción:</strong> 
                                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($reg['accion']); ?></span>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Usuario:</strong> <?php echo htmlspecialchars($reg['usuario_nombre'] ?? 'Sistema'); ?><br>
                                                    <strong>Fecha:</strong> <?php echo date('d/m/Y H:i:s', strtotime($reg['created_at'])); ?><br>
                                                    <strong>IP:</strong> <?php echo htmlspecialchars($reg['ip_address'] ?? 'N/A'); ?><br>
                                                    <?php
                                                        $pais = $reg['pais_origen'] ?? null;
                                                        if ($pais === 'Local') {
                                                            echo '<strong>País:</strong> <span class="badge bg-secondary">🏠 Local</span>';
                                                        } elseif ($pais) {
                                                            echo '<strong>País:</strong> 🌍 ' . htmlspecialchars($pais);
                                                        } else {
                                                            echo '<strong>País:</strong> <span class="text-muted">No disponible</span>';
                                                        }
                                                    ?>
                                                </div>
                                            </div>

                                            <?php if (!empty($reg['datos_anteriores'])): ?>
                                            <div class="mb-3">
                                                <h6 class="text-danger"><i class="bi bi-arrow-left"></i> Datos Anteriores:</h6>
                                                <div class="json-viewer">
                                                    <pre><?php echo htmlspecialchars(json_encode(json_decode($reg['datos_anteriores']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($reg['datos_nuevos'])): ?>
                                            <div class="mb-3">
                                                <h6 class="text-success"><i class="bi bi-arrow-right"></i> Datos Nuevos:</h6>
                                                <div class="json-viewer">
                                                    <pre><?php echo htmlspecialchars(json_encode(json_decode($reg['datos_nuevos']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($reg['user_agent'])): ?>
                                            <div class="mb-3">
                                                <h6><i class="bi bi-globe"></i> User Agent:</h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($reg['user_agent']); ?></small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#tablaAuditoria').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            order: [[0, 'desc']],
            pageLength: 25,
            responsive: true
        });
    });
</script>
</body>
</html>
