<?php
/**
 * vista/modulos/logistica_operativa/rutas/ver.php
 *
 * Vista de Detalle y Manifiesto de Despacho de Ruta.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/RutaModel.php';
require_once __DIR__ . '/../../../../services/logistica_operativa/RutaService.php';
require_once __DIR__ . '/../../../../utils/logistica_permissions.php';

require_permission('logistica_operativa_rutas');

$idRuta = isset($parametros[0]) ? (int)$parametros[0] : 0;

if ($idRuta <= 0) {
    echo "<script>window.location.href='" . RUTA_URL . "logistica-operativa/rutas';</script>";
    exit;
}

$ruta = null;
$pedidos = [];
$error = null;
$mensajeExito = null;

try {
    $db = (new Conexion())->conectar();
    $rutaModel = new RutaModel($db);
    $rutaService = new RutaService($db);

    // Procesar acción de sellar ruta vía POST
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sellar') {
        $rutaService->sellarRuta($idRuta, (int)($_SESSION['user_id'] ?? 1));
        $mensajeExito = '¡La ruta ha sido SELLADA exitosamente! No se admitirán más modificaciones.';
    }

    $ruta = $rutaModel->obtenerPorId($idRuta);
    $pedidos = $rutaModel->obtenerPedidosDeRuta($idRuta);

} catch (Throwable $e) {
    error_log('[rutas/ver] Error: ' . $e->getMessage());
    $error = $e->getMessage();
}

if (!$ruta && !$error) {
    echo "<script>window.location.href='" . RUTA_URL . "logistica-operativa/rutas';</script>";
    exit;
}

if (!function_exists('badgeEstadoRutaDetalle')) {
    function badgeEstadoRutaDetalle(string $estado): string {
        return match ($estado) {
            'ASIGNADA'   => '<span class="badge bg-primary fs-6 px-3 py-2">ASIGNADA</span>',
            'SELLADA'    => '<span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-lock-fill me-1"></i>SELLADA</span>',
            'EN_CURSO'   => '<span class="badge bg-warning text-dark fs-6 px-3 py-2">EN CURSO</span>',
            'COMPLETADA' => '<span class="badge bg-info text-dark fs-6 px-3 py-2">COMPLETADA</span>',
            default      => '<span class="badge bg-secondary fs-6 px-3 py-2">' . htmlspecialchars((string)$estado) . '</span>',
        };
    }
}

$pageTitle = 'Manifiesto de Ruta #' . $idRuta . ' — Logística Operativa';
?>
<?php require_once __DIR__ . '/../../../../vista/includes/header.php'; ?>

<!-- ESTILOS DE IMPRESIÓN PARA EL MANIFIESTO -->
<style>
@media print {
    .no-print, header, sidebar, footer, .btn, .card-header { display: none !important; }
    .card { border: none !important; shadow: none !important; }
    body { background: #fff !important; color: #000 !important; font-size: 12pt; }
    .print-header { display: block !important; margin-bottom: 20px; }
}
</style>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2 no-print">
    <div>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-file-earmark-text me-2 text-primary"></i>Manifiesto de Ruta: <?= htmlspecialchars((string)($ruta['codigo'] ?? '')) ?>
        </h1>
        <small class="text-muted"><?= htmlspecialchars((string)($ruta['nombre'] ?? '')) ?> &mdash; Fecha: <?= htmlspecialchars((string)($ruta['fecha'] ?? '')) ?></small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Imprimir Manifiesto
        </button>

        <?php if (($ruta['estado'] ?? '') === 'ASIGNADA'): ?>
        <form method="POST" action="" onsubmit="return confirm('¿Está seguro de SELLAR esta ruta? Una vez sellada no podrá agregar ni remover paquetes.');">
            <input type="hidden" name="action" value="sellar">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-lock-fill me-1"></i>Sellar Ruta
            </button>
        </form>
        <?php endif; ?>

        <a href="<?= RUTA_URL ?>logistica-operativa/rutas" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?php if ($mensajeExito): ?>
<div class="alert alert-success mb-4 no-print"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars((string)$mensajeExito) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger mb-4 no-print"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars((string)$error) ?></div>
<?php endif; ?>

<!-- ═══ Cabecera Imprimible del Manifiesto ═══ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-md-4">
                <div class="text-muted small fw-semibold text-uppercase">Ruta / Manifiesto</div>
                <div class="h4 mb-0 fw-bold text-primary"><?= htmlspecialchars((string)($ruta['codigo'] ?? '')) ?></div>
                <div class="small fw-semibold"><?= htmlspecialchars((string)($ruta['nombre'] ?? '')) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase">Repartidor Asignado</div>
                <div class="fw-bold"><i class="bi bi-person me-1"></i><?= htmlspecialchars((string)($ruta['repartidor_nombre'] ?? 'Sin asignar')) ?></div>
                <div class="small text-muted">Fecha: <?= htmlspecialchars((string)($ruta['fecha'] ?? '')) ?></div>
            </div>
            <div class="col-6 col-md-2 text-center">
                <div class="text-muted small fw-semibold text-uppercase">Estado de Ruta</div>
                <div class="mt-1"><?= badgeEstadoRutaDetalle($ruta['estado'] ?? '') ?></div>
            </div>
            <div class="col-12 col-md-3 text-md-end">
                <div class="text-muted small fw-semibold text-uppercase">Monto COD a Recaudar</div>
                <div class="h3 mb-0 fw-bold font-monospace text-success">C$ <?= number_format((float)($ruta['total_cod'] ?? 0), 2) ?></div>
                <div class="small text-muted"><?= (int)($ruta['cantidad_pedidos'] ?? 0) ?> paquetes en la ruta</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Tabla de Pedidos en el Manifiesto ═══ -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-light fw-semibold">
        <i class="bi bi-list-check me-2"></i>Detalle de Entregas y Secuencia de Visita
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 50px;">#</th>
                        <th>N.º Orden / Tracking</th>
                        <th>Destinatario</th>
                        <th>Teléfono</th>
                        <th>Dirección / Municipio</th>
                        <th>Ubicación Bodega</th>
                        <th class="text-end">Monto COD</th>
                        <th class="text-center">Firma / Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Sin paquetes asignados a esta ruta.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $idx => $p): ?>
                        <tr>
                            <td class="text-center fw-bold bg-light"><?= (int)($idx + 1) ?></td>
                            <td>
                                <span class="fw-bold font-monospace"><?= htmlspecialchars((string)($p['numero_orden'] ?? '#' . $p['id_pedido'])) ?></span>
                            </td>
                            <td class="fw-semibold"><?= htmlspecialchars((string)($p['destinatario'] ?? '—')) ?></td>
                            <td class="font-monospace small"><?= htmlspecialchars((string)($p['telefono'] ?? '—')) ?></td>
                            <td class="small">
                                <div><?= htmlspecialchars((string)($p['direccion'] ?? '—')) ?></div>
                                <span class="badge bg-light text-dark border mt-1">
                                    <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars((string)($p['municipalitiesName'] ?? $p['departmentName'] ?? 'Managua')) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-grid-3x3-gap me-1"></i><?= htmlspecialchars((string)($p['ubicacion_codigo'] ?? 'RECEPCION-01')) ?>
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold">
                                C$ <?= number_format((float)$p['monto_cod'], 2) ?>
                            </td>
                            <td class="text-center small text-muted" style="min-width: 150px;">
                                _______________________
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
