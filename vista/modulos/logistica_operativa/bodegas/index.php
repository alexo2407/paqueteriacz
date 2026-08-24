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

    // ── PROCESAR ACCIONES POST (SÍ CRASHEA UN POST, SE CAPTURA Y NO INTERRUMPE LA CARGA DE TABLAS) ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // ── 1. POST: Nueva Bodega ────────────────────────────────────────
            if (isset($_POST['accion_bodega'])) {
                $codigo    = trim($_POST['codigo'] ?? '');
                $nombre    = trim($_POST['nombre'] ?? '');
                $direccion = trim($_POST['direccion'] ?? '');

                if (!empty($codigo) && !empty($nombre)) {
                    $stmtIns = $db->prepare("INSERT INTO logistica_bodegas (codigo, nombre, direccion, tipo, activa) VALUES (:codigo, :nombre, :direccion, 'CENTRAL', 1)");
                    $stmtIns->execute(['codigo' => $codigo, 'nombre' => $nombre, 'direccion' => $direccion]);
                    $successMsg = "Bodega '$nombre' registrada exitosamente.";
                } else {
                    $errorMsg = "El código y el nombre de la bodega son obligatorios.";
                }
            }

            // ── 2. POST: Editar Bodega ───────────────────────────────────────
            if (isset($_POST['editar_bodega'])) {
                $id        = (int)($_POST['id_bodega'] ?? 0);
                $codigo    = trim($_POST['codigo'] ?? '');
                $nombre    = trim($_POST['nombre'] ?? '');
                $direccion = trim($_POST['direccion'] ?? '');

                if ($id > 0 && !empty($codigo) && !empty($nombre)) {
                    $stmtUpd = $db->prepare("UPDATE logistica_bodegas SET codigo = :codigo, nombre = :nombre, direccion = :direccion WHERE id = :id");
                    $stmtUpd->execute(['codigo' => $codigo, 'nombre' => $nombre, 'direccion' => $direccion, 'id' => $id]);
                    $successMsg = "Bodega '$nombre' actualizada exitosamente.";
                } else {
                    $errorMsg = "Datos de bodega inválidos.";
                }
            }

            // ── 3. POST: Eliminar/Cambiar estado Bodega ───────────────────────
            if (isset($_POST['toggle_bodega'])) {
                $id = (int)($_POST['id_bodega'] ?? 0);
                if ($id > 0) {
                    $stmtTog = $db->prepare("UPDATE logistica_bodegas SET activa = IF(activa = 1, 0, 1) WHERE id = :id");
                    $stmtTog->execute(['id' => $id]);
                    $successMsg = "Estado de la bodega actualizado.";
                }
            }

            // ── 4. POST: Nueva Ubicación ─────────────────────────────────────
            if (isset($_POST['accion_ubicacion'])) {
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

            // ── 5. POST: Editar Ubicación ────────────────────────────────────
            if (isset($_POST['editar_ubicacion'])) {
                $id       = (int)($_POST['id_ubicacion'] ?? 0);
                $idBodega = (int)($_POST['id_bodega'] ?? 0);
                $codigo   = trim($_POST['codigo'] ?? '');
                $zona     = trim($_POST['zona'] ?? '');
                $estante  = trim($_POST['estante'] ?? '');
                $tipo     = trim($_POST['tipo'] ?? 'ALMACENAMIENTO');

                if ($id > 0 && $idBodega > 0 && !empty($codigo)) {
                    $stmtUpdUbic = $db->prepare("UPDATE logistica_ubicaciones SET id_bodega = :id_bodega, codigo = :codigo, zona = :zona, estante = :estante, tipo = :tipo WHERE id = :id");
                    $stmtUpdUbic->execute(['id_bodega' => $idBodega, 'codigo' => $codigo, 'zona' => $zona, 'estante' => $estante, 'tipo' => $tipo, 'id' => $id]);
                    $successMsg = "Ubicación '$codigo' actualizada exitosamente.";
                } else {
                    $errorMsg = "Datos de ubicación incompletos.";
                }
            }

            // ── 6. POST: Eliminar/Cambiar estado Ubicación ───────────────────
            if (isset($_POST['toggle_ubicacion'])) {
                $id = (int)($_POST['id_ubicacion'] ?? 0);
                if ($id > 0) {
                    $stmtTogU = $db->prepare("UPDATE logistica_ubicaciones SET activa = IF(activa = 1, 0, 1) WHERE id = :id");
                    $stmtTogU->execute(['id' => $id]);
                    $successMsg = "Estado de la ubicación actualizado.";
                }
            }
        } catch (Throwable $e) {
            error_log('[bodegas/index] POST Error: ' . $e->getMessage());
            if ($e instanceof PDOException && ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false)) {
                if (strpos($e->getMessage(), 'uk_ubicaciones_bodega_codigo') !== false) {
                    $errorMsg = 'Ya existe una estantería/ubicación registrada con esa nomenclatura en esta bodega.';
                } elseif (strpos($e->getMessage(), 'uk_bodegas_codigo') !== false) {
                    $errorMsg = 'Ya existe una bodega registrada con ese mismo código.';
                } else {
                    $errorMsg = 'El código que intentas guardar ya existe en la base de datos.';
                }
            } else {
                $errorMsg = 'Error al procesar la solicitud: ' . $e->getMessage();
            }
        }
    }

    // ── CONSULTAR DATOS (SIEMPRE SE EJECUTA, INCLUSO SI UN POST FALLÓ) ─────────
    $stmtAllBodegas = $db->query("SELECT * FROM logistica_bodegas ORDER BY nombre ASC");
    $bodegas = $stmtAllBodegas->fetchAll(PDO::FETCH_ASSOC);

    $stmtAllUbic = $db->query("
        SELECT u.*, b.nombre AS bodega_nombre
          FROM logistica_ubicaciones u
          JOIN logistica_bodegas b ON b.id = u.id_bodega
         ORDER BY b.nombre ASC, u.codigo ASC
    ");
    $ubicaciones = $stmtAllUbic->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('[bodegas/index] Fatal Error: ' . $e->getMessage());
    if (empty($errorMsg)) {
        $errorMsg = 'Error al cargar la información de bodegas: ' . $e->getMessage();
    }
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'success',
        title: '¡Operación Exitosa!',
        text: <?= json_encode($successMsg) ?>,
        confirmButtonColor: '#0b4ea2',
        timer: 3500,
        timerProgressBar: true
    });
});
</script>
<?php endif; ?>

