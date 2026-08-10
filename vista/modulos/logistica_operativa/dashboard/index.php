<?php
/**
 * vista/modulos/logistica_operativa/dashboard/index.php
 *
 * Tablero Principal de Métricas y KPIs de Logística Operativa.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/DashboardOperativoModel.php';
require_once __DIR__ . '/../../../../services/logistica_operativa/DashboardOperativoService.php';
require_once __DIR__ . '/../../../../utils/logistica_permissions.php';

require_permission('logistica_operativa_bodega');

$dashboardService = new DashboardOperativoService();
$metrics = $dashboardService->obtenerResumenDashboard();

$colectas = $metrics['colectas'];
$estados = $metrics['estados'];
$rutas = $metrics['rutas'];
$recepciones = $metrics['recepciones'];

$pageTitle = 'Dashboard Logística Operativa';
require_once __DIR__ . '/../../../../vista/includes/header.php';
?>

<div class="content-wrapper p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fas fa-chart-line text-primary me-2"></i>Dashboard Logística Operativa</h2>
            <p class="text-muted mb-0">Métricas clave de rendimiento, colectas, efectividad de rutas y recepciones en tiempo real</p>
        </div>
        <div>
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill font-monospace"><i class="fas fa-clock text-primary me-1"></i><?= date('d/m/Y H:i') ?></span>
        </div>
    </div>

    <!-- Seccion 1: Tarjetas KPIs Principales -->
    <div class="row g-3 mb-4">
        <!-- Colectas Hoy -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted fw-semibold small">Colectas Hoy</span>
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                            <i class="fas fa-boxes-packing fa-lg"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?= (int)$colectas['total_colectas_hoy'] ?></h3>
                    <p class="text-muted small mb-0">
                        <span class="text-success fw-bold me-1"><i class="fas fa-qrcode me-1"></i><?= (int)$colectas['total_escaneado_hoy'] ?></span> escaneados de <?= (int)$colectas['total_esperado_hoy'] ?> esperados
                    </p>
                </div>
            </div>
        </div>

        <!-- Pedidos en Bodega -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted fw-semibold small">Inventario en Bodega</span>
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info">
                            <i class="fas fa-warehouse fa-lg"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?= (int)$estados['en_bodega'] ?></h3>
                    <p class="text-muted small mb-0">Paquetes clasificados y listos para ruta</p>
                </div>
            </div>
        </div>

        <!-- Rutas Activas Hoy -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted fw-semibold small">Rutas Hoy</span>
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                            <i class="fas fa-route fa-lg"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?= (int)$rutas['total_rutas_hoy'] ?></h3>
                    <p class="text-muted small mb-0">
                        <span class="text-primary fw-bold me-1"><?= (int)$rutas['rutas_selladas'] ?></span> selladas / <?= (int)$rutas['rutas_liquidadas'] ?> liquidadas
                    </p>
                </div>
            </div>
        </div>

        <!-- Pendientes Recolección -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted fw-semibold small">Pendientes Recolección</span>
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                            <i class="fas fa-truck-pickup fa-lg"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?= (int)$estados['pendientes_colecta'] ?></h3>
                    <p class="text-muted small mb-0">Órdenes digitales pendientes por recoger</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Seccion 2: Desglose de Recepciones y Estados -->
    <div class="row g-4 mb-4">
        <!-- Recepciones Físicas por Tipo -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-dolly me-2 text-primary"></i>Recepciones Físicas Registradas</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($recepciones)): ?>
                        <p class="text-muted text-center py-4">No hay recepciones físicas registradas aún.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recepciones as $tipo => $conteo): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-3">
                                    <span class="fw-semibold text-secondary"><i class="fas fa-circle-notch text-primary me-2"></i><?= htmlspecialchars($tipo) ?></span>
                                    <span class="badge bg-primary rounded-pill px-3 py-2 fs-6"><?= (int)$conteo ?> paquetes</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Accesos Rápidos a Módulos Operativos -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-rocket me-2 text-primary"></i>Accesos Rápidos Módulos Operativos</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="<?= RUTA_URL ?>logistica-operativa/colectas" class="btn btn-outline-primary w-100 p-3 text-start rounded-3 h-100">
                                <i class="fas fa-boxes-packing fa-2x mb-2 d-block text-primary"></i>
                                <span class="fw-bold d-block">Colectas</span>
                                <small class="text-muted">Apertura y escaneo</small>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="<?= RUTA_URL ?>logistica-operativa/bodega" class="btn btn-outline-primary w-100 p-3 text-start rounded-3 h-100">
                                <i class="fas fa-warehouse fa-2x mb-2 d-block text-primary"></i>
                                <span class="fw-bold d-block">Bodega</span>
                                <small class="text-muted">Recepciones y ubicaciones</small>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="<?= RUTA_URL ?>logistica-operativa/rutas" class="btn btn-outline-primary w-100 p-3 text-start rounded-3 h-100">
                                <i class="fas fa-route fa-2x mb-2 d-block text-primary"></i>
                                <span class="fw-bold d-block">Rutas</span>
                                <small class="text-muted">Despacho y manifiestos</small>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="<?= RUTA_URL ?>logistica-operativa/liquidaciones" class="btn btn-outline-primary w-100 p-3 text-start rounded-3 h-100">
                                <i class="fas fa-calculator fa-2x mb-2 d-block text-primary"></i>
                                <span class="fw-bold d-block">Liquidaciones</span>
                                <small class="text-muted">Arqueo COD de ruta</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>
