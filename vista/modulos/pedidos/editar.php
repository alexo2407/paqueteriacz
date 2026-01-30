<?php
ob_start(); // Start buffering immediately to catch any spurious output/whitespace

// El ID del pedido se pasa desde el controlador
$id_pedido = $parametros[0] ?? null;

if (!$id_pedido) {
    echo "<div class='alert alert-danger'>No order ID provided.</div>";
    exit;
}

// Instanciar el controlador
$pedidoController = new PedidosController();

// Redireccionar a vista restringida si es Proveedor de Logística (y no Admin)
require_once __DIR__ . '/../../../utils/permissions.php';
if (isProveedor() && !isSuperAdmin()) {
    require __DIR__ . '/editar_proveedor.php';
    exit;
}

// Si el formulario fue enviado, procesa la actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clean any output generated so far (whitespace, includes, etc.)
    ob_clean();
    // guardarEdicion maneja la respuesta (JSON para AJAX, Redirect para normal) y termina la ejecución
    $pedidoController->guardarEdicion($_POST);
    exit;
}

// Flush buffer and continue with normal page rendering
ob_end_flush();

include("vista/includes/header.php");

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

// Obtener listas para selects
try {
    $estados = $pedidoController->obtenerEstados();
} catch (Exception $e) {
    $estados = [];
}

try {
    // Listar usuarios con rol Repartidor para asignación
    $vendedores = $pedidoController->obtenerRepartidores();
} catch (Exception $e) {
    $vendedores = [];
}

try {
    $productos = $pedidoController->obtenerProductos();
} catch (Exception $e) {
    $productos = [];
}

try {
    $monedas = $pedidoController->obtenerMonedas();
} catch (Exception $e) {
    $monedas = [];
}

try {
    $proveedores = $pedidoController->obtenerProveedores();
} catch (Exception $e) {
    $proveedores = [];
}

try {
    $clientes = $pedidoController->obtenerClientes();
} catch (Exception $e) {
    $clientes = [];
}

// Cargar países, departamentos, municipios y barrios para selects de dirección
require_once __DIR__ . '/../../../modelo/pais.php';
require_once __DIR__ . '/../../../modelo/departamento.php';
require_once __DIR__ . '/../../../modelo/municipio.php';
require_once __DIR__ . '/../../../modelo/barrio.php';
require_once __DIR__ . '/../../../modelo/moneda.php';
try { $paises = PaisModel::listar(); } catch (Exception $e) { $paises = []; }
try { $departamentosAll = DepartamentoModel::listarPorPais(null); } catch (Exception $e) { $departamentosAll = []; }
try { $municipiosAll = MunicipioModel::listarPorDepartamento(null); } catch (Exception $e) { $municipiosAll = []; }
try { $barriosAll = BarrioModel::listarPorMunicipio(null); } catch (Exception $e) { $barriosAll = []; }


// Obtener los datos del pedido
$pedido = $pedidoController->obtenerPedido($id_pedido);

if (!$pedido) {
    echo "<div class='alert alert-danger'>Order not found.</div>";
    exit;
}

// Detect user's local currency based on their country
require_once __DIR__ . '/../../../utils/session.php'; start_secure_session();
$monedaLocalUsuario = null;
$id_pais_usuario = $_SESSION['id_pais'] ?? $_SESSION['pais_id'] ?? null;
if (!$id_pais_usuario && isset($_SESSION['usuario_id'])) {
    require_once __DIR__ . '/../../../modelo/usuario.php';
    try {
        $usuarioData = UsuarioModel::obtenerPorId($_SESSION['usuario_id']);
        if ($usuarioData && isset($usuarioData['id_pais'])) {
            $id_pais_usuario = $usuarioData['id_pais'];
        }
    } catch (Exception $e) {
        // Silently fail, will use default currency
    }
}
if ($id_pais_usuario) {
    try {
        $paisData = PaisModel::obtenerPorId($id_pais_usuario);
        if ($paisData && isset($paisData['id_moneda_local'])) {
            $monedaLocalUsuario = $paisData['id_moneda_local'];
        }
    } catch (Exception $e) {
        // Silently fail
    }
}

// If the previous non-AJAX edit submit failed, repopulate fields from session
$old_edit = $_SESSION['old_pedido_edit_' . $id_pedido] ?? null;
if (isset($_SESSION['old_pedido_edit_' . $id_pedido])) unset($_SESSION['old_pedido_edit_' . $id_pedido]);
if ($old_edit) {
    // override scalar values present in old edit
    $fieldsToCopy = ['numero_orden','destinatario','telefono','direccion','codigo_postal','comentario','latitud','longitud','precio_local','precio_usd','precio_total_local','precio_total_usd','tasa_conversion_usd','id_pais','id_departamento','id_municipio','id_barrio','proveedor','moneda','vendedor','estado'];
    foreach ($fieldsToCopy as $f) {
        if (isset($old_edit[$f])) $pedido[$f] = $old_edit[$f];
    }
    // override products array if provided
    if (isset($old_edit['productos']) && is_array($old_edit['productos'])) {
        $pedido['productos'] = $old_edit['productos'];
    }
}

// Si no tiene proveedor, asignar el primero por defecto para que se seleccione
if (empty($pedido['id_proveedor']) && !empty($proveedores)) {
    $pedido['id_proveedor'] = $proveedores[0]['id'];
}
// Si no tiene moneda, preferir la moneda local del usuario, o el primero por defecto
if (empty($pedido['id_moneda'])) {
    if ($monedaLocalUsuario) {
        $pedido['id_moneda'] = $monedaLocalUsuario;
    } elseif (!empty($monedas)) {
        $pedido['id_moneda'] = $monedas[0]['id'];
    }
}

