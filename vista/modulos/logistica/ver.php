<?php
require_once "controlador/logistica.php";

// Instanciar controlador
$controller = new LogisticaController();

// Obtener ID del URL (proporcionado por EnlacesController en $parametros)
$idPedido = $parametros[0] ?? null;

// Obtener datos
$datos = $controller->obtenerDatosPedido($idPedido);

// Si no hay datos (no existe o no autorizado), redirigir
if (!$idPedido || !$datos) {
    echo "<script>window.location.href='".RUTA_URL."logistica/dashboard';</script>";
    exit;
}

$pedido = $datos['pedido'];
$historialCambios = $datos['historial'] ?? [];
$estadosDisponibles = $datos['estados'] ?? [];

// Crear un mapa de ID => Nombre para registros antiguos
$mapaEstados = [];
foreach ($estadosDisponibles as $e) {
    $mapaEstados[$e['id']] = $e['nombre_estado'];
}

// Mapa de Colores Estandarizado y Vibrante
$estadoColores = [
    'EN BODEGA'           => 'primary',           // Azul (Proceso inicial)
    'EN RUTA'             => 'info text-dark',    // Celeste (En camino)
    'ENTREGADO'           => 'success',           // Verde (Completado)
    'CANCELADO'           => 'danger',            // Rojo (Anulado)
    'LIQUIDADO'           => 'dark',              // Negro (Cierre contable)
    'DEVOLUCION'          => 'warning text-dark', // Naranja (Problema/Retorno)
    'DEVOLUCION COMPLETA' => 'warning text-dark', // Naranja
    'EN_ESPERA'           => 'secondary',         // Gris
    'PENDIENTE'           => 'warning text-dark', // Amarillo
    'VENDIDO'             => 'success',           // Verde
    'RECHAZADO'           => 'danger',            // Rojo
    'DOMICILIO'           => 'warning text-dark', // Amarillo (No encontrado)
    'DEVUELTO'            => 'danger',            // Rojo
    'TRANSITO'            => 'info text-dark'     // Celeste
];

function getBadgeColor($estado, $map) {
    if (empty($estado)) return 'secondary';
    $estadoUpper = strtoupper($estado);
    
    // Búsqueda de palabra clave para mayor flexibilidad
    foreach ($map as $key => $val) {
        if (strpos($estadoUpper, $key) !== false) return $val;
    }
    return 'secondary';
}

