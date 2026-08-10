<?php
/**
 * vista/modulos/logistica_operativa/custodias/index.php
 *
 * Módulo de Custodia Departamental y Traspaso entre Bodegas (Fase 8).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/CustodiaModel.php';
require_once __DIR__ . '/../../../../services/logistica_operativa/CustodiaService.php';
require_once __DIR__ . '/../../../../utils/logistica_permissions.php';

require_permission('logistica_operativa_bodega');

$custodiaService = new CustodiaService();
$db = (new Conexion())->conectar();

// Procesar traspasos o recepción en custodia
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $idResponsable = (int) ($_SESSION['id'] ?? 1);

    if ($accion === 'crear_traspaso') {
        $idPedido = (int) ($_POST['id_pedido'] ?? 0);
        $idBodegaOrigen = !empty($_POST['id_bodega_origen']) ? (int)$_POST['id_bodega_origen'] : null;
        $idDeptoDestino = !empty($_POST['id_departamento_destino']) ? (int)$_POST['id_departamento_destino'] : null;
        $obs = trim($_POST['observaciones'] ?? '');

        $custodiaService->registrarTraspaso($idPedido, $idBodegaOrigen, $idDeptoDestino, $idResponsable, $obs);
        set_flash('success', 'Traspaso a custodia registrado correctamente.');
        header('Location: ' . RUTA_URL . 'logistica-operativa/custodias');
        exit;
    }

    if ($accion === 'recibir_custodia') {
        $idCustodia = (int) ($_POST['id_custodia'] ?? 0);
        $custodiaService->recibirEnCustodia($idCustodia, $idResponsable, "Recibido físicamente en deposito de destino.");
        set_flash('success', 'Paquete marcado como RECIBIDO EN CUSTODIA.');
        header('Location: ' . RUTA_URL . 'logistica-operativa/custodias');
        exit;
    }
}

$custodias = $custodiaService->listarCustodias();
$bodegas = $db->query("SELECT id, nombre FROM logistica_bodegas ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$departamentos = $db->query("SELECT id, nombre FROM departamentos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$pedidosDisponibles = $db->query("SELECT id, numero_orden, destinatario FROM pedidos WHERE id_estado IN (1, 13) ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Custodia Departamental — Logística Operativa';
require_once __DIR__ . '/../../../../vista/includes/header.php';
?>

<div class="content-wrapper p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fas fa-building-user text-primary me-2"></i>Custodia Departamental</h2>
            <p class="text-muted mb-0">Gestión de traslados e inventario retenido en sucursales fuera de bodega central</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoTraspaso">
            <i class="fas fa-truck-ramp-box me-2"></i>Nuevo Traspaso
        </button>
    </div>

    <!-- Lista de Custodias -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3">
            <h5 class="card-title fw-bold mb-0"><i class="fas fa-boxes-packing me-2 text-primary"></i>Partidas de Custodia (<?= count($custodias) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>N° Orden / Pedido</th>
                            <th>Bodega Origen</th>
                            <th>Departamento Destino</th>
                            <th>Responsable</th>
                            <th>Estado</th>
                            <th>Fecha Registro</th>
                            <th class="text-end pe-3">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($custodias)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No hay traslados ni custodias activas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($custodias as $c): ?>
                                <tr>
                                    <td class="ps-3 fw-bold">#<?= $c['id'] ?></td>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($c['numero_orden'] ?? 'Ped #' . $c['id_pedido']) ?></td>
                                    <td><?= htmlspecialchars($c['bodega_origen_nombre'] ?? 'Bodega Central') ?></td>
                                    <td><i class="fas fa-map-pin text-danger me-1"></i><?= htmlspecialchars($c['departamento_destino_nombre'] ?? 'General') ?></td>
                                    <td><?= htmlspecialchars($c['responsable_nombre'] ?? 'Operador') ?></td>
                                    <td>
                                        <span class="badge <?= $c['estado'] === 'RECIBIDO_CUSTODIA' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                            <?= htmlspecialchars($c['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?= htmlspecialchars($c['created_at']) ?></td>
                                    <td class="text-end pe-3">
                                        <?php if ($c['estado'] === 'EN_TRANSITO'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="accion" value="recibir_custodia">
                                                <input type="hidden" name="id_custodia" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">
                                                    <i class="fas fa-box-open me-1"></i>Confirmar Recepción
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="fas fa-check-double text-success me-1"></i>Recepcionado</span>
                                        <?php endif; ?>
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

<!-- Modal Registrar Traspaso -->
<div class="modal fade" id="modalNuevoTraspaso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="accion" value="crear_traspaso">
            
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-truck-moving me-2"></i>Registrar Traspaso a Custodia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Seleccionar Pedido:</label>
                    <select class="form-select" name="id_pedido" required>
                        <?php foreach ($pedidosDisponibles as $p): ?>
                            <option value="<?= $p['id'] ?>">Orden <?= htmlspecialchars((string)($p['numero_orden'] ?? '')) ?> — <?= htmlspecialchars((string)($p['destinatario'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Bodega Origen:</label>
                    <select class="form-select" name="id_bodega_origen">
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Departamento Destino:</label>
                    <select class="form-select" name="id_departamento_destino" required>
                        <?php foreach ($departamentos as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Observaciones / Guía de Envió:</label>
                    <textarea class="form-control" name="observaciones" rows="3" placeholder="Detalles de la transferencia..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fas fa-paper-plane me-1"></i>Iniciar Traspaso</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
