<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../modelo/categoria.php';

start_secure_session();
require_login();

// Obtener categorías con conteo de productos
$categorias = CategoriaModel::contarProductosPorCategoria();
$jerarquia = CategoriaModel::listarJerarquico();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías - Paquetería CruzValle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-folder2"></i> Categorías de Productos</h2>
            <p class="text-muted mb-0">Gestiona las categorías y subcategorías de tus productos</p>
        </div>
        <div>
            <a href="<?php echo RUTA_URL; ?>categorias/crear" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nueva Categoría
            </a>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-folder2"></i> Total Categorías</h6>
                    <h3 class="mb-0"><?php echo count($categorias); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-diagram-3"></i> Categorías Padre</h6>
                    <h3 class="mb-0"><?php echo count($jerarquia); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-box-seam"></i> Total Productos</h6>
                    <h3 class="mb-0"><?php echo array_sum(array_column($categorias, 'total_productos')); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Categorías -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-list-ul"></i> Listado de Categorías</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Tipo</th>
                            <th class="text-center">Productos</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jerarquia)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                    <p class="mb-0 mt-2">No hay categorías registradas</p>
                                    <a href="<?php echo RUTA_URL; ?>categorias/crear" class="btn btn-sm btn-primary mt-2">
                                        <i class="bi bi-plus-circle"></i> Crear Primera Categoría
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jerarquia as $categoria): ?>
                                <?php
                                // Buscar conteo de productos
                                $productCount = 0;
                                foreach ($categorias as $cat) {
                                    if ($cat['id'] == $categoria['id']) {
                                        $productCount = $cat['total_productos'];
                                        break;
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?php echo $categoria['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($categoria['nombre']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($categoria['descripcion'] ?? '-'); ?></td>
                                    <td><span class="badge bg-primary">Categoría Padre</span></td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo $productCount; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($categoria['activo']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?php echo RUTA_URL; ?>categorias/ver/<?php echo $categoria['id']; ?>" 
                                               class="btn btn-outline-info" title="Ver detalles">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?php echo RUTA_URL; ?>categorias/editar/<?php echo $categoria['id']; ?>" 
                                               class="btn btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Subcategorías -->
                                <?php if (!empty($categoria['subcategorias'])): ?>
                                    <?php foreach ($categoria['subcategorias'] as $subcategoria): ?>
                                        <?php
                                        // Buscar conteo de productos para subcategoría
                                        $subProductCount = 0;
                                        foreach ($categorias as $cat) {
                                            if ($cat['id'] == $subcategoria['id']) {
                                                $subProductCount = $cat['total_productos'];
                                                break;
                                            }
                                        }
                                        ?>
                                        <tr class="table-secondary">
                                            <td><?php echo $subcategoria['id']; ?></td>
                                            <td>
                                                <span class="ms-4">
                                                    <i class="bi bi-arrow-return-right"></i>
                                                    <?php echo htmlspecialchars($subcategoria['nombre']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($subcategoria['descripcion'] ?? '-'); ?></td>
                                            <td><span class="badge bg-secondary">Subcategoría</span></td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?php echo $subProductCount; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($subcategoria['activo']): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="<?php echo RUTA_URL; ?>categorias/ver/<?php echo $subcategoria['id']; ?>" 
                                                       class="btn btn-outline-info" title="Ver detalles">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="<?php echo RUTA_URL; ?>categorias/editar/<?php echo $subcategoria['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Editar">
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>

</body>
</html>
