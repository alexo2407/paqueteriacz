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

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($producto['nombre']); ?></h2>
            <?php if (!empty($producto['sku'])): ?>
                <p class="text-muted mb-0">SKU: <code><?php echo htmlspecialchars($producto['sku']); ?></code></p>
            <?php endif; ?>
        </div>
        <div>
            <a href="<?php echo RUTA_URL; ?>stock/registrar?producto=<?php echo $id; ?>" class="btn btn-success me-2">
                <i class="bi bi-plus-circle"></i> Reabastecer
            </a>
            <a href="<?php echo RUTA_URL; ?>productos/editar/<?php echo $id; ?>" class="btn btn-primary me-2">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="<?php echo RUTA_URL; ?>productos/listar" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Columna Izquierda: Imagen e Info Principal -->
        <div class="col-md-4">
            <!-- Imagen -->
            <div class="card mb-3">
                <div class="card-body text-center">
                    <?php if (!empty($producto['imagen_url'])): ?>
                        <?php 
                        $imgSrc = $producto['imagen_url'];
                        if (!str_starts_with($imgSrc, 'http')) {
                            $imgSrc = RUTA_URL . $imgSrc;
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                             class="img-fluid rounded" 
                             alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                             onerror="this.src='https://via.placeholder.com/400x300?text=Sin+Imagen'">
                    <?php else: ?>
                        <div class="bg-light rounded p-5">
                            <i class="bi bi-image text-muted" style="font-size: 5rem;"></i>
                            <p class="text-muted mt-2">Sin imagen</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Estado del Producto -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Estado del Producto</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Estado:</span>
                        <?php if (isset($producto['activo']) && $producto['activo']): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Activo</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Inactivo</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php 
                    $stockActual = (int)($producto['stock_total'] ?? 0);
                    $stockMinimo = (int)($producto['stock_minimo'] ?? 10);
                    $porcentaje = ($producto['stock_maximo'] > 0) ? min(100, ($stockActual / $producto['stock_maximo']) * 100) : 0;
                    
                    $badgeClass = 'success';
                    $badgeText = 'Stock Normal';
                    if ($stockActual <= 0) {
                        $badgeClass = 'danger';
                        $badgeText = 'Agotado';
                    } elseif ($stockActual < $stockMinimo) {
                        $badgeClass = 'warning';
                        $badgeText = 'Stock Bajo';
                    }
                    ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Nivel de Stock:</span>
                        <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                    </div>
                    
                    <div class="mt-3">
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar bg-<?php echo $badgeClass; ?>" role="progressbar" 
                                 style="width: <?php echo $porcentaje; ?>%" 
                                 aria-valuenow="<?php echo $stockActual; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="<?php echo $producto['stock_maximo']; ?>">
                                <?php echo $stockActual; ?> unidades
                            </div>
                        </div>
                        <small class="text-muted">
                            Mínimo: <?php echo $stockMinimo; ?> | Máximo: <?php echo $producto['stock_maximo'] ?? 100; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Información Detallada -->
        <div class="col-md-8">
            <!-- Información General -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Información General</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nombre:</strong><br><?php echo htmlspecialchars($producto['nombre']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>SKU:</strong><br><code><?php echo htmlspecialchars($producto['sku'] ?? 'N/A'); ?></code></p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Categoría:</strong><br>
                                <?php echo $categoria ? '<span class="badge bg-secondary">' . htmlspecialchars($categoria['nombre']) . '</span>' : '<span class="text-muted">Sin categoría</span>'; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Marca:</strong><br><?php echo htmlspecialchars($producto['marca'] ?? 'N/A'); ?></p>
                        </div>
                    </div>

                    <?php if (!empty($producto['descripcion'])): ?>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>Descripción:</strong><br><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Precios e Inventario -->
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-cash-stack"></i> Precios e Inventario</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted">Precio Unitario</small>
                                <h3 class="mb-0 text-success">$<?php echo number_format($producto['precio_usd'], 2); ?></h3>
                                <small class="text-muted">USD</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted">Stock Total</small>
                                <h3 class="mb-0"><?php echo $stockActual; ?></h3>
                                <small class="text-muted"><?php echo htmlspecialchars($producto['unidad'] ?? 'unidades'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted">Valor Total</small>
                                <h3 class="mb-0 text-primary">$<?php echo number_format($stockActual * $producto['precio_usd'], 2); ?></h3>
                                <small class="text-muted">USD</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-4">
                            <p><strong>Unidad de Medida:</strong><br><?php echo ucfirst($producto['unidad'] ?? 'unidad'); ?></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Peso:</strong><br><?php echo ($producto['peso'] ?? 0); ?> kg</p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Stock Mínimo/Máximo:</strong><br><?php echo $stockMinimo; ?> / <?php echo $producto['stock_maximo'] ?? 100; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fechas -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Información de Registro</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Fecha de Creación:</strong><br>
                                <?php 
                                if (!empty($producto['created_at'])) {
                                    echo date('d/m/Y H:i', strtotime($producto['created_at']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Última Actualización:</strong><br>
                                <?php 
                                if (!empty($producto['updated_at'])) {
                                    echo date('d/m/Y H:i', strtotime($producto['updated_at']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </p>
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