// CALCULAR PRECIOS TOTALES SI NO ES COMBO
// Si el pedido NO es combo, calcular el total desde los productos
if (empty($pedido['es_combo']) || $pedido['es_combo'] == 0) {
    $totalUsd = 0;
    
    // Sumar precio de cada producto
    if (!empty($pedido['productos']) && is_array($pedido['productos'])) {
        foreach ($pedido['productos'] as $item) {
            if (isset($item['id_producto']) && isset($item['cantidad'])) {
                // Buscar el producto para obtener su precio
                $productoEncontrado = null;
                foreach ($productos as $prod) {
                    if ((int)$prod['id'] === (int)$item['id_producto']) {
                        $productoEncontrado = $prod;
                        break;
                    }
                }
                
                if ($productoEncontrado && isset($productoEncontrado['precio_usd'])) {
                    $precioUnitario = (float)$productoEncontrado['precio_usd'];
                    $cantidad = (int)$item['cantidad'];
                    $totalUsd += $precioUnitario * $cantidad;
                }
            }
        }
    }
    
    // Si calculamos un total, actualizar los campos
    if ($totalUsd > 0) {
        // Obtener la tasa de la moneda seleccionada
        $tasaMoneda = 1;
        if (!empty($pedido['id_moneda'])) {
            foreach ($monedas as $mon) {
                if ((int)$mon['id'] === (int)$pedido['id_moneda']) {
                    $tasaMoneda = (float)($mon['tasa_usd'] ?? 1);
                    break;
                }
            }
        }
        
        // Calcular precio total local
        $totalLocal = $totalUsd * $tasaMoneda;
        
        // Actualizar los campos del pedido para mostrar en el formulario
        if (empty($pedido['precio_total_usd'])) {
            $pedido['precio_total_usd'] = number_format($totalUsd, 2, '.', '');
        }
        if (empty($pedido['precio_total_local'])) {
            $pedido['precio_total_local'] = number_format($totalLocal, 2, '.', '');
        }
        if (empty($pedido['tasa_conversion_usd'])) {
            $pedido['tasa_conversion_usd'] = number_format($tasaMoneda, 6, '.', '');
        }
    }
}

?>
<style>
.editar-pedido-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.editar-pedido-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem 2rem;
}
.editar-pedido-header h3 {
    margin: 0;
    font-weight: 600;
}
.form-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}
.form-section-title {
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.1rem;
}
.form-section-title i {
    color: #667eea;
}
.btn-submit-order {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 0.75rem 2rem;
    font-weight: 600;
    border-radius: 10px;
    font-size: 1rem;
}
.btn-submit-order:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}
.product-row {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 0.75rem;
}
.order-info-badge {
    background: rgba(255,255,255,0.2);
    padding: 0.4rem 0.8rem;
    border-radius: 50px;
    font-size: 0.85rem;
}

/* ==================== MODERN TABS STYLING ==================== */
#pills-tab {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    padding: 0.5rem !important;
    border-radius: 12px !important;
    border: 1px solid rgba(0,0,0,0.08) !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    gap: 0.5rem;
    margin-bottom: 2rem;
}
.nav-pills .nav-link {
    color: #495057;
    padding: 0.75rem 1.25rem;
    font-size: 0.95rem;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    background: transparent;
    border: 1px solid transparent;
}
.nav-pills .nav-link:hover:not(.active) {
    background: rgba(255, 255, 255, 0.8);
    color: #0d6efd;
    transform: translateY(-1px);
}
.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    color: white;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    transform: translateY(-2px);
}
.nav-pills .nav-link i { margin-right: 0.5rem; }
</style>

