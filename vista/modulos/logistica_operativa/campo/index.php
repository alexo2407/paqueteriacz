<?php
/**
 * vista/modulos/logistica_operativa/campo/index.php
 *
 * Web App Móvil del Repartidor en Campo (Fase 6).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/RutaModel.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/CampoModel.php';
require_once __DIR__ . '/../../../../services/logistica_operativa/CampoService.php';
require_once __DIR__ . '/../../../../utils/logistica_permissions.php';

require_permission('logistica_operativa_rutas');

$campoService = new CampoService();
$db = (new Conexion())->conectar();

// Procesamiento de entregas / incidencias vía POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $idRuta = (int) ($_POST['id_ruta'] ?? 0);
    $idPedido = (int) ($_POST['id_pedido'] ?? 0);

    if ($accion === 'marcar_entregado') {
        $firma = $_POST['firma_base64'] ?? null;
        $foto = $_POST['foto_url'] ?? null;
        $lat = !empty($_POST['lat']) ? (float)$_POST['lat'] : null;
        $lng = !empty($_POST['lng']) ? (float)$_POST['lng'] : null;
        $notas = trim($_POST['notas'] ?? '');

        $campoService->completarEntrega($idRuta, $idPedido, $firma, $foto, $lat, $lng, $notas);
        set_flash('success', 'Pedido #' . $idPedido . ' marcado como ENTREGADO en campo.');
        header('Location: ' . RUTA_URL . 'logistica-operativa/campo?ruta_id=' . $idRuta);
        exit;
    }

    if ($accion === 'marcar_incidencia') {
        $tipo = $_POST['tipo_incidencia'] ?? 'Otro';
        $notas = trim($_POST['notas'] ?? '');
        $lat = !empty($_POST['lat']) ? (float)$_POST['lat'] : null;
        $lng = !empty($_POST['lng']) ? (float)$_POST['lng'] : null;

        $campoService->reportarIncidencia($idRuta, $idPedido, $tipo, $notas, $lat, $lng);
        set_flash('warning', 'Incidencia registrada para el Pedido #' . $idPedido);
        header('Location: ' . RUTA_URL . 'logistica-operativa/campo?ruta_id=' . $idRuta);
        exit;
    }
}

// Obtener rutas activas
$rutasActivas = $db->query("
    SELECT r.*, rep.nombre as repartidor_nombre
    FROM logistica_rutas r
    LEFT JOIN usuarios rep ON rep.id = r.id_repartidor
    WHERE r.estado IN ('SELLADA', 'ASIGNADA', 'EN_RUTA')
    ORDER BY r.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$rutaSeleccionadaId = (int) ($_GET['ruta_id'] ?? ($rutasActivas[0]['id'] ?? 0));
$hojaRuta = null;

if ($rutaSeleccionadaId > 0) {
    try {
        $hojaRuta = $campoService->obtenerHojaDeRuta($rutaSeleccionadaId);
    } catch (Exception $e) {
        $hojaRuta = null;
    }
}

$pageTitle = 'Campo — Seguimiento en Ruta';
require_once __DIR__ . '/../../../../vista/includes/header.php';
?>

<div class="content-wrapper p-3 p-md-4 bg-light">
    <!-- Banner Móvil -->
    <div class="card border-0 shadow-sm rounded-4 bg-gradient-primary text-white mb-4 overflow-hidden" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-white text-primary rounded-pill mb-2 px-3 fw-bold">Modo Repartidor Mobile</span>
                    <h3 class="fw-bold mb-1"><i class="fas fa-truck-ramp-box me-2"></i>Entregas en Campo</h3>
                    <p class="mb-0 opacity-75 small">Firma digital, evidencias de foto y geolocalización en sitio</p>
                </div>
                <div class="d-none d-md-block text-end">
                    <i class="fas fa-mobile-screen-button fa-4x opacity-25"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Selector de Ruta y Escáner QR -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-8">
                    <label class="form-label fw-bold text-dark mb-1"><i class="fas fa-route text-primary me-2"></i>Seleccionar Ruta de Reparto:</label>
                    <select class="form-select form-select-lg rounded-3 border-primary" onchange="location.href='<?= RUTA_URL ?>logistica-operativa/campo?ruta_id=' + this.value">
                        <option value="">-- Seleccionar Ruta --</option>
                        <?php foreach ($rutasActivas as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $r['id'] === $rutaSeleccionadaId ? 'selected' : '' ?>>
                                Ruta <?= htmlspecialchars($r['codigo']) ?> — <?= htmlspecialchars($r['nombre']) ?> (<?= htmlspecialchars($r['repartidor_nombre'] ?? 'Sin asignar') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold text-dark mb-1 d-none d-md-block">&nbsp;</label>
                    <input type="hidden" id="inputScanCampo">
                    <button type="button" class="btn btn-dark btn-lg w-100 fw-bold rounded-3 shadow-sm" id="btnScanCampoQR">
                        <i class="bi bi-qr-code-scan text-info me-2"></i>Escanear QR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($hojaRuta && !empty($hojaRuta['pedidos'])): ?>
        <div class="row g-3">
            <?php foreach ($hojaRuta['pedidos'] as $index => $p): ?>
                <?php 
                    $est = $p['estado_entrega'] ?? 'PENDIENTE';
                    $badgeClass = $est === 'ENTREGADO' ? 'bg-success' : ($est === 'INCIDENCIA' ? 'bg-warning text-dark' : 'bg-secondary');
                ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3 h-100 position-relative overflow-hidden">
                        <div class="position-absolute top-0 start-0 h-100 <?= $est === 'ENTREGADO' ? 'bg-success' : ($est === 'INCIDENCIA' ? 'bg-warning' : 'bg-primary') ?>" style="width: 5px;"></div>
                        <div class="card-body p-3 ps-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-light text-dark border font-monospace">#<?= $index + 1 ?> — Orden <?= htmlspecialchars((string)($p['numero_orden'] ?? $p['id_pedido'])) ?></span>
                                <span class="badge <?= $badgeClass ?> rounded-pill px-3"><?= $est ?></span>
                            </div>

                            <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($p['destinatario'] ?? 'Cliente') ?></h5>
                            <p class="text-muted small mb-2"><i class="fas fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($p['direccion'] ?? 'Sin dirección') ?></p>
                            <p class="text-muted small mb-3"><i class="fas fa-phone text-success me-1"></i><?= htmlspecialchars($p['telefono'] ?? 'Sin tel') ?></p>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="fw-bold text-success fs-5">C$ <?= number_format((float)($p['monto_cod'] ?? 0), 2) ?></span>
                                
                                <?php if ($est === 'PENDIENTE'): ?>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-success rounded-pill px-3 me-1 btn-entregar-modal"
                                                data-ruta="<?= $rutaSeleccionadaId ?>"
                                                data-pedido="<?= $p['id_pedido'] ?>"
                                                data-destinatario="<?= htmlspecialchars($p['destinatario'] ?? '') ?>">
                                            <i class="fas fa-check me-1"></i>Entregar
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning rounded-pill px-3 btn-incidencia-modal"
                                                data-ruta="<?= $rutaSeleccionadaId ?>"
                                                data-pedido="<?= $p['id_pedido'] ?>">
                                            <i class="fas fa-triangle-exclamation"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small"><i class="fas fa-lock me-1"></i>Completado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ($rutaSeleccionadaId > 0): ?>
        <div class="card border-0 shadow-sm rounded-3 py-5 text-center text-muted">
            <i class="fas fa-box-open fa-3x mb-3 text-secondary opacity-50"></i>
            <h5>No hay paquetes asignados a esta ruta.</h5>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Entregar en Campo -->
<div class="modal fade" id="modalEntregarCampo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow rounded-4">
            <input type="hidden" name="accion" value="marcar_entregado">
            <input type="hidden" name="id_ruta" id="ent_id_ruta">
            <input type="hidden" name="id_pedido" id="ent_id_pedido">
            <input type="hidden" name="firma_base64" id="ent_firma_base64">
            <input type="hidden" name="lat" id="ent_lat">
            <input type="hidden" name="lng" id="ent_lng">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-signature me-2"></i>Confirmar Entrega en Sitio</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3 text-muted">Destinatario: <strong id="ent_destinatario" class="text-dark"></strong></p>

                <!-- Panel de Firma Digital Canvas -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Firma Digital del Cliente:</label>
                    <div class="border rounded-3 bg-white text-center position-relative overflow-hidden" style="height: 160px; touch-action: none;">
                        <canvas id="canvasFirma" style="width: 100%; height: 100%; cursor: crosshair;"></canvas>
                        <button type="button" class="btn btn-sm btn-outline-secondary position-absolute bottom-0 end-0 m-2" id="btnLimpiarFirma">Limpiar</button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">URL Foto Evidencia (Opcional):</label>
                    <input type="url" class="form-control" name="foto_url" placeholder="https://...">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Notas de Entrega:</label>
                    <input type="text" class="form-control" name="notas" placeholder="Ej: Recibido por familiar en puerta">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" id="btnSubmitEntregar"><i class="fas fa-check-double me-1"></i>Finalizar Entrega</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Incidencia en Campo -->
<div class="modal fade" id="modalIncidenciaCampo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow rounded-4">
            <input type="hidden" name="accion" value="marcar_incidencia">
            <input type="hidden" name="id_ruta" id="inc_id_ruta">
            <input type="hidden" name="id_pedido" id="inc_id_pedido">

            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="fas fa-triangle-exclamation me-2"></i>Reportar Incidencia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Motivo de la Incidencia:</label>
                    <select class="form-select" name="tipo_incidencia" required>
                        <option value="Domicilio Cerrado">Domicilio Cerrado</option>
                        <option value="No responde llamada">No responde llamada</option>
                        <option value="Cliente rechaza paquete">Cliente rechaza paquete</option>
                        <option value="Dirección incorrecta">Dirección incorrecta</option>
                        <option value="Sin efectivo disponible">Sin efectivo disponible</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Detalles adicionales:</label>
                    <textarea class="form-control" name="notas" rows="3" placeholder="Explicación de lo ocurrido..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold"><i class="fas fa-save me-1"></i>Guardar Incidencia</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalEnt = new bootstrap.Modal(document.getElementById('modalEntregarCampo'));
    const modalInc = new bootstrap.Modal(document.getElementById('modalIncidenciaCampo'));

    // Canvas Firma
    const canvas = document.getElementById('canvasFirma');
    const ctx = canvas.getContext('2d');
    let dibu = false;

    function resizeCanvas() {
        canvas.width = canvas.parentElement.clientWidth;
        canvas.height = canvas.parentElement.clientHeight;
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#0d6efd';
    }
    resizeCanvas();

    canvas.addEventListener('mousedown', () => dibu = true);
    canvas.addEventListener('mouseup', () => { dibu = false; ctx.beginPath(); });
    canvas.addEventListener('mousemove', (e) => {
        if (!dibu) return;
        const rect = canvas.getBoundingClientRect();
        ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
        ctx.stroke();
    });

    document.getElementById('btnLimpiarFirma').addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    });

    document.querySelectorAll('.btn-entregar-modal').forEach(b => {
        b.addEventListener('click', () => {
            document.getElementById('ent_id_ruta').value = b.dataset.ruta;
            document.getElementById('ent_id_pedido').value = b.dataset.pedido;
            document.getElementById('ent_destinatario').textContent = b.dataset.destinatario;
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // Obtener GPS
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(pos => {
                    document.getElementById('ent_lat').value = pos.coords.latitude;
                    document.getElementById('ent_lng').value = pos.coords.longitude;
                });
            }
            modalEnt.show();
        });
    });

    document.querySelectorAll('.btn-incidencia-modal').forEach(b => {
        b.addEventListener('click', () => {
            document.getElementById('inc_id_ruta').value = b.dataset.ruta;
            document.getElementById('inc_id_pedido').value = b.dataset.pedido;
            modalInc.show();
        });
    });

    document.getElementById('btnSubmitEntregar').addEventListener('click', () => {
        document.getElementById('ent_firma_base64').value = canvas.toDataURL();
    });

    const btnScanCampoQR = document.getElementById('btnScanCampoQR');
    if (btnScanCampoQR) {
        btnScanCampoQR.addEventListener('click', () => {
            if (typeof window.abrirScannerQR === 'function') {
                window.abrirScannerQR({
                    targetInputId: 'inputScanCampo',
                    onScanSuccess: (codigoLeido) => {
                        const targetBtn = document.querySelector(`.btn-entregar-modal[data-pedido="${codigoLeido}"]`);
                        if (targetBtn) {
                            targetBtn.click();
                        } else {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ icon: 'info', title: 'Paquete escaneado: ' + codigoLeido, text: 'Verifica si corresponde a un paquete asignado en la lista.' });
                            }
                        }
                    }
                });
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../partials/qr_scanner_modal.php'; ?>
<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
