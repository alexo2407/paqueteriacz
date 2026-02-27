<?php 
include("vista/includes/header.php"); 

 

// Llamamos al controlador para obtener los datos del pedido
// Si el controlador ya nos pasó los detalles, no hacemos nada.
// Si no, intentamos buscarlos por GET (comportamiento legacy o acceso directo).
// Si el controlador ya nos pasó los detalles, no hacemos nada.
// Si no, intentamos buscarlos por GET o parsing de URL
if (!isset($detallesPedido)) {
    $idPedido = 0;
    
    // 1. Intentar obtener ID de $_GET
    if (isset($_GET['idPedido'])) {
        $idPedido = intval($_GET['idPedido']);
    } 
    // 2. Intentar obtener ID de la URL amigable (pedidos/ver/123)
    else if (isset($_GET['enlace'])) {
        $partes = explode('/', $_GET['enlace']);
        // Esperamos: pedidos/ver/123 -> indices 0/1/2
        if (isset($partes[2]) && is_numeric($partes[2])) {
            $idPedido = intval($partes[2]);
        }
    }

    if ($idPedido > 0) {
        $pedidoExtendido = new PedidosController();
        $detallesPedido = $pedidoExtendido->verPedido($idPedido);
    } else {
        $detallesPedido = [];
    }
}

$pedido = !empty($detallesPedido) ? $detallesPedido[0] : null;

// ==========================================
// SEGURIDAD: Verificar acceso estricto
// ==========================================
require_once "utils/authorization.php"; // Asegurar helpers disponibles
$roles = $_SESSION['roles_nombres'] ?? [];

// Verificar si es Cliente
$isCliente = in_array(ROL_NOMBRE_CLIENTE, $roles, true) || in_array('Cliente', $roles, true);
$isAdmin = in_array(ROL_NOMBRE_ADMIN, $roles, true); // Admin siempre entra
$isRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $roles, true);

if ($pedido && $isCliente && !$isAdmin && !$isRepartidor) {
    if ((int)$pedido['id_cliente'] !== (int)$_SESSION['user_id']) {
        // Bloquear acceso si no es dueño
        echo "<script>window.location.href = '" . RUTA_URL . "pedidos/listar';</script>";
        exit;
    }
}

// --- Lógica de Alerta de Fecha de Entrega ---
$fechaAlertaBadge = '';
$fechaAlertaLabel = '';
$fechaBadgeColor = 'secondary';
$fechaEntregaRaw = $pedido['fecha_entrega'] ?? null;

if (!empty($fechaEntregaRaw)) {
    $hoy = new DateTime(date('Y-m-d'));
    $entrega = new DateTime($fechaEntregaRaw);
    $intervalo = $hoy->diff($entrega);
    $dias = (int)$intervalo->format('%r%a');

    if (strtoupper($pedido['nombre_estado'] ?? '') === 'ENTREGADO') {
       $fechaBadgeColor = 'outline-success';
       $fechaAlertaLabel = 'Entregado';
       $fechaSubLabel = 'Fecha de entrega registrada';
    } elseif ($dias === 0) {
        $fechaBadgeColor = 'danger';
        $fechaAlertaLabel = '¡HOY!';
        $fechaSubLabel = 'Entrega estimada durante el día';
    } elseif ($dias === 1) {
        $fechaBadgeColor = 'warning text-dark';
        $fechaAlertaLabel = '¡MAÑANA!';
        $fechaSubLabel = 'Entrega programada para mañana';
    } elseif ($dias > 1) {
        $fechaBadgeColor = 'success';
        $fechaAlertaLabel = 'PROGRAMADO';
        $fechaSubLabel = 'Entrega programada para esta fecha';
    } elseif ($dias < 0) {
        $fechaBadgeColor = 'danger';
        $fechaAlertaLabel = 'ATRASADO';
        $fechaSubLabel = 'La entrega se encuentra demorada';
    }
} else {
    $fechaAlertaLabel = 'No programada';
    $fechaSubLabel = 'Pendiente de definir fecha';
}
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
    border: 1px solid #e2e8f0;
}
.products-table thead th {
    background: #667eea !important;
    color: white !important;
    font-weight: 700;
    border: none;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    padding: 1rem 1.25rem;
}
.products-table tbody td {
    vertical-align: middle;
    padding: 1rem 1.25rem;
    color: #2d3748;
    border-bottom: 1px solid #edf2f7;
}
.products-table tfoot th {
    padding: 1rem 1.25rem;
    border: none;
}
.col-money { width: 160px; }
.col-qty { width: 100px; }

/* Grid de Ubicación */
.location-grid dt {
    font-weight: 600;
    color: #718096;
    font-size: 0.8rem;
    text-transform: uppercase;
    margin-bottom: 0.1rem;
}
.location-grid dd {
    color: #2d3748;
    font-size: 0.95rem;
    margin-bottom: 1rem;
}
</style>