<div class="container-fluid py-4">
    <div class="card editar-pedido-card">
        <div class="editar-pedido-header">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-25 rounded-circle p-3">
                        <i class="bi bi-pencil-square fs-3"></i>
                    </div>
                    <div>
                        <h3>Editar Pedido</h3>
                        <p class="mb-0 opacity-75">Modifica los datos del pedido #<?= htmlspecialchars($pedido['numero_orden'] ?? $id_pedido) ?></p>
                    </div>
                </div>
                <div class="order-info-badge">
                    <i class="bi bi-hash me-1"></i>ID: <?= $id_pedido ?>
                </div>
            </div>
        </div>
        
        <div class="card-body p-4">

            <!-- TABS NAVIGATION -->
            <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pills-info-tab" data-bs-toggle="pill" data-bs-target="#pills-info" type="button" role="tab">
                        <i class="bi bi-clipboard-data"></i> Información Básica
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-asignacion-tab" data-bs-toggle="pill" data-bs-target="#pills-asignacion" type="button" role="tab">
                        <i class="bi bi-gear"></i> Asignación y Estado
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-productos-tab" data-bs-toggle="pill" data-bs-target="#pills-productos" type="button" role="tab">
                        <i class="bi bi-bag"></i> Productos y Precios
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-destinatario-tab" data-bs-toggle="pill" data-bs-target="#pills-destinatario" type="button" role="tab">
                        <i class="bi bi-person-badge"></i> Destinatario
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-tracking-tab" data-bs-toggle="pill" data-bs-target="#pills-tracking" type="button" role="tab">
                        <i class="bi bi-clock-history"></i> Tracking
                    </button>
                </li>
            </ul>

            <?= $mensaje ?? '' ?>

            <div id="formErrors" class="alert alert-danger d-none" role="alert" tabindex="-1" style="display:block">
                <ul id="formErrorsList" class="mb-0"></ul>
            </div>

            <!-- FORM START (Wraps all tabs) -->
            <form id="formEditarPedido" method="POST" action="">
                <?php 
                require_once __DIR__ . '/../../../utils/csrf.php';
                echo csrf_field(); 
                ?>
                <input type="hidden" name="id_pedido" value="<?= htmlspecialchars($pedido['id']) ?>">

                <div class="tab-content" id="pills-tabContent">
                    
                    <!-- TAB 1: INFORMACIÓN BÁSICA -->
                    <div class="tab-pane fade show active" id="pills-info" role="tabpanel">
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h5 class="mb-4 text-primary"><i class="bi bi-clipboard-data me-2"></i>Información General</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="numero_orden" class="form-label">Número de Orden</label>
                                            <input type="number" class="form-control form-control-lg" id="numero_orden" name="numero_orden" value="<?= htmlspecialchars($pedido['numero_orden']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="moneda" class="form-label">Moneda del Pedido</label>
                                            <select class="form-control select2-searchable form-select-lg" id="moneda" name="moneda" required data-placeholder="Seleccionar moneda...">
                                                <option value="">Selecciona una moneda</option>
                                                <?php foreach ($monedas as $moneda): 
                                                    $isSelected = ((int)$pedido['id_moneda'] === (int)$moneda['id']);
                                                ?>
                                                    <option value="<?= $moneda['id'] ?>" data-tasa="<?= htmlspecialchars($moneda['tasa_usd']) ?>" <?= $isSelected ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($moneda['nombre']) ?> (<?= htmlspecialchars($moneda['codigo']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if ($monedaLocalUsuario && (int)$pedido['id_moneda'] === (int)$monedaLocalUsuario): ?>
                                                <small class="form-text text-success"><i class="bi bi-check-circle"></i> Moneda local</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="comentario" class="form-label">Comentarios / Notas Internas</label>
                                    <textarea class="form-control" id="comentario" name="comentario" maxlength="500" rows="4"><?= htmlspecialchars($pedido['comentario']) ?></textarea>
                                </div>
                            </div>
                        </div>
                        <!-- Botones de Acción Globales (visible en cada tab o al final) -->
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-primary" onclick="var t = new bootstrap.Tab(document.querySelector('#pills-asignacion-tab')); t.show();">Siguiente <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- TAB 2: ASIGNACIÓN Y ESTADO -->
                    <div class="tab-pane fade" id="pills-asignacion" role="tabpanel">
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h5 class="mb-4 text-warning"><i class="bi bi-gear me-2"></i>Configuración de Asignación</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label for="estado" class="form-label fw-bold">Estado Actual</label>
                                        <select class="form-control select2-searchable form-select-lg" id="estado" name="estado">
                                            <option value="">Selecciona un estado</option>
                                            <?php foreach ($estados as $estado): ?>
                                                <option value="<?= $estado['id'] ?>" <?= $pedido['id_estado'] == $estado['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($estado['nombre_estado']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label for="proveedor" class="form-label fw-bold">Proveedor (Logística)</label>
                                        <?php if (canSelectAnyProveedor()): ?>
                                            <select class="form-control select2-searchable" id="proveedor" name="proveedor" required>
                                                <option value="">Selecciona un proveedor</option>
                                                <?php foreach ($proveedores as $proveedor): ?>
                                                    <option value="<?= $proveedor['id'] ?>" <?= ((int)$pedido['id_proveedor'] === (int)$proveedor['id']) ? 'selected' : '' ?> >
                                                        <?= htmlspecialchars($proveedor['nombre']) ?><?= isset($proveedor['email']) && $proveedor['email'] ? ' — ' . htmlspecialchars($proveedor['email']) : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="hidden" id="proveedor" name="proveedor" value="<?= $pedido['id_proveedor'] ?>">
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($pedido['proveedor_nombre'] ?? 'Mi usuario') ?>" disabled>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-4">
                                        <label for="cliente" class="form-label fw-bold">Cliente (Opcional)</label>
                                        <select class="form-control select2-searchable" id="cliente" name="id_cliente">
                                            <option value="">Sin Cliente asignado</option>
                                            <?php 
                                            $clienteExistenteEnLista = false;
                                            foreach ($clientes as $cli): 
                                                $selected = ((int)($pedido['id_cliente'] ?? 0) === (int)$cli['id']);
                                                if ($selected) $clienteExistenteEnLista = true;
                                            ?>
                                                <option value="<?= $cli['id'] ?>" <?= $selected ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cli['nombre']) ?><?= isset($cli['email']) && $cli['email'] ? ' — ' . htmlspecialchars($cli['email']) : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                            
                                            <?php if (!$clienteExistenteEnLista && !empty($pedido['id_cliente'])): ?>
                                                <option value="<?= $pedido['id_cliente'] ?>" selected>
                                                    <?= htmlspecialchars($pedido['cliente_nombre'] ?? 'Cliente #' . $pedido['id_cliente']) ?> (Asignado)
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-4">
                                        <label for="vendedor" class="form-label fw-bold">Repartidor / Operador Asignado</label>
                                        <select class="form-control select2-searchable" id="vendedor" name="vendedor">
                                            <option value="">Sin Asignar</option>
                                            <?php foreach ($vendedores as $vendedor): ?>
                                                <option value="<?= $vendedor['id'] ?>" <?= $pedido['id_vendedor'] == $vendedor['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($vendedor['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                             <button type="button" class="btn btn-outline-secondary" onclick="var t = new bootstrap.Tab(document.querySelector('#pills-info-tab')); t.show();"><i class="bi bi-arrow-left"></i> Anterior</button>
                             <button type="button" class="btn btn-primary" onclick="var t = new bootstrap.Tab(document.querySelector('#pills-productos-tab')); t.show();">Siguiente <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- TAB 3: PRODUCTOS Y PRECIOS -->
                    <div class="tab-pane fade" id="pills-productos" role="tabpanel">
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="mb-0 text-success"><i class="bi bi-bag me-2"></i>Detalle de Productos</h5>
                                    <div>
                                         <button type="button" id="btnAddProducto" class="btn btn-success btn-sm"><i class="bi bi-plus-lg"></i> Agregar Item</button>
                                    </div>
                                </div>
                                
                                <div id="productosContainer" class="mb-4"></div>
                                
                                <hr class="my-4">
                                
                                <h5 class="mb-3 text-success"><i class="bi bi-cash-coin me-2"></i>Resumen Financiero</h5>
                                
                                <div class="alert alert-light border mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="es_combo" name="es_combo" value="1" <?= (!empty($pedido['es_combo']) && $pedido['es_combo'] == 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label user-select-none" for="es_combo">
                                            <strong>Modo Combo / Precio Cerrado</strong>
                                            <div class="text-muted small">Activar si se cobra un precio total único en lugar de por producto.</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Total (Moneda Local)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-cash"></i></span>
                                            <input type="number" class="form-control fw-bold" id="precio_total_local" name="precio_total_local" step="0.01" min="0" value="<?= htmlspecialchars($pedido['precio_total_local'] ?? $pedido['precio_local'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Total (USD)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-currency-dollar"></i></span>
                                            <input type="number" class="form-control bg-light" id="precio_total_usd" name="precio_total_usd" step="0.01" readonly value="<?= htmlspecialchars($pedido['precio_total_usd'] ?? $pedido['precio_usd'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Tasa Cambio</label>
                                        <input type="number" class="form-control bg-light text-muted" id="tasa_conversion_usd" name="tasa_conversion_usd" step="0.000001" readonly value="<?= htmlspecialchars($pedido['tasa_conversion_usd'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                             <button type="button" class="btn btn-outline-secondary" onclick="var t = new bootstrap.Tab(document.querySelector('#pills-asignacion-tab')); t.show();"><i class="bi bi-arrow-left"></i> Anterior</button>
                             <button type="button" class="btn btn-primary" onclick="var t = new bootstrap.Tab(document.querySelector('#pills-destinatario-tab')); t.show();">Siguiente <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- TAB 4: DESTINATARIO -->
                    <div class="tab-pane fade" id="pills-destinatario" role="tabpanel">
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h5 class="mb-4 text-info"><i class="bi bi-geo-alt me-2"></i>Datos de Entrega</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="destinatario" class="form-label">Nombre Contacto</label>
                                        <input type="text" class="form-control" id="destinatario" name="destinatario" value="<?= htmlspecialchars($pedido['destinatario']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="telefono" class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($pedido['telefono']) ?>" required>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="direccion" class="form-label">Dirección Exacta</label>
                                        <textarea class="form-control" id="direccion" name="direccion" rows="2" required><?= htmlspecialchars($pedido['direccion']) ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">País</label>
                                        <select class="form-select select2-searchable" id="id_pais" name="id_pais">
                                            <option value="">Selecciona</option>
                                            <?php foreach ($paises as $p): ?>
                                                <option value="<?= (int)$p['id'] ?>" <?= (!empty($pedido['id_pais']) && (int)$pedido['id_pais'] === (int)$p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Departamento</label>
                                        <select class="form-select select2-searchable" id="id_departamento" name="id_departamento">
                                            <option value="">Selecciona</option>
                                            <?php foreach ($departamentosAll as $d): ?>
                                                <option value="<?= (int)$d['id'] ?>" data-id-pais="<?= (int)($d['id_pais'] ?? 0) ?>" <?= (!empty($pedido['id_departamento']) && (int)$pedido['id_departamento'] === (int)$d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="id_municipio" class="form-label">Municipio</label>
                                        <select class="form-select select2-searchable" id="id_municipio" name="id_municipio">
                                            <option value="">Selecciona</option>
                                            <!-- Dynamically populated -->
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="id_barrio" class="form-label">Barrio</label>
                                        <select class="form-select select2-searchable" id="id_barrio" name="id_barrio">
                                            <option value="">Selecciona</option>
                                            <!-- Dynamically populated -->
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="codigo_postal" class="form-label">Código Postal</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-geo"></i></span>
                                            <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" value="<?= htmlspecialchars($pedido['codigo_postal'] ?? '') ?>" placeholder="Ej: 1000">
                                        </div>
                                        <small class="text-muted" style="font-size: 0.75rem;">Se autocompleta según ubicación.</small>
                                    </div>

                                    <div class="col-12 mt-3">
                                        <label class="form-label">Geolocalización</label>
                                        <div class="row g-2 mb-2">
                                            <div class="col-6"><input type="text" class="form-control form-control-sm" id="latitud" name="latitud" placeholder="Lat" value="<?= htmlspecialchars($pedido['latitud']) ?>"></div>
                                            <div class="col-6"><input type="text" class="form-control form-control-sm" id="longitud" name="longitud" placeholder="Lng" value="<?= htmlspecialchars($pedido['longitud']) ?>"></div>
                                        </div>
                                        <div id="map" style="width: 100%; height: 350px; border-radius: 8px;" class="border"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Submit Section -->
                        <div class="d-flex justify-content-between align-items-center mt-4 p-3 bg-light rounded border">
                             <button type="button" class="btn btn-outline-secondary" onclick="var t = new bootstrap.Tab(document.querySelector('#pills-productos-tab')); t.show();"><i class="bi bi-arrow-left"></i> Anterior</button>
                             <div>
                                 <a href="<?= RUTA_URL ?>pedidos/listar" class="btn btn-link text-decoration-none me-3">Cancelar</a>
                                 <button type="submit" class="btn btn-primary btn-lg px-5 shadow"><i class="bi bi-check-lg"></i> Guardar Cambios</button>
                             </div>
                        </div>
                    </div>

                    <!-- TAB 5: TRACKING -->
                    <div class="tab-pane fade" id="pills-tracking" role="tabpanel">
                        <div class="p-4 border rounded bg-white shadow-sm">
                            <h5 class="mb-4 text-primary"><i class="bi bi-clock-history me-2"></i>Timeline de Actividad</h5>
                            <div id="historial-container">
                                <!-- Ajax content -->
                            </div>
                        </div>
                    </div>

                </div> <!-- End Tab Content -->
            </form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab persistence logic
    const tabKey = 'activeTab_editar_pedido';
    const tabs = document.querySelectorAll('button[data-bs-toggle="pill"]');
    
    // Check local storage or URL hash
    const savedTab = localStorage.getItem(tabKey) || (window.location.hash ? window.location.hash.replace('#', '') : null);
    
    if (savedTab) {
        // Compatibility: map old 'historial' to new 'tracking'
        const targetTab = (savedTab === 'historial') ? 'tracking' : savedTab;
        const triggerEl = document.querySelector(`#pills-${targetTab}-tab`);
        
        if (triggerEl) {
            const tab = new bootstrap.Tab(triggerEl);
            tab.show();
        }
    }

    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', event => {
            const targetId = event.target.getAttribute('data-bs-target').replace('#pills-', '');
            localStorage.setItem(tabKey, targetId);
            // Optional: update URL hash without scrolling
            history.replaceState(null, null, `#${targetId}`);
        });
    });

    // History loading logic
    const ordenId = <?= json_encode($id_pedido) ?>;
    const container = document.getElementById('historial-container');
    
    fetch('<?= RUTA_URL ?>pedidos/historial/' + ordenId)
        .then(response => response.json())
        .then(res => {
            if(res.success && res.data && res.data.length > 0) {
                const getBadgeColor = (estado) => {
                    if (!estado) return 'secondary';
                    const s = estado.toUpperCase();
                    if (s.includes('ENTREGADO') || s.includes('VENDIDO')) return 'success';
                    if (s.includes('CANCELADO') || s.includes('RECHAZADO') || s.includes('DEVUELTO')) return 'danger';
                    if (s.includes('RUTA') || s.includes('TRANSITO')) return 'info text-dark';
                    if (s.includes('BODEGA')) return 'primary';
                    if (s.includes('DEVOLUCION') || s.includes('PENDIENTE') || s.includes('DOMICILIO')) return 'warning text-dark';
                    if (s.includes('LIQUIDADO')) return 'dark';
                    return 'secondary';
                };

                let html = '<ul class="list-group list-group-flush">';
                res.data.forEach(item => {
                    const fecha = new Date(item.created_at).toLocaleString();
                    const usuario = item.usuario_nombre || 'Sistema';
                    
                    const colorAnt = getBadgeColor(item.estado_anterior_nombre);
                    const colorNuevo = getBadgeColor(item.estado_nombre);
                    
                    const estadoAnt = item.estado_anterior_nombre ? `<span class="badge bg-${colorAnt}">${item.estado_anterior_nombre}</span> <i class="bi bi-arrow-right mx-1"></i> ` : '';
                    const estadoNuevo = `<span class="badge bg-${colorNuevo}">${item.estado_nombre}</span>`;
                    const obs = item.observaciones ? `<div class="mt-2 p-2 bg-light rounded small border-start border-4 border-info"><i class="bi bi-info-circle me-1"></i> ${item.observaciones}</div>` : '';
                    
                    html += `
                        <li class="list-group-item py-3 px-0 bg-transparent border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-light rounded-circle p-2 me-3 text-center" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-clock text-primary"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold">${estadoAnt}${estadoNuevo}</div>
                                        <small class="text-muted"><i class="bi bi-person me-1"></i>${usuario}</small>
                                    </div>
                                </div>
                                <span class="badge bg-white text-dark rounded-pill border shadow-sm font-monospace">${fecha}</span>
                            </div>
                            ${obs}
                        </li>
                    `;
                });
                html += '</ul>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="alert alert-light text-center border p-4"><i class="bi bi-info-circle fs-2 d-block mb-2"></i> No hay cambios de estado registrados en el historial detallado.</div>';
            }
        })
        .catch(err => {
             console.error(err);
             container.innerHTML = '<div class="alert alert-warning text-center">No se pudo cargar el historial.</div>';
        });
});
</script>

