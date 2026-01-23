<?php include("vista/includes/header.php"); ?>

<?php
$pedidosController = new PedidosController();

try {
    $estados = $pedidosController->obtenerEstados();
} catch (Exception $e) {
    $estados = [];
}

try {
    $vendedores = $pedidosController->obtenerRepartidores();
} catch (Exception $e) {
    $vendedores = [];
}

try {
    $productos = $pedidosController->obtenerProductos();
} catch (Exception $e) {
    $productos = [];
}

try {
    $monedas = $pedidosController->obtenerMonedas();
} catch (Exception $e) {
    $monedas = [];
}

try {
    $proveedores = $pedidosController->obtenerProveedores();
} catch (Exception $e) {
    $proveedores = [];
}
try {
    $clientes = $pedidosController->obtenerClientes();
} catch (Exception $e) {
    $clientes = [];
}

// Detectar moneda local del usuario basado en su país
$monedaLocalUsuario = null;
$idPaisUsuario = null;

// Intentar obtener id_pais de múltiples fuentes
if (isset($_SESSION['id_pais']) && $_SESSION['id_pais']) {
    $idPaisUsuario = $_SESSION['id_pais'];
} elseif (isset($_SESSION['user_id'])) {
    // Si no está en sesión, obtener del usuario en BD
    try {
        require_once __DIR__ . '/../../../modelo/usuario.php';
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('SELECT id_pais FROM usuarios WHERE id = :user_id');
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $idPaisUsuario = $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Error al obtener país del usuario: ' . $e->getMessage());
    }
}

if ($idPaisUsuario) {
    try {
        require_once __DIR__ . '/../../../modelo/moneda.php';
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('SELECT id_moneda_local FROM paises WHERE id = :id_pais');
        $stmt->execute([':id_pais' => $idPaisUsuario]);
        $monedaLocalUsuario = $stmt->fetchColumn();
        
        // Log para debugging (solo en desarrollo)
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Auto-detección de moneda: País ID={$idPaisUsuario}, Moneda ID={$monedaLocalUsuario}");
        }
    } catch (Exception $e) {
        error_log('Error al obtener moneda local del usuario: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../../../modelo/pais.php';
require_once __DIR__ . '/../../../modelo/departamento.php';
require_once __DIR__ . '/../../../utils/session.php';
start_secure_session();

$old_posted = $_SESSION['old_pedido'] ?? null;
if (isset($_SESSION['old_pedido'])) unset($_SESSION['old_pedido']);
require_once __DIR__ . '/../../../modelo/municipio.php';
require_once __DIR__ . '/../../../modelo/barrio.php';
try {
    $paises = PaisModel::listar();
} catch (Exception $e) {
    $paises = [];
}
try {
    $departamentosAll = DepartamentoModel::listarPorPais(null);
} catch (Exception $e) {
    $departamentosAll = [];
}
try {
    $municipiosAll = MunicipioModel::listarPorDepartamento(null);
} catch (Exception $e) {
    $municipiosAll = [];
}
try {
    $barriosAll = BarrioModel::listarPorMunicipio(null);
} catch (Exception $e) {
    $barriosAll = [];
}
?>

<style>
.crear-pedido-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.crear-pedido-header {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    padding: 1.5rem 2rem;
}
.crear-pedido-header h3 {
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
    color: #11998e;
}
.btn-submit-order {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    border: none;
    padding: 0.75rem 2rem;
    font-weight: 600;
    border-radius: 10px;
    font-size: 1rem;
}
.btn-submit-order:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
}
.product-row {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 0.75rem;
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
    color: #11998e;
    transform: translateY(-1px);
}
.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(17, 153, 142, 0.3);
    transform: translateY(-2px);
}
.nav-pills .nav-link i { margin-right: 0.5rem; }
</style>

