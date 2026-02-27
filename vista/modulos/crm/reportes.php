<?php 
start_secure_session();
if(!isset($_SESSION['registrado'])) { header('location:'.RUTA_URL.'login'); die(); }
require_once __DIR__ . '/../../../utils/permissions.php';
if (!isAdmin()) { header('Location: ' . RUTA_URL . 'dashboard'); exit; }

require_once __DIR__ . '/../../../controlador/crm.php';
$crmController = new CrmController();
$datos = $crmController->reportes();

include("vista/includes/header_materialize.php");
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-bar-chart"></i> Reportes CRM</h2>
        <a href="<?= RUTA_URL ?>crm/dashboard" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <!-- Filtros de Fecha -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?= $datos['fechaDesde'] ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?= $datos['fechaHasta'] ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block w-100">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Leads por Proveedor -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-people"></i> Leads por Proveedor</h5>
                </div>
                <div class="card-body">
                    <canvas id="proveedoresChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Leads por Cliente -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-building"></i> Leads por Cliente</h5>
                </div>
                <div class="card-body">
                    <canvas id="clientesChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Conversión por Estado -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="bi bi-funnel"></i> Conversión por Estado</h5>
        </div>
        <div class="card-body">
            <canvas id="conversionChart" height="100"></canvas>
        </div>
    </div>

    <!-- Tasa de Éxito Webhooks -->
    <div class="card">
        <div class="card-header">
            <h5><i class="bi bi-check-circle"></i> Tasa de Éxito de Webhooks</h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4">
                    <h3 class="text-success"><?= $datos['tasaExitoWebhooks']['enviados'] ?? 0 ?></h3>
                    <p>Enviados</p>
                </div>
                <div class="col-md-4">
                    <h3 class="text-danger"><?= $datos['tasaExitoWebhooks']['fallidos'] ?? 0 ?></h3>
                    <p>Fallidos</p>
                </div>
                <div class="col-md-4">
                    <h3 class="text-primary">
                        <?php 
                        $total = ($datos['tasaExitoWebhooks']['enviados'] ?? 0) + ($datos['tasaExitoWebhooks']['fallidos'] ?? 0);
                        $tasa = $total > 0 ? round(($datos['tasaExitoWebhooks']['enviados'] / $total) * 100, 2) : 0;
                        echo $tasa;
                        ?>%
                    </h3>
                    <p>Tasa de Éxito</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Leads por Proveedor
new Chart(document.getElementById('proveedoresChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($datos['leadsPorProveedor'], 'nombre')) ?>,
        datasets: [{
            label: 'Leads',
            data: <?= json_encode(array_column($datos['leadsPorProveedor'], 'total')) ?>,
            backgroundColor: '#667eea'
        }]
    }
});

// Leads por Cliente
new Chart(document.getElementById('clientesChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($datos['leadsPorCliente'], 'nombre')) ?>,
        datasets: [{
            label: 'Leads',
            data: <?= json_encode(array_column($datos['leadsPorCliente'], 'total')) ?>,
            backgroundColor: '#764ba2'
        }]
    }
});

// Conversión por Estado
new Chart(document.getElementById('conversionChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($datos['conversionPorEstado'], 'estado')) ?>,
        datasets: [{
            label: 'Cantidad',
            data: <?= json_encode(array_column($datos['conversionPorEstado'], 'total')) ?>,
            backgroundColor: ['#ffc107', '#28a745', '#007bff', '#17a2b8', '#6c757d', '#dc3545']
        }]
    },
    options: {
        indexAxis: 'y'
    }
});
</script>

<?php include("vista/includes/footer_materialize.php"); ?>