<script>
// Sync first product row into legacy hidden inputs for compatibility (editar)
(function(){
    const form = document.getElementById('formEditarPedido');
    if (!form) return;
    // create hidden legacy inputs if not present
    if (!document.getElementById('producto_id')) {
        const hidProd = document.createElement('input'); hidProd.type = 'hidden'; hidProd.id = 'producto_id'; hidProd.name = 'producto_id'; form.appendChild(hidProd);
    }
    if (!document.getElementById('cantidad_producto')) {
        const hidQty = document.createElement('input'); hidQty.type = 'hidden'; hidQty.id = 'cantidad_producto'; hidQty.name = 'cantidad_producto'; form.appendChild(hidQty);
    }
    form.addEventListener('submit', function(){
        const firstRow = document.querySelector('#productosContainer .producto-row');
        const hidProd = document.getElementById('producto_id');
        const hidQty = document.getElementById('cantidad_producto');
        if (!hidProd || !hidQty) return;
        if (!firstRow) { hidProd.value = ''; hidQty.value = ''; return; }
        const sel = firstRow.querySelector('.producto-select');
        const qty = firstRow.querySelector('.producto-cantidad');
        hidProd.value = sel ? sel.value : '';
        hidQty.value = qty ? qty.value : '';
    });
})();
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= API_MAP ?>&callback=initMap" async defer></script>
<script>
    let map, marker;

    function initMap() {
        // Coordenadas iniciales desde la base de datos
        const initialPosition = {
            lat: parseFloat(document.getElementById("latitud").value) || 12.13282,
            lng: parseFloat(document.getElementById("longitud").value) || -86.2504
        };

        // Crear el mapa
        map = new google.maps.Map(document.getElementById("map"), {
            center: initialPosition,
            zoom: 15,
        });

        // Crear un marcador inicial
        marker = new google.maps.Marker({
            position: initialPosition,
            map: map,
            draggable: true, // Permitir arrastrar el marcador
        });

        // Actualizar los campos de latitud y longitud al mover el marcador
        marker.addListener("dragend", (event) => {
            const position = event.latLng;
            document.getElementById("latitud").value = position.lat();
            document.getElementById("longitud").value = position.lng();
        });

        // Actualizar el marcador cuando cambien los inputs manualmente
        document.getElementById("latitud").addEventListener("input", updateMapPosition);
        document.getElementById("longitud").addEventListener("input", updateMapPosition);
    }

    function updateMapPosition() {
        const lat = parseFloat(document.getElementById("latitud").value);
        const lng = parseFloat(document.getElementById("longitud").value);

        if (!isNaN(lat) && !isNaN(lng)) {
            const newPosition = {
                lat: lat,
                lng: lng
            };
            marker.setPosition(newPosition);
            map.setCenter(newPosition);
        }
    }