<div class="container-fluid py-4">
    <div class="card crear-pedido-card">
        <div class="crear-pedido-header">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white bg-opacity-25 rounded-circle p-3">
                    <i class="bi bi-plus-circle fs-3"></i>
                </div>
                <div>
                    <h3>Nuevo Pedido</h3>
                    <p class="mb-0 opacity-75">Completa los datos para crear un nuevo pedido</p>
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
            </ul>

            <div id="formErrors" class="alert alert-danger d-none" role="alert" tabindex="-1" style="display:block">
                <ul id="formErrorsList" class="mb-0"></ul>
            </div>

            <form id="formCrearPedido" action="<?= RUTA_URL ?>pedidos/guardarPedido" method="POST">
                <?php 
                require_once __DIR__ . '/../../../utils/csrf.php';
                echo csrf_field(); 
                ?>
                
                <div class="tab-content" id="pills-tabContent">

                    <!-- TAB 1: INFORMACIÓN BÁSICA -->
                    <div class="tab-pane fade show active" id="pills-info" role="tabpanel">
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h5 class="mb-4 text-primary"><i class="bi bi-clipboard-data me-2"></i>Información General</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="numero_orden" class="form-label">Número de Orden</label>
                                            <input type="number" class="form-control" id="numero_orden" name="numero_orden" min="1" required value="<?= htmlspecialchars($old_posted['numero_orden'] ?? '') ?>">
                                            <div class="invalid-feedback">Por favor, ingresa un número de orden válido.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="moneda" class="form-label">Moneda 💱</label>
                                            <select class="form-select select2-searchable" id="moneda" name="moneda" required data-placeholder="Seleccionar moneda...">
                                                <option value="" disabled <?= !$monedaLocalUsuario && !isset($old_posted['moneda']) ? 'selected' : '' ?>>Selecciona una moneda</option>
                                                <?php foreach ($monedas as $moneda): 
                                                    // Pre-seleccionar la moneda local del usuario si existe
                                                    $selected = false;
                                                    if (isset($old_posted['moneda']) && (int)$old_posted['moneda'] === (int)$moneda['id']) {
                                                        $selected = true;
                                                    } elseif (!isset($old_posted['moneda']) && $monedaLocalUsuario && (int)$monedaLocalUsuario === (int)$moneda['id']) {
                                                        $selected = true;
                                                    }
                                                ?>
                                                    <option value="<?= $moneda['id']; ?>" data-tasa="<?= htmlspecialchars($moneda['tasa_usd']); ?>" <?= $selected ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($moneda['nombre']); ?> (<?= htmlspecialchars($moneda['codigo']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Por favor, selecciona una moneda.</div>
                                            <?php if ($monedaLocalUsuario): ?>
                                                <small class="form-text text-success"><i class="bi bi-check-circle"></i> Moneda local pre-seleccionada según tu país</small>
                                            <?php else: ?>
                                                <small class="form-text text-muted" id="tasaInfo">Tasa de cambio seleccionada</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                         <div class="mb-3">
                                            <label for="comentario" class="form-label">Comentario / Notas</label>
                                            <textarea class="form-control" id="comentario" name="comentario" maxlength="500" rows="1"><?= htmlspecialchars($old_posted['comentario'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="proveedor" class="form-label">Proveedor</label>
                                            <?php
                                            require_once __DIR__ . '/../../../utils/permissions.php';
                                            $canSelect = canSelectAnyProveedor();
                                            if ($canSelect): ?>
                                                <select class="form-select select2-searchable" id="proveedor" name="proveedor" required data-placeholder="Buscar proveedor...">
                                                    <option value="" disabled selected>Selecciona un proveedor</option>
                                                    <?php foreach ($proveedores as $proveedor): ?>
                                                        <option value="<?= $proveedor['id']; ?>" <?= (isset($old_posted['proveedor']) && (int)$old_posted['proveedor'] === (int)$proveedor['id']) ? 'selected' : '' ?> >
                                                            <?= htmlspecialchars($proveedor['nombre']); ?><?= isset($proveedor['email']) && $proveedor['email'] ? ' — ' . htmlspecialchars($proveedor['email']) : '' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if (empty($proveedores)): ?>
                                                    <div class="form-text text-warning">No hay usuarios con rol Proveedor activos.</div>
                                                <?php endif; ?>
                                                <div class="invalid-feedback">Por favor, selecciona un proveedor.</div>
                                            <?php else: ?>
                                                <!-- Usuario Proveedor: auto-asignado -->
                                                <input type="hidden" id="proveedor" name="proveedor" value="<?= $_SESSION['user_id'] ?>">
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['nombre'] ?? 'Mi usuario') ?>" disabled>
                                                <div class="form-text text-success">✓ Este pedido será asignado automáticamente a tu usuario.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="cliente" class="form-label">Cliente (Opcional)</label>
                                            <select class="form-select select2-searchable" id="cliente" name="id_cliente" data-placeholder="Buscar cliente...">
                                                <option value="" selected>Sin Cliente asignado</option>
                                                <?php foreach ($clientes as $cli): ?>
                                                    <option value="<?= $cli['id']; ?>" <?= (isset($old_posted['id_cliente']) && (int)$old_posted['id_cliente'] === (int)$cli['id']) ? 'selected' : '' ?> >
                                                        <?= htmlspecialchars($cli['nombre']); ?><?= isset($cli['email']) && $cli['email'] ? ' — ' . htmlspecialchars($cli['email']) : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="estado" class="form-label">Estado Inicial</label>
                                            <select class="form-select select2-searchable" id="estado" name="estado" data-placeholder="Seleccionar estado...">
                                                <option value="" disabled selected>Selecciona un estado</option>
                                                <?php foreach ($estados as $estado): ?>
                                                    <option value="<?= $estado['id']; ?>" <?= (isset($old_posted['estado']) && (int)$old_posted['estado'] === (int)$estado['id']) ? 'selected' : '' ?>><?= htmlspecialchars($estado['nombre_estado']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Por favor, selecciona un estado.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="vendedor" class="form-label">Repartidor Asignado</label>
                                            <select class="form-select select2-searchable" id="vendedor" name="vendedor" data-placeholder="Buscar repartidor...">
                                                <option value="" disabled selected>Selecciona un usuario (Repartidor)</option>
                                                <?php foreach ($vendedores as $vendedor): ?>
                                                    <option value="<?= $vendedor['id']; ?>" <?= (isset($old_posted['vendedor']) && (int)$old_posted['vendedor'] === (int)$vendedor['id']) ? 'selected' : '' ?> >
                                                        <?= htmlspecialchars($vendedor['nombre']); ?><?= isset($vendedor['email']) && $vendedor['email'] ? ' — ' . htmlspecialchars($vendedor['email']) : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (empty($vendedores)): ?>
                                                <div class="form-text text-warning">No hay usuarios con rol Repartidor activos.</div>
                                            <?php endif; ?>
                                        </div>
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

                                <div class="mb-3">
                                    <div id="productosContainer">
                                        <!-- Product rows will be injected here -->
                                    </div>
                                    <div class="form-text" id="productoAyuda">Puedes agregar múltiples productos.</div>
                                </div>

                                <hr class="my-4">
                                
                                <h5 class="mb-3 text-success"><i class="bi bi-cash-coin me-2"></i>Resumen Financiero</h5>

                                <!-- Toggle para activar modo combo -->
                                <div class="alert alert-light border mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="es_combo" name="es_combo" value="1" checked>
                                        <label class="form-check-label" for="es_combo">
                                            <strong>📦 Modo Combo / Precio Cerrado</strong>
                                            <div class="text-muted small">Activar si se cobra un precio total único en lugar de por producto.</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="row mt-4" id="combo-pricing-section" style="display: block;">
                                    <div class="col-12 mb-3">
                                        <div class="alert alert-info py-2" role="alert">
                                            <small><i class="bi bi-info-circle"></i> Ingresa el precio total que cobrarás por todos los productos.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="precio_total_local" class="form-label">Precio Total (Moneda Local) 💰</label>
                                            <input type="number" step="0.01" class="form-control fw-bold" id="precio_total_local" name="precio_total_local" min="0" value="<?= htmlspecialchars($old_posted['precio_total_local'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="precio_total_usd" class="form-label">Precio Total USD 💵</label>
                                            <input type="number" step="0.01" class="form-control bg-light" id="precio_total_usd" name="precio_total_usd" readonly value="<?= htmlspecialchars($old_posted['precio_total_usd'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="tasa_conversion_usd" class="form-label">Tasa Cambio</label>
                                            <input type="number" step="0.000001" class="form-control bg-light text-muted" id="tasa_conversion_usd" name="tasa_conversion_usd" readonly value="<?= htmlspecialchars($old_posted['tasa_conversion_usd'] ?? '') ?>">
                                        </div>
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
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="destinatario" class="form-label">Nombre del Destinatario</label>
                                            <input type="text" class="form-control" id="destinatario" name="destinatario" value="<?= htmlspecialchars($old_posted['destinatario'] ?? '') ?>">
                                            <div class="invalid-feedback">Por favor, ingresa un nombre válido.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="telefono" class="form-label">Teléfono</label>
                                            <input type="tel" class="form-control" id="telefono" name="telefono" pattern="[0-9]{8,15}" value="<?= htmlspecialchars($old_posted['telefono'] ?? '') ?>">
                                            <div class="invalid-feedback">Por favor, ingresa un número de teléfono válido.</div>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="direccion" class="form-label">Dirección de Entrega</label>
                                        <textarea class="form-control" id="direccion" name="direccion" rows="2"><?= htmlspecialchars($old_posted['direccion'] ?? '') ?></textarea>
                                        <div class="invalid-feedback">Por favor, proporciona una dirección válida.</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="id_pais" class="form-label">País</label>
                                        <select class="form-select select2-searchable" id="id_pais" name="id_pais">
                                            <option value="" selected>Selecciona un país</option>
                                            <?php foreach ($paises as $p): ?>
                                                        <option value="<?= (int)$p['id'] ?>" <?= (isset($old_posted['id_pais']) && (int)$old_posted['id_pais'] === (int)$p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="id_departamento" class="form-label">Departamento</label>
                                        <select class="form-select select2-searchable" id="id_departamento" name="id_departamento">
                                            <option value="" selected>Selecciona un departamento</option>
                                            <?php foreach ($departamentosAll as $d): ?>
                                                <option value="<?= (int)$d['id'] ?>" data-id-pais="<?= (int)($d['id_pais'] ?? 0) ?>" <?= (isset($old_posted['id_departamento']) && (int)$old_posted['id_departamento'] === (int)$d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-12 mt-3">
                                        <label class="form-label">Geolocalización</label>
                                        <div class="row g-2 mb-2">
                                            <div class="col-6"><input type="text" class="form-control form-control-sm" id="latitud" name="latitud" placeholder="Lat" value="<?= htmlspecialchars($old_posted['latitud'] ?? '') ?>"></div>
                                            <div class="col-6"><input type="text" class="form-control form-control-sm" id="longitud" name="longitud" placeholder="Lng" value="<?= htmlspecialchars($old_posted['longitud'] ?? '') ?>"></div>
                                        </div>
                                        <div id="map" style="height: 350px; width: 100%; border-radius: 8px;" class="border"></div>
                                        <small class="form-text text-muted">Arrastra el marcador para ajustar la ubicación</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="d-flex justify-content-between align-items-center mt-4 p-3 bg-light rounded border">
                             <button type="button" class="btn btn-outline-secondary" onclick="var t = new bootstrap.Tab(document.querySelector('#pills-productos-tab')); t.show();"><i class="bi bi-arrow-left"></i> Anterior</button>
                             <div>
                                 <!-- Legacy inputs -->
                                 <input type="hidden" id="producto_id" name="producto_id" value="">
                                 <input type="hidden" id="cantidad_producto" name="cantidad_producto" value="">
                                 
                                 <a href="<?= RUTA_URL ?>pedidos" class="btn btn-link text-decoration-none me-3">Cancelar</a>
                                 <button type="submit" class="btn btn-success btn-lg px-5 shadow"><i class="bi bi-check-circle"></i> Crear Pedido</button>
                             </div>
                        </div>
                    </div>

                </div> <!-- End Tab Content -->
            </form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab persistence logic
        const tabKey = 'activeTab_crear_pedido';
        const tabs = document.querySelectorAll('button[data-bs-toggle="pill"]');
        
        // Check local storage or URL hash
        const savedTab = localStorage.getItem(tabKey) || (window.location.hash ? window.location.hash.replace('#', '') : null);
        
        if (savedTab) {
            const triggerEl = document.querySelector(`#pills-${savedTab}-tab`);
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
                
                // Trigger map resize if switching to dest tab to prevent rendering issues
                if (targetId === 'destinatario' && typeof google !== 'undefined' && map) {
                    google.maps.event.trigger(map, 'resize');
                    if (marker) map.setCenter(marker.getPosition());
                }
            });
        });
    });
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=<?= API_MAP ?>&callback=initMap" async defer></script>
<script src="<?= RUTA_URL ?>js/pedidos-validation.js?v=<?= time() ?>"></script>
<script>
    let map;
    let marker;

    function initMap() {
        const latInput = document.getElementById('latitud');
        const lngInput = document.getElementById('longitud');
        const hasLat = latInput && latInput.value !== '';
        const hasLng = lngInput && lngInput.value !== '';

        const defaultPosition = {
            lat: hasLat ? parseFloat(latInput.value) : 12.13282,
            lng: hasLng ? parseFloat(lngInput.value) : -86.2504
        };

        map = new google.maps.Map(document.getElementById('map'), {
            center: defaultPosition,
            zoom: 15
        });

        marker = new google.maps.Marker({
            position: defaultPosition,
            map: map,
            draggable: true
        });

        marker.addListener('dragend', function (event) {
            if (latInput) latInput.value = event.latLng.lat();
            if (lngInput) lngInput.value = event.latLng.lng();
        });

        map.addListener('click', function (event) {
            marker.setPosition(event.latLng);
            if (latInput) latInput.value = event.latLng.lat();
            if (lngInput) lngInput.value = event.latLng.lng();
        });

        function updateMarkerFromInputs() {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                const newPosition = { lat: lat, lng: lng };
                marker.setPosition(newPosition);
                map.setCenter(newPosition);
            }
        }

        if (latInput) latInput.addEventListener('input', updateMarkerFromInputs);
        if (lngInput) lngInput.addEventListener('input', updateMarkerFromInputs);
    }

    window.initMap = initMap;

