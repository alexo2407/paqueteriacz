<?php 
include __DIR__ . '/../../includes/header_materialize.php'; 
require_once __DIR__ . '/../../../controlador/categoria.php';

$ctrl = new CategoriaController();
$jerarquia = $ctrl->listarJerarquico();
$categorias = $ctrl->obtenerEstadisticas();
?>

<style>
.category-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(161, 140, 209, 0.2);
}
.stat-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    transition: transform 0.3s;
    height: 100%;
}
.stat-card:hover {
    transform: translateY(-5px);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}
.table-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    overflow: hidden;
}
.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    color: #495057;
    text-transform: uppercase;
    font-size: 0.85rem;
    padding: 1rem;
}
.table tbody td {
    padding: 1rem;
    vertical-align: middle;
}
.sub-row {
    background-color: #fcfcfc;
}
.sub-indicator {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-left: 2px solid #ced4da;
    border-bottom: 2px solid #ced4da;
    margin-right: 10px;
    margin-left: 15px;
    border-radius: 0 0 0 5px;
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="category-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1 fw-bold"><i class="bi bi-folder2-open me-2"></i> Categorías</h2>
            <p class="mb-0 opacity-75">Organización jerárquica del catálogo de productos</p>
        </div>
        <div>
            <a href="<?php echo RUTA_URL; ?>categorias/crear" class="btn btn-light text-primary fw-bold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Nueva Categoría
            </a>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body p-4">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-folder2"></i>
                    </div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Total Categorías</h6>
                    <h3 class="mb-0 fw-bold text-dark"><?php echo count($categorias); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body p-4">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-diagram-3"></i>
                    </div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Categorías Padre</h6>
                    <h3 class="mb-0 fw-bold text-dark"><?php echo count($jerarquia); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body p-4">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Total Productos Asignados</h6>
                    <h3 class="mb-0 fw-bold text-dark"><?php echo array_sum(array_column($categorias, 'total_productos')); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Categorías -->
    <div class="card table-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Nivel</th>
                            <th class="text-center">Productos</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jerarquia)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted opacity-50 mb-3">
                                        <i class="bi bi-folder-x" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="text-muted">No hay categorías registradas</h5>
                                    <a href="<?php echo RUTA_URL; ?>categorias/crear" class="btn btn-primary mt-2">
                                        <i class="bi bi-plus-circle me-1"></i> Crear Primera Categoría
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jerarquia as $categoria): ?>
                                <?php
                                $productCount = 0;
                                foreach ($categorias as $cat) {
                                    if ($cat['id'] == $categoria['id']) {
                                        $productCount = $cat['total_productos'];
                                        break;
                                    }
                                }
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-muted">#<?php echo $categoria['id']; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark fs-6">
                                            <i class="bi bi-folder2 text-warning me-2"></i>
                                            <?php echo htmlspecialchars($categoria['nombre']); ?>
                                        </div>
                                    </td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($categoria['descripcion'] ?? '-'); ?></td>
                                    <td><span class="badge bg-primary text-white rounded-pill px-3">Principal</span></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-3"><?php echo $productCount; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($categoria['activo']): ?>
                                            <span class="badge rounded-pill bg-success p-2">
                                                <i class="bi bi-check-lg" style="font-size: 1.2rem;"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-danger p-2">
                                                <i class="bi bi-x-lg" style="font-size: 1.2rem;"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="<?php echo RUTA_URL; ?>categorias/ver/<?php echo $categoria['id']; ?>" 
                                               class="btn btn-info btn-square text-white" title="Ver detalles" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?php echo RUTA_URL; ?>categorias/editar/<?php echo $categoria['id']; ?>" 
                                               class="btn btn-primary btn-square" title="Editar" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Subcategorías -->
                                <?php if (!empty($categoria['subcategorias'])): ?>
                                    <?php foreach ($categoria['subcategorias'] as $subcategoria): ?>
                                        <?php
                                        $subProductCount = 0;
                                        foreach ($categorias as $cat) {
                                            if ($cat['id'] == $subcategoria['id']) {
                                                $subProductCount = $cat['total_productos'];
                                                break;
                                            }
                                        }
                                        ?>
                                        <tr class="sub-row">
                                            <td class="ps-4 text-muted small">#<?php echo $subcategoria['id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center text-dark">
                                                    <span class="sub-indicator"></span>
                                                    <i class="bi bi-folder2-open text-secondary me-2"></i>
                                                    <?php echo htmlspecialchars($subcategoria['nombre']); ?>
                                                </div>
                                            </td>
                                            <td class="text-muted small"><?php echo htmlspecialchars($subcategoria['descripcion'] ?? '-'); ?></td>
                                            <td><span class="badge bg-secondary rounded-pill px-3">Subcategoría</span></td>
                                            <td class="text-center">
                                                <span class="badge bg-white text-muted border px-3"><?php echo $subProductCount; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($subcategoria['activo']): ?>
                                                    <span class="badge rounded-pill bg-success p-2">
                                                        <i class="bi bi-check-lg" style="font-size: 1.2rem;"></i>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-danger p-2">
                                                        <i class="bi bi-x-lg" style="font-size: 1.2rem;"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <a href="<?php echo RUTA_URL; ?>categorias/ver/<?php echo $subcategoria['id']; ?>" 
                                                       class="btn btn-info btn-square text-white" title="Ver detalles" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="<?php echo RUTA_URL; ?>categorias/editar/<?php echo $subcategoria['id']; ?>" 
                                                       class="btn btn-primary btn-square" title="Editar" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer_materialize.php'; ?>
</body>
</html>