</script>

<script>
// Validación para el formulario de edición (tiempo real y submit)
function setInvalidEd(el, msg) {
    if (!el) return;
    el.classList.remove('is-valid');
    el.classList.add('is-invalid');
    const fb = el.parentElement.querySelector('.invalid-feedback');
    if (fb && msg) fb.textContent = msg;
}
function clearInvalidEd(el) {
    if (!el) return;
    el.classList.remove('is-invalid');
    el.classList.add('is-valid');
}

function validarTelefonoEd(value) {
    return /^\d{8,15}$/.test(value);
}

function validarDecimalEd(value) {
    return !isNaN(parseFloat(value)) && isFinite(value);
}

function validarFormularioEditar() {
    let valid = true;
    const fields = [
        {id:'destinatario', fn: v => v.trim().length >= 2, msg: 'Por favor, ingresa un nombre válido.'},
        {id:'telefono', fn: v => validarTelefonoEd(v), msg: 'Teléfono inválido (8-15 dígitos).'},
        {id:'producto_id', fn: v => v.trim().length > 0, msg: 'Por favor, selecciona un producto.'},
        {id:'cantidad_producto', fn: v => v === '' || (Number.isInteger(Number(v)) && Number(v) >= 1), msg: 'La cantidad debe ser al menos 1 si se proporciona.'},
        {id:'precio_local', fn: v => v === '' || validarDecimalEd(v), msg: 'Precio local inválido.'},
        {id:'direccion', fn: v => v.trim().length > 5, msg: 'Dirección demasiado corta.'},
        {id:'latitud', fn: v => validarDecimalEd(v), msg: 'Latitud inválida.'},
        {id:'longitud', fn: v => validarDecimalEd(v), msg: 'Longitud inválida.'}
    ];

    for (const f of fields) {
        const el = document.getElementById(f.id);
        const val = el ? el.value : '';
        if (!f.fn(val)) {
            setInvalidEd(el, f.msg);
            if (valid) el.focus();
            valid = false;
        } else {
            clearInvalidEd(el);
        }
    }

    return valid;
}
<script>
// Real-time listeners - simplified version for editar
document.addEventListener('DOMContentLoaded', function() {
    // La lógica de cálculo de precios ahora se maneja en el script de productos dinámicos
    // Este código se mantiene para compatibilidad con validaciones
});

