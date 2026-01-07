<?php 

start_secure_session();

if(!isset($_SESSION['registrado'])) {
    header('location:'.RUTA_URL.'login');
    die();
}

// Solo Admin tiene acceso al CRM
require_once __DIR__ . '/../../../utils/permissions.php';
if (!isAdmin()) {
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

require_once __DIR__ . '/../../../controlador/crm.php';
require_once __DIR__ . '/../../../utils/crm_status.php';

include("vista/includes/header.php");

// Obtener datos del dashboard
$crmController = new CrmController();
$datos = $crmController->dashboard();

$leadsPorEstado = $datos['leadsPorEstado'];
$ultimosLeads = $datos['ultimosLeads'];
$inbox = $datos['inbox'];
$outbox = $datos['outbox'];
$tendencia = $datos['tendencia'];

// Calcular totales
$totalLeads = array_sum(array_column($leadsPorEstado, 'total'));

$proveedores = $datos['proveedores'] ?? [];
$filters = $datos['filters'] ?? [];
$fechaDesde = $filters['fecha_desde'] ?? '';
$fechaHasta = $filters['fecha_hasta'] ?? '';
$proveedorId = $filters['proveedor_id'] ?? '';

// Preparar colores para el gráfico de forma consistente
$chartColors = [];
foreach ($leadsPorEstado as $item) {
    $meta = getEstadoMeta($item['estado']);
    $chartColors[] = $meta['color'];
}
?>

<style>
.crm-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    margin-bottom: 1.5rem;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
}
.stat-card {
    border: none;
    border-radius: 16px;
    padding: 1.5rem;
    color: white;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    height: 100%;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}
.stat-card.purple {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.stat-card.green {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}
.stat-card.blue {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}
.stat-card.yellow {
    background: linear-gradient(135deg, #ffa751 0%, #ffe259 100%);
}
.stat-card .stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}
.stat-card .stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}
.chart-card {
    background: white;
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.chart-card .chart-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f0f0f0;
}
.chart-card .chart-header h5 {
    margin: 0;
    font-weight: 600;
    color: #1a1a2e;
}
.chart-card .chart-body {
    padding: 1.5rem;
}
.lead-item {
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}
.lead-item:hover {
    background: #f8f9fa;
}
.lead-item:last-child {
    border-bottom: none;
}
</style>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="crm-header">
        <h2><i class="bi bi-diagram-3"></i> Dashboard CRM</h2>
        <p>Sistema de gestión de leads y webhooks</p>
    </div>

    <!-- Filtros -->
    <div class="card mb-4 border-0 shadow-sm" style="border-radius: 16px;">
        <div class="card-body p-4">
            <form method="GET" action="<?= RUTA_URL ?>crm/dashboard" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Proveedor</label>
                    <select name="proveedor_id" class="form-select">
                        <option value="">Todos los proveedores</option>
                        <?php foreach ($proveedores as $prov): ?>
                            <option value="<?= $prov['id'] ?>" <?= $proveedorId == $prov['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prov['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?= $fechaDesde ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?= $fechaHasta ?>">
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        <a href="<?= RUTA_URL ?>crm/dashboard" class="btn btn-light border">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card purple">
                <div class="stat-value"><?= $totalLeads ?></div>
                <div class="stat-label"><i class="bi bi-people"></i> Total Leads</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card green">
                <div class="stat-value"><?= $inbox['procesados'] ?></div>
                <div class="stat-label"><i class="bi bi-check-circle"></i> Inbox Procesados</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card blue">
                <div class="stat-value"><?= $outbox['enviados'] ?></div>
                <div class="stat-label"><i class="bi bi-send"></i> Webhooks Enviados</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card yellow">
                <div class="stat-value"><?= $inbox['pendientes'] + $outbox['pendientes'] ?></div>
                <div class="stat-label"><i class="bi bi-hourglass-split"></i> En Cola</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Leads por Estado -->
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-header">
                    <h5><i class="bi bi-pie-chart text-primary"></i> Leads por Estado</h5>
                </div>
                <div class="chart-body">
                    <canvas id="estadosChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Tendencia -->
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-header">
                    <h5><i class="bi bi-graph-up text-success"></i> Tendencia de Leads (30 días)</h5>
                </div>
                <div class="chart-body">
                    <canvas id="tendenciaChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimos Leads -->
    <div class="chart-card mt-4">
        <div class="chart-header d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-clock-history text-info"></i> Últimos Leads</h5>
            <a href="<?= RUTA_URL ?>crm/listar" class="btn btn-sm btn-primary">Ver Todos</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Proveedor Lead ID</th>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimosLeads as $lead): ?>
                    <tr>
                        <td><?= $lead['id'] ?></td>
                        <td><code><?= htmlspecialchars($lead['proveedor_lead_id']) ?></code></td>
                        <td><?= htmlspecialchars($lead['nombre'] ?? 'N/A') ?></td>
                        <td>
                            <?php
                            $meta = getEstadoMeta($lead['estado_actual']);
                            ?>
                            <span class="badge bg-<?= $meta['badge'] ?>"><?= $lead['estado_actual'] ?></span>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($lead['created_at'])) ?></td>
                        <td>
                            <a href="<?= RUTA_URL ?>crm/ver/<?= $lead['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Leads por Estado
const estadosData = <?= json_encode($leadsPorEstado) ?>;
const estadosLabels = estadosData.map(e => e.estado);
const estadosValues = estadosData.map(e => parseInt(e.total));
const estadosColors = <?= json_encode($chartColors) ?>;

new Chart(document.getElementById('estadosChart'), {
    type: 'doughnut',
    data: {
        labels: estadosLabels,
        datasets: [{
            data: estadosValues,
            backgroundColor: estadosColors
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Tendencia de Leads
const tendenciaData = <?= json_encode($tendencia) ?>;
const tendenciaLabels = tendenciaData.map(t => {
    const fecha = new Date(t.fecha);
    return fecha.getDate() + '/' + (fecha.getMonth()+1);
});
const tendenciaValues = tendenciaData.map(t => parseInt(t.total));

new Chart(document.getElementById('tendenciaChart'), {
    type: 'line',
    data: {
        labels: tendenciaLabels,
        datasets: [{
            label: 'Leads',
            data: tendenciaValues,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php 
include("vista/includes/footer.php");
?>
