<?php
require_once "controlador/logistica.php";

// Instanciar controlador
$controller = new LogisticaController();

// Obtener ID del URL (proporcionado por EnlacesController en $parametros)
$idPedido = $parametros[0] ?? null;

// Obtener datos
$data = $controller->obtenerDatosPedido($idPedido);

// Si no hay datos (no existe o no autorizado), redirigir
if ($data === null) {
    if (!headers_sent()) {
        header('Location: ' . RUTA_URL . 'logistica/dashboard');
    } else {
        echo "<script>window.location.href = '" . RUTA_URL . "logistica/dashboard';</script>";
    }
    exit;
}

$pedido = $data['pedido'];
$historialCambios = $data['historial'];

// Mapa de Colores Estandarizado
$estadoColores = [
    'EN BODEGA' => 'primary',
    'EN RUTA' => 'info text-dark',
    'ENTREGADO' => 'success',
    'CANCELADO' => 'danger',
    'LIQUIDADO' => 'dark',
    'DEVOLUCION' => 'warning text-dark',
    'DEVOLUCION COMPLETA' => 'warning text-dark',
    'EN_ESPERA' => 'secondary'
];

function getBadgeColor($estado, $map) {
    $estadoUpper = strtoupper($estado ?? '');
    foreach ($map as $key => $val) {
        if (strpos($estadoUpper, $key) !== false) return $val;
    }
    return 'secondary';
}

$badgeColor = getBadgeColor($pedido['nombre_estado'], $estadoColores);

include("vista/includes/header.php"); 
?>

<div class="container-fluid py-4">
    
    <!-- Header Page -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="bi bi-file-text me-2"></i>Detalle del Pedido #<?= htmlspecialchars($pedido['numero_orden']) ?>
            </h1>
            <p class="text-muted mb-0">Gestiona y revisa el historial de este pedido.</p>
        </div>
        <div class="d-flex gap-2">
           <span class="badge bg-<?= $badgeColor ?> fs-6 px-3 py-2 align-self-center"><?= htmlspecialchars($pedido['nombre_estado'] ?? 'Desconocido') ?></span>
           
           <?php if (!in_array(strtoupper($pedido['nombre_estado']), ['ENTREGADO', 'CANCELADO', 'DEVOLUCION COMPLETA', 'LIQUIDADO'])): ?>
                <button type="button" class="btn btn-warning text-dark align-self-center" data-bs-toggle="modal" data-bs-target="#cambiarEstadoModal">
                    <i class="bi bi-arrow-repeat"></i> Cambiar Estado
                </button>
           <?php endif; ?>

           <a href="<?= RUTA_URL ?>logistica/dashboard" class="btn btn-outline-secondary">
               <i class="bi bi-arrow-left"></i> Volver
           </a>
        </div>
    </div>

    <div class="row">
        <!-- Columna Izquierda: Información -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-primary text-white py-3">
                    <h6 class="m-0 fw-bold"><i class="bi bi-info-circle me-2"></i>Información del Pedido</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Número de Orden</label>
                            <div class="fs-5 text-dark"><?= htmlspecialchars($pedido['numero_orden']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Precio Total</label>
                            <div class="fs-5 text-dark">
                                <?= htmlspecialchars($pedido['moneda_codigo'] ?? 'GTQ') ?> 
                                <?= number_format($pedido['precio_total_local'] ?? 0, 2) ?>
                            </div>
                        </div>

                         <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Proveedor</label>
                            <div class="text-dark">
                                <i class="bi bi-shop me-1"></i>
                                <?= htmlspecialchars($pedido['proveedor_nombre'] ?? 'No asignado') ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Fecha Creación</label>
                            <div><?= date('d/m/Y H:i', strtotime($pedido['fecha_ingreso'])) ?></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Destinatario</label>
                            <div class="fw-bold"><?= htmlspecialchars($pedido['destinatario']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Teléfono</label>
                            <div><?= htmlspecialchars($pedido['telefono']) ?></div>
                        </div>

                        <div class="col-12">
                            <hr class="my-2">
                        </div>
                        
                        <div class="col-12 mt-1">
                            <label class="small text-muted fw-bold text-uppercase">Dirección de Entrega</label>
                            <div class="p-2 bg-light rounded border">
                                <?= htmlspecialchars($pedido['direccion'] ?? 'Sin dirección específica') ?>
                                <br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($pedido['municipio'] ?? '') ?>, 
                                    <?= htmlspecialchars($pedido['zona'] ?? '') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Productos -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-success text-white py-3">
                    <h6 class="m-0 fw-bold"><i class="bi bi-box-seam me-2"></i>Productos</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pedido['productos'])): ?>
                        <div class="p-4 text-center text-muted">No hay productos registrados.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                        <?php foreach ($pedido['productos'] as $prod): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($prod['nombre']) ?></div>
                                    <small class="text-muted">Cant: <?= $prod['cantidad'] ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Fila Inferior: Historial de Estados -->
        <div class="col-12">
            <div class="card shadow border-0">
                <div class="card-header bg-info text-white py-3">
                    <h6 class="m-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Historial de Estados y Cambios</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($historialCambios)): ?>
                        <p class="text-muted text-center my-3">No hay historial de cambios registrado.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($historialCambios as $cambio): 
                                $datosNuevos = $cambio['datos_nuevos'] ?? [];
                                $datosAnt = $cambio['datos_anteriores'] ?? [];
                                
                                $titulo = "Actualización";
                                $timelineBadgeColor = "secondary";
                                $detalle = "";

                                if ($cambio['accion'] == 'crear') {
                                    $titulo = "Pedido Creado";
                                    $timelineBadgeColor = "primary";
                                    $detalle = "El pedido fue ingresado al sistema.";
                                } elseif (isset($datosNuevos['id_estado']) && $datosNuevos['id_estado'] != ($datosAnt['id_estado'] ?? 0)) {
                                    $titulo = "Cambio de Estado";
                                    $timelineBadgeColor = "warning text-dark";
                                    $detalle = "Estado actualizado a ID: " . $datosNuevos['id_estado'];
                                } else {
                                    $titulo = "Actualización de Datos";
                                    $timelineBadgeColor = "info text-dark";
                                    $keys = array_keys(array_diff_assoc($datosNuevos, $datosAnt));
                                    $detalle = "Campos modificados: " . implode(', ', $keys);
                                }
                            ?>
                            <div class="d-flex mb-3 pb-3 border-bottom position-relative">
                                <div class="flex-shrink-0 me-3">
                                    <div class="badge bg-<?= $timelineBadgeColor ?> p-2 rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-arrow-right"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1 fw-bold"><?= $titulo ?></h6>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($cambio['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-0 text-muted small"><?= $detalle ?></p>
                                    <?php if($cambio['accion'] == 'actualizar'): ?>
                                        <div class="small mt-1 text-secondary fst-italic">
                                            Por: Sistema / Usuario
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal Cambiar Estado -->
<div class="modal fade" id="cambiarEstadoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= RUTA_URL ?>logistica/cambiarEstado/<?= $pedido['id'] ?>" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar Estado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nuevo Estado</label>
                        <select name="estado" class="form-select" required>
                            <option value="CANCELADO">CANCELADO</option>
                            <!-- Se pueden agregar más estados permitidos para el cliente aquí -->
                        </select>
                        <div class="form-text text-muted">Seleccione el nuevo estado para esta orden.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3" placeholder="Razón del cambio..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php include("vista/includes/footer.php"); ?>
