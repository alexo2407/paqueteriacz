<?php 
include("vista/includes/header.php"); 

// Llamamos al controlador para obtener los datos del pedido
if (isset($_GET['idPedido'])) {
    $idPedido = intval($_GET['idPedido']);

    if (!is_numeric($idPedido) || $idPedido <= 0) {
        die("ID de pedido no válido.");
    }
    else {
        $pedidoExtendido = new PedidosController();
        $detallesPedido = $pedidoExtendido->verPedido($idPedido);
    }
} else {
    $detallesPedido = [];
}

$pedido = !empty($detallesPedido) ? $detallesPedido[0] : null;
?>

<style>
.pedido-view-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.pedido-view-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.75rem 2rem;
}
.pedido-view-header h3 {
    margin: 0;
    font-weight: 600;
}
.info-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
}
.info-section-title {
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.info-section-title i {
    color: #667eea;
}
.info-item {
    margin-bottom: 0.75rem;
}
.info-item label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.85rem;
    margin-bottom: 0.25rem;
    display: block;
}
.info-item span {
    color: #1a1a2e;
    font-size: 1rem;
}
.order-badge {
    background: rgba(255,255,255,0.2);
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.9rem;
}
.products-table {
    border-radius: 12px;
    overflow: hidden;
}
.products-table thead th {
    background: #667eea;
    color: white;
    font-weight: 600;
    border: none;
}
.products-table tbody td {
    vertical-align: middle;
}
</style>

<div class="container-fluid py-4">
    <div class="card pedido-view-card">
        <?php if ($pedido): ?>
        <div class="pedido-view-header">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-25 rounded-circle p-3">
                        <i class="bi bi-box-seam fs-3"></i>
                    </div>
                    <div>
                        <h3>Pedido #<?= htmlspecialchars($pedido['Numero_Orden']) ?></h3>
                        <p class="mb-0 opacity-75">Creado el <?= htmlspecialchars($pedido['Fecha_Ingreso']) ?></p>
                    </div>
                </div>
                <div class="order-badge">
                    <i class="bi bi-tag me-1"></i>
                    <?= htmlspecialchars($pedido['Estado']) ?>
                </div>
            </div>
        </div>
        
        <div class="card-body p-4">
            <div class="row">
                <!-- Información del Pedido -->
                <div class="col-md-6">
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="bi bi-info-circle"></i>
                            Información del Pedido
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="info-item">
                                    <label>ID Pedido</label>
                                    <span><?= htmlspecialchars($pedido['ID_Pedido']) ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-item">
                                    <label>Número de Orden</label>
                                    <span><?= htmlspecialchars($pedido['Numero_Orden']) ?></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-item">
                                    <label>Comentario</label>
                                    <span><?= htmlspecialchars($pedido['Comentario']) ?: '—' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="bi bi-person"></i>
                            Cliente
                        </div>
                        <div class="info-item">
                            <label>Nombre</label>
                            <span><?= htmlspecialchars($pedido['Cliente']) ?></span>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="bi bi-person-badge"></i>
                            Usuario Responsable
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="info-item">
                                    <label>Nombre</label>
                                    <span><?= htmlspecialchars($pedido['Usuario']) ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-item">
                                    <label>Email</label>
                                    <span><?= htmlspecialchars($pedido['UsuarioEmail']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ubicación -->
                <div class="col-md-6">
                    <div class="info-section h-100">
                        <div class="info-section-title">
                            <i class="bi bi-geo-alt"></i>
                            Ubicación
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="info-item">
                                    <label>Zona</label>
                                    <span><?= htmlspecialchars($pedido['Zona']) ?: '—' ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-item">
                                    <label>Departamento</label>
                                    <span><?= htmlspecialchars($pedido['Departamento']) ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-item">
                                    <label>Municipio</label>
                                    <span><?= htmlspecialchars($pedido['Municipio']) ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-item">
                                    <label>Barrio</label>
                                    <span><?= htmlspecialchars($pedido['Barrio']) ?: '—' ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-item">
                                    <label>Código Postal</label>
                                    <span><?= htmlspecialchars($pedido['codigo_postal']) ?: '—' ?></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-item">
                                    <label>Dirección Completa</label>
                                    <span><?= htmlspecialchars($pedido['Direccion_Completa']) ?></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-item">
                                    <label>Coordenadas</label>
                                    <span><?= htmlspecialchars($pedido['COORDINATES']) ?: '—' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Productos -->
            <div class="info-section mt-3">
                <div class="info-section-title">
                    <i class="bi bi-box"></i>
                    Productos del Pedido
                </div>
                <div class="table-responsive">
                    <table class="table products-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Precio Unitario</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalPedido = 0;
                            foreach ($detallesPedido as $producto): 
                                $subtotal = $producto["Precio"] * $producto["Cantidad"];
                                $totalPedido += $subtotal;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($producto["Producto"]) ?></td>
                                <td class="text-center"><?= htmlspecialchars($producto["Cantidad"]) ?></td>
                                <td class="text-end">$<?= number_format($producto["Precio"], 2) ?></td>
                                <td class="text-end">$<?= number_format($subtotal, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th colspan="3" class="text-end">Total del Pedido:</th>
                                <th class="text-end">$<?= number_format($totalPedido, 2) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="d-flex justify-content-between mt-4">
                <a href="<?= RUTA_URL ?>pedidos" class="btn btn-outline-secondary px-4">
                    <i class="bi bi-arrow-left me-1"></i>Volver al listado
                </a>
                <a href="<?= RUTA_URL ?>pedidos/editar/<?= $pedido['ID_Pedido'] ?>" class="btn btn-warning px-4">
                    <i class="bi bi-pencil me-1"></i>Editar Pedido
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="card-body p-4">
            <div class="alert alert-danger mb-3">
                <i class="bi bi-exclamation-triangle me-2"></i>
                No se encontraron detalles para este pedido.
            </div>
            <a href="<?= RUTA_URL ?>pedidos" class="btn btn-primary">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
