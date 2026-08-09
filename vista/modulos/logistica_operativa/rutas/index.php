<?php
/**
 * vista/modulos/logistica_operativa/rutas/index.php
 *
 * Listado de Rutas de Despacho — Logística Operativa.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/RutaModel.php';
require_once __DIR__ . '/../../../../utils/logistica_permissions.php';

require_permission('logistica_operativa_rutas');

$errorCarga = null;
$rutas = [];

try {
    $db = (new Conexion())->conectar();
    $model = new RutaModel($db);

    $rolesSession = $_SESSION['roles_nombres'] ?? [];
    $isRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $rolesSession, true) || in_array('Repartidor', $rolesSession, true);
    $isAdmin      = in_array(ROL_NOMBRE_ADMIN, $rolesSession, true) || in_array('Administrador', $rolesSession, true);

    $filtroFecha      = $_GET['fecha']      ?? '';
    $filtroEstado     = $_GET['estado']     ?? '';
    $filtroRepartidor = $_GET['repartidor'] ?? '';

    $filtrosRuta = [
        'fecha'      => $filtroFecha,
        'estado'     => $filtroEstado,
        'repartidor' => $filtroRepartidor
    ];

    // Si es repartidor y no admin, filtrar solo sus rutas asignadas
    if ($isRepartidor && !$isAdmin) {
        $filtrosRuta['id_repartidor'] = (int)($_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? 0);
    }

    $rutas = $model->listarConFiltros($filtrosRuta);

} catch (Throwable $e) {
    error_log('[rutas/index] Error: ' . $e->getMessage());
    $errorCarga = 'No se pudo cargar la lista de rutas.';
}

if (!function_exists('badgeEstadoRuta')) {
    function badgeEstadoRuta(string $estado): string {
        return match ($estado) {
            'ASIGNADA'   => '<span class="badge badge-outline-success">ASIGNADA</span>',
            'SELLADA'    => '<span class="badge badge-outline-secondary"><i class="bi bi-lock-fill me-1"></i>SELLADA</span>',
            'EN_CURSO'   => '<span class="badge badge-outline-warning">EN CURSO</span>',
            'COMPLETADA' => '<span class="badge badge-outline-success">COMPLETADA</span>',
            default      => '<span class="badge bg-secondary">' . htmlspecialchars((string)$estado) . '</span>',
        };
    }
}

// Contadores KPI
$countAsignadas = 0;
$countSelladas = 0;
$totalMontoCod = 0.0;
$totalPedidosRutas = 0;
foreach ($rutas as $r) {
    if (($r['estado'] ?? '') === 'ASIGNADA') $countAsignadas++;
    if (($r['estado'] ?? '') === 'SELLADA') $countSelladas++;
    $totalMontoCod += (float)($r['total_cod'] ?? 0);
    $totalPedidosRutas += (int)($r['cantidad_pedidos'] ?? 0);
}

$pageTitle = 'Rutas de Despacho — Logística Operativa';
?>
<?php require_once __DIR__ . '/../../../../vista/includes/header.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-diagram-3 me-2 text-primary"></i>Rutas y Despacho
        </h1>
        <small class="text-muted">Gestión de rutas, agrupación de paquetes y manifiestos de mensajería</small>
    </div>
    <div>
        <a href="<?= RUTA_URL ?>logistica-operativa/rutas/crear" class="btn btn-warning fw-bold px-3 text-dark shadow-sm">
            <i class="bi bi-plus-lg me-1"></i>Armar Nueva Ruta
        </a>
    </div>
</div>

<!-- ═══ 4 Tarjetas KPI Superiores (Rutas) ═══ -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-circle kpi-icon-blue me-3">
                    <i class="bi bi-diagram-3"></i>
                </div>
                <div>
                    <div class="h3 mb-0 fw-bold"><?= count($rutas) ?></div>
                    <div class="fw-semibold text-primary small">Rutas totales</div>
                    <div class="text-muted small" style="font-size:0.75rem;"><?= $countAsignadas ?> asignadas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-circle kpi-icon-green me-3">
                    <i class="bi bi-lock-fill"></i>
                </div>
                <div>
                    <div class="h3 mb-0 fw-bold"><?= $countSelladas ?></div>
                    <div class="fw-semibold text-success small">Rutas selladas</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Listas para despacho</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-circle kpi-icon-purple me-3">
                    <i class="bi bi-boxes"></i>
                </div>
                <div>
                    <div class="h3 mb-0 fw-bold"><?= $totalPedidosRutas ?></div>
                    <div class="fw-semibold text-purple small" style="color:#9333ea;">Paquetes en rutas</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Total asignado</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-circle kpi-icon-yellow me-3">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div>
                    <div class="h3 mb-0 fw-bold text-success">C$ <?= number_format($totalMontoCod, 2) ?></div>
                    <div class="fw-semibold text-warning small">Monto COD</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Total a recaudar</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($errorCarga): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars((string)$errorCarga) ?></div>
<?php endif; ?>

<!-- ═══ Filtros ═══ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?= RUTA_URL ?>index.php" class="row g-3">
            <input type="hidden" name="enlace" value="logistica-operativa/rutas">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Fecha</label>
                <input type="date" name="fecha" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($filtroFecha ?? '')) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">-- Todos los estados --</option>
                    <option value="ASIGNADA" <?= $filtroEstado === 'ASIGNADA' ? 'selected' : '' ?>>ASIGNADA</option>
                    <option value="SELLADA" <?= $filtroEstado === 'SELLADA' ? 'selected' : '' ?>>SELLADA</option>
                    <option value="EN_CURSO" <?= $filtroEstado === 'EN_CURSO' ? 'selected' : '' ?>>EN CURSO</option>
                    <option value="COMPLETADA" <?= $filtroEstado === 'COMPLETADA' ? 'selected' : '' ?>>COMPLETADA</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold text-muted mb-1">Repartidor</label>
                <input type="text" name="repartidor" class="form-control form-control-sm" placeholder="Nombre de repartidor..." value="<?= htmlspecialchars((string)($filtroRepartidor ?? '')) ?>">
            </div>
            <div class="col-12 col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold"><i class="bi bi-filter me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Tabla de Rutas ═══ -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-navy align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Código / Ruta</th>
                        <th>Fecha</th>
                        <th>Repartidor</th>
                        <th class="text-center">Paquetes</th>
                        <th class="text-end">Total COD</th>
                        <th>Estado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rutas)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-6 d-block mb-2 opacity-25"></i>
                            No se encontraron rutas registradas.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($rutas as $r): ?>
                        <tr>
                            <td class="ps-3">
                                <a href="<?= RUTA_URL ?>logistica-operativa/rutas/ver/<?= (int)$r['id'] ?>" class="fw-bold text-decoration-none">
                                    <?= htmlspecialchars((string)$r['codigo']) ?>
                                </a>
                                <div class="small text-muted"><?= htmlspecialchars((string)$r['nombre']) ?></div>
                            </td>
                            <td class="small"><?= htmlspecialchars((string)$r['fecha']) ?></td>
                            <td>
                                <i class="bi bi-person me-1 text-secondary"></i>
                                <?= htmlspecialchars((string)($r['repartidor_nombre'] ?? 'Sin asignar')) ?>
                            </td>
                            <td class="text-center fw-semibold"><?= (int)$r['cantidad_pedidos'] ?></td>
                            <td class="text-end fw-mono font-monospace">C$ <?= number_format((float)$r['total_cod'], 2) ?></td>
                            <td><?= badgeEstadoRuta($r['estado']) ?></td>
                            <td class="text-end pe-3">
                                <a href="<?= RUTA_URL ?>logistica-operativa/rutas/ver/<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver Manifiesto">
                                    <i class="bi bi-file-earmark-text me-1"></i>Manifiesto
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