<?php if ($errorMsg): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'error',
        title: 'Atención',
        text: <?= json_encode($errorMsg) ?>,
        confirmButtonColor: '#d33'
    });
});
</script>
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
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bodegas as $b): ?>
                            <tr>
                                <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($b['codigo']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($b['nombre']) ?></div>
                                    <small class="text-muted d-block"><?= htmlspecialchars($b['direccion'] ?? 'Sin dirección registrada') ?></small>
                                </td>
                                <td>
                                    <?php if ((int)$b['activa'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditarBodega<?= (int)$b['id'] ?>"
                                                title="Editar bodega">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirmarAccionToggle(event, this, '¿Cambiar estado de bodega?', '¿Estás seguro de cambiar la disponibilidad de esta bodega?');">
                                            <input type="hidden" name="toggle_bodega" value="1">
                                            <input type="hidden" name="id_bodega" value="<?= (int)$b['id'] ?>">
                                            <button type="submit" class="btn btn-outline-<?= (int)$b['activa'] === 1 ? 'danger' : 'success' ?>"
                                                    title="<?= (int)$b['activa'] === 1 ? 'Desactivar bodega' : 'Activar bodega' ?>">
                                                <i class="bi bi-<?= (int)$b['activa'] === 1 ? 'slash-circle' : 'check-circle' ?>"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Editar Bodega -->
                                    <div class="modal fade text-start" id="modalEditarBodega<?= (int)$b['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Editar Bodega</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="editar_bodega" value="1">
                                                    <input type="hidden" name="id_bodega" value="<?= (int)$b['id'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Código de Bodega <span class="text-danger">*</span></label>
                                                            <input type="text" name="codigo" class="form-control font-monospace" value="<?= htmlspecialchars($b['codigo']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Nombre de la Bodega <span class="text-danger">*</span></label>
                                                            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($b['nombre']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Dirección exacta</label>
                                                            <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($b['direccion'] ?? '') ?>" placeholder="Ej: Semáforos del Zumen 2c al sur">
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

                                </td>
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
                                <th>Zona / Tipo</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ubicaciones as $u): ?>
                            <tr>
                                <td class="fw-bold font-monospace text-dark">
                                    📍 <?= htmlspecialchars($u['codigo']) ?>
                                </td>
                                <td class="small fw-semibold"><?= htmlspecialchars($u['bodega_nombre']) ?></td>
                                <td>
                                    <div class="small text-muted"><?= htmlspecialchars($u['zona'] ?? 'General') ?></div>
                                    <?php if ($u['tipo'] === 'RECEPCION'): ?>
                                        <span class="badge bg-info text-dark">RECEPCIÓN</span>
                                    <?php elseif ($u['tipo'] === 'INCIDENCIA'): ?>
                                        <span class="badge bg-danger">INCIDENCIA</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ALMACENAJE</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$u['activa'] === 1): ?>
                                        <span class="text-success small fw-bold">✓ Activo</span>
                                    <?php else: ?>
                                        <span class="text-muted small">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditarUbicacion<?= (int)$u['id'] ?>"
                                                title="Editar ubicación">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirmarAccionToggle(event, this, '¿Cambiar estado de ubicación?', '¿Estás seguro de cambiar la disponibilidad de esta nomenclatura?');">
                                            <input type="hidden" name="toggle_ubicacion" value="1">
                                            <input type="hidden" name="id_ubicacion" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="btn btn-outline-<?= (int)$u['activa'] === 1 ? 'danger' : 'success' ?>"
                                                    title="<?= (int)$u['activa'] === 1 ? 'Desactivar ubicación' : 'Activar ubicación' ?>">
                                                <i class="bi bi-<?= (int)$u['activa'] === 1 ? 'slash-circle' : 'check-circle' ?>"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Editar Ubicación -->
                                    <div class="modal fade text-start" id="modalEditarUbicacion<?= (int)$u['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
                                                <div class="modal-header text-dark" style="background:#ffc107;">
                                                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Editar Nomenclatura</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="editar_ubicacion" value="1">
                                                    <input type="hidden" name="id_ubicacion" value="<?= (int)$u['id'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Bodega Perteneciente <span class="text-danger">*</span></label>
                                                            <select name="id_bodega" class="form-select" required>
                                                                <?php foreach ($bodegas as $b): ?>
                                                                <option value="<?= (int)$b['id'] ?>" <?= (int)$b['id'] === (int)$u['id_bodega'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($b['nombre']) ?> (<?= htmlspecialchars($b['codigo']) ?>)
                                                                </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Código Nomenclatura <span class="text-danger">*</span></label>
                                                            <input type="text" name="codigo" class="form-control font-monospace" value="<?= htmlspecialchars($u['codigo']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Zona o Área</label>
                                                            <input type="text" name="zona" class="form-control" value="<?= htmlspecialchars($u['zona'] ?? '') ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Estante / Rack</label>
                                                            <input type="text" name="estante" class="form-control" value="<?= htmlspecialchars($u['estante'] ?? '') ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Tipo de Ubicación</label>
                                                            <select name="tipo" class="form-select">
                                                                <option value="ALMACENAMIENTO" <?= $u['tipo'] === 'ALMACENAMIENTO' ? 'selected' : '' ?>>ALMACENAMIENTO GENERAL</option>
                                                                <option value="RECEPCION" <?= $u['tipo'] === 'RECEPCION' ? 'selected' : '' ?>>RECEPCIÓN / INGRESO</option>
                                                                <option value="INCIDENCIA" <?= $u['tipo'] === 'INCIDENCIA' ? 'selected' : '' ?>>INCIDENCIAS / REVISIÓN</option>
                                                                <option value="DEVOLUCION" <?= $u['tipo'] === 'DEVOLUCION' ? 'selected' : '' ?>>DEVOLUCIONES</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top-0 px-4 pb-4">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-warning fw-bold text-dark">Guardar Cambios</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                </td>
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
                            <?php if ((int)$b['activa'] === 1): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?> (<?= htmlspecialchars($b['codigo']) ?>)</option>
                            <?php endif; ?>
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

<script>
function confirmarAccionToggle(e, form, titulo, texto) {
    e.preventDefault();
    Swal.fire({
        title: titulo,
        text: texto,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0b4ea2',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-check-lg me-1"></i>Sí, cambiar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    return false;
}
</script>
