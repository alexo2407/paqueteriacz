<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/producto.php';
require_once __DIR__ . '/../../../modelo/categoria.php';

start_secure_session();
require_login();

// Obtener filtro de usuario para proveedores
$filtroUsuario = getIdUsuarioCreadorFilter();

// Obtener métricas (filtrado por proveedor si aplica)
$totalProductos = 0;
$stockBajo = 0;
$agotados = 0;

$productos = ProductoModel::listarConInventario($filtroUsuario);
foreach ($productos as $p) {
    $totalProductos++;
    $stock = (int)($p['stock_total'] ?? 0);
    $minimo = (int)($p['stock_minimo'] ?? 10);
    
    if ($stock <= 0) $agotados++;
    elseif ($stock < $minimo) $stockBajo++;
}

// Obtener productos con stock bajo (filtrado por proveedor si aplica)
$productosCriticos = ProductoModel::obtenerStockBajo(10, $filtroUsuario);

// Obtener categorías para el gráfico (filtrado por proveedor si aplica)
$categorias = CategoriaModel::contarProductosPorCategoria($filtroUsuario);

// Calcular valor estimado
$valorEstimado = array_sum(array_map(function($p) {
    return ($p['stock_total'] ?? 0) * ($p['precio_usd'] ?? 0);
}, $productos));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Productos - Paquetería CruzValle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-box-seam"></i> Dashboard de Productos</h2>
            <p class="text-muted mb-0">Vista general del inventario y productos</p>
        </div>
        <div>
            <a href="<?php echo RUTA_URL; ?>productos/listar" class="btn btn-outline-primary me-2">
                <i class="bi bi-list"></i> Ver Lista Completa
            </a>
            <a href="<?php echo RUTA_URL; ?>productos/crear" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nuevo Producto
            </a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row">
        <!-- Total Productos -->
        <div class="col-md-3 mb-3">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Total Productos</h6>
                            <h2 class="mb-0"><?php echo number_format($totalProductos); ?></h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.8;" class="text-primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Bajo -->
        <div class="col-md-3 mb-3">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Stock Bajo</h6>
                            <h2 class="mb-0"><?php echo number_format($stockBajo); ?></h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.8;" class="text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agotados -->
        <div class="col-md-3 mb-3">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Agotados</h6>
                            <h2 class="mb-0"><?php echo number_format($agotados); ?></h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.8;" class="text-danger">
                            <i class="bi bi-x-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Valor Estimado -->
        <div class="col-md-3 mb-3">
            <div class="card border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Valor Estimado</h6>
                            <h2 class="mb-0">$<?php echo number_format($valorEstimado, 2); ?></h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.8;" class="text-success">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico y Resumen -->
    <div class="row mt-4">
        <!-- Gráfico de Categorías -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Productos por Categoría</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoriasChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Resumen Rápido -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Resumen Rápido</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-check-circle text-success"></i> Stock Normal</span>
                            <span class="badge bg-success"><?php echo $totalProductos - $stockBajo - $agotados; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-exclamation-triangle text-warning"></i> Stock Bajo</span>
                            <span class="badge bg-warning"><?php echo $stockBajo; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-x-circle text-danger"></i> Agotados</span>
                            <span class="badge bg-danger"><?php echo $agotados; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-tags"></i> Categorías Activas</span>
                            <span class="badge bg-primary"><?php echo count($categorias); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Productos Críticos -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill"></i> Productos Críticos (Stock Bajo)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($productosCriticos)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-check-circle" style="font-size: 4rem; opacity: 0.5; color: #198754;"></i>
                            <h5 class="mt-3">¡Excelente! No hay productos con stock crítico</h5>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>SKU</th>
                                        <th>Producto</th>
                                        <th>Stock Actual</th>
                                        <th>Stock Mínimo</th>
                                        <th>Faltante</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productosCriticos as $prod): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($prod['sku'] ?? 'N/A'); ?></code></td>
                                            <td><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></td>
                                            <td><span class="badge bg-danger"><?php echo $prod['stock_actual']; ?></span></td>
                                            <td><?php echo $prod['stock_minimo']; ?></td>
                                            <td><span class="text-danger fw-bold">-<?php echo $prod['faltante']; ?> unidades</span></td>
                                            <td>
                                                <?php 
                                                $stock = $prod['stock_actual'];
                                                $minimo = $prod['stock_minimo'];
                                                if ($stock <= 0) {
                                                    echo '<span class="badge bg-danger">Agotado</span>';
                                                } elseif ($stock < $minimo) {
                                                    echo '<span class="badge bg-warning text-dark">Stock Bajo</span>';
                                                } else {
                                                    echo '<span class="badge bg-success">Normal</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo RUTA_URL; ?>stock/registrar?producto=<?php echo $prod['id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="bi bi-plus-circle"></i> Reabastecer
                                                </a>
                                                <a href="<?php echo RUTA_URL; ?>productos/editar/<?php echo $prod['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Gráfico de Categorías
    const ctx = document.getElementById('categoriasChart');
    if (ctx) {
        const categoriasData = <?php echo json_encode($categorias); ?>;
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: categoriasData.map(c => c.nombre),
                datasets: [{
                    data: categoriasData.map(c => c.total_productos),
                    backgroundColor: [
                        '#0d6efd', '#198754', '#ffc107', '#dc3545',
                        '#0dcaf0', '#6c757d', '#fd7e14', '#6f42c1'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' productos';
                            }
                        }
                    }
                }
            }
        });
    }
</script>
</body>
</html>
