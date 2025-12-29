<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../modelo/producto.php';
require_once __DIR__ . '/../../../modelo/categoria.php';

start_secure_session();
require_login();

// Obtener ID del producto
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($parametros[0]) ? (int)$parametros[0] : 0);

if ($id <= 0) {
    header('Location: ' . RUTA_URL . 'productos/listar');
    exit;
}

// Obtener datos del producto
$producto = ProductoModel::obtenerPorId($id);
if (!$producto) {
    header('Location: ' . RUTA_URL . 'productos/listar');
    exit;
}

// Obtener categoría si existe
$categoria = null;
if (isset($producto['categoria_id']) && $producto['categoria_id']) {
    $categoria = CategoriaModel::obtenerPorId($producto['categoria_id']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($producto['nombre']); ?> - Paquetería CruzValle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<style>
.detalle-producto-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.detalle-producto-header {
    background: linear-gradient(135deg, #FF416C 0%, #FF4B2B 100%);
    color: white;
    padding: 1.5rem 2rem;
}
.detalle-producto-header h3 {
    margin: 0;
    font-weight: 600;
}
.info-section {
    padding: 1.5rem;
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
}
.info-section:last-child {
    border-bottom: none;
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
.kpi-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.25rem;
    text-align: center;
    border: 1px solid #e9ecef;
    transition: transform 0.3s ease;
}
.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}
.kpi-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #f5576c;
    margin-bottom: 0px;
}
.kpi-label {
    font-size: 0.9rem;
    color: #6c757d;
}
.img-container {
    background: #fff;
    border-radius: 16px;
    padding: 1rem;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    text-align: center;
}
.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
}
.btn-action {
    border-radius: 10px;
    font-weight: 500;
    padding: 0.6rem 1.25rem;
    transition: all 0.3s;
}
.btn-back {
    background: #fff;
    border: 1px solid #dee2e6;
    color: #6c757d;
}
.btn-back:hover {
    background: #f8f9fa;
    color: #495057;
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h2 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($producto['nombre']); ?></h2>
                <?php if (isset($producto['activo']) && $producto['activo']): ?>
                    <span class="badge bg-success rounded-pill px-3"><i class="bi bi-check-circle-fill me-1"></i> Activo</span>
                <?php else: ?>
                    <span class="badge bg-danger rounded-pill px-3"><i class="bi bi-x-circle-fill me-1"></i> Inactivo</span>
                <?php endif; ?>
            </div>
            <p class="text-muted mt-1 mb-0"><i class="bi bi-upc-scan me-1"></i> SKU: <code class="text-dark bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($producto['sku'] ?? 'N/A'); ?></code></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo RUTA_URL; ?>stock/registrar?producto=<?php echo $id; ?>" class="btn btn-success btn-action shadow-sm text-white">
                <i class="bi bi-plus-circle me-1"></i> Reabastecer
            </a>
            <a href="<?php echo RUTA_URL; ?>productos/editar/<?php echo $id; ?>" class="btn btn-primary btn-action shadow-sm">
                <i class="bi bi-pencil me-1"></i> Editar
            </a>
            <a href="<?php echo RUTA_URL; ?>productos/listar" class="btn btn-back btn-action">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Columna Izquierda: Imagen -->
        <div class="col-md-4 mb-4">
            <div class="img-container mb-4">
                <?php if (!empty($producto['imagen_url'])): ?>
                    <?php 
                    $imgSrc = $producto['imagen_url'];
                    if (!str_starts_with($imgSrc, 'http')) {
                        $imgSrc = RUTA_URL . $imgSrc;
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                         class="img-fluid rounded" 
                         style="max-height: 400px; object-fit: contain;"
                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                         onerror="this.src='https://via.placeholder.com/400x300?text=Sin+Imagen'">
                <?php else: ?>
                    <div class="py-5">
                        <i class="bi bi-image text-muted opacity-25" style="font-size: 8rem;"></i>
                        <p class="text-muted mt-3 mb-0">Sin imagen disponible</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Estado de Stock (Card pequeño) -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <h6 class="text-muted text-uppercase fw-bold mb-3 small"><i class="bi bi-graph-up-arrow me-1"></i> Estado del Inventario</h6>
                    
                    <?php 
                    $stockActual = (int)($producto['stock_total'] ?? 0);
                    $stockMinimo = (int)($producto['stock_minimo'] ?? 10);
                    $porcentaje = ($producto['stock_maximo'] > 0) ? min(100, ($stockActual / $producto['stock_maximo']) * 100) : 0;
                    
                    $stockColor = 'success';
                    $stockText = 'Stock Normal';
                    if ($stockActual <= 0) {
                        $stockColor = 'danger';
                        $stockText = 'Agotado';
                    } elseif ($stockActual < $stockMinimo) {
                        $stockColor = 'warning';
                        $stockText = 'Stock Bajo';
                    }
                    ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold fs-5 text-dark"><?php echo $stockActual; ?> <small class="text-muted fw-normal">unidades</small></span>
                        <span class="badge bg-<?php echo $stockColor; ?> bg-opacity-10 text-<?php echo $stockColor; ?> border border-<?php echo $stockColor; ?>"><?php echo $stockText; ?></span>
                    </div>
                    
                    <div class="progress" style="height: 10px; border-radius: 10px;">
                        <div class="progress-bar bg-<?php echo $stockColor; ?>" role="progressbar" 
                             style="width: <?php echo $porcentaje; ?>%" 
                             aria-valuenow="<?php echo $stockActual; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="<?php echo $producto['stock_maximo']; ?>">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-2 small text-muted">
                        <span>Min: <?php echo $stockMinimo; ?></span>
                        <span>Max: <?php echo $producto['stock_maximo'] ?? 100; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Información Detallada -->
        <div class="col-md-8">
            <div class="card detalle-producto-card">
                <div class="detalle-producto-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-info-circle fs-3"></i>
                        </div>
                        <div>
                            <h3>Detalles del Producto</h3>
                            <p class="mb-0 opacity-75">Información completa y métricas</p>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <!-- Sección KPIs -->
                    <div class="info-section bg-light">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="kpi-card">
                                    <div class="kpi-label mb-1">Precio Unitario</div>
                                    <h4 class="kpi-value text-success">$<?php echo number_format($producto['precio_usd'], 2); ?></h4>
                                    <small class="text-muted">USD</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="kpi-card">
                                    <div class="kpi-label mb-1">Existencia Total</div>
                                    <h4 class="kpi-value text-dark"><?php echo $stockActual; ?></h4>
                                    <small class="text-muted"><?php echo ucfirst($producto['unidad'] ?? 'Unidades'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="kpi-card">
                                    <div class="kpi-label mb-1">Valor Inventario</div>
                                    <h4 class="kpi-value text-primary">$<?php echo number_format($stockActual * $producto['precio_usd'], 2); ?></h4>
                                    <small class="text-muted">Estimado USD</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información General -->
                    <div class="info-section">
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <p class="info-label"><i class="bi bi-tag me-1"></i> Categoría</p>
                                <div class="info-value">
                                    <?php echo $categoria ? '<span class="badge bg-light text-secondary border px-3 py-2">' . htmlspecialchars($categoria['nombre']) . '</span>' : 'Sin categoría'; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <p class="info-label"><i class="bi bi-award me-1"></i> Marca</p>
                                <div class="info-value"><?php echo htmlspecialchars($producto['marca'] ?? 'No especificada'); ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($producto['descripcion'])): ?>
                        <div class="mb-2">
                            <p class="info-label"><i class="bi bi-text-paragraph me-1"></i> Descripción</p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Datos Técnicos -->
                    <div class="info-section">
                        <h6 class="text-muted text-uppercase fw-bold mb-3 small"><i class="bi bi-rulers me-1"></i> Especificaciones</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <p class="info-label">Unidad de Medida</p>
                                <p class="fw-bold text-dark"><?php echo ucfirst($producto['unidad'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="col-md-4">
                                <p class="info-label">Peso</p>
                                <p class="fw-bold text-dark"><?php echo ($producto['peso'] ?? 0); ?> kg</p>
                            </div>
                            <div class="col-md-4">
                                <p class="info-label">Última Actualización</p>
                                <p class="fw-bold text-dark">
                                    <?php 
                                    echo (!empty($producto['updated_at'])) ? date('d/m/Y h:i A', strtotime($producto['updated_at'])) : 'N/A';
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

</body>
</html>
