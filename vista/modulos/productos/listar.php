<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/producto.php';
require_once __DIR__ . '/../../../modelo/categoria.php';

// Incluir helpers solo si existen
if (file_exists(__DIR__ . '/../../../utils/ui_helpers.php')) {
    require_once __DIR__ . '/../../../utils/ui_helpers.php';
}

start_secure_session();
require_login();

// Obtener filtro de usuario (proveedores solo ven sus productos)
$filtroUsuarioCreador = getIdUsuarioCreadorFilter();

// Obtener filtros de búsqueda
$categoriaFiltro = $_GET['categoria'] ?? '';
$marcaFiltro = $_GET['marca'] ?? '';
$estadoFiltro = $_GET['estado'] ?? '';

// Construir filtros
$filtros = [];
if ($categoriaFiltro) $filtros['categoria_id'] = $categoriaFiltro;
if ($marcaFiltro) $filtros['marca'] = $marcaFiltro;
if ($estadoFiltro !== '') $filtros['activo'] = $estadoFiltro === '1';

// Obtener datos con filtro de usuario
if (empty($filtros)) {
    $productos = ProductoModel::listarConInventario($filtroUsuarioCreador);
} else {
    // Si hay filtros adicionales, usar listarConFiltros y luego filtrar por usuario
    $productos = ProductoModel::listarConFiltros($filtros);
    // Aplicar filtro de usuario manualmente si es proveedor
    if ($filtroUsuarioCreador !== null) {
        $productos = array_filter($productos, function($p) use ($filtroUsuarioCreador) {
            return (isset($p['id_usuario_creador']) && (int)$p['id_usuario_creador'] === $filtroUsuarioCreador)
                   || !isset($p['id_usuario_creador']) || $p['id_usuario_creador'] === null;
        });
        $productos = array_values($productos); // Re-indexar
    }
}

$categorias = CategoriaModel::listarJerarquico();

// Obtener marcas únicas (solo de productos visibles para el usuario)
$marcasUnicas = [];
foreach ($productos as $p) {
    if (!empty($p['marca']) && !in_array($p['marca'], $marcasUnicas)) {
        $marcasUnicas[] = $p['marca'];
    }
}
sort($marcasUnicas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Productos - Paquetería CruzValle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

    
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-box-seam"></i> Productos</h2>
                <p class="text-muted mb-0">Gestión completa del catálogo de productos</p>
            </div>
            <div>
                <a href="<?php echo RUTA_URL; ?>productos/dashboard" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="<?php echo RUTA_URL; ?>productos/crear" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nuevo Producto
                </a>
            </div>
        </div>

        <!--Filtros -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small">Categoría</label>
                            <select name="categoria" class="form-select select2-searchable" data-placeholder="Buscar categoría...">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoriaFiltro == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nombre' ]);?>
                                    </option>
                                    <?php if (!empty($cat['subcategorias'])): ?>
                                        <?php foreach ($cat['subcategorias'] as $subcat): ?>
                                            <option value="<?php echo $subcat['id']; ?>" <?php echo $categoriaFiltro == $subcat['id'] ? 'selected' : ''; ?>>
                                                &nbsp;&nbsp;↳ <?php echo htmlspecialchars($subcat['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small">Marca</label>
                            <select name="marca" class="form-select select2-searchable" data-placeholder="Buscar marca...">
                                <option value="">Todas las marcas</option>
                                <?php foreach ($marcasUnicas as $marca): ?>
                                    <option value="<?php echo htmlspecialchars($marca); ?>" <?php echo $marcaFiltro === $marca ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($marca); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small">Estado</label>
                            <select name="estado" class="form-select select2-searchable" data-placeholder="Seleccionar estado...">
                                <option value="">Todos</option>
                                <option value="1" <?php echo $estadoFiltro === '1' ? 'selected' : ''; ?>>Activos</option>
                                <option value="0" <?php echo $estadoFiltro === '0' ? 'selected' : ''; ?>>Inactivos</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label small">Buscar</label>
                            <input type="search" id="busquedaTabla" class="form-control" placeholder="Buscar...">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel"></i> Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Productos -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaProductos" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>SKU</th>
                                <th>Nombre</th>
                                <th>Categoría</th>
                                <th>Marca</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $prod): 
                                $stock = (int)($prod['stock_total'] ?? 0);
                                $minimo = (int)($prod['stock_minimo'] ?? 10);
                                $badgeClass = $stock <= 0 ? 'danger' : ($stock < $minimo ? 'warning' : 'success');
                                $badgeText = $stock <= 0 ? 'Agotado' : ($stock < $minimo ? 'Bajo' : 'Normal');
                            ?>
                                <tr>
                                    <td><?php echo $prod['id']; ?></td>
                                    <td><code><?php echo htmlspecialchars($prod['sku'] ?? 'N/A'); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></td>
                                    <td>
                                        <?php
                                        $cat = $prod['categoria_id'] ? CategoriaModel::obtenerPorId($prod['categoria_id']) : null;
                                        echo $cat ? '<span class="badge bg-secondary">' . htmlspecialchars($cat['nombre']) . '</span>' : '-';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($prod['marca'] ?? '-'); ?></td>
                                    <td><strong>$<?php echo number_format($prod['precio_usd'] ?? 0, 2); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                                        <strong class="ms-2"><?php echo $stock; ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($prod['activo'] ?? true): ?>
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-circle-fill text-danger"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo RUTA_URL; ?>productos/ver/<?php echo $prod['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?php echo RUTA_URL; ?>productos/editar/<?php echo $prod['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#tablaProductos').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            pageLength: 25,
            responsive: true
        });

        $('#busquedaTabla').on('keyup', function() {
            $('#tablaProductos').DataTable().search(this.value).draw();
        });
    });
</script>
</body>
</html>