$badgeColor = getBadgeColor($pedido['nombre_estado'], $estadoColores);

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

    if (strtoupper($pedido['nombre_estado']) === 'ENTREGADO') {
       $fechaBadgeColor = 'outline-success';
       $fechaAlertaLabel = 'Entregado';
    } elseif ($dias === 0) {
        $fechaBadgeColor = 'danger';
        $fechaAlertaLabel = '¡HOY!';
    } elseif ($dias === 1) {
        $fechaBadgeColor = 'warning text-dark';
        $fechaAlertaLabel = '¡MAÑANA!';
    } elseif ($dias > 1) {
        $fechaBadgeColor = 'success';
        $fechaAlertaLabel = 'PROGRAMADO';
    } elseif ($dias < 0) {
        $fechaBadgeColor = 'dark';
        $fechaAlertaLabel = 'ATRASADO';
    }
} else {
    $fechaAlertaLabel = 'No programada';
}


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
                            <label class="small text-muted fw-bold text-uppercase">Proveedor</label>
                            <div class="text-dark">
                                <i class="bi bi-shop me-1"></i>
                                <?= htmlspecialchars($pedido['proveedor_nombre'] ?? 'No asignado') ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Cliente</label>
                            <div class="text-dark">
                                <i class="bi bi-person me-1"></i>
                                <?= htmlspecialchars($pedido['cliente_nombre'] ?? 'No asignado') ?>
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

        <!-- Columna Derecha: Entrega y Productos -->
        <div class="col-lg-4 mb-4">
            
            <!-- TARJETA DEDICADA: FECHA DE ENTREGA -->
            <div class="card shadow border-0 mb-4 overflow-hidden">
                <div class="card-header bg-<?= explode(' ', $fechaBadgeColor)[0] ?> text-white py-3">
                    <h6 class="m-0 fw-bold"><i class="bi bi-calendar-check me-2"></i>Fecha de Entrega</h6>
                </div>
                <div class="card-body text-center py-4">
                    <?php if (empty($fechaEntregaRaw)): ?>
                        <div class="text-muted">
                            <i class="bi bi-calendar-x fs-1 mb-2 d-block"></i>
                            <span class="fw-bold fs-5">Pendiente de programación</span>
                        </div>
                    <?php else: ?>
                        <div class="mb-2">
                            <span class="badge bg-<?= $fechaBadgeColor ?> rounded-pill px-3 py-2 fs-6 mb-3">
                                <?= $fechaAlertaLabel ?>
                            </span>
                        </div>
                        <div class="display-5 fw-bold text-dark border-bottom pb-2 mb-2 mx-auto" style="max-width: fit-content;">
                            <?= date('d', strtotime($fechaEntregaRaw)) ?>
                        </div>
                        <div class="fs-4 text-uppercase text-muted fw-light">
                            <?= date('M Y', strtotime($fechaEntregaRaw)) ?>
                        </div>
                        <div class="mt-3 small text-secondary">
                             <i class="bi bi-clock me-1"></i> Entrega estimada durante el día
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow border-0 mb-4 ">
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
                        <div class="card-footer bg-light border-top-0 d-flex justify-content-between align-items-center py-3">
                            <span class="small text-muted fw-bold text-uppercase">Total a Pagar:</span>
                            <span class="fs-4 fw-bold text-primary">
                                <?= htmlspecialchars($pedido['moneda_codigo'] ?? 'GTQ') ?> 
                                <?= number_format($pedido['precio_total_local'] ?? 0, 2) ?>
                            </span>
                        </div>
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
                                
                                $titulo = "Actualización de Pedido";
                                $detalle = "";
                                $badgeColor = "info";

                                if ($cambio['accion'] == 'crear') {
                                    $titulo = "Pedido Creado";
                                    $detalle = "El pedido fue ingresado al sistema.";
                                    $badgeColor = "success";
                                } else {
                                    // 1. Si hay cambio de estado (Formato Nuevo)
                                    if (isset($datosNuevos['estado'])) {
                                        $titulo = "Cambio de Estado: " . htmlspecialchars($datosNuevos['estado']);
                                        $detalle = $datosNuevos['observaciones'] ?? 'Sin observaciones.';
                                        $badgeColor = getBadgeColor($datosNuevos['estado'], $estadoColores);
                                    } 
                                    // 2. Si es formato antiguo pero cambió el ID del estado
                                    elseif (isset($datosNuevos['id_estado'])) {
                                        $nombreEs = $mapaEstados[$datosNuevos['id_estado']] ?? "Estado #" . $datosNuevos['id_estado'];
                                        $titulo = "Cambio de Estado: " . htmlspecialchars($nombreEs);
                                        $detalle = "Actualización de estado procesada por el sistema.";
                                        $badgeColor = getBadgeColor($nombreEs, $estadoColores);
                                    }
                                    // 3. Otros cambios
                                    else {
                                        $keys = array_keys(array_diff_assoc($datosNuevos, $datosAnt));
                                        $detalle = "Campos modificados: " . implode(', ', $keys);
                                    }
                                }
                            ?>
                            <div class="d-flex mb-3 pb-3 border-bottom position-relative">
                                <div class="flex-shrink-0 me-3">
                                    <div class="badge bg-<?= $badgeColor ?> p-2 rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1 fw-bold"><?= $titulo ?></h6>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($cambio['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-0 text-muted small"><?= $detalle ?></p>
                                    <div class="small mt-1 text-secondary fst-italic">
                                        Por: <?= htmlspecialchars($cambio['usuario_nombre'] ?? 'Sistema') ?>
                                    </div>
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
                            <option value="">Seleccione un estado...</option>
                            <?php foreach ($estadosDisponibles as $est): ?>
                                <option value="<?= htmlspecialchars($est['nombre_estado']) ?>" <?= ($est['nombre_estado'] == $pedido['nombre_estado']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($est['nombre_estado']) ?>
                                </option>
                            <?php endforeach; ?>
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



<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#cambiarEstadoModal form');
    const submitBtn = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                console.error('Error parseando JSON. Respuesta del servidor:', text);
                throw new Error('La respuesta del servidor no es un JSON válido.');
            }
        })
        .then(data => {
            if (data.success) {
                // Cerrar modal
                const modalEl = document.getElementById('cambiarEstadoModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: data.message || 'Estado actualizado correctamente',
                        confirmButtonColor: '#0d6efd'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    alert(data.message || 'Estado actualizado correctamente');
                    window.location.reload();
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error al actualizar el estado',
                        confirmButtonColor: '#0d6efd'
                    });
                } else {
                    alert(data.message || 'Error al actualizar el estado');
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        })
        .catch(error => {
            console.error('Error en fetch:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Conexión',
                    text: error.message || 'Error en la comunicación con el servidor',
                    confirmButtonColor: '#0d6efd'
                });
            } else {
                alert(error.message || 'Error en la comunicación con el servidor');
            }
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
});
</script>

<?php include("vista/includes/footer.php"); ?>
