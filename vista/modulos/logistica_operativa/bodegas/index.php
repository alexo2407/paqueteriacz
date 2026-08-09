<?php
/**
 * vista/modulos/logistica_operativa/bodegas/index.php
 *
 * Gestión de Bodegas y Nomenclaturas de Estantes (Logística Operativa - Maestros).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/BodegaModel.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/UbicacionModel.php';

$errorMsg = null;
$successMsg = null;
$bodegas = [];
$ubicaciones = [];

try {
    $db = (new Conexion())->conectar();
    $bodegaModel = new BodegaModel($db);

    // Procesar POST de nueva bodega
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_bodega'])) {
        $codigo    = trim($_POST['codigo'] ?? '');
        $nombre    = trim($_POST['nombre'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $ciudad    = trim($_POST['ciudad'] ?? '');

        if (!empty($codigo) && !empty($nombre)) {
            $stmtIns = $db->prepare("INSERT INTO logistica_bodegas (codigo, nombre, direccion, ciudad, activa) VALUES (:codigo, :nombre, :direccion, :ciudad, 1)");
            $stmtIns->execute(['codigo' => $codigo, 'nombre' => $nombre, 'direccion' => $direccion, 'ciudad' => $ciudad]);
            $successMsg = "Bodega '$nombre' registrada exitosamente.";
        } else {
            $errorMsg = "El código y el nombre de la bodega son obligatorios.";
        }
    }

    // Procesar POST de nueva ubicación
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_ubicacion'])) {
        $idBodega = (int)($_POST['id_bodega'] ?? 0);
        $codigo   = trim($_POST['codigo'] ?? '');
        $zona     = trim($_POST['zona'] ?? '');
        $estante  = trim($_POST['estante'] ?? '');
        $tipo     = trim($_POST['tipo'] ?? 'ALMACENAMIENTO');

        if ($idBodega > 0 && !empty($codigo)) {
            $stmtUbic = $db->prepare("INSERT INTO logistica_ubicaciones (id_bodega, codigo, zona, pasillo, estante, cajon, nivel, tipo, activa) VALUES (:id_bodega, :codigo, :zona, 'P1', :estante, 'C1', 'N1', :tipo, 1)");
            $stmtUbic->execute(['id_bodega' => $idBodega, 'codigo' => $codigo, 'zona' => $zona, 'estante' => $estante, 'tipo' => $tipo]);
            $successMsg = "Ubicación '$codigo' registrada exitosamente.";
        } else {
            $errorMsg = "Selecciona una bodega e ingresa el código de nomenclatura.";
        }
    }

    // Consultar datos
    $bodegas = $bodegaModel->listarActivas();
    $stmtAllUbic = $db->query("
        SELECT u.*, b.nombre AS bodega_nombre
          FROM logistica_ubicaciones u
          JOIN logistica_bodegas b ON b.id = u.id_bodega
         ORDER BY b.nombre ASC, u.codigo ASC
    ");
    $ubicaciones = $stmtAllUbic->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('[bodegas/index] Error: ' . $e->getMessage());
    $errorMsg = 'Error al procesar la información de bodegas.';
}

$pageTitle = 'Bodegas y Estanterías — Logística Operativa';
?>
<?php require_once __DIR__ . '/../../../../vista/includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb mb-0 small">
    <li class="breadcrumb-item"><a href="<?= RUTA_URL ?>dashboard" class="text-decoration-none">Home</a></li>
    <li class="breadcrumb-item">Logística Operativa</li>
    <li class="breadcrumb-item active">Bodegas y Estanterías</li>
  </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-buildings me-2 text-primary"></i>Bodegas y Nomenclaturas de Estante
        </h1>
        <small class="text-muted">Administración de recintos físicos y estanterías para ubicación de paquetes</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary fw-bold btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaBodega">
            <i class="bi bi-plus-lg me-1"></i>Nueva Bodega
        </button>
        <button class="btn btn-warning fw-bold btn-sm text-dark shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaUbicacion">
            <i class="bi bi-geo-alt me-1"></i>Nueva Estantería / Ubicación
        </button>
    </div>
</div>

<?php if ($successMsg): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errorMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Listado de Bodegas -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-dark text-white fw-bold d-flex align-items-center justify-content-between" style="background:#0f172a !important;">
                <span><i class="bi bi-building me-2"></i>Bodegas Registradas</span>
                <span class="badge bg-secondary"><?= count($bodegas) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th>Código</th>
                                <th>Bodega</th>
                                <th>Ciudad</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bodegas as $b): ?>
                            <tr>
                                <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($b['codigo']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($b['nombre']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($b['direccion'] ?? '') ?></small>
                                </td>
                                <td class="small"><?= htmlspecialchars($b['ciudad'] ?? 'Managua') ?></td>
                                <td><span class="badge bg-success-subtle text-success border border-success-subtle">Activa</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Listado de Estanterías y Ubicaciones -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-light fw-bold d-flex align-items-center justify-content-between py-3">
                <span><i class="bi bi-geo-alt-fill me-2 text-primary"></i>Nomenclaturas de Estante y Zonas</span>
                <span class="badge bg-light text-dark border font-monospace"><?= count($ubicaciones) ?> ubicaciones</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th>Nomenclatura</th>
                                <th>Bodega</th>
                                <th>Zona</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ubicaciones as $u): ?>
                            <tr>
                                <td class="fw-bold font-monospace text-dark">
                                    📍 <?= htmlspecialchars($u['codigo']) ?>
                                </td>
                                <td class="small fw-semibold"><?= htmlspecialchars($u['bodega_nombre']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($u['zona'] ?? 'General') ?></td>
                                <td>
                                    <?php if ($u['tipo'] === 'RECEPCION'): ?>
                                        <span class="badge bg-info text-dark">RECEPCIÓN</span>
                                    <?php elseif ($u['tipo'] === 'INCIDENCIA'): ?>
                                        <span class="badge bg-danger">INCIDENCIA</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ALMACENAJE</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-success small fw-bold">✓ Activo</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Bodega -->
<div class="modal fade" id="modalNuevaBodega" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-building me-2"></i>Registrar Nueva Bodega</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion_bodega" value="1">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Código de Bodega <span class="text-danger">*</span></label>
                        <input type="text" name="codigo" class="form-control font-monospace" placeholder="Ej: BOD-02" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nombre de la Bodega <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Bodega Sucursal León" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Ciudad / Municipio</label>
                        <input type="text" name="ciudad" class="form-control" placeholder="Ej: León">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Dirección exacta</label>
                        <input type="text" name="direccion" class="form-control" placeholder="Dirección del recinto...">
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold">Guardar Bodega</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nueva Ubicación -->
<div class="modal fade" id="modalNuevaUbicacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header text-dark" style="background:#ffc107;">
                <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt me-2"></i>Registrar Nueva Nomenclatura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion_ubicacion" value="1">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Bodega Perteneciente <span class="text-danger">*</span></label>
                        <select name="id_bodega" class="form-select" required>
                            <option value="">-- Seleccionar bodega --</option>
                            <?php foreach ($bodegas as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?> (<?= htmlspecialchars($b['codigo']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Código Nomenclatura <span class="text-danger">*</span></label>
                        <input type="text" name="codigo" class="form-control font-monospace" placeholder="Ej: EST-A01-N2" required>
                        <small class="text-muted">Formato recomendado: ZONA-ESTANTE-NIVEL</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Zona o Área</label>
                        <input type="text" name="zona" class="form-control" placeholder="Ej: Zona A - Paquete Ligero">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tipo de Ubicación</label>
                        <select name="tipo" class="form-select">
                            <option value="ALMACENAMIENTO" selected>ALMACENAMIENTO GENERAL</option>
                            <option value="RECEPCION">RECEPCIÓN / INGRESO</option>
                            <option value="INCIDENCIA">INCIDENCIAS / REVISIÓN</option>
                            <option value="DEVOLUCION">DEVOLUCIONES</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark">Guardar Ubicación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
