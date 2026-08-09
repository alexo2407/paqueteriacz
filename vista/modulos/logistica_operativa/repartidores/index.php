<?php
/**
 * vista/modulos/logistica_operativa/repartidores/index.php
 *
 * Gestión de Mensajeros y Flota de Reparto (Logística Operativa - Maestros).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';

$successMsg = null;
$errorMsg = null;
$repartidores = [];

try {
    $db = (new Conexion())->conectar();

    // Guardar o actualizar datos de vehículo
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_repartidor'])) {
        $idUsuario = (int)($_POST['id_usuario'] ?? 0);
        $vehiculo  = trim($_POST['vehiculo_tipo'] ?? 'MOTOCICLETA');
        $placa     = trim($_POST['vehiculo_placa'] ?? '');
        $licencia  = trim($_POST['licencia'] ?? '');

        if ($idUsuario > 0) {
            $stmtSave = $db->prepare("
                INSERT INTO logistica_repartidores_info (id_usuario, vehiculo_tipo, vehiculo_placa, licencia, activo)
                VALUES (:uid, :vtipo, :vplaca, :lic, 1)
                ON DUPLICATE KEY UPDATE vehiculo_tipo = VALUES(vehiculo_tipo), vehiculo_placa = VALUES(vehiculo_placa), licencia = VALUES(licencia)
            ");
            $stmtSave->execute([
                'uid'    => $idUsuario,
                'vtipo'  => $vehiculo,
                'vplaca' => $placa,
                'lic'    => $licencia
            ]);
            $successMsg = "Información del mensajero actualizada exitosamente.";
        }
    }

    // Listar repartidores (usuarios con rol 3 o Repartidor)
    $stmtRep = $db->query("
        SELECT u.id, u.nombre, u.telefono, u.email,
               rinfo.vehiculo_tipo, rinfo.vehiculo_placa, rinfo.licencia
          FROM usuarios u
          JOIN usuarios_roles ur ON ur.id_usuario = u.id
          JOIN roles r ON r.id = ur.id_rol
     LEFT JOIN logistica_repartidores_info rinfo ON rinfo.id_usuario = u.id
         WHERE (r.nombre_rol = 'Repartidor' OR r.id = 3)
      ORDER BY u.nombre ASC
    ");
    $repartidores = $stmtRep->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('[repartidores/index] Error: ' . $e->getMessage());
    $errorMsg = 'Error al cargar el listado de mensajeros.';
}

$pageTitle = 'Mensajeros y Flota — Logística Operativa';
?>
<?php require_once __DIR__ . '/../../../../vista/includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb mb-0 small">
    <li class="breadcrumb-item"><a href="<?= RUTA_URL ?>dashboard" class="text-decoration-none">Home</a></li>
    <li class="breadcrumb-item">Logística Operativa</li>
    <li class="breadcrumb-item active">Mensajeros y Flota</li>
  </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-bicycle me-2 text-primary"></i>Mensajeros y Flota de Reparto
        </h1>
        <small class="text-muted">Administración del personal de entrega, vehículos y licencias</small>
    </div>
</div>

<?php if ($successMsg): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errorMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-dark text-white fw-bold d-flex align-items-center justify-content-between py-3" style="background:#0f172a !important;">
        <span><i class="bi bi-people me-2"></i>Personal de Mensajería Registrado</span>
        <span class="badge bg-secondary"><?= count($repartidores) ?> mensajeros</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-navy align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Mensajero / Repartidor</th>
                        <th>Teléfono</th>
                        <th>Vehículo</th>
                        <th>Placa</th>
                        <th>Licencia</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($repartidores)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-person-x display-6 d-block mb-2 opacity-25"></i>
                            No se encontraron repartidores registrados en el sistema.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($repartidores as $rep): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($rep['nombre']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($rep['email'] ?? '') ?></small>
                            </td>
                            <td class="small font-monospace"><?= htmlspecialchars($rep['telefono'] ?? '—') ?></td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">
                                    <i class="bi bi-truck me-1"></i><?= htmlspecialchars($rep['vehiculo_tipo'] ?? 'MOTOCICLETA') ?>
                                </span>
                            </td>
                            <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($rep['vehiculo_placa'] ?? 'SIN REGISTRO') ?></td>
                            <td class="small text-muted font-monospace"><?= htmlspecialchars($rep['licencia'] ?? '—') ?></td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-primary shadow-sm"
                                        onclick="abrirModalVehiculo(<?= (int)$rep['id'] ?>, '<?= htmlspecialchars($rep['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($rep['vehiculo_tipo'] ?? 'MOTOCICLETA', ENT_QUOTES) ?>', '<?= htmlspecialchars($rep['vehiculo_placa'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($rep['licencia'] ?? '', ENT_QUOTES) ?>')">
                                    <i class="bi bi-pencil-square me-1"></i>Editar Vehículo
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

<!-- Modal Editar Vehículo -->
<div class="modal fade" id="modalVehiculo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>Asignar / Editar Vehículo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion_repartidor" value="1">
                <input type="hidden" name="id_usuario" id="modRepId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Mensajero</label>
                        <input type="text" id="modRepNombre" class="form-control bg-light fw-bold" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tipo de Vehículo</label>
                        <select name="vehiculo_tipo" id="modRepTipo" class="form-select">
                            <option value="MOTOCICLETA">MOTOCICLETA</option>
                            <option value="CAMIONETA PANEL">CAMIONETA PANEL</option>
                            <option value="CAMION 3.5 TON">CAMIÓN 3.5 TON</option>
                            <option value="BICICLETA">BICICLETA</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Número de Placa</label>
                        <input type="text" name="vehiculo_placa" id="modRepPlaca" class="form-control font-monospace" placeholder="Ej: M-123456">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Número de Licencia de Conducir</label>
                        <input type="text" name="licencia" id="modRepLicencia" class="form-control font-monospace" placeholder="Ej: 001-120590-0001A">
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalVehiculo(id, nombre, tipo, placa, licencia) {
    document.getElementById('modRepId').value = id;
    document.getElementById('modRepNombre').value = nombre;
    document.getElementById('modRepTipo').value = tipo || 'MOTOCICLETA';
    document.getElementById('modRepPlaca').value = placa || '';
    document.getElementById('modRepLicencia').value = licencia || '';
    new bootstrap.Modal(document.getElementById('modalVehiculo')).show();
}
</script>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