</script>

<script>
    // Filtrar departamentos por país seleccionado (client-side)
    (function(){
        const paisSelect = document.getElementById('id_pais');
        const deptSelect = document.getElementById('id_departamento');
        if (!paisSelect || !deptSelect) return;

        function filterDepartments() {
            const selected = paisSelect.value;
            const options = Array.from(deptSelect.querySelectorAll('option[data-id-pais]'));
            options.forEach(opt => {
                const pid = opt.getAttribute('data-id-pais');
                if (!selected || selected === '' || pid === '' || pid === '0') {
                    // if no country selected show all
                    opt.style.display = '';
                } else {
                    opt.style.display = (pid === selected) ? '' : 'none';
                }
            });
            // If current selection is hidden, reset
            const cur = deptSelect.value;
            const curOpt = deptSelect.querySelector('option[value="' + cur + '"]');
            if (curOpt && curOpt.style.display === 'none') {
                deptSelect.value = '';
            }
        }

        paisSelect.addEventListener('change', filterDepartments);
        // run once on load
        document.addEventListener('DOMContentLoaded', filterDepartments);
    })();
</script>



<script>
// Dynamic products rows for crearPedido with Price Calculation
(function(){
    const productos = <?php echo json_encode(array_map(function($p){
        return [
            'id' => (int)$p['id'],
            'nombre' => $p['nombre'],
            'marca' => $p['marca'] ?? '',
            'stock' => isset($p['stock_total']) ? (int)$p['stock_total'] : 0,
            'precio_usd' => isset($p['precio_usd']) ? $p['precio_usd'] : null,
            'id_usuario_creador' => isset($p['id_usuario_creador']) ? (int)$p['id_usuario_creador'] : null
        ];
    }, $productos)); ?>;
    const oldPosted = <?php echo json_encode($old_posted ?? null); ?>;
    
    // Detectar si el usuario actual es Admin
    const esAdmin = <?php 
        require_once __DIR__ . '/../../../utils/permissions.php';
        echo isSuperAdmin() ? 'true' : 'false'; 
    ?>;
    
    // Solo para proveedores: obtener su ID de usuario
    const usuarioProveedorId = <?php 
        echo isProveedor() && !isSuperAdmin() ? ($_SESSION['user_id'] ?? 'null') : 'null'; 
    ?>;

    const productosContainer = document.getElementById('productosContainer');
    const btnAdd = document.getElementById('btnAddProducto');
    const precioUsdInput = document.getElementById('precio_usd');
    const precioLocalInput = document.getElementById('precio_local');
    const monedaSelect = document.getElementById('moneda');
    const tasaInfo = document.getElementById('tasaInfo');
    const proveedorSelect = document.getElementById('proveedor');

    function escapeHtml(s) {
        if (!s) return '';
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function makeProductOptions(selectedId) {
        let opts = '<option value="">Selecciona un producto</option>';
        
        // Obtener el proveedor seleccionado o el del usuario logueado
        const proveedorId = proveedorSelect ? parseInt(proveedorSelect.value) : null;
        
        // ADMIN ve TODOS los productos sin filtro
        let productosDelProveedor;
        if (esAdmin) {
            // Admin: mostrar todos los productos
            productosDelProveedor = productos;
        } else {
            // Proveedor: filtrar solo sus productos y los legacy sin creador
            productosDelProveedor = productos.filter(p => {
                // Si no hay proveedor seleccionado, no mostrar nada
                if (!proveedorId) return false;
                
                // Mostrar productos del proveedor seleccionado
                if (p.id_usuario_creador && p.id_usuario_creador === proveedorId) {
                    return true;
                }
                
                // También mostrar productos legacy sin creador (disponibles para todos)
                if (!p.id_usuario_creador || p.id_usuario_creador === null) {
                    return true;
                }
                
                return false;
            });
        }
        
        productosDelProveedor.forEach(p => {
            const sel = (selectedId && parseInt(selectedId) === parseInt(p.id)) ? ' selected' : '';
            // Mostrar nombre, marca/modelo y stock
            const marcaText = p.marca ? ` (${escapeHtml(p.marca)})` : '';
            opts += `<option value="${p.id}" data-stock="${p.stock}"${sel}>${escapeHtml(p.nombre)}${marcaText}${p.stock !== null ? ' — Stock: ' + p.stock : ''}</option>`;
        });
        
        if (productosDelProveedor.length === 0 && proveedorId) {
            opts += '<option value="" disabled>No hay productos disponibles para este proveedor</option>';
        } else if (!proveedorId) {
            opts += '<option value="" disabled>Primero selecciona un proveedor</option>';
        }
        
        return opts;
    }

    // Función para obtener la tasa de cambio actual
    function getTasaMoneda() {
        if (!monedaSelect) return 1;
        const selectedOption = monedaSelect.options[monedaSelect.selectedIndex];
        if (!selectedOption) return 1;
        const tasa = parseFloat(selectedOption.getAttribute('data-tasa'));
        return isNaN(tasa) || tasa <= 0 ? 1 : tasa;
    }

    // Función para actualizar el campo de tasa de conversión cuando cambia la moneda
    function actualizarTasaConversion() {
        const tasa = getTasaMoneda();
        const tasaInput = document.getElementById('tasa_conversion_usd');
        
        if (tasaInput) {
            tasaInput.value = tasa.toFixed(6);
        }
        
        // Actualizar información de tasa
        if (tasaInfo) {
            const monedaNombre = monedaSelect ? monedaSelect.options[monedaSelect.selectedIndex]?.text : '';
            tasaInfo.textContent = `Tasa actual: ${tasa.toFixed(4)} ${monedaNombre}`;
        }
        
        // Recalcular precio USD si ya hay un precio local
        const precioTotalLocal = document.getElementById('precio_total_local');
        if (precioTotalLocal && precioTotalLocal.value) {
            calcularPrecioUSD();
        }
    }

    // Función para calcular el precio total en USD basado en precio local y tasa
    function calcularPrecioUSD() {
        const precioTotalLocal = document.getElementById('precio_total_local');
        const precioTotalUSD = document.getElementById('precio_total_usd');
        const tasa = getTasaMoneda();
        
        if (precioTotalLocal && precioTotalUSD && precioTotalLocal.value) {
            const valorLocal = parseFloat(precioTotalLocal.value) || 0;
            const valorUSD = valorLocal / tasa;
            precioTotalUSD.value = valorUSD.toFixed(2);
        }
    }

    // Función para actualizar los precios (mantener compatibilidad con código existente)
    function actualizarPrecios() {
        actualizarTasaConversion();
        calcularPrecioUSD();
    }

    // Función para actualizar la disponibilidad de opciones en los selects
    function updateProductOptionsAvailability() {
        const allSelects = Array.from(productosContainer.querySelectorAll('.producto-select'));
        const selectedValues = allSelects.map(s => s.value).filter(v => v !== "");

        allSelects.forEach(select => {
            const currentVal = select.value;
            Array.from(select.options).forEach(option => {
                if (option.value === "") return; // Skip placeholder
                
                if (selectedValues.includes(option.value) && option.value !== currentVal) {
                    option.style.display = 'none';
                    option.disabled = true; 
                } else {
                    option.style.display = '';
                    option.disabled = false;
                }
            });
        });
    }

    function addProductRow(selectedId, qty) {
        // Prevent adding another empty row if the last row still has no product selected.
        const last = productosContainer.querySelector('.producto-row:last-child');
        if (last) {
            const lastSel = last.querySelector('.producto-select');
            if (lastSel && lastSel.value === '') {
                // Focus en el input de Select2 si está activo
                const select2Container = last.querySelector('.select2-container');
                if (select2Container) {
                    $(lastSel).select2('open');
                } else {
                    lastSel.focus();
                }
                return;
            }
        }

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

        const btnRemove = row.querySelector('.btnRemove');
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
                actualizarPrecios();
                updateProductOptionsAvailability();
            });
        }

        btnRemove.addEventListener('click', () => {
            // Destruir Select2 antes de eliminar el elemento
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $(select).select2('destroy');
            }
            row.remove();
            actualizarPrecios();
            updateProductOptionsAvailability();
        });
        
        if (!$.fn.select2) {
            // Fallback si Select2 no está disponible
            select.addEventListener('change', () => {
                actualizarPrecios();
                updateProductOptionsAvailability();
            });
        }
        
        cantidad.addEventListener('input', actualizarPrecios);
        
        updateProductOptionsAvailability();
    }

    // Initialize rows from previous failed submit (if any) or with one empty row
    document.addEventListener('DOMContentLoaded', function(){
        // Inicializar tasa de conversión PRIMERO si hay moneda pre-seleccionada
        if (monedaSelect && monedaSelect.value) {
            actualizarTasaConversion();
        }
        
        // Luego agregar filas de productos
        if (oldPosted && Array.isArray(oldPosted.productos) && oldPosted.productos.length > 0) {
            oldPosted.productos.forEach(function(it){
                const pid = it.producto_id ?? it.producto ?? '';
                const qty = it.cantidad ?? 1;
                addProductRow(pid, qty);
            });
        } else {
            // Also, if oldPosted contains simple legacy producto_id/cantidad_producto values
            if (oldPosted && (oldPosted.producto_id || oldPosted.cantidad_producto)) {
                addProductRow(oldPosted.producto_id, oldPosted.cantidad_producto);
            } else {
                addProductRow('', 1);
            }
        }
        actualizarPrecios();
        updateProductOptionsAvailability();
    });

    btnAdd.addEventListener('click', function(){ addProductRow('', 1); });
    
    // Recargar productos cuando cambie el proveedor
    if (proveedorSelect) {
        proveedorSelect.addEventListener('change', function() {
            // Actualizar todas las filas de productos existentes
            const allRows = productosContainer.querySelectorAll('.producto-row');
            allRows.forEach(row => {
                const select = row.querySelector('.producto-select');
                if (select) {
                    const currentValue = select.value;
                    // Regenerar opciones
                    select.innerHTML = makeProductOptions(currentValue);
                    
                    // Reinicializar Select2 si está disponible
                    if (typeof $ !== 'undefined' && $.fn.select2 && $(select).hasClass('select2-hidden-accessible')) {
                        $(select).select2('destroy');
                        $(select).select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Escribe para buscar un producto...',
                            allowClear: true,
                            width: '100%'
                        });
                    }
                }
            });
        });
    }
    
    // Recalcular cuando cambie la moneda
    if (monedaSelect) {
        monedaSelect.addEventListener('change', actualizarTasaConversion);
    }
    
    // Recalcular cuando cambie la moneda
    if (monedaSelect) {
        monedaSelect.addEventListener('change', actualizarTasaConversion);
    }
    
    // Recalcular cuando cambie el precio total local
    const precioTotalLocalInput = document.getElementById('precio_total_local');
    if (precioTotalLocalInput) {
        precioTotalLocalInput.addEventListener('input', calcularPrecioUSD);
    }
})();
// Expose previously submitted values to the following scripts
const OLD_POSTED = <?php echo json_encode($old_posted ?? null); ?>;

