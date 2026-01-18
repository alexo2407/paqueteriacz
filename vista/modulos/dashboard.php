<?php 

//iniciar sesion

start_secure_session();

// Cargar constantes de roles antes de usarlas
require_once __DIR__ . '/../../utils/permissions.php';

if(!isset($_SESSION['registrado']))
{
    header('location:'.RUTA_URL.'login');
    die();
}
else
{

// Redirigir repartidores a su interfaz de seguimiento
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
$isRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $rolesNombres, true);
$isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
$isClienteCRM = in_array(ROL_NOMBRE_CLIENTE_CRM, $rolesNombres, true);  // CRM
$isProveedorCRM = in_array(ROL_NOMBRE_PROVEEDOR_CRM, $rolesNombres, true);  // CRM

// Repartidores van a seguimiento
if ($isRepartidor && !$isAdmin) {
    header('Location: ' . RUTA_URL . 'seguimiento/listar');
    exit;
}

// Usuarios CRM van a notificaciones (su página principal)
if (($isClienteCRM || $isProveedorCRM) && !$isAdmin) {
    header('Location: ' . RUTA_URL . 'crm/notificaciones');
    exit;
}

require_once __DIR__ . '/../../controlador/dashboard.php';

include("vista/includes/header.php");

// Instanciar controlador y obtener datos
$dashboard = new DashboardController();
$datos = $dashboard->obtenerDatosDashboard();

// Extraer datos para uso fácil en la vista
$kpis = $datos['kpis'];
$comparativa = $datos['comparativa'];
$acumulada = $datos['acumulada'];
$topProductos = $datos['topProductos'];
$fechaDesde = $datos['fechaDesde'];
$fechaHasta = $datos['fechaHasta'];

// Formatear fechas para mostrar
$fechaDesdeFormateada = date('d/m/Y', strtotime($fechaDesde));
$fechaHastaFormateada = date('d/m/Y', strtotime($fechaHasta));
$periodoTexto = "del $fechaDesdeFormateada al $fechaHastaFormateada";

// Nombre del usuario
$nombreUsuario = $_SESSION['nombre'] ?? 'Usuario';
$primerNombre = explode(' ', $nombreUsuario)[0];

// Hora del día para saludo
$hora = (int)date('H');
if ($hora < 12) {
    $saludo = 'Buenos días';
    $saludoIcon = '☀️';
} elseif ($hora < 19) {
    $saludo = 'Buenas tardes';
    $saludoIcon = '🌤️';
} else {
    $saludo = 'Buenas noches';
    $saludoIcon = '🌙';
}
?>

