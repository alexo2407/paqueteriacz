<?php
$usaDataTables = true;
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/historial_accesos.php';

start_secure_session();
require_login();

// Solo admins pueden ver el historial de accesos
if (!isSuperAdmin()) {
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

// --- Filtros ---
$usuarioFiltro  = isset($_GET['usuario'])     ? (int)$_GET['usuario']         : 0;
$tipoFiltro     = isset($_GET['tipo'])        ? trim($_GET['tipo'])            : '';
$paisFiltro     = isset($_GET['pais'])        ? trim($_GET['pais'])            : '';
$fechaInicio    = $_GET['fecha_inicio']       ?? date('Y-m-d', strtotime('-7 days'));
$fechaFin       = $_GET['fecha_fin']          ?? date('Y-m-d');

$filtros = ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin];
if ($usuarioFiltro) $filtros['id_usuario']  = $usuarioFiltro;
if ($tipoFiltro)    $filtros['tipo']        = $tipoFiltro;
if ($paisFiltro)    $filtros['pais_origen'] = $paisFiltro;

$registros       = HistorialAccesosModel::listar($filtros, 500);
$usuarios        = HistorialAccesosModel::obtenerUsuariosConAcceso();
$paisesUnicos    = HistorialAccesosModel::obtenerPaisesUnicos();

// --- Conteos por tipo ---
$totalGui = count(array_filter($registros, fn($r) => $r['tipo'] === 'gui'));
$totalApi = count(array_filter($registros, fn($r) => $r['tipo'] === 'api'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Accesos - App RutaEx-Latam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        .badge-gui { background-color: #0d6efd; }
        .badge-api { background-color: #6f42c1; }
        .country-flag { font-size: 1.1em; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-person-badge"></i> Historial de Accesos</h2>
            <p class="text-muted mb-0">Registro histórico de quién, cuándo y desde dónde inició sesión</p>
        </div>
        <a href="<?php echo RUTA_URL; ?>auditoria/historial" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver a Auditoría
        </a>
    </div>

    <!-- Estadísticas rápidas -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card border-0 bg-primary text-white">
                <div class="card-body text-center py-3">
                    <h3 class="mb-0"><?php echo count($registros); ?></h3>
                    <small>Total accesos</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-info text-white">
                <div class="card-body text-center py-3">
                    <h3 class="mb-0"><?php echo $totalGui; ?></h3>
                    <small><i class="bi bi-globe"></i> Accesos vía Web (GUI)</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0" style="background:#6f42c1;color:#fff">
                <div class="card-body text-center py-3">
                    <h3 class="mb-0"><?php echo $totalApi; ?></h3>
                    <small><i class="bi bi-code-slash"></i> Accesos vía API</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="row g-3">

                    <div class="col-md-2">
                        <label class="form-label small">Usuario</label>
                        <select name="usuario" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $usuarioFiltro == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small">Tipo de acceso</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos</option>
                            <option value="gui" <?php echo $tipoFiltro === 'gui' ? 'selected' : ''; ?>>🌐 Web (GUI)</option>
                            <option value="api" <?php echo $tipoFiltro === 'api' ? 'selected' : ''; ?>>⚡ API</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small">País</label>
                        <select name="pais" class="form-select">
                            <option value="">Todos los países</option>
                            <?php foreach ($paisesUnicos as $p): ?>
                                <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $paisFiltro === $p ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?php echo htmlspecialchars($fechaInicio); ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?php echo htmlspecialchars($fechaFin); ?>">
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                        <a href="<?php echo RUTA_URL; ?>auditoria/accesos" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de accesos -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaAccesos" class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Usuario</th>
                            <th>Tipo</th>
                            <th>IP</th>
                            <th>País</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $reg): ?>
                        <tr>
                            <td>
                                <small><?php echo date('d/m/Y H:i:s', strtotime($reg['created_at'])); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($reg['usuario_nombre'] ?? '—'); ?></strong>
                            </td>
                            <td>
                                <?php if ($reg['tipo'] === 'gui'): ?>
                                    <span class="badge badge-gui"><i class="bi bi-globe"></i> Web</span>
                                <?php else: ?>
                                    <span class="badge badge-api"><i class="bi bi-code-slash"></i> API</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo htmlspecialchars($reg['ip_address'] ?? 'N/A'); ?></small>
                            </td>
                            <td>
                                <?php
                                    $pais = $reg['pais_origen'] ?? null;
                                    if ($pais === 'Local') {
                                        echo '<span class="badge bg-secondary">🏠 Local</span>';
                                    } elseif ($pais) {
                                        echo '🌍 ' . htmlspecialchars($pais);
                                    } else {
                                        echo '<small class="text-muted">—</small>';
                                    }
                                ?>
                            </td>
                            <td>
                                <small class="text-muted text-truncate d-inline-block" style="max-width:200px" 
                                       title="<?php echo htmlspecialchars($reg['user_agent'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($reg['user_agent'] ?? '—'); ?>
                                </small>
                            </td>
                        </tr>
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
        $('#tablaAccesos').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            order: [[0, 'desc']],
            pageLength: 25,
            responsive: true
        });
    });
</script>
</body>
</html>
