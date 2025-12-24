<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/producto.php';

start_secure_session();
require_login();

// Obtener filtro de usuario (proveedores solo ven sus productos)
$filtroUsuario = getIdUsuarioCreadorFilter();

$productoId = $_GET['producto'] ?? '';
$producto = null;

// Obtener productos con filtro de usuario
$productos = ProductoModel::listarConInventario($filtroUsuario);

// Si hay producto seleccionado, verificar que tenga permiso
if ($productoId) {
    $producto = ProductoModel::obtenerPorId($productoId);
    // Verificar permiso de acceso
    if ($producto && !canViewProduct($producto)) {
        $producto = null; // No tiene permiso
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Kardex - Paquetería CruzValle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-file-earmark-text"></i> Reporte Kardex</h2>
            <p class="text-muted mb-0">Historial de movimientos por producto</p>
        </div>
        <a href="<?php echo RUTA_URL; ?>stock/listar" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <!-- Selector -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-search"></i> Seleccionar Producto</h5>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="row">
                    <div class="col-md-10">
                        <select class="form-select form-select-lg" name="producto" required>
                            <option value="">Selecciona un producto...</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo ($productoId == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['nombre']); ?>
                                    <?php if (!empty($p['sku'])): ?>
                                        - SKU: <?php echo htmlspecialchars($p['sku']); ?>
                                    <?php endif; ?>
                                    - Stock: <?php echo (int)($p['stock_total'] ?? 0); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-search"></i> Ver
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($producto): ?>
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($producto['nombre']); ?></h5>
                    <button class="btn btn-light btn-sm" onclick="window.print()">
                        <i class="bi bi-printer"></i> Imprimir
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <p><strong>SKU:</strong> <code><?php echo htmlspecialchars($producto['sku'] ?? 'N/A'); ?></code></p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Precio:</strong> $<?php echo number_format($producto['precio_usd'] ?? 0, 2); ?> USD</p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Stock Actual:</strong> 
                            <span class="badge bg-primary fs-6"><?php echo (int)($producto['stock_total'] ?? 0); ?> unidades</span>
                        </p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Marca:</strong> <?php echo htmlspecialchars($producto['marca'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-warning">
                <h6 class="mb-0"><i class="bi bi-clock-history"></i> Historial de Movimientos</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle"></i>
                    <strong>Nota:</strong> El reporte Kardex completo con entradas/salidas/saldos acumulados estará disponible cuando se actualice la tabla stock con los nuevos campos (tipo_movimiento, motivo, etc.).
                    <br><br>
                    Mientras tanto, puedes:
                    <ul class="mb-0 mt-2">
                        <li>Ver <a href="<?php echo RUTA_URL; ?>stock/listar" class="alert-link">todos los movimientos de stock</a></li>
                        <li>Registrar <a href="<?php echo RUTA_URL; ?>stock/crear?producto=<?php echo $productoId; ?>" class="alert-link">un nuevo movimiento</a> para este producto</li>
                    </ul>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-file-earmark-text" style="font-size: 4rem; opacity: 0.5;"></i>
                <h5 class="mt-3">Selecciona un producto</h5>
                <p class="text-muted">Elige un producto del selector para ver su reporte Kardex</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<style media="print">
    .btn, nav, footer, .alert { display: none !important; }
</style>

</body>
</html>
