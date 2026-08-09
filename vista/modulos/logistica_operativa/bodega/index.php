<?php
/**
 * vista/modulos/logistica_operativa/bodega/index.php
 *
 * Panel operativo de Bodega — Logística Operativa.
 *
 * Permite a un operador autorizado:
 *   - Buscar un paquete (ID o número de orden).
 *   - Ver su información y ubicación física actual.
 *   - Registrar recepción, asignar ubicación, reubicar y retirar.
 *   - Consultar el historial completo de movimientos físicos.
 *
 * Seguridad:
 *   - Incluida desde rutas/logistica_bodega.php (sesión/permiso/flags ya verificados).
 *   - id_operador se obtiene del JWT en el endpoint, nunca del formulario ni del JS.
 *   - CSRF token generado en sesión.
 *   - Datos renderizados con htmlspecialchars().
 *
 * PENDIENTE (Fase 4): migrar a permiso formal 'logistica_operativa_bodega'.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';

// ── CSRF token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token_bodega'])) {
    $_SESSION['csrf_token_bodega'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token_bodega'];

$pageTitle = 'Bodega — Logística Operativa';
?>
<?php require_once __DIR__ . '/../../../../vista/includes/header.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-archive-fill me-2 text-primary"></i>Bodega y Ubicación
        </h1>
        <small class="text-muted">Recepción, ubicación, reubicación y trazabilidad física de paquetes</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-warning text-dark">
            <i class="bi bi-shield-check me-1"></i>Modo sombra activo
        </span>
    </div>
</div>

<!-- ═══ 4 Tarjetas KPI Superiores (Bodega) ═══ -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-circle kpi-icon-green me-3">
                    <i class="bi bi-box-arrow-in-down"></i>
                </div>
                <div>
                    <div class="h3 mb-0 fw-bold">126</div>
                    <div class="fw-semibold text-success small">Recibidos hoy</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Paquetes ingresados</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-circle kpi-icon-yellow me-3">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="h3 mb-0 fw-bold">18</div>
                    <div class="fw-semibold text-warning small">Pendientes de ubicación</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Esperando ser ubicados</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-circle kpi-icon-red me-3">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="h3 mb-0 fw-bold text-danger">7</div>
                    <div class="fw-semibold text-danger small">En incidencia</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Requieren atención</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-circle kpi-icon-purple me-3">
                    <i class="bi bi-box-arrow-up"></i>
                </div>
                <div>
                    <div class="h3 mb-0 fw-bold">32</div>
                    <div class="fw-semibold text-purple small" style="color:#9333ea;">Retirados hoy</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Paquetes entregados</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Buscador ═══ -->
<?php require_once __DIR__ . '/partials/buscador.php'; ?>

<!-- ═══ Panel de paquete (oculto hasta búsqueda) ═══ -->
<div id="panelPaquete" class="d-none">

    <!-- ── Info del paquete ── -->
    <div class="card border-0 shadow-sm mb-4" id="cardInfoPaquete">
        <div class="card-header d-flex align-items-center gap-2"
             style="background:linear-gradient(135deg,#061C4C,#0B4EA2);">
            <i class="bi bi-box-seam text-white"></i>
            <span class="fw-semibold text-white">Información del paquete</span>
            <span class="ms-auto badge bg-light text-dark" id="badgeEstadoPedido"></span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="text-muted small fw-semibold">ID Pedido</div>
                    <div class="fw-bold" id="infoPedidoId">—</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small fw-semibold">N.° Orden</div>
                    <div class="fw-bold font-monospace" id="infoPedidoOrden">—</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small fw-semibold">Destinatario</div>
                    <div id="infoPedidoDestinatario">—</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small fw-semibold">Teléfono</div>
                    <div class="font-monospace text-muted" id="infoPedidoTelefono">—</div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="text-muted small fw-semibold">Municipio</div>
                    <div id="infoPedidoMunicipio">—</div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="text-muted small fw-semibold">Estado logístico</div>
                    <div id="infoPedidoEstadoLogistico">—</div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="text-muted small fw-semibold">Ingreso</div>
                    <div id="infoPedidoFecha">—</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Ubicación actual + acciones ── -->
    <div class="row g-4 mb-4">

        <!-- Card: ubicación actual -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm h-100" id="cardUbicacion">
                <div class="card-header d-flex align-items-center gap-2"
                     style="background:linear-gradient(135deg,#1B5E20,#2E7D32);">
                    <i class="bi bi-geo-alt-fill text-white"></i>
                    <span class="fw-semibold text-white">Ubicación física actual</span>
                    <span class="ms-auto badge" id="badgeUbicado"></span>
                </div>
                <div class="card-body">
                    <!-- Sin ubicación -->
                    <div id="sinUbicacion" class="text-center py-4 d-none">
                        <i class="bi bi-geo-alt display-5 text-muted opacity-50 d-block mb-2"></i>
                        <p class="text-muted mb-0">Este paquete no tiene una ubicación física activa.</p>
                    </div>
                    <!-- Con ubicación -->
                    <div id="conUbicacion" class="d-none">
                        <div class="text-center mb-3">
                            <span class="font-monospace fw-bold fs-5 text-primary" id="ubicNomenclatura">—</span>
                        </div>
                        <div class="row g-2 small">
                            <div class="col-6">
                                <span class="text-muted">Bodega:</span>
                                <strong id="ubicBodega">—</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Código:</span>
                                <span class="font-monospace" id="ubicCodigo">—</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Zona:</span>
                                <span id="ubicZona">—</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Pasillo:</span>
                                <span id="ubicPasillo">—</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Estante:</span>
                                <span id="ubicEstante">—</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Cajón:</span>
                                <span id="ubicCajon">—</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Nivel:</span>
                                <span id="ubicNivel">—</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Tipo:</span>
                                <span id="ubicTipo">—</span>
                            </div>
                            <div class="col-12">
                                <span class="text-muted">Ingresado:</span>
                                <span id="ubicFechaIngreso">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card: acciones disponibles -->
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header d-flex align-items-center gap-2"
                     style="background:linear-gradient(135deg,#4A148C,#6A1B9A);">
                    <i class="bi bi-lightning-fill text-white"></i>
                    <span class="fw-semibold text-white">Acciones disponibles</span>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <div id="accionesContainer">
                        <!-- Generado por JS según el estado del paquete -->
                        <p class="text-muted text-center small pt-3">Cargando estado…</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Historial de movimientos ── -->
    <?php require_once __DIR__ . '/partials/historial.php'; ?>

</div><!-- /panelPaquete -->

<!-- ═══ Modales ═══ -->
<?php require_once __DIR__ . '/partials/modal_recepcion.php'; ?>
<?php require_once __DIR__ . '/partials/modal_ubicar.php'; ?>
<?php require_once __DIR__ . '/partials/modal_reubicar.php'; ?>
<?php require_once __DIR__ . '/partials/modal_retirar.php'; ?>

<!-- ═══ Variables para JS ═══ -->
<script>
/* global del módulo — sin datos sensibles, sin tokens */
const CSRF_BODEGA      = '<?= $csrfToken ?>';
const RUTA_BODEGA_BASE = '<?= RUTA_URL ?>api/logistica-operativa/bodega/';
</script>
<script src="<?= RUTA_URL ?>vista/modulos/logistica_operativa/bodega/js/bodega.js?v=<?= filemtime(__FILE__) ?>"></script>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
