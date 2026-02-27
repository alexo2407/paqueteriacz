<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/moneda.php';
$ctrl = new MonedasController();
$id = isset($ruta[2]) ? (int)$ruta[2] : null;
$m = $ctrl->ver($id);

if (!$m) {
    echo '<div class="container-fluid py-4"><div class="alert alert-danger shadow-sm border-0 rounded-3">Moneda no encontrada.</div></div>';
    include("vista/includes/footer.php");
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
    background: linear-gradient(135deg, #FF512F 0%, #DD2476 100%);
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
                            <i class="bi bi-currency-exchange fs-3"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 fw-bold"><?= htmlspecialchars($m['nombre']) ?></h3>
                            <p class="mb-0 opacity-75">Detalles de la divisa</p>
                        </div>
                    </div>
                    <a href="<?= RUTA_URL ?>monedas/listar" class="btn btn-back">
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
                                <p class="info-label"><i class="bi bi-upc me-1"></i> Código</p>
                                <div class="info-value font-monospace"><?= htmlspecialchars($m['codigo']) ?></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3">
                                <p class="info-label"><i class="bi bi-graph-up me-1"></i> Tasa de Cambio (USD)</p>
                                <div class="info-value text-success fw-bold fs-4"><?= htmlspecialchars($m['tasa_usd'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top text-end">
                         <a href="<?= RUTA_URL ?>monedas/editar/<?= $m['id'] ?>" class="btn btn-primary px-4">
                            <i class="bi bi-pencil me-1"></i> Editar Moneda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
