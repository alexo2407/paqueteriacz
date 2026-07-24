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
$pedidos   = $resumen['pedidos'] ?? [];
$conteos   = $resumen['conteos'] ?? ['ESPERADO' => 0, 'RECIBIDO' => 0, 'FALTANTE' => 0, 'EXTRA' => 0];
$esAbierta = ($colecta['estado'] ?? '') === 'ABIERTA';

// ── CSRF token para escaneo y cierre ─────────────────────────────────────────
if (empty($_SESSION['csrf_token_colectas'])) {
    $_SESSION['csrf_token_colectas'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token_colectas'];

// ── Helpers badge ─────────────────────────────────────────────────────────────
function badgeResultado(string $resultado): string
{
    return match ($resultado) {
        'RECIBIDO'  => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Recibido</span>',
        'FALTANTE'  => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Faltante</span>',
        'EXTRA'     => '<span class="badge bg-warning text-dark"><i class="bi bi-plus-circle me-1"></i>Extra</span>',
        'ESPERADO'  => '<span class="badge bg-light text-dark border"><i class="bi bi-clock me-1"></i>Esperado</span>',
        default     => '<span class="badge bg-secondary">' . htmlspecialchars($resultado) . '</span>',
    };
}

$pageTitle = 'Colecta #' . $idColecta . ' — Detalle';
?>
<?php require_once __DIR__ . '/../../../../vista/includes/header.php'; ?>

<!-- ═══ Encabezado ═══ -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-collection me-2 text-warning"></i>Colecta #<?= $idColecta ?>
        </h1>
        <?php if ($colecta): ?>
        <small class="text-muted">
            <?= htmlspecialchars($colecta['fecha'] ?? '') ?>
            &mdash;
            <?= $colecta['turno'] === 'MANANA' ? '<i class="bi bi-sun text-warning"></i> Mañana' : '<i class="bi bi-moon text-primary"></i> Tarde' ?>
            &mdash;
            Cliente: <strong><?= htmlspecialchars($colecta['cliente_nombre'] ?? $colecta['id_cliente'] ?? '—') ?></strong>
        </small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?php if ($esAbierta): ?>
        <span class="badge bg-success fs-6 px-3 py-2">ABIERTA</span>
        <?php else: ?>
        <span class="badge bg-secondary fs-6 px-3 py-2">CONCILIADA</span>
        <?php endif; ?>
        <a href="<?= RUTA_URL ?>logistica-operativa/colectas"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?php if ($errorCarga): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($errorCarga) ?></div>
<?php endif; ?>

<!-- ═══ Cards de resumen ═══ -->
<div class="row g-3 mb-4">
    <!-- Esperados -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="display-6 fw-bold text-secondary" id="cntEsperado">
                    <?= (int)($conteos['ESPERADO'] ?? 0) ?>
                </div>
                <small class="text-muted fw-semibold text-uppercase">Esperados</small>
            </div>
        </div>
    </div>
    <!-- Recibidos -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #198754!important">
            <div class="card-body text-center py-3">
                <div class="display-6 fw-bold text-success" id="cntRecibido">
                    <?= (int)($conteos['RECIBIDO'] ?? 0) ?>
                </div>
                <small class="text-muted fw-semibold text-uppercase">Recibidos</small>
            </div>
        </div>
    </div>
    <!-- Faltantes -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #dc3545!important">
            <div class="card-body text-center py-3">
                <div class="display-6 fw-bold text-danger" id="cntFaltante">
                    <?= (int)($conteos['FALTANTE'] ?? 0) ?>
                </div>
                <small class="text-muted fw-semibold text-uppercase">Faltantes</small>
            </div>
        </div>
    </div>
    <!-- Extras -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #ffc107!important">
            <div class="card-body text-center py-3">
                <div class="display-6 fw-bold text-warning" id="cntExtra">
                    <?= (int)($conteos['EXTRA'] ?? 0) ?>
                </div>
                <small class="text-muted fw-semibold text-uppercase">Extras</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- ═══ Columna izq: escaneo ═══ -->
    <div class="col-12 col-lg-5">

        <!-- Área de escaneo -->
        <div class="card border-0 shadow-sm mb-4 <?= !$esAbierta ? 'opacity-75' : '' ?>">
            <div class="card-header fw-semibold"
                 style="background:linear-gradient(135deg,#061C4C,#0B4EA2);color:#fff">
                <i class="bi bi-upc-scan me-2"></i>Escaneo de paquetes
            </div>
            <div class="card-body">
                <?php if (!$esAbierta): ?>
                <div class="alert alert-secondary text-center mb-3">
                    <i class="bi bi-lock-fill me-2"></i>
                    Esta colecta está <strong>CONCILIADA</strong>. No se admiten más escaneos.
                </div>
                <?php else: ?>
                <p class="small text-muted mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Escanea o escribe el código del paquete y presiona <kbd>Enter</kbd>.
                    El lector USB actúa como teclado automáticamente.
                </p>
                <?php endif; ?>

                <!-- Campo de escaneo -->
                <div class="input-group">
                    <span class="input-group-text bg-light">
                        <i class="bi bi-upc"></i>
                    </span>
                    <input type="text"
                           id="inputEscaneo"
                           class="form-control form-control-lg fw-mono"
                           placeholder="Código del paquete..."
                           autocomplete="off"
                           autofocus
                           <?= !$esAbierta ? 'disabled' : '' ?>>
                    <button class="btn btn-primary"
                            type="button"
                            id="btnEscanear"
                            <?= !$esAbierta ? 'disabled' : '' ?>>
                        <i class="bi bi-lightning-fill me-1"></i>Enviar
                    </button>
                </div>

                <!-- Resultado del escaneo -->
                <div id="resultadoEscaneo" class="mt-3 d-none"></div>

                <!-- Historial de escaneos recientes (en sesión de vista) -->
                <div id="historialEscaneo" class="mt-3">
                    <small class="text-muted">
                        <i class="bi bi-clock-history me-1"></i>Escaneos recientes de esta sesión:
                    </small>
                    <ul class="list-group list-group-flush mt-1" id="listaHistorial" style="max-height:180px;overflow-y:auto">
                        <li class="list-group-item text-muted small text-center py-2">
                            Sin escaneos aún.
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Botón cerrar colecta -->
        <?php if ($esAbierta): ?>
        <div class="card border-0 shadow-sm border-danger-subtle">
            <div class="card-body">
                <h6 class="fw-semibold text-danger mb-2">
                    <i class="bi bi-door-closed me-2"></i>Cerrar y conciliar
                </h6>
                <p class="small text-muted mb-3">
                    Al cerrar se calcula el estado final de cada pedido
                    (Recibido / Faltante). Esta acción no modifica pedidos, inventario ni stock.
                </p>
                <button class="btn btn-danger w-100 fw-semibold"
                        id="btnCerrarColecta">
                    <i class="bi bi-check2-all me-1"></i>Cerrar y conciliar
                </button>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ═══ Columna der: tabla de pedidos ═══ -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold bg-light d-flex justify-content-between">
                <span><i class="bi bi-list-check me-2"></i>Pedidos de la colecta</span>
                <small class="text-muted" id="lblTotalPedidos">
                    <?= count($pedidos) ?> pedidos
                </small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="tablaPedidos">
                        <thead class="table-light">
                            <tr>
                                <th>Pedido</th>
                                <th>Destinatario</th>
                                <th>Resultado</th>
                                <th>Escaneado</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyPedidos">
                        <?php if (empty($pedidos)): ?>
                            <tr id="trSinPedidos">
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox display-6 opacity-25 d-block mb-2"></i>
                                    Sin pedidos en esta colecta.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $p): ?>
                            <tr id="fila-pedido-<?= (int)$p['id_pedido'] ?>">
                                <td class="fw-semibold">
                                    <?= htmlspecialchars($p['numero_orden'] ?? '#' . $p['id_pedido']) ?>
                                </td>
                                <td class="small">
                                    <?= htmlspecialchars($p['destinatario'] ?? '—') ?>
                                </td>
                                <td>
                                    <?= badgeResultado($p['resultado_pedido'] ?? 'ESPERADO') ?>
                                </td>
                                <td class="small text-muted">
                                    <?= isset($p['escaneado_at']) ? date('d/m H:i', strtotime($p['escaneado_at'])) : '—' ?>
                                </td>
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
<script src="<?= RUTA_URL ?>vista/modulos/logistica_operativa/colectas/js/colectas.js?v=<?= filemtime(__FILE__) ?>"></script>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
