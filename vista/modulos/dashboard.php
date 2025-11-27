<?php 


//iniciar sesion

start_secure_session();

if(!isset($_SESSION['registrado']))
{
    header('location:'.RUTA_URL.'login');
    die();
}
else
{

include("vista/includes/header.php");

?>


<div class="row">
    <div class="col-sm-6">
        <h3>Dashboard</h3>
    </div> 
</div>
<div class="row mt-2 caja">
    <h1>BIENVENIDO ADMIN: <?php echo $_SESSION['nombre']; ?> </h1>
</div>

<?php
// Instanciar controlador y obtener datos
$dashboard = new DashboardController();
$datos = $dashboard->obtenerDatosDashboard();

// Extraer datos para uso fácil en la vista
$kpis = $datos['kpis'];
$comparativa = $datos['comparativa'];
$acumulada = $datos['acumulada'];
$topProductos = $datos['topProductos'];
?>

<!-- KPIs Section -->
<div class="row mt-4">
    <div class="col-md-4">
        <div class="card text-white bg-success mb-3">
            <div class="card-header">💰 Total Vendido (Mes)</div>
            <div class="card-body">
                <h3 class="card-title"><?= number_format($kpis['totalVendido'], 2) ?></h3>
                <p class="card-text">Ingresos confirmados</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-info mb-3">
            <div class="card-header">🧾 Ticket Promedio</div>
            <div class="card-body">
                <h3 class="card-title"><?= number_format($kpis['ticketPromedio'], 2) ?></h3>
                <p class="card-text">Gasto promedio por pedido</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-warning mb-3">
            <div class="card-header">📦 Total Pedidos</div>
            <div class="card-body">
                <h3 class="card-title"><?= $kpis['totalPedidos'] ?></h3>
                <p class="card-text">Órdenes entregadas</p>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row mt-4">
    <!-- Comparativa Mensual -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">� Comparativa de Ventas (Mes Actual vs Anterior)</h5>
            </div>
            <div class="card-body">
                <canvas id="comparativaChart" height="150"></canvas>
            </div>
        </div>
    </div>
    <!-- Top Productos -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">🏆 Top 5 Productos</h5>
            </div>
            <div class="card-body">
                <canvas id="productosChart" height="320"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Acumulado Mensual -->
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">📊 Progreso de Ventas Acumuladas</h5>
            </div>
            <div class="card-body">
                <canvas id="acumuladoChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