<style>
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    margin-bottom: 1.5rem;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
}
.dashboard-header h2 {
    font-weight: 700;
    margin-bottom: 0.25rem;
}
.dashboard-header p {
    opacity: 0.9;
    margin-bottom: 0;
}
.filter-card {
    background: white;
    border-radius: 16px;
    padding: 1rem 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border: none;
    margin-bottom: 1.5rem;
}
.filter-card .form-control {
    border-radius: 10px;
    border: 2px solid #e9ecef;
    padding: 0.5rem 1rem;
}
.filter-card .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
}
.filter-card .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 10px;
    padding: 0.5rem 1.5rem;
}
.filter-card .btn-secondary {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    color: #6c757d;
    border-radius: 10px;
    padding: 0.5rem 1.5rem;
}
.kpi-card {
    border: none;
    border-radius: 16px;
    padding: 1.5rem;
    color: white;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    height: 100%;
}
.kpi-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}
.kpi-card.green {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}
.kpi-card.blue {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}
.kpi-card.orange {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}
.kpi-card .kpi-icon {
    font-size: 2.5rem;
    opacity: 0.8;
    margin-bottom: 0.5rem;
}
.kpi-card .kpi-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}
.kpi-card .kpi-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 0.5rem;
}
.kpi-card .kpi-desc {
    font-size: 0.8rem;
    opacity: 0.75;
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
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.chart-card .chart-header small {
    color: #6c757d;
    display: block;
    margin-top: 0.25rem;
}
.chart-card .chart-body {
    padding: 1.5rem;
}
.period-badge {
    background: rgba(255,255,255,0.2);
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
</style>

<div class="container-fluid py-3">
    <!-- Header con saludo -->
    <div class="dashboard-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2><?= $saludoIcon ?> <?= $saludo ?>, <?= htmlspecialchars($primerNombre) ?></h2>
                <p>Aquí tienes un resumen de tu negocio</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="period-badge">
                    <i class="bi bi-calendar3"></i>
                    <?= $periodoTexto ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtro de Rango de Fechas -->
    <div class="filter-card">
        <form method="GET" action="<?= RUTA_URL ?>dashboard" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="fecha_desde" class="form-label small text-muted mb-1">
                    <i class="bi bi-calendar-event me-1"></i>Desde
                </label>
                <input type="date" id="fecha_desde" name="fecha_desde" class="form-control" value="<?= $fechaDesde ?>" required>
            </div>
            <div class="col-md-3">
                <label for="fecha_hasta" class="form-label small text-muted mb-1">
                    <i class="bi bi-calendar-event me-1"></i>Hasta
                </label>
                <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control" value="<?= $fechaHasta ?>" required>
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="<?= RUTA_URL ?>dashboard" class="btn btn-secondary">
                    <i class="bi bi-x-lg me-1"></i>Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- KPIs Section -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="kpi-card green">
                <div class="kpi-icon">💰</div>
                <div class="kpi-value">$<?= number_format($kpis['totalVendido'], 2) ?></div>
                <div class="kpi-label">Total Vendido</div>
                <div class="kpi-desc">Ingresos confirmados en el período</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card blue">
                <div class="kpi-icon">🧾</div>
                <div class="kpi-value">$<?= number_format($kpis['ticketPromedio'], 2) ?></div>
                <div class="kpi-label">Ticket Promedio</div>
                <div class="kpi-desc">Gasto promedio por pedido</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card orange">
                <div class="kpi-icon">📦</div>
                <div class="kpi-value"><?= $kpis['totalPedidos'] ?></div>
                <div class="kpi-label">Total Pedidos</div>
                <div class="kpi-desc">Órdenes entregadas</div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row g-4">
        <!-- Comparativa Mensual -->
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-header">
                    <h5><i class="bi bi-graph-up text-primary"></i> Comparativa de Ventas</h5>
                    <small>Período actual vs anterior</small>
                </div>
                <div class="chart-body">
                    <canvas id="comparativaChart" height="130"></canvas>
                </div>
            </div>
        </div>
        <!-- Top Productos -->
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-header">
                    <h5><i class="bi bi-trophy text-warning"></i> Top 5 Productos</h5>
                    <small>Más vendidos en el período</small>
                </div>
                <div class="chart-body">
                    <canvas id="productosChart" height="280"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <!-- Acumulado Mensual -->
        <div class="col-12">
            <div class="chart-card">
                <div class="chart-header">
                    <h5><i class="bi bi-bar-chart-line text-success"></i> Progreso de Ventas Acumuladas</h5>
                    <small>Evolución <?= $periodoTexto ?></small>
                </div>
                <div class="chart-body">
                    <canvas id="acumuladoChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Pass PHP data to JavaScript
    const dashboardData = {
        comparativa: <?= json_encode($comparativa) ?>,
        topProductos: {
            nombres: <?= json_encode($topProductos['nombres']) ?>,
            cantidades: <?= json_encode($topProductos['cantidades']) ?>
        },
        acumulada: <?= json_encode($acumulada) ?>
    };
</script>
<script src="js/dashboard.js?v=<?= time() ?>"></script>
<?php 

include("vista/includes/footer.php");

}

?>
