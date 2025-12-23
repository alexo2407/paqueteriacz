<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../modelo/producto.php';

start_secure_session();
require_login();

// Obtener productos
$productos = ProductoModel::listarConInventario();

// Obtener producto preseleccionado si viene de URL
$productoPreseleccionado = $_GET['producto'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Movimiento de Stock - Paquetería CruzValle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .tipo-movimiento-option {
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid #dee2e6;
        }
        .tipo-movimiento-option:hover {
            border-color: #0d6efd;
            transform: translateY(-2px);
        }
        .tipo-movimiento-option.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .tipo-icon {
            font-size: 2.5rem;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-arrow-down-up"></i> Registrar Movimiento de Stock</h2>
                    <p class="text-muted mb-0">Registra entradas, salidas o ajustes de inventario</p>
                </div>
                <a href="<?php echo RUTA_URL; ?>stock/listar" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>

            <form id="formStock" method="POST" action="<?php echo RUTA_URL; ?>stock/guardar">
                <?php 
                    require_once __DIR__ . '/../../../utils/csrf.php';
                    echo csrf_field(); 
                ?>
                <!-- Tipo de Movimiento -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-card-list"></i> Tipo de Movimiento</h5>
                    </div>
                    <div class="card-body">
                        <input type="hidden" id="tipo_movimiento" name="tipo_movimiento" required>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="tipo-movimiento-option card h-100 text-center p-3" data-tipo="entrada">
                                    <i class="bi bi-arrow-down-circle tipo-icon text-success"></i>
                                    <h5 class="mt-2">Entrada</h5>
                                    <small class="text-muted">Compra o recepción</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="tipo-movimiento-option card h-100 text-center p-3" data-tipo="salida">
                                    <i class="bi bi-arrow-up-circle tipo-icon text-danger"></i>
                                    <h5 class="mt-2">Salida</h5>
                                    <small class="text-muted">Venta o despacho</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="tipo-movimiento-option card h-100 text-center p-3" data-tipo="ajuste">
                                    <i class="bi bi-wrench tipo-icon text-warning"></i>
                                    <h5 class="mt-2">Ajuste</h5>
                                    <small class="text-muted">Corrección de inventario</small>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="tipo-movimiento-option card h-100 text-center p-3" data-tipo="devolucion">
                                    <i class="bi bi-arrow-counterclockwise tipo-icon text-info"></i>
                                    <h5 class="mt-2">Devolución</h5>
                                    <small class="text-muted">Retorno de producto</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="tipo-movimiento-option card h-100 text-center p-3" data-tipo="transferencia">
                                    <i class="bi bi-arrow-left-right tipo-icon text-primary"></i>
                                    <h5 class="mt-2">Transferencia</h5>
                                    <small class="text-muted">Entre ubicaciones</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información del Producto -->
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-box-seam"></i> Producto y Cantidad</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="id_producto" class="form-label">Producto <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_producto" name="id_producto" required>
                                <option value="">Selecciona un producto</option>
                                <?php foreach ($productos as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" 
                                            data-stock="<?php echo (int)($p['stock_total'] ?? 0); ?>"
                                            data-nombre="<?php echo htmlspecialchars($p['nombre']); ?>"
                                            <?php echo ($productoPreseleccionado == $p['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['nombre']); ?> 
                                        <?php if (!empty($p['sku'])): ?>
                                            (<?php echo htmlspecialchars($p['sku']); ?>)
                                        <?php endif; ?>
                                        - Stock: <?php echo (int)($p['stock_total'] ?? 0); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="stock-info" class="alert alert-info d-none">
                            <strong>Stock actual:</strong> <span id="stock-actual">0</span> unidades
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cantidad" class="form-label">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" required>
                                <div id="cantidad-error" class="text-danger small d-none"></div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="costo_unitario" class="form-label">Costo Unitario (opcional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="costo_unitario" name="costo_unitario" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ubicaciones -->
                <div class="card mb-3" id="ubicaciones-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Ubicaciones</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3" id="ubicacion-origen-container">
                                <label for="ubicacion_origen" class="form-label">Ubicación Origen</label>
                                <input type="text" class="form-control" id="ubicacion_origen" name="ubicacion_origen" placeholder="Ej: Almacén Principal">
                            </div>
                            <div class="col-md-6 mb-3" id="ubicacion-destino-container" style="display:none;">
                                <label for="ubicacion_destino" class="form-label">Ubicación Destino</label>
                                <input type="text" class="form-control" id="ubicacion_destino" name="ubicacion_destino" placeholder="Ej: Almacén Secundario">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Motivo y Referencia -->
                <div class="card mb-3">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Motivo y Referencia</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="motivo" class="form-label">Motivo <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="motivo" name="motivo" rows="3" required placeholder="Describe el motivo del movimiento..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="referencia_tipo" class="form-label">Tipo de Referencia</label>
                                <select class="form-select" id="referencia_tipo" name="referencia_tipo">
                                    <option value="">Sin referencia</option>
                                    <option value="pedido">Pedido</option>
                                    <option value="compra">Compra</option>
                                    <option value="devolucion">Devolución</option>
                                    <option value="ajuste_manual">Ajuste Manual</option>
                                    <option value="transferencia">Transferencia</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="referencia_id" class="form-label">ID de Referencia</label>
                                <input type="number" class="form-control" id="referencia_id" name="referencia_id" min="1" placeholder="ID del documento">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo RUTA_URL; ?>stock/listar" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg" id="btnGuardar">
                                <i class="bi bi-save"></i> Registrar Movimiento
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
    let tipoSeleccionado = '';
    let stockActual = 0;

    // Selección de tipo de movimiento
    document.querySelectorAll('.tipo-movimiento-option').forEach(option => {
        option.addEventListener('click', function() {
            // Remover selección anterior
            document.querySelectorAll('.tipo-movimiento-option').forEach(o => o.classList.remove('selected'));
            
            // Marcar como seleccionado
            this.classList.add('selected');
            tipoSeleccionado = this.dataset.tipo;
            document.getElementById('tipo_movimiento').value = tipoSeleccionado;
            
            // Mostrar/ocultar ubicación destino según tipo
            const ubicacionDestino = document.getElementById('ubicacion-destino-container');
            if (tipoSeleccionado === 'transferencia') {
                ubicacionDestino.style.display = 'block';
            } else {
                ubicacionDestino.style.display = 'none';
            }
            
            validarCantidad();
        });
    });

    // Selección de producto
    document.getElementById('id_producto').addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        stockActual = parseInt(option.dataset.stock) || 0;
        
        const stockInfo = document.getElementById('stock-info');
        document.getElementById('stock-actual').textContent = stockActual;
        stockInfo.classList.remove('d-none');
        
        validarCantidad();
    });

    // Validar cantidad según tipo de movimiento
    document.getElementById('cantidad').addEventListener('input', validarCantidad);

    function validarCantidad() {
        const cantidad = parseInt(document.getElementById('cantidad').value) || 0;
        const errorDiv = document.getElementById('cantidad-error');
        const btnGuardar = document.getElementById('btnGuardar');
        
        if (tipoSeleccionado === 'salida' && cantidad > stockActual) {
            errorDiv.textContent = `⚠️ No hay suficiente stock disponible (máximo: ${stockActual})`;
            errorDiv.classList.remove('d-none');
            btnGuardar.disabled = true;
        } else {
            errorDiv.classList.add('d-none');
           btnGuardar.disabled = false;
        }
    }

    // Envío del formulario
    document.getElementById('formStock').addEventListener('submit', function(e) {
        if (!tipoSeleccionado) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: 'Por favor selecciona un tipo de movimiento'
            });
            return false;
        }
        
        const idProducto = document.getElementById('id_producto').value;
        if (!idProducto) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: 'Por favor selecciona un producto'
            });
            return false;
        }
        
        const cantidad = document.getElementById('cantidad').value;
        if (!cantidad || parseInt(cantidad) < 1) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: 'Por favor ingresa una cantidad válida'
            });
            return false;
        }
        
        // Permitir submit normal - la ruta web maneja la respuesta
        return true;
    });
</script>
</body>
</html>
