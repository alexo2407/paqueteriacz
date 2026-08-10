<?php
/**
 * vista/modulos/logistica_operativa/liquidaciones/index.php
 *
 * Módulo de Liquidación de Rutas y Arqueo Financiero (Fase 7).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/RutaModel.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/LiquidacionModel.php';
require_once __DIR__ . '/../../../../services/logistica_operativa/LiquidacionService.php';
require_once __DIR__ . '/../../../../utils/logistica_permissions.php';

require_permission('logistica_operativa_rutas');

$liquidacionService = new LiquidacionService();

// Manejo de POST para liquidar una ruta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'liquidar_ruta') {
    try {
        $idRuta = (int) ($_POST['id_ruta'] ?? 0);
        $montoRecibido = (float) ($_POST['monto_recibido'] ?? 0);
        $obs = trim($_POST['observaciones'] ?? '');
        $idOperador = (int) ($_SESSION['id'] ?? 1);

        $idLiq = $liquidacionService->liquidarRuta($idRuta, $idOperador, $montoRecibido, $obs);
        set_flash('success', 'Ruta liquidada correctamente (ID Liquidación: ' . $idLiq . ').');
        header('Location: ' . RUTA_URL . 'logistica-operativa/liquidaciones');
        exit;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

$db = (new Conexion())->conectar();
$rutaModel = new RutaModel($db);

$rutasPorLiquidar = $db->query("
    SELECT r.*, rep.nombre as repartidor_nombre
    FROM logistica_rutas r
    LEFT JOIN usuarios rep ON rep.id = r.id_repartidor
    LEFT JOIN logistica_liquidaciones l ON l.id_ruta = r.id
    WHERE l.id IS NULL AND r.estado IN ('SELLADA', 'EN_RUTA', 'ASIGNADA')
    ORDER BY r.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$liquidacionesHistorial = $liquidacionService->obtenerLiquidaciones();

$pageTitle = 'Liquidación de Rutas — Logística Operativa';
require_once __DIR__ . '/../../../../vista/includes/header.php';
?>

<div class="content-wrapper p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fas fa-calculator text-primary me-2"></i>Liquidación de Rutas</h2>
            <p class="text-muted mb-0">Arqueo financiero COD y conciliación de entregas por ruta</p>
        </div>
    </div>

    <?php if (isset($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($errorMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Seccion 1: Rutas Pendientes por Liquidar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title fw-bold mb-0 text-primary">
                <i class="fas fa-hourglass-half me-2"></i>Rutas Pendientes por Liquidar (<?= count($rutasPorLiquidar) ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">Código</th>
                            <th>Nombre Ruta</th>
                            <th>Repartidor</th>
                            <th>Fecha</th>
                            <th>Pedidos</th>
                            <th>Total COD Esperado</th>
                            <th class="text-end pe-3">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rutasPorLiquidar)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-check-circle fa-2x mb-2 d-block text-success"></i>
                                    No hay rutas pendientes de liquidar.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rutasPorLiquidar as $r): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($r['codigo']) ?></td>
                                    <td><?= htmlspecialchars($r['nombre']) ?></td>
                                    <td><i class="fas fa-motorcycle text-muted me-1"></i><?= htmlspecialchars($r['repartidor_nombre'] ?? 'Sin asignar') ?></td>
                                    <td><?= htmlspecialchars($r['fecha']) ?></td>
                                    <td><span class="badge bg-secondary"><?= (int)$r['cantidad_pedidos'] ?> paq.</span></td>
                                    <td class="fw-bold text-success">C$ <?= number_format((float)$r['total_cod'], 2) ?></td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-primary rounded-pill px-3 btn-liquidar-modal"
                                                data-id="<?= $r['id'] ?>"
                                                data-codigo="<?= htmlspecialchars($r['codigo']) ?>"
                                                data-cod="<?= (float)$r['total_cod'] ?>">
                                            <i class="fas fa-hand-holding-usd me-1"></i>Liquidar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Seccion 2: Historial de Liquidaciones -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3">
            <h5 class="card-title fw-bold mb-0">
                <i class="fas fa-history me-2"></i>Historial de Liquidaciones
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Ruta</th>
                            <th>Operador</th>
                            <th>COD Esperado</th>
                            <th>COD Recibido</th>
                            <th>Diferencia</th>
                            <th>Estado</th>
                            <th>Fecha Liquidación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($liquidacionesHistorial)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No hay liquidaciones registradas en el historial.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($liquidacionesHistorial as $l): ?>
                                <tr>
                                    <td class="ps-3 fw-bold">#<?= $l['id'] ?></td>
                                    <td><?= htmlspecialchars($l['codigo_ruta'] ?? 'Ruta') ?></td>
                                    <td><?= htmlspecialchars($l['operador_nombre'] ?? 'Sistema') ?></td>
                                    <td>C$ <?= number_format((float)$l['total_cod_esperado'], 2) ?></td>
                                    <td class="fw-bold">C$ <?= number_format((float)$l['total_cod_recibido'], 2) ?></td>
                                    <td>
                                        <?php $dif = (float)$l['diferencia']; ?>
                                        <span class="badge <?= $dif == 0 ? 'bg-success' : ($dif > 0 ? 'bg-info' : 'bg-danger') ?>">
                                            C$ <?= number_format($dif, 2) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $l['estado'] === 'LIQUIDADA' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                            <?= $l['estado'] ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?= htmlspecialchars($l['liquidado_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Liquidador -->
<div class="modal fade" id="modalLiquidarRuta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="accion" value="liquidar_ruta">
            <input type="hidden" name="id_ruta" id="liq_id_ruta">
            
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-cash-register me-2"></i>Liquidar Ruta <span id="liq_codigo_ruta"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3 p-3 bg-light rounded-3 border">
                    <span class="text-muted d-block small">Monto COD Esperado:</span>
                    <h4 class="fw-bold text-success mb-0">C$ <span id="liq_cod_esperado">0.00</span></h4>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Monto Efectivo Recibido (COD):</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white">C$</span>
                        <input type="number" step="0.01" min="0" class="form-control fw-bold" name="monto_recibido" id="liq_monto_recibido" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Observaciones / Justificación de Diferencia:</label>
                    <textarea class="form-control" name="observaciones" rows="3" placeholder="Notas opcionales del arqueo de caja..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fas fa-check-circle me-1"></i>Confirmar Liquidación</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('modalLiquidarRuta');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);

    document.querySelectorAll('.btn-liquidar-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('liq_id_ruta').value = btn.dataset.id;
            document.getElementById('liq_codigo_ruta').textContent = btn.dataset.codigo;
            document.getElementById('liq_cod_esperado').textContent = parseFloat(btn.dataset.cod).toFixed(2);
            document.getElementById('liq_monto_recibido').value = parseFloat(btn.dataset.cod).toFixed(2);
            modal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
