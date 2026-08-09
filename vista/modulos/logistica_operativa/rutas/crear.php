<?php
/**
 * vista/modulos/logistica_operativa/rutas/crear.php
 *
 * Interfaz para armar una nueva Ruta de Despacho seleccionando paquetes en bodega.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/RutaModel.php';
require_once __DIR__ . '/../../../../services/logistica_operativa/RutaService.php';
require_once __DIR__ . '/../../../../utils/logistica_permissions.php';

require_permission('logistica_operativa_rutas');

$error = null;
$exito = null;
$repartidores = [];
$paquetesEnBodega = [];

try {
    $db = (new Conexion())->conectar();
    $rutaModel = new RutaModel($db);
    $rutaService = new RutaService($db);

    // Obtener repartidores (usuarios con rol 'Repartidor' o ID de rol 3 vía usuarios_roles)
    $stmtRep = $db->query("
        SELECT DISTINCT u.id, u.nombre
          FROM usuarios u
          JOIN usuarios_roles ur ON ur.id_usuario = u.id
          JOIN roles r ON r.id = ur.id_rol
         WHERE (r.nombre_rol = 'Repartidor' OR r.id = 3)
      ORDER BY u.nombre ASC
    ");
    $repartidores = $stmtRep->fetchAll(PDO::FETCH_ASSOC);

    // Si no hay repartidores con ese rol, obtener usuarios activos como fallback
    if (empty($repartidores)) {
        $stmtRepFB = $db->query("SELECT id, nombre FROM usuarios WHERE activo = 1 LIMIT 20");
        $repartidores = $stmtRepFB->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener paquetes en bodega elegibles
    $paquetesEnBodega = $rutaModel->obtenerPaquetesUbicadosElegibles();

    // Procesar formulario POST
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $nombre       = $_POST['nombre'] ?? '';
        $fecha        = $_POST['fecha'] ?? date('Y-m-d');
        $idRepartidor = (int)($_POST['id_repartidor'] ?? 0);
        $pedidosSel   = $_POST['pedidos'] ?? [];

        $res = $rutaService->crearRuta([
            'nombre'        => $nombre,
            'fecha'         => $fecha,
            'id_repartidor' => $idRepartidor,
            'id_creada_por' => (int)($_SESSION['user_id'] ?? 1),
            'pedidos'       => array_map('intval', (array)$pedidosSel)
        ]);

        header('Location: ' . RUTA_URL . 'logistica-operativa/rutas/ver/' . $res['id_ruta']);
        exit;
    }

} catch (Throwable $e) {
    $error = $e->getMessage();
}

$pageTitle = 'Armar Nueva Ruta — Logística Operativa';
?>
<?php require_once __DIR__ . '/../../../../vista/includes/header.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-diagram-3 me-2 text-primary"></i>Armar Nueva Ruta de Despacho
        </h1>
        <small class="text-muted">Selecciona paquetes en bodega para agrupar en un manifiesto de ruta</small>
    </div>
    <div>
        <a href="<?= RUTA_URL ?>logistica-operativa/rutas" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver al Listado
        </a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars((string)$error) ?></div>
<?php endif; ?>

<form method="POST" action="">
    <div class="row g-4">

        <!-- ═══ Columna Izquierda: Datos de la Ruta ═══ -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold">
                    <i class="bi bi-info-circle me-2"></i>Información de la Ruta
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nombre / Nombre de Zona <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Ruta 01 - Zona Managua Centro" required value="Ruta 01 - Managua Centro [DEMO LOCAL]">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Fecha Programada <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Repartidor / Mensajero Asignado <span class="text-danger">*</span></label>
                        <select name="id_repartidor" class="form-select" required>
                            <option value="">-- Seleccionar repartidor --</option>
                            <?php foreach ($repartidores as $r): ?>
                            <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars((string)$r['nombre']) ?> (ID: <?= (int)$r['id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm text-white bg-primary">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small opacity-75">Paquetes seleccionados</div>
                            <div class="h3 mb-0 fw-bold" id="cntSeleccionados">0</div>
                        </div>
                        <div>
                            <div class="small opacity-75 text-end">Total COD a Recaudar</div>
                            <div class="h4 mb-0 fw-bold font-monospace text-end" id="totalCodCalculado">C$ 0.00</div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success btn-lg w-100 mt-4 shadow-sm">
                <i class="bi bi-check-circle me-2"></i>Crear y Generar Ruta
            </button>
        </div>

        <!-- ═══ Columna Derecha: Selección de Paquetes ═══ -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light d-flex align-items-center justify-content-between">
                    <span class="fw-semibold"><i class="bi bi-boxes me-2"></i>Paquetes Disponibles en Bodega</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSelectAll">
                        <i class="bi bi-check-all me-1"></i>Seleccionar Todos
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-3" style="width: 40px;"></th>
                                    <th>N.º Orden / ID</th>
                                    <th>Destinatario</th>
                                    <th>Municipio / Zona</th>
                                    <th>Ubicación Bodega</th>
                                    <th class="text-end pe-3">Monto COD</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($paquetesEnBodega)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="bi bi-inbox display-6 d-block mb-2 opacity-25"></i>
                                        No hay paquetes disponibles en estado UBICADO sin ruta activa.
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($paquetesEnBodega as $p): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <input type="checkbox" name="pedidos[]" value="<?= (int)$p['id_pedido'] ?>" data-cod="<?= (float)$p['monto_cod'] ?>" class="form-check-input chk-pedido">
                                        </td>
                                        <td>
                                            <span class="fw-bold"><?= htmlspecialchars((string)($p['numero_orden'] ?? '#' . $p['id_pedido'])) ?></span>
                                        </td>
                                        <td class="small"><?= htmlspecialchars((string)($p['destinatario'] ?? '—')) ?></td>
                                        <td class="small text-muted">
                                            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars((string)($p['municipalitiesName'] ?? $p['departmentName'] ?? 'Managua')) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-grid-3x3-gap me-1"></i><?= htmlspecialchars((string)($p['ubicacion_codigo'] ?? 'RECEPCION-01')) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-3 font-monospace fw-semibold">
                                            C$ <?= number_format((float)$p['monto_cod'], 2) ?>
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

    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chks = document.querySelectorAll('.chk-pedido');
    const cntSel = document.getElementById('cntSeleccionados');
    const totalCodEl = document.getElementById('totalCodCalculado');
    const btnSelectAll = document.getElementById('btnSelectAll');

    function recualcularTotales() {
        let count = 0;
        let totalCod = 0;
        chks.forEach(chk => {
            if (chk.checked) {
                count++;
                totalCod += parseFloat(chk.getAttribute('data-cod') || 0);
            }
        });
        cntSel.textContent = count;
        totalCodEl.textContent = 'C$ ' + totalCod.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    chks.forEach(chk => chk.addEventListener('change', recualcularTotales));

    let allChecked = false;
    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function() {
            allChecked = !allChecked;
            chks.forEach(chk => chk.checked = allChecked);
            recualcularTotales();
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
