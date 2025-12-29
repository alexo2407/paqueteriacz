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

<style>
.dashboard-header {
    background: linear-gradient(135deg, #FF416C 0%, #FF4B2B 100%);
    border-radius: 16px;
    padding: 2rem;
    color: white;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(245, 87, 108, 0.2);
}
.kpi-card {
    border: none;
    border-radius: 16px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow: hidden;
    height: 100%;
    background: white;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}
.kpi-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.kpi-icon-wrapper {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}
.kpi-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: #2c3e50;
}
.kpi-label {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 500;
}
.chart-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    height: 100%;
}
.chart-header {
    background: transparent;
    border-bottom: 1px solid #f0f0f0;
    padding: 1.25rem 1.5rem;
}
.table-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    overflow: hidden;
}
.table-header {
    background: linear-gradient(to right, #fff0f3, #fff);
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #ffeef0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.btn-action-primary {
    background: white;
    color: #f5576c;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-action-primary:hover {
    background: #fff0f3;
    color: #da4f63;
    transform: translateY(-1px);
}
</style>

<div class="container-fluid py-4">
    <!-- Modern Header -->
    <div class="dashboard-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1 fw-bold"><i class="bi bi-speedometer2 me-2"></i> Dashboard de Productos</h2>
            <p class="mb-0 opacity-75">Resumen general del inventario, alertas y métricas clave.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo RUTA_URL; ?>productos/crear" class="btn btn-action-primary shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Nuevo Producto
            </a>
            <a href="<?php echo RUTA_URL; ?>productos/listar" class="btn btn-outline-light border-2 text-white fw-bold">
                <i class="bi bi-list me-1"></i> Ver Catálogo
            </a>
        </div>
    </div>

    <!-- KPI Row -->
    <div class="row g-4 mb-4">
        <!-- Total Productos -->
        <div class="col-md-3">
            <div class="kpi-card p-4 position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-icon-wrapper bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <h3 class="kpi-value text-primary"><?php echo number_format($totalProductos); ?></h3>
                        <p class="kpi-label mb-0">Total de Productos</p>
                    </div>
                </div>
                <div class="position-absolute bottom-0 end-0 p-3 opacity-25">
                    <i class="bi bi-box-seam" style="font-size: 4rem; color: #cfe2ff;"></i>
                </div>
            </div>
        </div>

        <!-- Stock Bajo -->
        <div class="col-md-3">
            <div class="kpi-card p-4 position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-icon-wrapper bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <h3 class="kpi-value text-warning"><?php echo number_format($stockBajo); ?></h3>
                        <p class="kpi-label mb-0">Alertas de Stock Bajo</p>
                    </div>
                </div>
                 <div class="position-absolute bottom-0 end-0 p-3 opacity-25">
                    <i class="bi bi-exclamation-triangle" style="font-size: 4rem; color: #ffecb5;"></i>
                </div>
            </div>
        </div>

        <!-- Agotados -->
        <div class="col-md-3">
            <div class="kpi-card p-4 position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-icon-wrapper bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <h3 class="kpi-value text-danger"><?php echo number_format($agotados); ?></h3>
                        <p class="kpi-label mb-0">Productos Agotados</p>
                    </div>
                </div>
                <div class="position-absolute bottom-0 end-0 p-3 opacity-25">
                    <i class="bi bi-slash-circle" style="font-size: 4rem; color: #f8d7da;"></i>
                </div>
            </div>
        </div>

        <!-- Valor Estimado -->
        <div class="col-md-3">
            <div class="kpi-card p-4 position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-icon-wrapper bg-success bg-opacity-10 text-success">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <h3 class="kpi-value text-success">$<?php echo number_format($valorEstimado, 2); ?></h3>
                        <p class="kpi-label mb-0">Valor Total Inventario</p>
                    </div>
                </div>
                 <div class="position-absolute bottom-0 end-0 p-3 opacity-25">
                    <i class="bi bi-graph-up-arrow" style="font-size: 4rem; color: #d1e7dd;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos y Resumen -->
    <div class="row g-4 mb-4">
        <!-- Gráfico Categorías logic -->
        <div class="col-lg-8">
            <div class="card chart-card">
                <div class="chart-header">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-pie-chart text-primary me-2"></i> Distribución por Categoría</h5>
                </div>
                <div class="card-body position-relative">
                    <?php if (empty($categorias)): ?>
                        <div class="d-flex flex-column align-items-center justify-content-center h-100 py-5">
                            <i class="bi bi-pie-chart text-muted opacity-25" style="font-size: 4rem;"></i>
                            <p class="text-muted mt-3">No hay datos suficientes para mostrar el gráfico</p>
                        </div>
                    <?php else: ?>
                        <div style="height: 300px;">
                            <canvas id="categoriasChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Resumen -->
        <div class="col-lg-4">
            <div class="card chart-card bg-light border-0">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4 text-dark"><i class="bi bi-clipboard-data me-2"></i> Estado General</h5>
                    
                    <div class="bg-white p-3 rounded-3 shadow-sm mb-3 d-flex align-items-center justify-content-between border-start border-4 border-success">
                        <div>
                            <span class="text-muted small fw-bold text-uppercase d-block mb-1">Stock Normal</span>
                            <span class="h4 mb-0 fw-bold text-dark"><?php echo number_format($totalProductos - $stockBajo - $agotados); ?></span>
                        </div>
                        <i class="bi bi-check-circle-fill text-success fs-3"></i>
                    </div>

                    <div class="bg-white p-3 rounded-3 shadow-sm mb-3 d-flex align-items-center justify-content-between border-start border-4 border-warning">
                        <div>
                            <span class="text-muted small fw-bold text-uppercase d-block mb-1">En Riesgo</span>
                            <span class="h4 mb-0 fw-bold text-dark"><?php echo number_format($stockBajo); ?></span>
                        </div>
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-3"></i>
                    </div>

                    <div class="bg-white p-3 rounded-3 shadow-sm mb-3 d-flex align-items-center justify-content-between border-start border-4 border-danger">
                        <div>
                            <span class="text-muted small fw-bold text-uppercase d-block mb-1">Sin Stock</span>
                            <span class="h4 mb-0 fw-bold text-dark"><?php echo number_format($agotados); ?></span>
                        </div>
                        <i class="bi bi-x-circle-fill text-danger fs-3"></i>
                    </div>

                    <div class="bg-white p-3 rounded-3 shadow-sm d-flex align-items-center justify-content-between border-start border-4 border-primary">
                        <div>
                            <span class="text-muted small fw-bold text-uppercase d-block mb-1">Categorías</span>
                            <span class="h4 mb-0 fw-bold text-dark"><?php echo count($categorias); ?></span>
                        </div>
                        <i class="bi bi-tags-fill text-primary fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Productos Críticos -->
    <div class="row">
        <div class="col-12">
            <div class="card table-card">
                <div class="table-header">
                    <h5 class="mb-0 fw-bold text-danger"><i class="bi bi-exclamation-octagon-fill me-2"></i> Productos con Stock Crítico</h5>
                    <?php if (!empty($productosCriticos)): ?>
                        <span class="badge bg-danger rounded-pill px-3 py-2"><?php echo count($productosCriticos); ?> productos requieren atención</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($productosCriticos)): ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h4 class="fw-bold text-success">¡Todo en orden!</h4>
                            <p class="text-muted">No hay productos con stock por debajo del mínimo.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-4">Producto</th>
                                        <th class="text-center">SKU</th>
                                        <th class="text-center">Stock Actual</th>
                                        <th class="text-center">Mínimo Req.</th>
                                        <th class="text-center">Déficit</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productosCriticos as $prod): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($prod['categoria_nombre'] ?? 'Sin categoría'); ?></small>
                                            </td>
                                            <td class="text-center"><code class="bg-light px-2 py-1 rounded border"><?php echo htmlspecialchars($prod['sku'] ?? '--'); ?></code></td>
                                            <td class="text-center">
                                                <span class="fw-bold fs-5 <?php echo $prod['stock_actual'] == 0 ? 'text-danger' : 'text-warning'; ?>">
                                                    <?php echo $prod['stock_actual']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center text-muted"><?php echo $prod['stock_minimo']; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill">
                                                    -<?php echo $prod['faltante']; ?> unid.
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($prod['stock_actual'] == 0): ?>
                                                    <span class="badge bg-danger">AGOTADO</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">BAJO</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <a href="<?php echo RUTA_URL; ?>stock/registrar?producto=<?php echo $prod['id']; ?>" class="btn btn-sm btn-success shadow-sm" title="Reabastecer">
                                                    <i class="bi bi-plus-lg"></i>
                                                </a>
                                                <a href="<?php echo RUTA_URL; ?>productos/editar/<?php echo $prod['id']; ?>" class="btn btn-sm btn-outline-primary ms-1" title="Editar">
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
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('categoriasChart');
        if (ctx) {
            const categoriasData = <?php echo json_encode($categorias); ?>;
            
            // Paleta de colores vibrantes moderna
            const colors = [
                '#f5576c', '#f093fb', '#4facfe', '#00f2fe', 
                '#43e97b', '#38f9d7', '#fa709a', '#fee140', 
                '#667eea', '#764ba2'
            ];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: categoriasData.map(c => c.nombre),
                    datasets: [{
                        data: categoriasData.map(c => c.total_productos),
                        backgroundColor: colors,
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 255, 255, 0.9)',
                            titleColor: '#2c3e50',
                            bodyColor: '#2c3e50',
                            borderColor: '#e9ecef',
                            borderWidth: 1,
                            padding: 10,
                            boxPadding: 4,
                            usePointStyle: true,
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1) + '%';
                                    return ` ${context.label}: ${value} (${percentage})`;
                                }
                            }
                        }
                    },
                    layout: {
                        padding: 20
                    }
                }
            });
        }
    });
</script>
</body>
</html>