// Dependent selects: departamento -> municipio -> barrio
(function(){
    const deptSelect = document.getElementById('id_departamento');
    const paisSelect = document.getElementById('id_pais');
    // municipios and barrios data
    const municipios = <?php echo json_encode($municipiosAll); ?>;
    const barrios = <?php echo json_encode($barriosAll); ?>;

    // create municipio select
    const munWrapper = document.createElement('div');
    munWrapper.className = 'col-md-6 mb-3';
    munWrapper.innerHTML = `
        <label for="id_municipio" class="form-label">Municipio</label>
        <select class="form-select select2-searchable" id="id_municipio" name="id_municipio" data-placeholder="Buscar municipio...">
            <option value="" selected>Selecciona un municipio</option>
        </select>`;
    // insert before barrio placeholder (append to the row after departamento)
    deptSelect.parentElement.parentElement.insertBefore(munWrapper, deptSelect.parentElement.nextSibling);

    // create barrio select below municipio
    const barrioWrapper = document.createElement('div');
    barrioWrapper.className = 'col-md-6 mb-3';
    barrioWrapper.innerHTML = `
        <label for="id_barrio" class="form-label">Barrio</label>
        <select class="form-select select2-searchable" id="id_barrio" name="id_barrio" data-placeholder="Buscar barrio...">
            <option value="" selected>Selecciona un barrio</option>
        </select>`;
    munWrapper.parentElement.insertBefore(barrioWrapper, munWrapper.nextSibling);

    const munSelect = document.getElementById('id_municipio');
    const barrioSelect = document.getElementById('id_barrio');
    const initialMun = (OLD_POSTED && OLD_POSTED.id_municipio) ? OLD_POSTED.id_municipio : null;
    const initialBarrio = (OLD_POSTED && OLD_POSTED.id_barrio) ? OLD_POSTED.id_barrio : null;

    // Inicializar Select2 en los nuevos selects
    function initSelect2ForLocationSelects() {
        if (typeof $ !== 'undefined' && $.fn.select2) {
            // Inicializar Select2 si aún no está inicializado
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
                munSelect.appendChild(opt);
            }
        });
        // If a selectedMunId was provided, set it; otherwise use initialMun from oldPosted
        const sel = selectedMunId || initialMun;
        if (sel) {
            const opt = munSelect.querySelector('option[value="' + sel + '"]');
            if (opt) opt.selected = true;
        }
        
        // Reinicializar Select2
        initSelect2ForLocationSelects();
        
        populateBarrios(munSelect.value, initialBarrio);
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
                barrioSelect.appendChild(opt);
            }
        });
        const sb = selectedBarrioId || initialBarrio;
        if (sb) {
            const optb = barrioSelect.querySelector('option[value="' + sb + '"]');
            if (optb) optb.selected = true;
        }
        
        // Reinicializar Select2
        initSelect2ForLocationSelects();
    }

    // Events - usar eventos de Select2 si está disponible
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $(deptSelect).on('change.select2', function(){
            populateMunicipios(deptSelect.value);
        });
        $(munSelect).on('change.select2', function(){
            populateBarrios(munSelect.value);
        });
    } else {
        deptSelect.addEventListener('change', function(){
            const dep = deptSelect.value;
            populateMunicipios(dep);
        });
        munSelect.addEventListener('change', function(){
            populateBarrios(munSelect.value);
        });
    }

    // initialize on load
    document.addEventListener('DOMContentLoaded', function(){
        // run existing department filter first
        const event = new Event('change');
        deptSelect.dispatchEvent(event);
    });
})();
</script>

<script>
// Sync first product row into legacy hidden inputs for compatibility
(function(){
    const form = document.getElementById('formCrearPedido');
    if (!form) return;
    form.addEventListener('submit', function(){
        const firstRow = document.querySelector('#productosContainer .producto-row');
        const hidProd = document.getElementById('producto_id');
        const hidQty = document.getElementById('cantidad_producto');
        if (!hidProd || !hidQty) return;
        if (!firstRow) {
            hidProd.value = '';
            hidQty.value = '';
            return;
        }
        const sel = firstRow.querySelector('.producto-select');
        const qty = firstRow.querySelector('.producto-cantidad');
        hidProd.value = sel ? sel.value : '';
        hidQty.value = qty ? qty.value : '';
    });
})();
</script>

<script>
// Toggle combo pricing section
document.getElementById('es_combo').addEventListener('change', function() {
    const comboPricingSection = document.getElementById('combo-pricing-section');
    if (this.checked) {
        comboPricingSection.style.display = 'block';
    } else {
        comboPricingSection.style.display = 'none';
    }
});
</script>

        </div><!-- card-body -->
    </div><!-- card crear-pedido-card -->
</div><!-- container-fluid -->

<?php include("vista/includes/footer.php"); ?>
