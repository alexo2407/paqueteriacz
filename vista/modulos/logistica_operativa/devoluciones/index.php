<?php
/**
 * vista/modulos/logistica_operativa/devoluciones/index.php
 *
 * Logística Inversa y Devoluciones a Clientes/Comercios (Fase 9).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/DevolucionModel.php';
require_once __DIR__ . '/../../../../services/logistica_operativa/DevolucionService.php';
require_once __DIR__ . '/../../../../utils/logistica_permissions.php';

require_permission('logistica_operativa_bodega');

$devolucionService = new DevolucionService();
$db = (new Conexion())->conectar();

// Manejo de creación o entrega de manifiesto de devolución
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $idOperador = (int) ($_SESSION['id'] ?? 1);

    if ($accion === 'crear_devolucion') {
        $idCliente = (int) ($_POST['id_cliente'] ?? 0);
        $fecha = $_POST['fecha_devolucion'] ?? date('Y-m-d');
        $pedidosSel = $_POST['pedidos_ids'] ?? [];
        $obs = trim($_POST['observaciones'] ?? '');

        if ($idCliente > 0 && !empty($pedidosSel)) {
            $idDev = $devolucionService->crearManifiesto($idCliente, null, $idOperador, $fecha, $pedidosSel, $obs);
            set_flash('success', 'Manifiesto de Devolución creado correctamente.');
            header('Location: ' . RUTA_URL . 'logistica-operativa/devoluciones');
            exit;
        }
    }

    if ($accion === 'entregar_cliente') {
        $idDev = (int) ($_POST['id_devolucion'] ?? 0);
        $firma = $_POST['firma_base64'] ?? null;

        $devolucionService->entregarACliente($idDev, $firma);
        set_flash('success', 'Manifiesto de devolución ENTREGADO al cliente.');
        header('Location: ' . RUTA_URL . 'logistica-operativa/devoluciones');
        exit;
    }
}

$devoluciones = $devolucionService->listarDevoluciones();
$clientes = $db->query("SELECT id, nombre, email FROM usuarios WHERE id IN (SELECT DISTINCT id_cliente FROM pedidos) ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$pedidosDevolver = $db->query("SELECT id, numero_orden, destinatario, id_cliente FROM pedidos WHERE id_estado IN (7, 9, 15, 16) ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Devoluciones — Logística Operativa';
require_once __DIR__ . '/../../../../vista/includes/header.php';
?>

<div class="content-wrapper p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fas fa-rotate-left text-primary me-2"></i>Logística Inversa y Devoluciones</h2>
            <p class="text-muted mb-0">Retorno físico de paquetes devueltos y rechazados a clientes de origen</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaDevolucion">
            <i class="fas fa-file-invoice me-2"></i>Crear Manifiesto de Devolución
        </button>
    </div>

    <!-- Tabla de Devoluciones -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3">
            <h5 class="card-title fw-bold mb-0"><i class="fas fa-boxes-packing me-2 text-primary"></i>Manifiestos de Devolución (<?= count($devoluciones) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">Código Manifiesto</th>
                            <th>Cliente Origen</th>
                            <th>Total Paquetes</th>
                            <th>Operador</th>
                            <th>Fecha Devolución</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($devoluciones)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No hay manifiestos de devolución registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($devoluciones as $d): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($d['codigo_manifiesto']) ?></td>
                                    <td><i class="fas fa-user-tie text-muted me-1"></i><?= htmlspecialchars($d['cliente_nombre'] ?? 'Cliente #' . $d['id_cliente']) ?></td>
                                    <td><span class="badge bg-secondary"><?= (int)$d['total_paquetes'] ?> paq.</span></td>
                                    <td><?= htmlspecialchars($d['operador_nombre'] ?? 'Sistema') ?></td>
                                    <td><?= htmlspecialchars($d['fecha_devolucion']) ?></td>
                                    <td>
                                        <span class="badge <?= $d['estado'] === 'ENTREGADO_CLIENTE' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                            <?= htmlspecialchars($d['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <?php if ($d['estado'] !== 'ENTREGADO_CLIENTE'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="accion" value="entregar_cliente">
                                                <input type="hidden" name="id_devolucion" value="<?= $d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">
                                                    <i class="fas fa-handshake me-1"></i>Entregar a Cliente
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="fas fa-check-circle text-success me-1"></i>Completado</span>
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

<!-- Modal Nuevo Manifiesto Devolucion -->
<div class="modal fade" id="modalNuevaDevolucion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="accion" value="crear_devolucion">
            
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-invoice me-2"></i>Crear Manifiesto de Devolución</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Cliente Origen:</label>
                        <select class="form-select" name="id_cliente" required>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?> (<?= htmlspecialchars($c['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Fecha Programada:</label>
                        <input type="date" class="form-control" name="fecha_devolucion" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Seleccionar Paquetes Devueltos / Rechazados:</label>
                    <div class="border rounded-3 p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                        <?php foreach ($pedidosDevolver as $p): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="pedidos_ids[]" value="<?= $p['id'] ?>" id="chk_<?= $p['id'] ?>">
                                <label class="form-check-label small" for="chk_<?= $p['id'] ?>">
                                    <strong>Orden <?= htmlspecialchars((string)($p['numero_orden'] ?? '')) ?></strong> — <?= htmlspecialchars((string)($p['destinatario'] ?? '')) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Observaciones:</label>
                    <textarea class="form-control" name="observaciones" rows="2" placeholder="Notas sobre el retorno..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fas fa-save me-1"></i>Generar Manifiesto</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