</script>

<script src="<?= RUTA_URL ?>js/pedidos-validation.js?v=<?= time() ?>"></script>

<script>
// Dynamic products rows for editar.php
(function(){
    const productos = <?php echo json_encode(array_map(function($p){
        return [ 
            'id' => (int)$p['id'], 
            'nombre' => $p['nombre'], 
            'marca' => $p['marca'] ?? '', 
            'stock' => isset($p['stock_total']) ? (int)$p['stock_total'] : 0, 
            'precio_usd' => isset($p['precio_usd']) ? $p['precio_usd'] : null,
            'id_usuario_creador' => $p['id_usuario_creador'] ?? null 
        ];
    }, $productos)); ?>;

    const existingItems = <?php echo json_encode($pedido['productos'] ?? []); ?>;
    
    // Detectar si el usuario actual es Admin
    const esAdmin = <?php 
        require_once __DIR__ . '/../../../utils/permissions.php';
        echo isSuperAdmin() ? 'true' : 'false'; 
    ?>;
    
    const productosContainer = document.getElementById('productosContainer');
    const btnAdd = document.getElementById('btnAddProducto');
    const precioTotalLocalInput = document.getElementById('precio_total_local');
    const precioTotalUsdInput = document.getElementById('precio_total_usd');
    const tasaConversionInput = document.getElementById('tasa_conversion_usd');
    const monedaSelect = document.getElementById('moneda');
    const proveedorSelect = document.getElementById('proveedor');

    function escapeHtml(s) { if (!s) return ''; return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    function makeProductOptions(selectedId) {
        let opts = '<option value="">Selecciona un producto</option>';
        const idProveedorSeleccionado = proveedorSelect ? parseInt(proveedorSelect.value) : null;

        if (!idProveedorSeleccionado) {
            opts += '<option value="" disabled>Selecciona un proveedor primero</option>';
            return opts;
        }

        // ADMIN ve TODOS los productos, Proveedor solo los suyos
        let productosFiltrados;
        if (esAdmin) {
            // Admin: mostrar todos los productos
            productosFiltrados = productos;
        } else {
            // Proveedor: Aplicar filtro - solo productos del proveedor y legacy sin creador
            productosFiltrados = productos.filter(p => {
                const idCreador = p.id_usuario_creador;
                // Permitir el producto si:
                // 1. Pertenece al proveedor seleccionado, O
                // 2. No tiene creador (legacy), O
                // 3. Es el producto que está siendo pre-seleccionado (para edición de pedidos antiguos)
                return (idCreador !== null && parseInt(idCreador) === idProveedorSeleccionado) ||
                       (idCreador === null) ||
                       (selectedId && parseInt(p.id) === parseInt(selectedId));
            });
        }

        if (productosFiltrados.length === 0) {
            opts += '<option value="" disabled>No hay productos disponibles para este proveedor</option>';
            return opts;
        }

        productosFiltrados.forEach(p => {
            const sel = (selectedId && parseInt(selectedId) === parseInt(p.id)) ? ' selected' : '';
            const marcaText = p.marca ? ` (${escapeHtml(p.marca)})` : '';
            opts += `<option value="${p.id}" data-stock="${p.stock}"${sel}>${escapeHtml(p.nombre)}${marcaText} — Stock: ${p.stock}</option>`;
        });
        return opts;
    }

    // Función para actualizar la tasa de conversión
    function actualizarTasaConversion() {
        if (!monedaSelect || !tasaConversionInput) return;
        const selectedOption = monedaSelect.options[monedaSelect.selectedIndex];
        if (!selectedOption) return;
        const tasa = parseFloat(selectedOption.getAttribute('data-tasa'));
        if (!isNaN(tasa) && tasa > 0) {
            tasaConversionInput.value = tasa.toFixed(6);
        }
    }

    // Función para calcular precio USD basado en el precio total local
    function calcularPrecioUSD() {
        if (!precioTotalLocalInput || !precioTotalUsdInput || !tasaConversionInput) return;
        
        const precioLocal = parseFloat(precioTotalLocalInput.value) || 0;
        const tasa = parseFloat(tasaConversionInput.value) || 1;
        
        if (tasa > 0) {
            const precioUsd = precioLocal / tasa;
            precioTotalUsdInput.value = precioUsd.toFixed(2);
        }
    }

    // Función para recalcular totales automáticamente cuando NO es combo
    function recalcularTotalesNoCombo() {
        // Solo recalcular si NO es combo
        const esComboCheckbox = document.getElementById('es_combo');
        if (esComboCheckbox && esComboCheckbox.checked) {
            return; // Es combo, no recalcular
        }
        
        console.log("Recalculando totales...");
        let totalUsd = 0;
        let itemsFound = 0;
        
        // Sumar precio de cada fila de producto
        const allRows = productosContainer.querySelectorAll('.producto-row');
        allRows.forEach(row => {
            const select = row.querySelector('.producto-select');
            const cantidadInput = row.querySelector('.producto-cantidad');
            
            if (select && cantidadInput && select.value) {
                const productoId = parseInt(select.value);
                const cantidad = parseFloat(cantidadInput.value) || 0; // Usar parseFloat para cantidad
                
                // Buscar el producto en el array para obtener su precio
                const producto = productos.find(p => parseInt(p.id) === productoId);
                
                if (producto) {
                    // Asegurar que el precio sea número 
                    const precioUnitario = parseFloat(producto.precio_usd);
                    
                    if (!isNaN(precioUnitario)) {
                        const subtotal = precioUnitario * cantidad;
                        totalUsd += subtotal;
                        itemsFound++;
                        console.log(`Producto: ${producto.nombre}, Precio: ${precioUnitario}, Cantidad: ${cantidad}, Subtotal: ${subtotal}`);
                    } else {
                        console.warn(`Producto ${producto.nombre} tiene precio inválido:`, producto.precio_usd);
                    }
                } else {
                    console.warn(`Producto ID ${productoId} no encontrado en array de productos`);
                }
            }
        });
        
        console.log(`Total calculado: ${totalUsd} (${itemsFound} items)`);

        // Actualizar los campos
        if (totalUsd > 0) {
            const tasa = parseFloat(tasaConversionInput.value) || 1;
            const totalLocal = totalUsd * tasa;
            
            precioTotalUsdInput.value = totalUsd.toFixed(2);
            precioTotalLocalInput.value = totalLocal.toFixed(2);
        } else if (itemsFound === 0) {
            // Si no hay productos válidos seleccionados
            precioTotalUsdInput.value = '0.00';
            precioTotalLocalInput.value = '0.00';
        }
    }

    // Función para actualizar la disponibilidad de opciones en los selects
    function updateProductOptionsAvailability() {
        const allSelects = Array.from(productosContainer.querySelectorAll('.producto-select'));
        const selectedValues = allSelects.map(s => s.value).filter(v => v !== "");

        allSelects.forEach(select => {
            const currentVal = select.value;
            Array.from(select.options).forEach(option => {
                if (option.value === "") return; // Skip placeholder
                
                // Si la opción está seleccionada en OTRO select, ocultarla
                // Pero si es la opción actualmente seleccionada en ESTE select, mostrarla
                if (selectedValues.includes(option.value) && option.value !== currentVal) {
                    option.style.display = 'none';
                    // También deshabilitarla para navegadores que no soportan display: none en options
                    option.disabled = true; 
                } else {
                    option.style.display = '';
                    option.disabled = false;
                }
            });
        });
    }

    function addProductRow(selectedId, qty) {
        const row = document.createElement('div');
        row.className = 'row mb-2 producto-row align-items-center';
        const index = Date.now() + Math.floor(Math.random() * 1000); // Unique index
        row.innerHTML = `
            <div class="col-md-7">
                <select name="productos[${index}][producto_id]" class="form-select producto-select" required>
                    ${makeProductOptions(selectedId)}
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" name="productos[${index}][cantidad]" class="form-control producto-cantidad" min="1" value="${qty ? qty : 1}" placeholder="Cantidad" required>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-outline-danger btnRemove w-100">
                    <i class="bi bi-trash"></i> Eliminar
                </button>
            </div>
        `;
        productosContainer.appendChild(row);
        
        // Event listeners - Calcular precios automáticamente cuando NO es combo
        const select = row.querySelector('.producto-select');
        const cantidad = row.querySelector('.producto-cantidad');
        
        // Inicializar Select2 para este dropdown
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(select).select2({
                theme: 'bootstrap-5',
                placeholder: 'Escribe para buscar un producto...',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return 'No se encontraron productos';
                    },
                    searching: function() {
                        return 'Buscando...';
                    }
                }
            });

            // Evento change de Select2
            $(select).on('change.select2', function() {
                updateProductOptionsAvailability();
                recalcularTotalesNoCombo(); // Recalcular cuando cambia el producto
            });
        } else {
            // Fallback si Select2 no está disponible
            select.addEventListener('change', () => {
                updateProductOptionsAvailability();
                recalcularTotalesNoCombo(); // Recalcular cuando cambia el producto
            });
        }
        
        // Recalcular cuando cambia la cantidad
        cantidad.addEventListener('input', () => {
            recalcularTotalesNoCombo();
        });
        
        row.querySelector('.btnRemove').addEventListener('click', () => {
            // Destruir Select2 antes de eliminar el elemento
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $(select).select2('destroy');
            }
            row.remove();
            updateProductOptionsAvailability();
            recalcularTotalesNoCombo(); // Recalcular después de eliminar
        });
        
        // Actualizar opciones al agregar nueva fila
        updateProductOptionsAvailability();
        recalcularTotalesNoCombo(); // Recalcular al agregar nueva fila
    }

    // initialize rows from existingItems, or create one if empty
    document.addEventListener('DOMContentLoaded', function(){
        // Inicializar tasa de conversión al cargar
        actualizarTasaConversion();

        if (existingItems && existingItems.length > 0) {
            existingItems.forEach(it => {
                // Handle both DB key (id_producto) and Session key (producto_id)
                // Also handle if keys are missing but values are in the object (e.g. from interleaved array)
                let pId = it.id_producto || it.producto_id;
                let qVal = it.cantidad;
                
                // Asegurar que id_producto sea un entero
                const productoId = pId ? parseInt(pId) : '';
                const cantidad = qVal ? parseInt(qVal) : 1;
                
                addProductRow(productoId, cantidad);
            });
        } else {
            addProductRow('', 1);
        }
        
        // NO calculamos precios iniciales - el usuario debe ingresar el precio total manualmente
        updateProductOptionsAvailability();
    });
    
    btnAdd.addEventListener('click', function(){ 
        addProductRow('', 1); 
    });

    // Actualizar tasa cuando cambie la moneda y recalcular precio USD
    if (monedaSelect) {
        monedaSelect.addEventListener('change', function() {
            actualizarTasaConversion();
            calcularPrecioUSD();
            recalcularTotalesNoCombo(); // Recalcular con la nueva tasa
        });
    }

    // Recalcular precio USD cuando cambie el precio total local
    if (precioTotalLocalInput) {
        precioTotalLocalInput.addEventListener('input', calcularPrecioUSD);
    }

    // Actualizar opciones de productos cuando cambie el proveedor
    if (proveedorSelect) {
        proveedorSelect.addEventListener('change', function() {
            // Recargar opciones en todos los selects de productos existentes
            const allSelects = productosContainer.querySelectorAll('.producto-select');
            allSelects.forEach(select => {
                const currentVal = select.value;
                select.innerHTML = makeProductOptions(currentVal);
                
                // Reinicializar Select2 si está disponible
                if (typeof $ !== 'undefined' && $.fn.select2) {
                    $(select).select2('destroy');
                    $(select).select2({
                        theme: 'bootstrap-5',
                        placeholder: 'Escribe para buscar un producto...',
                        allowClear: true,
                        width: '100%'
                    });
                }
            });
            updateProductOptionsAvailability();
        });
    }
})();