<div class="container-fluid py-4">
    <div class="card pedido-view-card">
        <?php if ($pedido): ?>
            <div class="pedido-view-header">
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-white bg-opacity-25 rounded-circle p-3">
                                <i class="bi bi-box-seam fs-3"></i>
                            </div>
                            <div>
                                <h3>Pedido #<?= htmlspecialchars($pedido['numero_orden'] ?? 'N/A') ?></h3>
                                <p class="mb-0 opacity-75">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?= htmlspecialchars($pedido['fecha_ingreso'] ?? '') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7 text-md-end mt-3 mt-md-0">
                        <span class="order-badge">
                            Estado: <?= htmlspecialchars($pedido['nombre_estado'] ?? $pedido['estado'] ?? 'Desconocido') ?>
                        </span>
                    </div>
                </div> <!-- .row -->
            </div> <!-- .pedido-view-header -->
            
            <div class="card-body p-4">
                <div class="row">
                    <!-- Columna Izquierda: Detalles del Pedido -->
                    <div class="col-md-6">
                        <div class="info-section h-100">
                            <div class="info-section-title">
                                <i class="bi bi-info-circle"></i>
                                Información del Pedido
                            </div>
                            
                            <div class="info-item">
                                <label>ID Pedido</label>
                                <span><?= htmlspecialchars($pedido['id']) ?></span>
                            </div>

                            <div class="info-item">
                                <label>Número de Orden</label>
                                <span><?= htmlspecialchars($pedido['numero_orden'] ?? '') ?></span>
                            </div>

                            <div class="info-item">
                                <label>Comentario</label>
                                <span><?= htmlspecialchars($pedido['comentario'] ?? 'Sin comentarios') ?></span>
                            </div>

                            <div class="info-item">
                                <label>Precio Total Local</label>
                                <span class="fw-bold text-primary">
                                    <?= htmlspecialchars($pedido['moneda_codigo'] ?? 'GTQ') ?> 
                                    <?= number_format($pedido['precio_total_local'] ?? 0, 2) ?>
                                </span>
                            </div>

                            <?php if (!empty($pedido['fecha_ultima_entrega'])): ?>
                            <div class="info-item">
                                <label>Última Entrega Registrada</label>
                                <span><?= htmlspecialchars($pedido['fecha_ultima_entrega']) ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="mt-4 pt-3 border-top">
                                <div class="info-section-title mb-2">
                                    <i class="bi bi-person"></i> Cliente
                                </div>
                                <div class="info-item">
                                    <label>Nombre</label>
                                    <span><?= htmlspecialchars($pedido['cliente_nombre'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($pedido['vendedor_nombre'])): ?>
                            <div class="mt-3">
                                <div class="info-section-title mb-2">
                                    <i class="bi bi-person-badge"></i> Usuario Responsable
                                </div>
                                <div class="info-item">
                                    <label>Nombre</label>
                                    <span><?= htmlspecialchars($pedido['vendedor_nombre']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                     <!-- Columna Derecha: Entrega y Ubicación -->
                    <div class="col-md-6">
                        
                        <!-- TARJETA DEDICADA: FECHA DE ENTREGA (High Visibility) -->
                        <div class="card shadow-sm border-0 mb-4 overflow-hidden" style="border-radius: 12px;">
                            <div class="card-header bg-<?= explode(' ', $fechaBadgeColor)[0] ?> text-white py-2">
                                <h6 class="m-0 fw-bold small text-uppercase"><i class="bi bi-calendar-check me-2"></i>Fecha de Entrega</h6>
                            </div>
                            <div class="card-body text-center py-4 bg-light bg-opacity-50">
                                <?php if (empty($fechaEntregaRaw)): ?>
                                    <div class="text-muted py-2">
                                        <i class="bi bi-calendar-x fs-2 mb-2 d-block opacity-50"></i>
                                        <span class="fw-bold">Pendiente de programación</span>
                                    </div>
                                <?php else: ?>
                                    <div class="mb-2">
                                        <span class="badge bg-<?= $fechaBadgeColor ?> rounded-pill px-3 py-1 small mb-3">
                                            <?= $fechaAlertaLabel ?>
                                        </span>
                                    </div>
                                    <div class="display-6 fw-bold text-dark border-bottom border-2 pb-1 mb-1 mx-auto" style="max-width: fit-content; line-height: 1;">
                                        <?= date('d', strtotime($fechaEntregaRaw)) ?>
                                    </div>
                                    <div class="fs-5 text-uppercase text-muted fw-bold">
                                        <?= date('M Y', strtotime($fechaEntregaRaw)) ?>
                                    </div>
                                    <div class="mt-3 small text-secondary">
                                         <i class="bi bi-clock me-1"></i> <?= $fechaSubLabel ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                            <div class="info-section">
                                <div class="info-section-title mb-3">
                                    <i class="bi bi-geo-alt-fill"></i>
                                    Ubicación de Entrega
                                </div>
                                
                                <dl class="row location-grid mb-0">
                                    <div class="col-6">
                                        <dt>Zona</dt>
                                        <dd><?= htmlspecialchars($pedido['zona'] ?? '—') ?></dd>
                                    </div>
                                    <div class="col-6">
                                        <dt>Departamento</dt>
                                        <dd><?= htmlspecialchars($pedido['departamento'] ?? $pedido['id_departamento'] ?? '—') ?></dd>
                                    </div>
                                    <div class="col-6">
                                        <dt>Municipio</dt>
                                        <dd><?= htmlspecialchars($pedido['municipio'] ?? $pedido['id_municipio'] ?? '—') ?></dd>
                                    </div>
                                    <div class="col-6">
                                        <dt>Barrio</dt>
                                        <dd><?= htmlspecialchars($pedido['barrio'] ?? $pedido['id_barrio'] ?? '—') ?></dd>
                                    </div>
                                    <div class="col-12">
                                        <dt>Código Postal</dt>
                                        <dd><?= htmlspecialchars($pedido['codigo_postal'] ?? '—') ?></dd>
                                    </div>
                                    <div class="col-12">
                                        <dt>Dirección Completa</dt>
                                        <dd class="p-2 bg-white rounded border border-light"><?= htmlspecialchars($pedido['direccion'] ?? '—') ?></dd>
                                    </div>
                                    <div class="col-12 mb-0">
                                        <dt>Coordenadas</dt>
                                        <dd class="mb-0">
                                            <?php if (!empty($pedido['latitud']) && !empty($pedido['longitud'])): ?>
                                                <a href="https://maps.google.com/?q=<?= $pedido['latitud'] ?>,<?= $pedido['longitud'] ?>" target="_blank" class="text-decoration-none fw-bold">
                                                    <i class="bi bi-map me-1"></i>Ver en Mapa
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted italic">— No disponibles —</span>
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                </dl>
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
                        <table class="table products-table mb-0">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center col-qty">Cantidad</th>
                                    <th class="text-end col-money">Unitario (USD)</th>
                                    <th class="text-end col-money">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalPedido = 0;
                                $productosList = $pedido['productos'] ?? [];
                                foreach ($productosList as $producto): 
                                    $precio = $producto["precio_usd"] ?? 0;
                                    $cantidad = $producto["cantidad"] ?? 0;
                                    $subtotal = $precio * $cantidad;
                                    $totalPedido += $subtotal;
                                ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($producto["nombre"] ?? 'Producto sin nombre') ?></td>
                                    <td class="text-center"><?= htmlspecialchars($cantidad) ?></td>
                                    <td class="text-end">$<?= number_format($precio, 2) ?></td>
                                    <td class="text-end fw-bold">$<?= number_format($subtotal, 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                             <tfoot class="bg-light">
                                <tr>
                                    <th colspan="3" class="text-end text-muted small py-2">Subtotal Pedido (USD):</th>
                                    <th class="text-end text-muted small py-2">$<?= number_format($totalPedido, 2) ?></th>
                                </tr>
                                <tr class="table-primary bg-opacity-10 border-top">
                                    <th colspan="3" class="text-end fs-5 py-3">Total (<?= htmlspecialchars($pedido['moneda_codigo'] ?? 'Local') ?>):</th>
                                    <th class="text-end fs-5 text-primary py-3">
                                        <?= htmlspecialchars($pedido['moneda_codigo'] ?? 'GTQ') ?> 
                                        <?= number_format($pedido['precio_total_local'] ?? 0, 2) ?>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <!-- Botones -->
                <div class="d-flex justify-content-between mt-4">
                    <a href="<?= RUTA_URL ?>pedidos/listar" class="btn btn-outline-secondary px-4">
                        <i class="bi bi-arrow-left me-1"></i>Volver al listado
                    </a>
                    <?php 
                    // Check permissions for edit button
                    // Admin, Repartidor, Proveedor (owner) can edit. Client cannot.
                    $canEdit = false;
                    if (!empty($_SESSION['roles_nombres'])) {
                        $roles = $_SESSION['roles_nombres'];
                        if (in_array(ROL_NOMBRE_ADMIN, $roles, true) || in_array('Administrador', $roles, true) ||
                            in_array(ROL_NOMBRE_REPARTIDOR, $roles, true) || in_array('Repartidor', $roles, true)) {
                            $canEdit = true;
                        } elseif (in_array(ROL_NOMBRE_PROVEEDOR, $roles, true) || in_array('Proveedor', $roles, true)) {
                             // Proveedor can edit if it's their order
                             if (isset($pedido['id_proveedor']) && $pedido['id_proveedor'] == $_SESSION['user_id']) {
                                $canEdit = true;
                             }
                        }
                    }
                    ?>
                    <?php if ($canEdit): ?>
                    <a href="<?= RUTA_URL ?>pedidos/editar/<?= $pedido['id'] ?>" class="btn btn-warning px-4">
                        <i class="bi bi-pencil me-1"></i>Editar Pedido
                    </a>
                    <?php endif; ?>
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
