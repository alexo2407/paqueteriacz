<?php
include("vista/includes/header_materialize.php");
require_once __DIR__ . '/../../../controlador/municipio.php';
require_once __DIR__ . '/../../../controlador/departamento.php';
$ctrl = new MunicipiosController();
$depCtrl = new DepartamentosController();
$id = isset($ruta[2]) ? (int)$ruta[2] : null;
$m = $ctrl->ver($id);
$d = $depCtrl->ver($m['id_departamento'] ?? null);

if (!$m) {
    echo '<div class="container-fluid py-4"><div class="alert alert-danger shadow-sm border-0 rounded-3">Municipio no encontrado.</div></div>';
    include("vista/includes/footer_materialize.php");
    exit;
}
?>

<style>
.detail-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.detail-header {
    background: linear-gradient(135deg, #005C97 0%, #363795 100%);
    color: white;
    padding: 2rem;
}
.info-label {
    font-size: 0.85rem;
    color: #6c757d;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}
.info-value {
    font-size: 1.1rem;
    color: #2c3e50;
    font-weight: 500;
}
.btn-back {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.4);
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
    text-decoration: none;
}
.btn-back:hover {
    background: rgba(255,255,255,0.3);
    color: white;
}
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card detail-card">
                <div class="detail-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-geo-alt fs-3"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 fw-bold"><?= htmlspecialchars($m['nombre']) ?></h3>
                            <p class="mb-0 opacity-75">Municipio / Ciudad</p>
                        </div>
                    </div>
                    <a href="<?= RUTA_URL ?>municipios/listar" class="btn btn-back">
                        <i class="bi bi-arrow-left me-1"></i> Volver
                    </a>
                </div>

                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <p class="info-label"><i class="bi bi-hash me-1"></i> ID</p>
                                <div class="info-value"><?= $m['id'] ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <p class="info-label"><i class="bi bi-map me-1"></i> Departamento</p>
                                <div class="info-value">
                                    <?php if ($d): ?>
                                        <a href="<?= RUTA_URL ?>departamentos/ver/<?= $d['id'] ?>" class="text-decoration-none fw-bold">
                                            <?= htmlspecialchars($d['nombre']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No asignado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top text-end">
                         <a href="<?= RUTA_URL ?>municipios/editar/<?= $m['id'] ?>" class="btn btn-primary px-4">
                            <i class="bi bi-pencil me-1"></i> Editar Municipio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer_materialize.php"); ?>