// Dependent selects for editar: departamento -> municipio -> barrio
(function(){
    const deptSelect = document.getElementById('id_departamento');
    const munSelect = document.getElementById('id_municipio');
    const barrioSelect = document.getElementById('id_barrio');
    const cpInput = document.getElementById('codigo_postal');
    const municipios = <?php echo json_encode($municipiosAll); ?>;
    const barrios = <?php echo json_encode($barriosAll); ?>;

    // Inicializar Select2 en los nuevos selects
    function initSelect2ForLocationSelects() {
        if (typeof $ !== 'undefined' && $.fn.select2) {
            if (!$(munSelect).hasClass('select2-hidden-accessible')) {
                $(munSelect).select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Buscar municipio...',
                    allowClear: true,
                    width: '100%'
                });
            }
            if (!$(barrioSelect).hasClass('select2-hidden-accessible')) {
                $(barrioSelect).select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Buscar barrio...',
                    allowClear: true,
                    width: '100%'
                });
            }
        }
    }

    function populateMunicipios(depId, selectedMunId) {
        // Destruir Select2 temporalmente para repoblar
        if (typeof $ !== 'undefined' && $.fn.select2 && $(munSelect).hasClass('select2-hidden-accessible')) {
            $(munSelect).select2('destroy');
        }
        
        munSelect.innerHTML = '<option value="" selected>Selecciona un municipio</option>';
        municipios.forEach(m => {
            if (!depId || depId === '' || parseInt(m.id_departamento) === parseInt(depId)) {
                const opt = document.createElement('option');
                opt.value = m.id; 
                opt.textContent = m.nombre; 
                opt.setAttribute('data-id-departamento', m.id_departamento); 
                opt.setAttribute('data-cp', m.codigo_postal || '');
                if (selectedMunId && parseInt(selectedMunId) === parseInt(m.id)) opt.selected = true;
                munSelect.appendChild(opt);
            }
        });
        
        // Reinicializar Select2
        initSelect2ForLocationSelects();
        
        populateBarrios(munSelect.value, <?= json_encode($pedido['id_barrio'] ?? '') ?>);
    }

    function populateBarrios(munId, selectedBarrioId) {
        // Destruir Select2 temporalmente para repoblar
        if (typeof $ !== 'undefined' && $.fn.select2 && $(barrioSelect).hasClass('select2-hidden-accessible')) {
            $(barrioSelect).select2('destroy');
        }
        
        barrioSelect.innerHTML = '<option value="" selected>Selecciona un barrio</option>';
        barrios.forEach(b => {
            if (!munId || munId === '' || parseInt(b.id_municipio) === parseInt(munId)) {
                const opt = document.createElement('option'); 
                opt.value = b.id; 
                opt.textContent = b.nombre; 
                opt.setAttribute('data-id-municipio', b.id_municipio); 
                opt.setAttribute('data-cp', b.codigo_postal || '');
                if (selectedBarrioId && parseInt(selectedBarrioId) === parseInt(b.id)) opt.selected = true; 
                barrioSelect.appendChild(opt);
            }
        });
        
        // Reinicializar Select2
        initSelect2ForLocationSelects();
    }

    function updatePostalCode() {
        if (!cpInput) return;
        let detectedCP = '';

        // 1. Intentar obtener de Barrio
        const selectedBarrio = barrioSelect.options[barrioSelect.selectedIndex];
        if (selectedBarrio && selectedBarrio.getAttribute('data-cp')) {
            detectedCP = selectedBarrio.getAttribute('data-cp');
        }

        // 2. Si no hay en Barrio, intentar obtener de Municipio
        if (!detectedCP) {
            const selectedMun = munSelect.options[munSelect.selectedIndex];
            if (selectedMun && selectedMun.getAttribute('data-cp')) {
                detectedCP = selectedMun.getAttribute('data-cp');
            }
        }

        if (detectedCP) {
            cpInput.value = detectedCP;
            cpInput.classList.add('is-valid');
            setTimeout(() => cpInput.classList.remove('is-valid'), 2000);
        }
    }

    // Events - usar eventos de Select2 si está disponible
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $(deptSelect).on('change.select2', function(){
            populateMunicipios(deptSelect.value, <?= json_encode($pedido['id_municipio'] ?? '') ?>);
        });
        $(munSelect).on('change.select2', function(){ 
            populateBarrios(munSelect.value); 
            updatePostalCode();
        });
        $(barrioSelect).on('change.select2', function(){ 
            updatePostalCode();
        });
    } else {
        deptSelect.addEventListener('change', function(){
            const dep = deptSelect.value;
            populateMunicipios(dep, <?= json_encode($pedido['id_municipio'] ?? '') ?>);
        });
        munSelect.addEventListener('change', function(){ 
            populateBarrios(munSelect.value); 
            updatePostalCode();
        });
        barrioSelect.addEventListener('change', function(){ 
            updatePostalCode();
        });
    }

    document.addEventListener('DOMContentLoaded', function(){
        // trigger initial population and set selections based on pedido
        const dep = <?= json_encode($pedido['id_departamento'] ?? '') ?>;
        if (dep && dep !== '') {
            // ensure department filter (if any) runs
            const evt = new Event('change'); deptSelect.dispatchEvent(evt);
        } else {
            populateMunicipios('', <?= json_encode($pedido['id_municipio'] ?? '') ?>);
        }
    });
})();
</script>

