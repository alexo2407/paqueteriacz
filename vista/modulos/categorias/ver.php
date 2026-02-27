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
    <title><?php echo htmlspecialchars($categoria['nombre']); ?> - App RutaEx-Latam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header_materialize.php'; ?>

<style>
.detail-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-bottom: 2rem;
}
.detail-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
}
.info-section {
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
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
.btn-back {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.4);
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
    text-decoration: none;
}
.btn-back:hover {
    background: rgba(255,255,255,0.3);
    color: white;
}
.sub-card-header {
    background: #f8f9fa;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e9ecef;
    font-weight: 600;
    color: #495057;
}
</style>

<div class="container-fluid py-4">
    <!-- Header Card -->
    <div class="card detail-card">
        <div class="detail-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white bg-opacity-25 rounded-circle p-3">
                    <i class="bi bi-folder2-open fs-3"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                    <?php if ($categoriaPadre): ?>
                        <p class="mb-0 opacity-75">
                            Subcategoría de: <strong><?php echo htmlspecialchars($categoriaPadre['nombre']); ?></strong>
                        </p>
                    <?php else: ?>
                        <p class="mb-0 opacity-75">Categoría Principal</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo RUTA_URL; ?>categorias/editar/<?php echo $id; ?>" class="btn btn-light text-primary fw-bold shadow-sm">
                    <i class="bi bi-pencil me-1"></i> Editar
                </a>
                <a href="<?php echo RUTA_URL; ?>categorias/listar" class="btn btn-back">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>
        </div>
        
        <div class="card-body p-4">
            <div class="row">
                <!-- Info General -->
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded-3 mb-4">
                        <h6 class="text-secondary text-uppercase small fw-bold mb-3"><i class="bi bi-info-circle me-1"></i> Información General</h6>
                        
                        <div class="mb-3">
                            <p class="info-label">Descripción</p>
                            <div class="info-value fs-6">
                                <?php echo !empty($categoria['descripcion']) ? nl2br(htmlspecialchars($categoria['descripcion'])) : '<span class="text-muted fst-italic">Sin descripción</span>'; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <p class="info-label">Estado</p>
                            <?php if ($categoria['activo']): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3"><i class="bi bi-check-circle me-1"></i> Activo</span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3"><i class="bi bi-x-circle me-1"></i> Inactivo</span>
                            <?php endif; ?>
                        </div>
                        
                        <hr class="text-muted opacity-25">
                        
                        <div class="row g-2">
                             <div class="col-6">
                                <p class="info-label mb-1">Creado</p>
                                <small class="text-muted"><?php echo !empty($categoria['created_at']) ? date('d/m/Y', strtotime($categoria['created_at'])) : 'N/A'; ?></small>
                             </div>
                             <div class="col-6">
                                <p class="info-label mb-1">Actualizado</p>
                                <small class="text-muted"><?php echo !empty($categoria['updated_at']) ? date('d/m/Y', strtotime($categoria['updated_at'])) : 'N/A'; ?></small>
                             </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contenido Relacionado -->
                <div class="col-md-8">
                    
                    <!-- Subcategorías -->
                    <?php if (!empty($subcategorias)): ?>
                    <div class="card mb-4 border shadow-sm">
                        <div class="sub-card-header bg-white">
                            <i class="bi bi-diagram-3 me-2 text-primary"></i> Subcategorías (<?php echo count($subcategorias); ?>)
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($subcategorias as $sub): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <a href="<?php echo RUTA_URL; ?>categorias/ver/<?php echo $sub['id']; ?>" class="fw-bold text-decoration-none text-dark stretched-link">
                                            <?php echo htmlspecialchars($sub['nombre']); ?>
                                        </a>
                                        <?php if (!empty($sub['descripcion'])): ?>
                                            <div class="small text-muted text-truncate" style="max-width: 300px;"><?php echo htmlspecialchars($sub['descripcion']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($sub['activo']): ?>
                                        <span class="badge bg-success rounded-pill p-2"><i class="bi bi-check-lg"></i></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary rounded-pill p-2"><i class="bi bi-x-lg"></i></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Productos -->
                    <div class="card border shadow-sm">
                        <div class="sub-card-header bg-white d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-box-seam me-2 text-primary"></i> Productos en esta categoría (<?php echo count($productos); ?>)</span>
                            <a href="<?php echo RUTA_URL; ?>productos/crear?categoria=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                                <i class="bi bi-plus-lg me-1"></i> Agregar
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($productos)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 opacity-25"></i>
                                    <p class="mt-2 text-0.9rem">No hay productos registrados en esta categoría.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="ps-4">Producto</th>
                                                <th>SKU</th>
                                                <th class="text-end">Precio</th>
                                                <th class="text-center">Stock</th>
                                                <th class="text-center">Estado</th>
                                                <th class="text-end pe-4">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($productos as $producto): ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                                    <td><code class="text-dark bg-light px-2 py-1 rounded small"><?php echo htmlspecialchars($producto['sku'] ?? '-'); ?></code></td>
                                                    <td class="text-end text-success fw-bold">$<?php echo number_format($producto['precio_usd'], 2); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-secondary bg-opacity-10 text-dark"><?php echo $producto['stock_total'] ?? 0; ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($producto['activo']): ?>
                                                            <i class="bi bi-check-circle-fill text-success"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-x-circle-fill text-danger"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end pe-4">
                                                        <a href="<?php echo RUTA_URL; ?>productos/ver/<?php echo $producto['id']; ?>" class="btn btn-sm btn-light text-primary border" title="Ver producto">
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
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer_materialize.php'; ?>

</body>
</html>
