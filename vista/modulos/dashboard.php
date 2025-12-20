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

// Redirigir repartidores a su interfaz de seguimiento
// Redirigir repartidores a su interfaz de seguimiento
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
$isRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $rolesNombres, true);
$isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);

if ($isRepartidor && !$isAdmin) {
    header('Location: ' . RUTA_URL . 'seguimiento/listar');
    exit;
}

require_once __DIR__ . '/../../controlador/dashboard.php';
// The original `isRepartidor()` check was likely from `permissions.php`.
// If `permissions.php` is still needed for other parts, it should be included.
// Assuming the new logic replaces the old one for repartidor redirection.
// If `isRepartidor()` is still used elsewhere, `permissions.php` might need to be included.
// For now, I'm replacing the block as instructed.
// If the `isRepartidor()` function is defined in `permissions.php` and is still needed,
// that `require_once` should be kept or moved.
// Given the instruction, I'm replacing the entire block.
// If the `isRepartidor()` function is defined in `permissions.php` and is still needed,
// that `require_once` should be kept or moved.
// For now, I'm replacing the block as instructed.
// The new code snippet provided by the user includes `if (isRepartidor())` again,
// which suggests that `isRepartidor()` might be defined in `dashboard.php` or
// another globally included file, or the user intends to keep that check.
// I will include `permissions.php` to ensure `isRepartidor()` is available if needed by the second check.
require_once __DIR__ . '/../../utils/permissions.php'; // Ensure isRepartidor() is available
if (isRepartidor()) {
    header('Location: ' . RUTA_URL . 'seguimiento');
    exit;
}

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
$fechaDesde = $datos['fechaDesde'];
$fechaHasta = $datos['fechaHasta'];

// Formatear fechas para mostrar
$fechaDesdeFormateada = date('d/m/Y', strtotime($fechaDesde));
$fechaHastaFormateada = date('d/m/Y', strtotime($fechaHasta));
$periodoTexto = "del $fechaDesdeFormateada al $fechaHastaFormateada";
?>

<!-- Filtro de Rango de Fechas -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="<?= RUTA_URL ?>dashboard" class="form-inline">
                    <div class="form-group mr-3">
                        <label for="fecha_desde" class="mr-2">Desde:</label>
                        <input type="date" id="fecha_desde" name="fecha_desde" class="form-control" value="<?= $fechaDesde ?>" required>
                    </div>
                    <div class="form-group mr-3">
                        <label for="fecha_hasta" class="mr-2">Hasta:</label>
                        <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control" value="<?= $fechaHasta ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <a href="<?= RUTA_URL ?>dashboard" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- KPIs Section -->
<div class="row mt-4">
    <div class="col-md-4">
        <div class="card text-white bg-success mb-3">
            <div class="card-header">💰 Total Vendido (Período)</div>
            <div class="card-body">
                <h3 class="card-title"><?= number_format($kpis['totalVendido'], 2) ?></h3>
                <p class="card-text">Ingresos confirmados <?= $periodoTexto ?></p>
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
                <h5 class="mb-0">📊 Comparativa de Ventas (Período Actual vs Anterior)</h5>
                <small>Comparando <?= $periodoTexto ?></small>
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
                <small>Período: <?= $periodoTexto ?></small>
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