<script>
// Toggle combo pricing behavior
document.getElementById('es_combo').addEventListener('change', function() {
    const precioTotalLocalInput = document.getElementById('precio_total_local');
    const precioInfoTexto = document.getElementById('precio-info-texto');
    const precioDescripcion = document.getElementById('precio-descripcion');
    
    if (this.checked) {
        // ES COMBO: El usuario puede editar el precio total
        precioTotalLocalInput.removeAttribute('readonly');
        precioTotalLocalInput.classList.remove('bg-light');
        
        // Actualizar textos
        if (precioInfoTexto) {
            precioInfoTexto.textContent = 'Precio del Combo:';
        }
        if (precioDescripcion) {
            precioDescripcion.textContent = 'Ingresa el precio total que te cobró el proveedor en su moneda local. El precio en USD se calculará automáticamente.';
        }
        
        // NO BORRAR los valores - el usuario puede querer mantenerlos como base
        // Si quiere cambiar los precios, puede hacerlo manualmente
        
    } else {
        // NO ES COMBO: El precio se calcula automáticamente
        precioTotalLocalInput.setAttribute('readonly', 'readonly');
        precioTotalLocalInput.classList.add('bg-light');
        
        // Actualizar textos
        if (precioInfoTexto) {
            precioInfoTexto.textContent = 'Total del Pedido:';
        }
        if (precioDescripcion) {
            precioDescripcion.textContent = 'El precio total se calcula automáticamente sumando el precio de cada producto multiplicado por su cantidad.';
        }
        
        // NO BORRAR los valores - el backend recalculará si es necesario
        // Esto previene pérdida de datos si el usuario activa/desactiva accidentalmente
    }
});
</script>

<?php include("vista/includes/footer.php"); ?>