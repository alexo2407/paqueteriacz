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
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-folder-fill me-2 text-warning"></i>Colecta #<?= $idColecta ?>
        </h1>
        <?php if ($colecta): ?>
        <small class="text-muted">
            <?= htmlspecialchars($colecta['fecha'] ?? '') ?>
            &mdash;
            <?= $colecta['turno'] === 'MANANA' ? '<span class="badge badge-turno-manana"><i class="bi bi-sun me-1"></i>Mañana</span>' : '<span class="badge badge-turno-tarde"><i class="bi bi-moon me-1"></i>Tarde</span>' ?>
            &mdash;
            Cliente: <strong><?= htmlspecialchars((string)($colecta['cliente_nombre'] ?? $colecta['id_cliente'] ?? '—')) ?></strong>
        </small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?php if ($esAbierta): ?>
        <span class="badge badge-outline-success px-3 py-2 fs-6">ABIERTA</span>
        <?php else: ?>
        <span class="badge badge-outline-secondary px-3 py-2 fs-6">✓ CONCILIADA</span>
        <?php endif; ?>
        <a href="<?= RUTA_URL ?>logistica-operativa/colectas"
           class="btn btn-sm btn-outline-secondary px-3">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?php if (!$esAbierta): ?>
<div class="alert alert-success border-success-subtle d-flex align-items-center mb-4 rounded-3 shadow-sm">
    <i class="bi bi-check-circle-fill fs-3 me-3 text-success"></i>
    <div>
        <h6 class="fw-bold mb-0 text-success">La colecta fue cerrada y conciliada exitosamente.</h6>
        <small class="text-muted">No se pueden realizar más escaneos ni modificar esta información.</small>
    </div>
</div>
<?php endif; ?>

<?php if ($errorCarga): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($errorCarga) ?></div>
<?php endif; ?>

