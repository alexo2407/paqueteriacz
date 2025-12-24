<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../modelo/categoria.php';
require_once __DIR__ . '/../../../modelo/producto.php';

start_secure_session();
require_login();

// Obtener ID de la categoría
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($parametros[0]) ? (int)$parametros[0] : 0);

if ($id <= 0) {
    header('Location: ' . RUTA_URL . 'categorias/listar');
    exit;
}

// Obtener datos de la categoría
$categoria = CategoriaModel::obtenerPorId($id);
if (!$categoria) {
    header('Location: ' . RUTA_URL . 'categorias/listar');
    exit;
}

// Obtener categoría padre si existe
$categoriaPadre = null;
if (!empty($categoria['padre_id'])) {
    $categoriaPadre = CategoriaModel::obtenerPorId($categoria['padre_id']);
}

// Obtener subcategorías
$subcategorias = CategoriaModel::obtenerSubcategorias($id);

// Obtener productos de esta categoría
$productos = ProductoModel::listarPorCategoria($id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($categoria['nombre']); ?> - Paquetería CruzValle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-folder2"></i> <?php echo htmlspecialchars($categoria['nombre']); ?></h2>
            <?php if ($categoriaPadre): ?>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="<?php echo RUTA_URL; ?>categorias/ver/<?php echo $categoriaPadre['id']; ?>">
                                <?php echo htmlspecialchars($categoriaPadre['nombre']); ?>
                            </a>
                        </li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($categoria['nombre']); ?></li>
                    </ol>
                </nav>
            <?php endif; ?>
        </div>
        <div>
            <a href="<?php echo RUTA_URL; ?>categorias/editar/<?php echo $id; ?>" class="btn btn-primary me-2">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="<?php echo RUTA_URL; ?>categorias/listar" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Columna Izquierda -->
        <div class="col-md-4">
            <!-- Información General -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Información General</h6>
                </div>
                <div class="card-body">
                    <p><strong>Nombre:</strong><br><?php echo htmlspecialchars($categoria['nombre']); ?></p>
                    
                    <?php if (!empty($categoria['descripcion'])): ?>
                        <p><strong>Descripción:</strong><br><?php echo nl2br(htmlspecialchars($categoria['descripcion'])); ?></p>
                    <?php endif; ?>
                    
                    <p>
                        <strong>Tipo:</strong><br>
                        <?php if (empty($categoria['padre_id'])): ?>
                            <span class="badge bg-primary">Categoría Padre</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Subcategoría</span>
                        <?php endif; ?>
                    </p>
                    
                    <?php if ($categoriaPadre): ?>
                        <p>
                            <strong>Categoría Padre:</strong><br>
                            <a href="<?php echo RUTA_URL; ?>categorias/ver/<?php echo $categoriaPadre['id']; ?>">
                                <?php echo htmlspecialchars($categoriaPadre['nombre']); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <p>
                        <strong>Estado:</strong><br>
                        <?php if ($categoria['activo']): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> Inactivo</span>
                        <?php endif; ?>
                    </p>
                    
                    <hr>
                    
                    <p class="mb-1">
                        <strong>Fecha de Creación:</strong><br>
                        <small class="text-muted">
                            <?php echo !empty($categoria['created_at']) ? date('d/m/Y H:i', strtotime($categoria['created_at'])) : 'N/A'; ?>
                        </small>
                    </p>
                    
                    <p class="mb-0">
                        <strong>Última Actualización:</strong><br>
                        <small class="text-muted">
                            <?php echo !empty($categoria['updated_at']) ? date('d/m/Y H:i', strtotime($categoria['updated_at'])) : 'N/A'; ?>
                        </small>
                    </p>
                </div>
            </div>
        </div>

        <!-- Columna Derecha -->
        <div class="col-md-8">
            <!-- Subcategorías -->
            <?php if (!empty($subcategorias)): ?>
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Subcategorías (<?php echo count($subcategorias); ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach ($subcategorias as $sub): ?>
                            <a href="<?php echo RUTA_URL; ?>categorias/ver/<?php echo $sub['id']; ?>" 
                               class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($sub['nombre']); ?></h6>
                                    <?php if ($sub['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($sub['descripcion'])): ?>
                                    <p class="mb-1 small text-muted"><?php echo htmlspecialchars($sub['descripcion']); ?></p>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Productos -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-box-seam"></i> Productos (<?php echo count($productos); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($productos)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <p class="mb-0 mt-2">No hay productos en esta categoría</p>
                            <a href="<?php echo RUTA_URL; ?>productos/crear?categoria=<?php echo $id; ?>" class="btn btn-sm btn-primary mt-2">
                                <i class="bi bi-plus-circle"></i> Agregar Producto
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>SKU</th>
                                        <th class="text-end">Precio</th>
                                        <th class="text-center">Stock</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos as $producto): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                            <td><code><?php echo htmlspecialchars($producto['sku'] ?? '-'); ?></code></td>
                                            <td class="text-end">$<?php echo number_format($producto['precio_usd'], 2); ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?php echo $producto['stock_total'] ?? 0; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($producto['activo']): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="<?php echo RUTA_URL; ?>productos/ver/<?php echo $producto['id']; ?>" 
                                                   class="btn btn-sm btn-outline-info">
                                                    <i class="bi bi-eye"></i>
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

</body>
</html>
