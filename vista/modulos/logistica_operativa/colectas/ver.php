<?php
/**
 * vista/modulos/logistica_operativa/colectas/ver.php
 *
 * Vista de detalle de una colecta.
 * Muestra:
 *   - Cards de resumen (Esperados / Recibidos / Faltantes / Extras).
 *   - Tabla de pedidos con resultado y estado visual.
 *   - Área de escaneo manual/USB.
 *   - Botón de cierre con confirmación previa.
 *
 * Seguridad:
 *   - Incluida desde rutas/logistica_operativa.php (sesión/permiso/flag ya verificados).
 *   - id_operador se extrae de la sesión en el endpoint, nunca del formulario.
 *   - CSRF token en sesión.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/ColectaModel.php';
require_once __DIR__ . '/../../../../services/logistica_operativa/ColectaService.php';

// ── ID de colecta ─────────────────────────────────────────────────────────────
$idColecta = isset($parametros[0]) ? (int)$parametros[0] : 0;

if ($idColecta <= 0) {
    echo "<script>window.location.href='" . RUTA_URL . "logistica-operativa/colectas';</script>";
    exit;
}

// ── Datos de la colecta ───────────────────────────────────────────────────────
$resumen    = null;
$pedidos    = [];
$errorCarga = null;

try {
    $db      = (new Conexion())->conectar();
    $colModel = new ColectaModel($db);
    $service  = new ColectaService($db);

    $resumen = $service->obtenerResumen($idColecta);
    $pedidos = $colModel->obtenerPedidosDetalle($idColecta);

} catch (Throwable $e) {
    error_log('[colectas/ver] Error al cargar resumen: ' . $e->getMessage());
    $errorCarga = 'No se pudo cargar el detalle de la colecta.';
}

if (!$resumen && !$errorCarga) {
    echo "<script>window.location.href='" . RUTA_URL . "logistica-operativa/colectas';</script>";
    exit;
}

// ── Extraer datos del resumen ─────────────────────────────────────────────────
$colecta   = $resumen['colecta'] ?? [];
$conteos   = $resumen['conteos'] ?? ['ESPERADO' => 0, 'RECIBIDO' => 0, 'FALTANTE' => 0, 'EXTRA' => 0];
$esAbierta = ($colecta['estado'] ?? '') === 'ABIERTA';

// ── CSRF token para escaneo y cierre ─────────────────────────────────────────
if (empty($_SESSION['csrf_token_colectas'])) {
    $_SESSION['csrf_token_colectas'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token_colectas'];

// ── Helpers badge ─────────────────────────────────────────────────────────────
if (!function_exists('badgeResultado')) {
    function badgeResultado(string $resultado): string
    {
        return match ($resultado) {
            'RECIBIDO'  => '<span class="badge badge-outline-success"><i class="bi bi-check-circle me-1"></i>RECIBIDO</span>',
            'FALTANTE'  => '<span class="badge badge-outline-danger"><i class="bi bi-x-circle me-1"></i>FALTANTE</span>',
            'EXTRA'     => '<span class="badge badge-outline-warning"><i class="bi bi-plus-circle me-1"></i>EXTRA</span>',
            'ESPERADO', 'PENDIENTE'  => '<span class="badge badge-outline-secondary"><i class="bi bi-clock me-1"></i>PENDIENTE</span>',
            default     => '<span class="badge bg-secondary">' . htmlspecialchars($resultado) . '</span>',
        };
    }
}

$pageTitle = 'Colecta #' . $idColecta . ' — Detalle';
?>
<?php require_once __DIR__ . '/../../../../vista/includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb mb-0 small">
    <li class="breadcrumb-item"><a href="<?= RUTA_URL ?>dashboard" class="text-decoration-none">Home</a></li>
    <li class="breadcrumb-item">Logística Operativa</li>
    <li class="breadcrumb-item"><a href="<?= RUTA_URL ?>logistica-operativa/colectas" class="text-decoration-none">Colectas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detalle</li>
  </ol>
</nav>

<!-- ═══ Encabezado ═══ -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <h1 class="h3 fw-bold text-dark mb-0">
                        <i class="bi bi-box-seam-fill me-2 text-primary"></i>Colecta #<?= $idColecta ?>
                    </h1>
                    <?php if ($esAbierta): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill fw-bold fs-7">🟢 ABIERTA</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill fw-bold fs-7">✓ CONCILIADA</span>
                    <?php endif; ?>
                </div>
                <?php if ($colecta): ?>
                <div class="d-flex align-items-center gap-3 text-muted small flex-wrap">
                    <span><i class="bi bi-building me-1 text-primary"></i>Cliente: <strong class="text-dark"><?= htmlspecialchars((string)($colecta['cliente_nombre'] ?? $colecta['id_cliente'] ?? '—')) ?></strong></span>
                    <span><i class="bi bi-calendar3 me-1 text-primary"></i><?= htmlspecialchars($colecta['fecha'] ?? '') ?></span>
                    <span>
                        <?= $colecta['turno'] === 'MANANA' ? '<span class="badge badge-turno-manana"><i class="bi bi-sun me-1"></i>Turno Mañana</span>' : '<span class="badge badge-turno-tarde"><i class="bi bi-moon me-1"></i>Turno Tarde</span>' ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-sm btn-outline-primary px-3 rounded-3" onclick="location.reload();" title="Recargar estado">
                    <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
                </button>
                <a href="<?= RUTA_URL ?>logistica-operativa/colectas" class="btn btn-sm btn-outline-secondary px-3 rounded-3">
                    <i class="bi bi-arrow-left me-1"></i>Volver a Lista
                </a>
            </div>
        </div>

        <?php 
        $espTotal = (int)($conteos['ESPERADO'] ?? 0);
        $recTotal = (int)($conteos['RECIBIDO'] ?? 0);
        $pctProgreso = $espTotal > 0 ? min(100, round(($recTotal / $espTotal) * 100)) : 0;
        ?>
        <!-- Barra de Progreso de Recolección -->
        <div class="mt-3 pt-3 border-top">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-semibold text-muted">Progreso de Recolección</span>
                <span class="small font-monospace fw-bold text-primary" id="lblPorcentajeRecoleccion">
                    <span id="lblConteoProgreso"><?= $recTotal ?></span> de <?= $espTotal ?> esperados (<?= $pctProgreso ?>%)
                </span>
            </div>
            <div class="progress rounded-pill bg-light" style="height: 10px;">
                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated rounded-pill" 
                     id="progressBarRecoleccion"
                     role="progressbar" 
                     style="width: <?= $pctProgreso ?>%;" 
                     aria-valuenow="<?= $pctProgreso ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100"></div>
            </div>
        </div>
    </div>
</div>

<?php if (!$esAbierta): ?>
<div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4 rounded-4 p-3 bg-success bg-opacity-10 text-success">
    <i class="bi bi-check-circle-fill fs-3 me-3"></i>
    <div>
        <h6 class="fw-bold mb-0">La colecta fue cerrada y conciliada exitosamente.</h6>
        <small class="opacity-75">No se pueden realizar más escaneos ni modificar los registros de esta colecta.</small>
    </div>
</div>
<?php endif; ?>

<?php if ($errorCarga): ?>
<div class="alert alert-danger border-0 shadow-sm rounded-4 p-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errorCarga) ?></div>
<?php endif; ?>

<!-- ═══ Cards de resumen KPI ═══ -->
<div class="row g-3 mb-4">
    <!-- Esperados -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white card-kpi-hover">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-bold small text-uppercase" style="font-size: 0.75rem;">ESPERADOS</span>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2.5 text-primary d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                        <i class="bi bi-inbox fs-5"></i>
                    </div>
                </div>
                <h2 class="fw-bold text-primary mb-0" id="cntEsperado"><?= (int)($conteos['ESPERADO'] ?? 0) ?></h2>
                <small class="text-muted" style="font-size: 0.75rem;">Paquetes manifestados</small>
            </div>
        </div>
    </div>
    <!-- Recibidos -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white card-kpi-hover">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-bold small text-uppercase" style="font-size: 0.75rem;">RECIBIDOS</span>
                    <div class="rounded-circle bg-success bg-opacity-10 p-2.5 text-success d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                    </div>
                </div>
                <h2 class="fw-bold text-success mb-0" id="cntRecibido"><?= (int)($conteos['RECIBIDO'] ?? 0) ?></h2>
                <small class="text-muted" style="font-size: 0.75rem;">Confirmados en escaneo</small>
            </div>
        </div>
    </div>
    <!-- Faltantes -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white card-kpi-hover">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-bold small text-uppercase" style="font-size: 0.75rem;">FALTANTES</span>
                    <div class="rounded-circle bg-danger bg-opacity-10 p-2.5 text-danger d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    </div>
                </div>
                <h2 class="fw-bold text-danger mb-0" id="cntFaltante"><?= (int)($conteos['FALTANTE'] ?? 0) ?></h2>
                <small class="text-muted" style="font-size: 0.75rem;">Pendientes de recojo</small>
            </div>
        </div>
    </div>
    <!-- Extras -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white card-kpi-hover">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-bold small text-uppercase" style="font-size: 0.75rem;">EXTRAS</span>
                    <div class="rounded-circle bg-warning bg-opacity-15 p-2.5 text-warning d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                        <i class="bi bi-plus-circle-fill fs-5"></i>
                    </div>
                </div>
                <h2 class="fw-bold text-warning mb-0" id="cntExtra"><?= (int)($conteos['EXTRA'] ?? 0) ?></h2>
                <small class="text-muted" style="font-size: 0.75rem;">No pertenecientes a ruta</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- ═══ Columna izq: escaneo ═══ -->
    <div class="col-12 col-lg-5">

        <!-- Área de escaneo ultra-fácil y limpia -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden bg-white">
            <div class="card-header text-white fw-bold py-3 px-4 d-flex align-items-center justify-content-between" style="background: #0f172a !important;">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-upc-scan text-info fs-5"></i>
                    <span>Escaneo de paquetes</span>
                </span>
                <span class="badge bg-secondary text-white font-monospace px-2.5 py-1" style="font-size:0.75rem;">
                    Auto-Enter
                </span>
            </div>
            <div class="card-body p-4">
                <?php if (!$esAbierta): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-lock-fill display-4 text-secondary opacity-50 d-block mb-2"></i>
                    <div class="fw-bold">Esta colecta está CONCILIADA.</div>
                    <small>No se admiten más escaneos.</small>
                </div>
                <?php else: ?>

                <!-- Bar de escaneo principal -->
                <div class="mb-3">
                    <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                        <span class="input-group-text bg-light border-end-0 px-3">
                            <i class="bi bi-barcode text-primary fs-3"></i>
                        </span>
                        <input type="text"
                               id="inputEscaneo"
                               class="form-control font-monospace border-start-0 border-end-0 fs-6 py-2.5"
                               placeholder="Escanea o escribe el código..."
                               autocomplete="off"
                               autofocus
                               style="box-shadow: none !important;">
                        <button class="btn btn-primary fw-bold px-4 d-inline-flex align-items-center gap-2"
                                type="button"
                                id="btnEscanear">
                            <i class="bi bi-lightning-fill text-warning fs-5"></i>
                            <span>Registrar</span>
                        </button>
                    </div>
                </div>

                <!-- Botón de Cámara y Estado del Lector -->
                <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
                    <button class="btn btn-outline-dark fw-bold btn-sm rounded-pill px-3 py-1.5 d-inline-flex align-items-center gap-2 shadow-xs"
                            type="button"
                            id="btnAbrirCamaraQR"
                            title="Escanear con Cámara de Celular / Laptop">
                        <i class="bi bi-camera-video-fill text-info fs-6"></i>
                        <span>Abrir Cámara QR</span>
                    </button>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill small">
                        🟢 Lector conectado (Pistola USB / Bluetooth)
                    </span>
                </div>

                <!-- Resultado del escaneo -->
                <div id="resultadoEscaneo" class="mt-3 d-none"></div>

                <!-- Historial de escaneos recientes -->
                <div id="historialEscaneo" class="mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted fw-bold text-uppercase" style="font-size:0.75rem;"><i class="bi bi-clock-history me-1 text-primary"></i>Escaneos recientes en esta sesión</small>
                    </div>
                    <ul class="list-group list-group-flush border rounded-3 overflow-hidden" id="listaHistorial" style="max-height:220px;overflow-y:auto">
                        <li class="list-group-item text-muted small text-center py-3">
                            <i class="bi bi-qr-code text-muted opacity-50 d-block fs-4 mb-1"></i>
                            Escanea un paquete para iniciar el registro.
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Botón cerrar colecta -->
        <?php if ($esAbierta): ?>
        <div class="card border-0 shadow-sm border-start border-4 border-danger bg-white p-3.5 rounded-4">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-shield-lock-fill fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Cerrar y conciliar colecta</h6>
                        <small class="text-muted">Calcula el estado final (Recibido / Faltante) de cada pedido.</small>
                    </div>
                </div>
                <button class="btn btn-danger fw-bold px-4 py-2.5 rounded-3 shadow-sm text-nowrap ms-auto"
                        id="btnCerrarColecta">
                    <i class="bi bi-lock-fill me-1"></i>Cerrar y conciliar
                </button>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ═══ Columna der: tabla de pedidos ═══ -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
            <div class="card-header bg-white fw-bold d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-list-task me-1 text-primary fs-5"></i>
                    <span class="text-dark">Pedidos de la Colecta</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-monospace ms-1" id="lblTotalPedidos">
                        <?= count($pedidos) ?> pedidos
                    </span>
                </div>
                <div>
                    <!-- Buscador en tiempo real de la tabla -->
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text"
                               id="inputFiltroTablaPedidos"
                               class="form-control font-monospace border-start-0 bg-light"
                               placeholder="Filtrar pedidos..."
                               style="max-width: 180px;">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="tablaPedidos">
                        <thead class="bg-light sticky-top" style="z-index: 5;">
                            <tr class="small text-muted text-uppercase">
                                <th class="ps-4">Pedido / Orden</th>
                                <th>Destinatario</th>
                                <th>Resultado</th>
                                <th>Escaneado</th>
                                <th>Hora</th>
                                <?php if ($esAbierta): ?><th class="text-end pe-4">Acción</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tbodyPedidos">
                        <?php if (empty($pedidos)): ?>
                            <tr id="trSinPedidos">
                                <td colspan="<?= $esAbierta ? '6' : '5' ?>" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox display-5 opacity-25 d-block mb-2"></i>
                                    Sin pedidos en esta colecta.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $p): ?>
                            <tr id="fila-pedido-<?= (int)$p['id_pedido'] ?>" class="fila-pedido-item">
                                <td class="fw-bold font-monospace ps-4">
                                    <?= htmlspecialchars((string)($p['numero_orden'] ?? '#' . $p['id_pedido'])) ?>
                                </td>
                                <td class="small fw-semibold text-dark">
                                    <?= htmlspecialchars((string)($p['destinatario'] ?? '—')) ?>
                                </td>
                                <td>
                                    <?= badgeResultado($p['resultado_pedido'] ?? 'ESPERADO') ?>
                                </td>
                                <td>
                                    <?= isset($p['escaneado_at']) ? '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check me-1"></i>Sí</span>' : '<span class="badge bg-light text-muted border">No</span>' ?>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= isset($p['escaneado_at']) ? date('d/m/Y H:i:s', strtotime($p['escaneado_at'])) : '—' ?>
                                </td>
                                <?php if ($esAbierta): ?>
                                <td class="text-end pe-4">
                                    <?php if (($p['resultado_pedido'] ?? '') === 'EXTRA'): ?>
                                    <button class="btn btn-sm btn-outline-danger py-1 px-2.5 text-nowrap rounded-3"
                                            onclick="eliminarPedidoExtra(<?= (int)$p['id_pedido'] ?>, '<?= htmlspecialchars((string)($p['numero_orden'] ?? $p['id_pedido']), ENT_QUOTES) ?>')"
                                            title="Quitar este paquete extra">
                                        <i class="bi bi-trash me-1"></i>Quitar
                                    </button>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div><!-- /row -->

<?php if (!$esAbierta): ?>
<!-- Resumen de auditoría para colectas conciliadas -->
<div class="card border-0 shadow-sm mt-4 bg-light">
    <div class="card-body p-4">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Resumen de auditoría</h6>
        <div class="row g-3">
            <div class="col-12 col-md-3">
                <small class="text-muted d-block text-uppercase">Abierta por</small>
                <span class="fw-bold"><?= htmlspecialchars((string)($colecta['operador_nombre'] ?? 'Admin General')) ?></span>
            </div>
            <div class="col-12 col-md-3">
                <small class="text-muted d-block text-uppercase">Apertura</small>
                <span class="fw-semibold"><?= isset($colecta['created_at']) ? date('d/m/Y H:i', strtotime($colecta['created_at'])) : '—' ?></span>
            </div>
            <div class="col-12 col-md-3">
                <small class="text-muted d-block text-uppercase">Cerrada por</small>
                <span class="fw-bold"><?= htmlspecialchars((string)($colecta['cerrada_por_nombre'] ?? $colecta['operador_nombre'] ?? 'Admin General')) ?></span>
            </div>
            <div class="col-12 col-md-3">
                <small class="text-muted d-block text-uppercase">Cierre</small>
                <span class="fw-semibold"><?= isset($colecta['cerrada_at']) ? date('d/m/Y H:i', strtotime($colecta['cerrada_at'])) : '—' ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══ Script de configuración ═══ -->
<script>
const COLECTA_ID     = <?= $idColecta ?>;
const COLECTA_ABIERTA = <?= $esAbierta ? 'true' : 'false' ?>;
const CSRF_TOKEN_COLECTAS = '<?= $csrfToken ?>';

// Contadores iniciales (se actualizan por JS tras cada escaneo/cierre)
let contadores = {
    ESPERADO: <?= (int)($conteos['ESPERADO'] ?? 0) ?>,
    RECIBIDO: <?= (int)($conteos['RECIBIDO'] ?? 0) ?>,
    FALTANTE: <?= (int)($conteos['FALTANTE'] ?? 0) ?>,
    EXTRA:    <?= (int)($conteos['EXTRA']    ?? 0) ?>,
};
</script>
<script src="<?= RUTA_URL ?>vista/modulos/logistica_operativa/colectas/js/colectas.js?v=<?= time() ?>"></script>

<?php require_once __DIR__ . '/../partials/qr_scanner_modal.php'; ?>
<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