<!-- ═══ Cards de resumen KPI con Borde Superior ═══ -->
<div class="row g-3 mb-4">
    <!-- Esperados -->
    <div class="col-6 col-md-3">
        <div class="card card-kpi card-kpi-topborder border-top-blue p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="h2 mb-0 fw-bold text-primary" id="cntEsperado">
                        <?= (int)($conteos['ESPERADO'] ?? 0) ?>
                    </div>
                    <small class="text-muted fw-bold text-uppercase">ESPERADOS</small>
                </div>
                <div class="kpi-icon-circle kpi-icon-blue">
                    <i class="bi bi-inbox"></i>
                </div>
            </div>
        </div>
    </div>
    <!-- Recibidos -->
    <div class="col-6 col-md-3">
        <div class="card card-kpi card-kpi-topborder border-top-green p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="h2 mb-0 fw-bold text-success" id="cntRecibido">
                        <?= (int)($conteos['RECIBIDO'] ?? 0) ?>
                    </div>
                    <small class="text-muted fw-bold text-uppercase">RECIBIDOS</small>
                </div>
                <div class="kpi-icon-circle kpi-icon-green">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <!-- Faltantes -->
    <div class="col-6 col-md-3">
        <div class="card card-kpi card-kpi-topborder border-top-red p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="h2 mb-0 fw-bold text-danger" id="cntFaltante">
                        <?= (int)($conteos['FALTANTE'] ?? 0) ?>
                    </div>
                    <small class="text-muted fw-bold text-uppercase">FALTANTES</small>
                </div>
                <div class="kpi-icon-circle kpi-icon-red">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
    <!-- Extras -->
    <div class="col-6 col-md-3">
        <div class="card card-kpi card-kpi-topborder border-top-yellow p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="h2 mb-0 fw-bold text-warning" id="cntExtra">
                        <?= (int)($conteos['EXTRA'] ?? 0) ?>
                    </div>
                    <small class="text-muted fw-bold text-uppercase">EXTRAS</small>
                </div>
                <div class="kpi-icon-circle kpi-icon-yellow">
                    <i class="bi bi-plus-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- ═══ Columna izq: escaneo ═══ -->
    <div class="col-12 col-lg-5">

        <!-- Área de escaneo -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header fw-bold bg-dark text-white d-flex align-items-center justify-content-between" style="background:#0f172a !important;">
                <span><i class="bi bi-upc-scan me-2"></i>Escaneo de paquetes</span>
                <span class="badge bg-secondary font-monospace" style="font-size:0.7rem;">Teclado rápido: Enter</span>
            </div>
            <div class="card-body p-4">
                <?php if (!$esAbierta): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-lock-fill display-4 text-secondary opacity-50 d-block mb-2"></i>
                    <div class="fw-bold">Esta colecta está CONCILIADA.</div>
                    <small>No se admiten más escaneos.</small>
                </div>
                <?php else: ?>

                <!-- Campo de escaneo -->
                <div class="input-group input-group-lg mb-3">
                    <span class="input-group-text bg-light text-muted">
                        <i class="bi bi-barcode"></i>
                    </span>
                    <input type="text"
                           id="inputEscaneo"
                           class="form-control form-control-lg font-monospace fs-6"
                           placeholder="Escanea o escribe el código del paquete"
                           autocomplete="off"
                           autofocus>
                    <button class="btn btn-primary fw-bold px-3"
                            type="button"
                            id="btnEscanear">
                        <i class="bi bi-lightning-fill me-1"></i>Registrar
                    </button>
                </div>

                <!-- Estado del lector -->
                <div class="d-flex align-items-center justify-content-between mb-3 bg-light p-2 rounded-3 border">
                    <span class="small fw-semibold text-muted d-flex align-items-center">
                        <span class="badge bg-success-subtle text-success border border-success-subtle me-2">🟢 Lector conectado</span>
                        USB Keyboard
                    </span>
                </div>

                <!-- Resultado del escaneo -->
                <div id="resultadoEscaneo" class="mt-3 d-none"></div>

                <!-- Historial de escaneos recientes -->
                <div id="historialEscaneo" class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted fw-bold text-uppercase" style="font-size:0.75rem;">Escaneos recientes (últimos 10)</small>
                    </div>
                    <ul class="list-group list-group-flush border rounded-3" id="listaHistorial" style="max-height:220px;overflow-y:auto">
                        <li class="list-group-item text-muted small text-center py-3">
                            Escanea un paquete para iniciar el registro.
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Botón cerrar colecta -->
        <?php if ($esAbierta): ?>
        <div class="card border-0 shadow-sm border-start border-4 border-danger bg-white p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon-circle kpi-icon-red me-3">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Cerrar y conciliar</h6>
                        <small class="text-muted">Al cerrar se calcula el estado final de cada pedido (Recibido / Faltante).</small>
                    </div>
                </div>
                <button class="btn btn-danger fw-bold px-3 ms-2 shadow-sm text-nowrap"
                        id="btnCerrarColecta">
                    <i class="bi bi-lock-fill me-1"></i>Cerrar y conciliar
                </button>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ═══ Columna der: tabla de pedidos ═══ -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light fw-bold d-flex align-items-center justify-content-between py-3">
                <span><i class="bi bi-list-task me-2 text-primary"></i>Pedidos de la colecta</span>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border font-monospace" id="lblTotalPedidos">
                        <?= count($pedidos) ?> pedidos
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaPedidos">
                        <thead class="table-light">
                            <tr class="small text-muted text-uppercase">
                                <th>Pedido</th>
                                <th>Destinatario</th>
                                <th>Resultado</th>
                                <th>Escaneado</th>
                                <th>Hora</th>
                                <?php if ($esAbierta): ?><th>Acción</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tbodyPedidos">
                        <?php if (empty($pedidos)): ?>
                            <tr id="trSinPedidos">
                                <td colspan="<?= $esAbierta ? '6' : '5' ?>" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox display-6 opacity-25 d-block mb-2"></i>
                                    Sin pedidos en esta colecta.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $p): ?>
                            <tr id="fila-pedido-<?= (int)$p['id_pedido'] ?>">
                                <td class="fw-bold font-monospace">
                                    <?= htmlspecialchars((string)($p['numero_orden'] ?? '#' . $p['id_pedido'])) ?>
                                </td>
                                <td class="small">
                                    <?= htmlspecialchars((string)($p['destinatario'] ?? '—')) ?>
                                </td>
                                <td>
                                    <?= badgeResultado($p['resultado_pedido'] ?? 'ESPERADO') ?>
                                </td>
                                <td>
                                    <?= isset($p['escaneado_at']) ? '<span class="text-success fw-bold">Si</span>' : '<span class="text-muted">No</span>' ?>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= isset($p['escaneado_at']) ? date('d/m/Y H:i:s', strtotime($p['escaneado_at'])) : '—' ?>
                                </td>
                                <?php if ($esAbierta): ?>
                                <td>
                                    <?php if (($p['resultado_pedido'] ?? '') === 'EXTRA'): ?>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-2 text-nowrap"
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

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
