<?php include("vista/includes/header.php"); ?>

<?php
$stockController = new StockController();
$movimientos = $stockController->listar();
$inventario = $stockController->inventarioActual();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Inventario</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= RUTA_URL ?>dashboard">Dashboard</a></li>
        <li class="breadcrumb-item active">Inventario</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-boxes me-1"></i>
                    Control de Stock
                </div>
                <div>
                    <a href="<?= RUTA_URL ?>stock/crear" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus-circle me-1"></i> Nuevo Movimiento
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            
            <!-- Tabs de Navegación -->
            <ul class="nav nav-tabs mb-4" id="inventoryTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="actual-tab" data-bs-toggle="tab" data-bs-target="#actual" type="button" role="tab" aria-controls="actual" aria-selected="true">
                        <i class="fas fa-clipboard-list me-1"></i> Inventario Actual
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="movimientos-tab" data-bs-toggle="tab" data-bs-target="#movimientos" type="button" role="tab" aria-controls="movimientos" aria-selected="false">
                        <i class="fas fa-history me-1"></i> Historial de Movimientos
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="inventoryTabsContent">
                
                <!-- Tab: Inventario Actual -->
                <div class="tab-pane fade show active" id="actual" role="tabpanel" aria-labelledby="actual-tab">
                    <div class="table-responsive">
                        <table id="tablaInventario" class="table table-striped table-hover table-bordered" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID Producto</th>
                                    <th>Producto</th>
                                    <th>Descripción</th>
                                    <th class="text-center">Stock Disponible</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventario as $prod): ?>
                                    <?php 
                                        $stock = (int) $prod['stock_total'];
                                        // Lógica de semáforo (sin stock_minimo en BD, usamos 10 por defecto)
                                        if ($stock <= 0) {
                                            $badgeClass = 'bg-danger';
                                            $estadoTexto = 'Agotado';
                                        } elseif ($stock < 10) {
                                            $badgeClass = 'bg-warning text-dark';
                                            $estadoTexto = 'Bajo Stock';
                                        } else {
                                            $badgeClass = 'bg-success';
                                            $estadoTexto = 'En Stock';
                                        }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($prod['id']); ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($prod['nombre']); ?></td>
                                        <td><?= htmlspecialchars($prod['descripcion'] ?? '—'); ?></td>
                                        <td class="text-center fw-bold fs-5"><?= $stock; ?></td>
                                        <td class="text-center">
                                            <span class="badge rounded-pill <?= $badgeClass; ?>"><?= $estadoTexto; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <!-- Podríamos filtrar movimientos por este producto en el futuro -->
                                            <button class="btn btn-sm btn-info text-white" title="Ver detalle (Próximamente)">
                                                <i class="fas fa-eye"></i> Ver Detalle
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Movimientos -->
                <div class="tab-pane fade" id="movimientos" role="tabpanel" aria-labelledby="movimientos-tab">
                    <div class="table-responsive">
                        <table id="tablaMovimientos" class="table table-hover table-bordered" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Producto</th>
                                    <th class="text-center">Cantidad</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movimientos as $item): ?>
                                    <?php 
                                        $cantidad = (int) $item['cantidad'];
                                        $isPositive = $cantidad > 0;
                                        // Usamos colores sólidos para mejor legibilidad
                                        $badgeClass = $isPositive ? 'bg-success' : 'bg-danger';
                                        $icon = $isPositive ? '<i class="fas fa-arrow-up me-1"></i>' : '<i class="fas fa-arrow-down me-1"></i>';
                                        $signo = $isPositive ? '+' : '';
                                        
                                        $fecha = !empty($item['updated_at']) ? date('d/m/Y H:i', strtotime($item['updated_at'])) : '—';
                                        $usuario = !empty($item['usuario']) ? $item['usuario'] : ($item['id_usuario'] ? 'ID: '.$item['id_usuario'] : 'Sistema');
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['id']); ?></td>
                                        <td>
                                            <i class="far fa-calendar-alt text-muted me-1"></i>
                                            <?= $fecha; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-initials bg-light text-primary rounded-circle me-2" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                                                    <?= strtoupper(substr($usuario, 0, 1)); ?>
                                                </div>
                                                <?= htmlspecialchars($usuario); ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($item['producto'] ?? ($item['producto_nombre'] ?? 'Producto Eliminado')); ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $badgeClass; ?> p-2 fs-6">
                                                <?= $icon . $signo . $cantidad; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="<?= RUTA_URL ?>stock/editar/<?= $item['id']; ?>" class="btn btn-sm btn-warning" title="Editar">
                                                    <i class="fas fa-pencil-alt"></i> Editar
                                                </a>
                                                <form method="POST" action="<?= RUTA_URL ?>stock/eliminar/<?= $item['id']; ?>" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este movimiento? Esto afectará el stock actual.');">
                                                    <button type="submit" class="btn btn-sm btn-danger ms-1" title="Eliminar">
                                                        <i class="fas fa-trash-alt"></i> Eliminar
                                                    </button>
                                                </form>
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
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>

<script>
    $(document).ready(function () {
        // Configuración común para DataTables
        const dtConfig = {
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
            },
            order: [[0, 'desc']] // Ordenar por ID descendente por defecto
        };

        // Inicializar tabla de Inventario
        $('#tablaInventario').DataTable({
            ...dtConfig,
            order: [[1, 'asc']] // Ordenar por nombre de producto
        });

        // Inicializar tabla de Movimientos
        $('#tablaMovimientos').DataTable(dtConfig);
    });
</script>
