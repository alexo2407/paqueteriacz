<?php
/**
 * vista/modulos/logistica_operativa/zonas/index.php
 *
 * Administración de Zonas de Reparto y Cobertura (Logística Operativa - Maestros).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';

$successMsg = null;
$errorMsg = null;
$zonas = [];
$municipios = [];

try {
    $db = (new Conexion())->conectar();

    // Crear nueva zona
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_zona'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');

        if (!empty($nombre)) {
            $stmt = $db->prepare("INSERT INTO logistica_zonas (nombre, descripcion, activa) VALUES (:nombre, :desc, 1)");
            $stmt->execute(['nombre' => $nombre, 'desc' => $desc]);
            $successMsg = "Zona '$nombre' creada exitosamente.";
        } else {
            $errorMsg = "El nombre de la zona es obligatorio.";
        }
    }

    // Editar zona existente
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_editar_zona'])) {
        $idZona = (int)($_POST['id_zona'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');

        if ($idZona > 0 && !empty($nombre)) {
            $stmtUpd = $db->prepare("UPDATE logistica_zonas SET nombre = :nombre, descripcion = :desc WHERE id = :id");
            $stmtUpd->execute(['nombre' => $nombre, 'desc' => $desc, 'id' => $idZona]);
            $successMsg = "Zona '$nombre' actualizada exitosamente.";
        } else {
            $errorMsg = "El nombre de la zona no puede estar vacío.";
        }
    }

    // Listar zonas
    $stmtZonas = $db->query("SELECT * FROM logistica_zonas ORDER BY id ASC");
    $zonas = $stmtZonas->fetchAll(PDO::FETCH_ASSOC);

    // Listar municipios
    $stmtMun = $db->query("SELECT id, nombre FROM municipios ORDER BY nombre ASC LIMIT 100");
    $municipios = $stmtMun->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('[zonas/index] Error: ' . $e->getMessage());
    $errorMsg = 'Error al cargar las zonas de reparto.';
}

$pageTitle = 'Zonas de Reparto y Cobertura — Logística Operativa';
?>
<?php require_once __DIR__ . '/../../../../vista/includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb mb-0 small">
    <li class="breadcrumb-item"><a href="<?= RUTA_URL ?>dashboard" class="text-decoration-none">Home</a></li>
    <li class="breadcrumb-item">Logística Operativa</li>
    <li class="breadcrumb-item active">Zonas de Reparto</li>
  </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-map-fill me-2 text-primary"></i>Zonas de Reparto y Cobertura
        </h1>
        <small class="text-muted">Agrupación geográfica de municipios y barrios para armado de rutas</small>
    </div>
    <div>
        <button class="btn btn-primary fw-bold btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaZona">
            <i class="bi bi-plus-lg me-1"></i>Nueva Zona de Reparto
        </button>
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

<div class="row g-4">
    <?php foreach ($zonas as $z): ?>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-top border-4 border-primary">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">Zona #<?= (int)$z['id'] ?></span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Activa</span>
                </div>
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($z['nombre']) ?></h5>
                <p class="text-muted small mb-3"><?= htmlspecialchars($z['descripcion'] ?? 'Sin descripción') ?></p>
                
                <div class="border-top pt-3 d-flex align-items-center justify-content-between">
                    <span class="small text-muted"><i class="bi bi-geo me-1"></i>Cobertura configurada</span>
                    <button class="btn btn-sm btn-outline-secondary px-2 py-1 shadow-sm"
                            onclick="abrirModalEditarZona(<?= (int)$z['id'] ?>, '<?= htmlspecialchars($z['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($z['descripcion'] ?? '', ENT_QUOTES) ?>')">
                        <i class="bi bi-pencil me-1"></i>Editar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal Nueva Zona -->
<div class="modal fade" id="modalNuevaZona" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-map me-2"></i>Crear Zona de Reparto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion_zona" value="1">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nombre de la Zona <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Managua Sur / Carretera a Masaya" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Descripción / Alcance</label>
                        <textarea name="descripcion" class="form-control" rows="3" placeholder="Detalles de cobertura de la zona..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold">Guardar Zona</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Zona -->
<div class="modal fade" id="modalEditarZona" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Zona de Reparto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion_editar_zona" value="1">
                <input type="hidden" name="id_zona" id="editZonaId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nombre de la Zona <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" id="editZonaNombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Descripción / Alcance</label>
                        <textarea name="descripcion" id="editZonaDesc" class="form-control" rows="3"></textarea>
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
function abrirModalEditarZona(id, nombre, desc) {
    document.getElementById('editZonaId').value = id;
    document.getElementById('editZonaNombre').value = nombre;
    document.getElementById('editZonaDesc').value = desc || '';
    new bootstrap.Modal(document.getElementById('modalEditarZona')).show();
}
</script>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
