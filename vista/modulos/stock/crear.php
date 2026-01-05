<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/producto.php';

start_secure_session();
require_login();

// Obtener filtro de usuario (proveedores solo ven sus productos)
$filtroUsuario = getIdUsuarioCreadorFilter();

// Obtener productos con filtro
$productos = ProductoModel::listarConInventario($filtroUsuario);

// Obtener producto preseleccionado si viene de URL
$productoPreseleccionado = $_GET['producto'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Movimiento de Stock - Paquetería RutaEx-Latam</title>
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

<style>
.stock-header {
    background: linear-gradient(135deg, #093028 0%, #237A57 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(17, 153, 142, 0.2);
}
.form-section-title {
    color: #11998e;
    font-weight: 700;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.type-card {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    background: white;
    height: 100%;
}
.type-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.05);
    border-color: #38ef7d;
}
.type-card.selected {
    border-color: #11998e;
    background-color: #f0fdf4;
    box-shadow: 0 0 0 4px rgba(17, 153, 142, 0.1);
}
.type-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    display: block;
    transition: transform 0.3s;
}
.type-card:hover .type-icon {
    transform: scale(1.1);
}
.form-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    overflow: hidden;
}
.stock-info-card {
    background-color: #e8f5e9;
    border-radius: 12px;
    padding: 1.25rem;
    border-left: 5px solid #2e7d32;
    margin-bottom: 1.5rem;
    display: none; /* Oculto por defecto */
}
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header -->
            <div class="stock-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1 fw-bold"><i class="bi bi-arrow-down-up me-2"></i> Registrar Movimiento</h2>
                    <p class="mb-0 opacity-75">Control de entradas, salidas y ajustes de inventario</p>
                </div>
                <a href="<?php echo RUTA_URL; ?>stock/listar" class="btn btn-outline-light border-2 text-white fw-bold">
                    <i class="bi bi-arrow-left me-1"></i> Volver al Listado
                </a>
            </div>

            <form id="formStock" method="POST" action="<?php echo RUTA_URL; ?>stock/guardar" class="needs-validation" novalidate>
                <?php 
                    require_once __DIR__ . '/../../../utils/csrf.php';
                    echo csrf_field(); 
                ?>
                <input type="hidden" id="tipo_movimiento" name="tipo_movimiento" required>

                <div class="card form-card mb-4">
                    <div class="card-body p-4 p-md-5">
                        
                        <!-- Paso 1: Tipo de Movimiento -->
                        <h4 class="form-section-title"><i class="bi bi-1-circle-fill"></i> Selecciona el Tipo de Movimiento</h4>
                        
                        <div class="row g-3 mb-5">
                            <div class="col-md-4 col-sm-6">
                                <div class="type-card" onclick="seleccionarTipo('entrada', this)">
                                    <i class="bi bi-box-arrow-in-down type-icon text-success"></i>
                                    <h5 class="fw-bold mb-1">Entrada</h5>
                                    <p class="text-muted small mb-0">Recepción de mercancía, compras</p>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="type-card" onclick="seleccionarTipo('salida', this)">
                                    <i class="bi bi-box-arrow-up type-icon text-danger"></i>
                                    <h5 class="fw-bold mb-1">Salida</h5>
                                    <p class="text-muted small mb-0">Ventas, despachos, mermas</p>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="type-card" onclick="seleccionarTipo('ajuste', this)">
                                    <i class="bi bi-sliders type-icon text-warning"></i>
                                    <h5 class="fw-bold mb-1">Ajuste</h5>
                                    <p class="text-muted small mb-0">Corrección de inventario manual</p>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="type-card" onclick="seleccionarTipo('devolucion', this)">
                                    <i class="bi bi-arrow-counterclockwise type-icon text-info"></i>
                                    <h5 class="fw-bold mb-1">Devolución</h5>
                                    <p class="text-muted small mb-0">Retorno de productos</p>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="type-card" onclick="seleccionarTipo('transferencia', this)">
                                    <i class="bi bi-arrow-left-right type-icon text-primary"></i>
                                    <h5 class="fw-bold mb-1">Transferencia</h5>
                                    <p class="text-muted small mb-0">Mover entre ubicaciones</p>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 2: Detalles del Movimiento -->
                        <h4 class="form-section-title"><i class="bi bi-2-circle-fill"></i> Detalles del Producto</h4>
                        
                        <div class="row g-4 mb-4">
                            <div class="col-md-12">
                                <label for="id_producto" class="form-label fw-bold">Producto <span class="text-danger">*</span></label>
                                <select class="form-select select2-searchable p-3" id="id_producto" name="id_producto" required data-placeholder="Buscar producto por nombre o SKU...">
                                    <option value="">Selecciona un producto</option>
                                    <?php foreach ($productos as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" 
                                                data-stock="<?php echo (int)($p['stock_total'] ?? 0); ?>"
                                                data-nombre="<?php echo htmlspecialchars($p['nombre']); ?>"
                                                data-sku="<?php echo htmlspecialchars($p['sku'] ?? ''); ?>"
                                                <?php echo ($productoPreseleccionado == $p['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['nombre']); ?> 
                                            <?php if (!empty($p['sku'])): ?> (SKU: <?php echo htmlspecialchars($p['sku']); ?>) <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Info de Stock (Visible al seleccionar producto) -->
                            <div class="col-12">
                                <div id="stock-info" class="stock-info-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-0 fw-bold text-success" id="nombre-producto-display">Nombre Producto</h5>
                                            <small class="text-muted">SKU: <span id="sku-display">--</span></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="d-block text-muted small text-uppercase">Stock Actual</span>
                                            <span class="h2 fw-bold text-dark mb-0" id="stock-actual">0</span> <span class="small text-muted">unidades</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="cantidad" class="form-label fw-bold">Cantidad <span class="text-danger">*</span></label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light"><i class="bi bi-123"></i></span>
                                    <input type="number" class="form-control fw-bold" id="cantidad" name="cantidad" min="1" required placeholder="0">
                                </div>
                                <div id="cantidad-error" class="text-danger small mt-1 d-none fw-bold"></div>
                            </div>

                            <div class="col-md-6">
                                <label for="costo_unitario" class="form-label fw-bold">Costo Unitario (Opcional)</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light">$</span>
                                    <input type="number" class="form-control" id="costo_unitario" name="costo_unitario" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <div class="form-text">Si se deja vacío, se usará el costo actual del producto.</div>
                            </div>
                        </div>

                        <!-- Paso 3: Ubicación y Referencias -->
                        <h4 class="form-section-title"><i class="bi bi-3-circle-fill"></i> Ubicación y Motivo</h4>
                        
                        <div class="row g-4 mb-4">
                            <div class="col-md-6" id="ubicacion-origen-container">
                                <label for="ubicacion_origen" class="form-label fw-bold">Ubicación <span class="origen-label">Principal</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-geo-alt"></i></span>
                                    <input type="text" class="form-control" id="ubicacion_origen" name="ubicacion_origen" placeholder="Ej: Almacén Central, Estante 4B">
                                </div>
                            </div>
                            
                            <div class="col-md-6" id="ubicacion-destino-container" style="display:none;">
                                <label for="ubicacion_destino" class="form-label fw-bold">Ubicación Destino <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-geo-alt-fill"></i></span>
                                    <input type="text" class="form-control" id="ubicacion_destino" name="ubicacion_destino" placeholder="Ej: Tienda Sucursal 1">
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="motivo" class="form-label fw-bold">Motivo / Observaciones <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="motivo" name="motivo" rows="3" required placeholder="Explique brevemente la razón de este movimiento..."></textarea>
                            </div>

                            <div class="col-md-4">
                                <label for="referencia_tipo" class="form-label fw-bold">Documento Ref.</label>
                                <select class="form-select" id="referencia_tipo" name="referencia_tipo">
                                    <option value="">-- Seleccionar --</option>
                                    <option value="pedido">Pedido</option>
                                    <option value="compra">Factura de Compra</option>
                                    <option value="devolucion">Nota de Crédito/Devolución</option>
                                    <option value="transferencia">Guía de Remisión</option>
                                    <option value="ajuste_manual">Ajuste de Inventario</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label for="referencia_id" class="form-label fw-bold">Número/ID Ref.</label>
                                <input type="text" class="form-control" id="referencia_id" name="referencia_id" placeholder="Ej: 001-23456">
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="d-flex justify-content-end gap-3 mt-5 pt-3 border-top">
                            <a href="<?php echo RUTA_URL; ?>stock/listar" class="btn btn-light btn-lg px-4 border">Cancelar</a>
                            <button type="submit" class="btn btn-success btn-lg px-5 shadow fw-bold" id="btnGuardar">
                                <i class="bi bi-check-lg me-2"></i> Registrar Movimiento
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

    // Inicializar Select2
    $('.select2-searchable').select2({
        theme: "bootstrap-5",
        width: '100%',
        selectionCssClass: "p-2",
    });

    // Función para seleccionar tipo
    window.seleccionarTipo = function(tipo, element) {
        // UI Updates
        document.querySelectorAll('.type-card').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        
        // Logic Updates
        tipoSeleccionado = tipo;
        document.getElementById('tipo_movimiento').value = tipo;
        
        // Mostrar/Ocultar campos específicos
        const destinoContainer = document.getElementById('ubicacion-destino-container');
        const origenLabel = document.querySelector('.origen-label');
        
        if (tipo === 'transferencia') {
            destinoContainer.style.display = 'block';
            document.getElementById('ubicacion_destino').required = true;
            origenLabel.textContent = 'Origen';
        } else {
            destinoContainer.style.display = 'none';
            document.getElementById('ubicacion_destino').required = false;
            origenLabel.textContent = 'Principal';
        }

        // Re-validar cantidad por si cambió las reglas (ej: salida vs entrada)
        validarCantidad();
    }

    // Listener para cambio de producto
    $('#id_producto').on('select2:select', function (e) {
        const data = e.params.data.element.dataset;
        mostrarInfoStock(data.stock, data.nombre, data.sku);
    });

    // Si hay producto preseleccionado
    <?php if ($productoPreseleccionado): ?>
        const select = document.getElementById('id_producto');
        const selectedOption = select.options[select.selectedIndex];
        if(selectedOption) {
             mostrarInfoStock(selectedOption.dataset.stock, selectedOption.dataset.nombre, selectedOption.dataset.sku);
        }
    <?php endif; ?>

    function mostrarInfoStock(stock, nombre, sku) {
        stockActual = parseInt(stock) || 0;
        document.getElementById('stock-actual').textContent = stockActual;
        document.getElementById('nombre-producto-display').textContent = nombre;
        document.getElementById('sku-display').textContent = sku || '--';
        
        const infoCard = document.getElementById('stock-info');
        infoCard.style.display = 'block';
        infoCard.classList.add('animate__animated', 'animate__fadeIn');
        
        validarCantidad();
    }

    // Validar cantidad en tiempo real
    document.getElementById('cantidad').addEventListener('input', validarCantidad);

    function validarCantidad() {
        const inputCantidad = document.getElementById('cantidad');
        const cantidad = parseInt(inputCantidad.value) || 0;
        const errorDiv = document.getElementById('cantidad-error');
        const btnGuardar = document.getElementById('btnGuardar');
        
        let error = '';

        if (tipoSeleccionado === 'salida' && cantidad > stockActual) {
            error = `⚠️ Stock insuficiente. Máximo disponible: ${stockActual}`;
        }
        
        if (error) {
            errorDiv.textContent = error;
            errorDiv.classList.remove('d-none');
            inputCantidad.classList.add('is-invalid');
            btnGuardar.disabled = true;
        } else {
            errorDiv.classList.add('d-none');
            inputCantidad.classList.remove('is-invalid');
            btnGuardar.disabled = false;
        }
    }

    // Submit Handler
    document.getElementById('formStock').addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        if (!tipoSeleccionado) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Falta información',
                text: 'Por favor selecciona un Tipo de Movimiento'
            });
            // Scroll to top to see types
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return false;
        }

        // Validación extra de stock
        const cantidad = parseInt(document.getElementById('cantidad').value) || 0;
        if (tipoSeleccionado === 'salida' && cantidad > stockActual) {
            e.preventDefault();
             Swal.fire({
                icon: 'error',
                title: 'Error de Stock',
                text: 'No tienes suficiente stock para realizar esta salida.'
            });
            return false;
        }

        this.classList.add('was-validated');
    });
</script>
</body>
</html>
