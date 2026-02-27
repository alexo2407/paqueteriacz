<?php
$usaDataTables = true;
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
    $productos = ProductoModel::listarConInventario($filtroUsuarioCreador, false); // false = incluir inactivos en panel admin
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
    <title>Lista de Productos - App RutaEx-Latam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<style>
.productos-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.productos-header {
    background: linear-gradient(135deg, #FF416C 0%, #FF4B2B 100%);
    color: white;
    padding: 1.75rem 2rem;
}
.productos-header h3 {
    margin: 0;
    font-weight: 600;
}
.filter-container {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}
.btn-new-product {
    background: white;
    color: #f5576c;
    border: none;
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-new-product:hover {
    background: #fff0f3;
    color: #d93d52;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.btn-dashboard {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.4);
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
    text-decoration: none;
}
.btn-dashboard:hover {
    background: rgba(255,255,255,0.3);
    color: white;
}
.btn-action-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
#tablaProductos thead th {
    background: #f8f9fa;
    font-weight: 600;
    color: #1a1a2e;
    border-bottom: 2px solid #e9ecef;
    padding: 1rem 0.75rem;
}
#tablaProductos tbody tr:hover {
    background-color: #f8f9ff;
}
#tablaProductos td {
    padding: 0.875rem 0.75rem;
    vertical-align: middle;
}
</style>
    
<div class="container-fluid py-3">
    <!-- Card Principal -->
    <div class="card productos-card mb-4">
        <div class="productos-header">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-box-seam fs-3"></i>
                        </div>
                        <div>
                            <h3>Catálogo de Productos</h3>
                            <p class="mb-0 opacity-75">Gestión completa del inventario y productos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-md-end justify-content-center gap-2">
                        <a href="<?php echo RUTA_URL; ?>productos/dashboard" class="btn btn-dashboard">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                        <a href="<?php echo RUTA_URL; ?>productos/crear" class="btn btn-new-product">
                            <i class="bi bi-plus-circle me-1"></i> Nuevo Producto
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            <!--Filtros -->
            <div class="filter-container">
                <form method="GET">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Categoría</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-tags"></i></span>
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
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">Marca</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-award"></i></span>
                                <select name="marca" class="form-select select2-searchable" data-placeholder="Buscar marca...">
                                    <option value="">Todas las marcas</option>
                                    <?php foreach ($marcasUnicas as $marca): ?>
                                        <option value="<?php echo htmlspecialchars($marca); ?>" <?php echo $marcaFiltro === $marca ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($marca); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">Estado</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-toggle-on"></i></span>
                                <select name="estado" class="form-select select2-searchable" data-placeholder="Seleccionar estado...">
                                    <option value="">Todos</option>
                                    <option value="1" <?php echo $estadoFiltro === '1' ? 'selected' : ''; ?>>Activos</option>
                                    <option value="0" <?php echo $estadoFiltro === '0' ? 'selected' : ''; ?>>Inactivos</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Buscar</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="search" id="busquedaTabla" class="form-control" placeholder="Nombre, SKU...">
                            </div>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 btn-sm" style="height: 34px;">
                                <i class="bi bi-funnel me-1"></i> Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabla de Productos -->
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
                            <th class="text-end">Acciones</th>
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
                                <td><span class="text-muted small">#<?php echo $prod['id']; ?></span></td>
                                <td><code class="text-dark bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($prod['sku'] ?? 'N/A'); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></td>
                                <td>
                                    <?php
                                    $cat = $prod['categoria_id'] ? CategoriaModel::obtenerPorId($prod['categoria_id']) : null;
                                    echo $cat ? '<span class="badge bg-light text-secondary border">' . htmlspecialchars($cat['nombre']) . '</span>' : '-';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($prod['marca'] ?? '-'); ?></td>
                                <td><strong class="text-success">$<?php echo number_format($prod['precio_usd'] ?? 0, 2); ?></strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $badgeClass; ?> bg-opacity-75 text-white"><?php echo $badgeText; ?></span>
                                    <span class="small ms-1 text-muted">(<?php echo $stock; ?>)</span>
                                </td>
                                <td class="text-center">
                                    <?php if ($prod['activo'] ?? true): ?>
                                        <span class="badge rounded-pill bg-success p-2">
                                            <i class="bi bi-check-lg text-white" style="font-size: 1.2rem;"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-danger p-2">
                                            <i class="bi bi-x-lg text-white" style="font-size: 1.2rem;"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="<?php echo RUTA_URL; ?>productos/ver/<?php echo $prod['id']; ?>" class="btn btn-info btn-square text-white" title="Ver detalles" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?php echo RUTA_URL; ?>productos/editar/<?php echo $prod['id']; ?>" class="btn btn-primary btn-square" title="Editar" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button"
                                            class="btn btn-danger btn-square btn-eliminar-producto"
                                            title="Eliminar"
                                            data-id="<?php echo $prod['id']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                                            style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmación Eliminar -->
<div class="modal fade" id="modalEliminarProducto" tabindex="-1" aria-labelledby="modalEliminarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalEliminarLabel"><i class="bi bi-exclamation-triangle me-2"></i>Eliminar Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">¿Estás seguro de que deseas eliminar el producto:</p>
                <p class="fw-bold fs-6" id="nombreProductoEliminar"></p>
                <div class="alert alert-warning py-2 mb-0"><i class="bi bi-info-circle me-1"></i>Esta acción no se puede deshacer.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">
                    <i class="bi bi-trash me-1"></i>Sí, eliminar
                </button>
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
            responsive: true,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"p>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            initComplete: function() {
                $('.dataTables_length select').addClass('form-select form-select-sm border-2 w-auto d-inline-block');
            }
        });

        $('#busquedaTabla').on('keyup', function() {
            $('#tablaProductos').DataTable().search(this.value).draw();
        });

        // --- Eliminar producto ---
        let productoIdAEliminar = null;
        let filaAEliminar = null;
        const csrfToken = '<?php
            require_once __DIR__ . "/../../../utils/csrf.php";
            echo csrf_token();
        ?>';

        $(document).on('click', '.btn-eliminar-producto', function() {
            productoIdAEliminar = $(this).data('id');
            filaAEliminar = $(this).closest('tr');
            $('#nombreProductoEliminar').text($(this).data('nombre'));
            var modalEl = document.getElementById('modalEliminarProducto');
            var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        });

        $('#btnConfirmarEliminar').on('click', function() {
            if (!productoIdAEliminar) return;
            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Eliminando...');

            $.post('<?php echo RUTA_URL; ?>productos/eliminar/' + productoIdAEliminar, {
                csrf_token: csrfToken
            }, function(resp) {
                if (resp.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalEliminarProducto')).hide();
                    $('#tablaProductos').DataTable().row(filaAEliminar).remove().draw();
                    productoIdAEliminar = null;
                } else {
                    alert('Error: ' + (resp.message || 'No se pudo eliminar el producto.'));
                }
            }, 'json').fail(function() {
                location.reload();
            }).always(function() {
                $btn.prop('disabled', false).html('<i class="bi bi-trash me-1"></i>Sí, eliminar');
            });
        });
    });
</script>
</body>
</html>
