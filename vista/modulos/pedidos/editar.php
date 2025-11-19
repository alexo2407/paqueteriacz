<?php
include("vista/includes/header.php");

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/
// El ID del pedido se pasa desde el controlador
$id_pedido = $parametros[0] ?? null;

if (!$id_pedido) {
    echo "<div class='alert alert-danger'>No order ID provided.</div>";
    exit;
}

// Instanciar el controlador
$pedidoController = new PedidosController();

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

// Cargar países, departamentos, municipios y barrios para selects de dirección
require_once __DIR__ . '/../../../modelo/pais.php';
require_once __DIR__ . '/../../../modelo/departamento.php';
require_once __DIR__ . '/../../../modelo/municipio.php';
require_once __DIR__ . '/../../../modelo/barrio.php';
try { $paises = PaisModel::listar(); } catch (Exception $e) { $paises = []; }
try { $departamentosAll = DepartamentoModel::listarPorPais(null); } catch (Exception $e) { $departamentosAll = []; }
try { $municipiosAll = MunicipioModel::listarPorDepartamento(null); } catch (Exception $e) { $municipiosAll = []; }
try { $barriosAll = BarrioModel::listarPorMunicipio(null); } catch (Exception $e) { $barriosAll = []; }



// Si el formulario fue enviado, procesa la actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $resultado = $pedidoController->guardarEdicion($_POST);

    // Mensajes de éxito o error
    if ($resultado['success']) {
        $mensaje = "<div class='alert alert-success'>Order updated successfully.</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error: " . htmlspecialchars($resultado['message']) . "</div>";
    }
}

// Obtener los datos del pedido
$pedido = $pedidoController->obtenerPedido($id_pedido);

if (!$pedido) {
    echo "<div class='alert alert-danger'>Order not found.</div>";
    exit;
}

// If the previous non-AJAX edit submit failed, repopulate fields from session
require_once __DIR__ . '/../../../utils/session.php'; start_secure_session();
$old_edit = $_SESSION['old_pedido_edit_' . $id_pedido] ?? null;
if (isset($_SESSION['old_pedido_edit_' . $id_pedido])) unset($_SESSION['old_pedido_edit_' . $id_pedido]);
if ($old_edit) {
    // override scalar values present in old edit
    $fieldsToCopy = ['numero_orden','destinatario','telefono','direccion','comentario','latitud','longitud','precio_local','precio_usd','id_pais','id_departamento','id_municipio','id_barrio','proveedor','moneda','vendedor','estado'];
    foreach ($fieldsToCopy as $f) {
        if (isset($old_edit[$f])) $pedido[$f] = $old_edit[$f];
    }
    // override products array if provided
    if (isset($old_edit['productos']) && is_array($old_edit['productos'])) {
        $pedido['productos'] = $old_edit['productos'];
    }
}

// Si no tiene proveedor o moneda, asignar el primero por defecto para que se seleccione
if (empty($pedido['id_proveedor']) && !empty($proveedores)) {
    $pedido['id_proveedor'] = $proveedores[0]['id'];
}
if (empty($pedido['id_moneda']) && !empty($monedas)) {
    $pedido['id_moneda'] = $monedas[0]['id'];
}
?>
<div class="container mt-4">
    <h2>Editar Orden de compra</h2>

    <?= $mensaje ?? '' ?>

    <div id="formErrors" class="alert alert-danger d-none" role="alert" tabindex="-1" style="display:block">
        <ul id="formErrorsList" class="mb-0"></ul>
    </div>

    <form id="formEditarPedido" method="POST" action="">
        <input type="hidden" name="id_pedido" value="<?= htmlspecialchars($pedido['id']) ?>">

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="numero_orden" class="form-label">Número de Orden</label>
                    <input type="number" class="form-control" id="numero_orden" name="numero_orden" value="<?= htmlspecialchars($pedido['numero_orden']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="destinatario" class="form-label">Destinatario</label>
                    <input type="text" class="form-control" id="destinatario" name="destinatario" value="<?= htmlspecialchars($pedido['destinatario']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="tel" class="form-control" id="telefono" name="telefono" pattern="\d{8,15}" value="<?= htmlspecialchars($pedido['telefono']) ?>" required>
                    <div class="invalid-feedback">Teléfono inválido (8-15 dígitos).</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Productos</label>
                    <div id="productosContainer">
                        <!-- product rows will be injected here -->
                    </div>
                    <button type="button" id="btnAddProducto" class="btn btn-sm btn-outline-primary mt-2">Agregar producto</button>
                </div>
                <div class="mb-3">
                    <label for="precio_local" class="form-label">Precio Local</label>
                    <input type="number" class="form-control" id="precio_local" name="precio_local" step="0.01" min="0" value="<?= htmlspecialchars($pedido['precio_local'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="precio_usd" class="form-label">Precio USD</label>
                    <input type="number" class="form-control" id="precio_usd" name="precio_usd" step="0.01" readonly value="<?= htmlspecialchars($pedido['precio_usd'] ?? '') ?>">
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-control" id="estado" name="estado" required>
                        <option value="">Selecciona un estado</option>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= $estado['id'] ?>" <?= $pedido['id_estado'] == $estado['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado['nombre_estado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="vendedor" class="form-label">Usuario Asignado</label>
                    <select class="form-control" id="vendedor" name="vendedor" required>
                        <option value="">Selecciona un usuario (Repartidor)</option>
                        <?php foreach ($vendedores as $vendedor): ?>
                            <option value="<?= $vendedor['id'] ?>" <?= $pedido['id_vendedor'] == $vendedor['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($vendedor['nombre']) ?><?= isset($vendedor['email']) && $vendedor['email'] ? ' — ' . htmlspecialchars($vendedor['email']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($vendedores)): ?>
                        <div class="form-text text-warning">No hay usuarios con rol Repartidor activos.</div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label for="proveedor" class="form-label">Proveedor</label>
                    <select class="form-control" id="proveedor" name="proveedor" required>
                        <option value="">Selecciona un proveedor</option>
                        <?php foreach ($proveedores as $proveedor): ?>
                            <option value="<?= $proveedor['id'] ?>" <?= ((int)$pedido['id_proveedor'] === (int)$proveedor['id']) ? 'selected' : '' ?> >
                                <?= htmlspecialchars($proveedor['nombre']) ?><?= isset($proveedor['email']) && $proveedor['email'] ? ' — ' . htmlspecialchars($proveedor['email']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($proveedores)): ?>
                        <div class="form-text text-warning">No hay usuarios con rol Proveedor activos.</div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label for="moneda" class="form-label">Moneda</label>
                    <select class="form-control" id="moneda" name="moneda" required>
                        <option value="">Selecciona una moneda</option>
                        <?php foreach ($monedas as $moneda): ?>
                            <option value="<?= $moneda['id'] ?>"
                                    data-tasa="<?= htmlspecialchars($moneda['tasa_usd']) ?>"
                                    <?= ((int)$pedido['id_moneda'] === (int)$moneda['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($moneda['nombre']) ?> (Tasa: <?= htmlspecialchars($moneda['tasa_usd']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="id_pais" class="form-label">País</label>
                        <select class="form-select" id="id_pais" name="id_pais">
                            <option value="" selected>Selecciona un país</option>
                            <?php foreach ($paises as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= (!empty($pedido['id_pais']) && (int)$pedido['id_pais'] === (int)$p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="id_departamento" class="form-label">Departamento</label>
                        <select class="form-select" id="id_departamento" name="id_departamento">
                            <option value="" selected>Selecciona un departamento</option>
                            <?php foreach ($departamentosAll as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" data-id-pais="<?= (int)($d['id_pais'] ?? 0) ?>" <?= (!empty($pedido['id_departamento']) && (int)$pedido['id_departamento'] === (int)$d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="comentario" class="form-label">Comentario</label>
                    <textarea class="form-control" id="comentario" name="comentario" maxlength="500" rows="3"><?= htmlspecialchars($pedido['comentario']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="direccion" class="form-label">Dirección</label>
                    <textarea class="form-control" id="direccion" name="direccion" rows="3" required><?= htmlspecialchars($pedido['direccion']) ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label for="latitud" class="form-label">Latitud</label>
                        <input type="text" class="form-control" id="latitud" name="latitud" pattern="-?\d{1,3}\.\d+" value="<?= htmlspecialchars($pedido['latitud']) ?>" required>
                        <div class="invalid-feedback">Please enter a valid latitude (decimal number).</div>
                    </div>
                    <div class="col-md-6">
                        <label for="longitud" class="form-label">Longitud</label>
                        <input type="text" class="form-control" id="longitud" name="longitud" pattern="-?\d{1,3}\.\d+" value="<?= htmlspecialchars($pedido['longitud']) ?>" required>
                        <div class="invalid-feedback">Please enter a valid longitude (decimal number).</div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label for="map" class="form-label">Ubicación</label>
                        <div id="map" style="width: 100%; height: 400px; border: 1px solid #ccc;"></div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-3">Guardar Cambios</button>
        <a href="<?= RUTA_URL ?>pedidos/listar" class="btn btn-secondary mt-3">Cancelar</a>
    </form>
</div>
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
            form.addEventListener('submit', function() {
                // Remove empty product rows so the server receives only valid items
                const rows = document.querySelectorAll('#productosContainer .producto-row');
                rows.forEach(row => {
                    const sel = row.querySelector('.producto-select');
                    const qty = row.querySelector('.producto-cantidad');
                    if (!sel || sel.value === '' || !qty || qty.value === '' || parseInt(qty.value) < 1) {
                        row.remove();
                    }
                });

                const firstRow = document.querySelector('#productosContainer .producto-row');
                const hidProd = document.getElementById('producto_id');
                const hidQty = document.getElementById('cantidad_producto');
                if (!hidProd || !hidQty) return;
                if (!firstRow) { hidProd.value = ''; hidQty.value = ''; return; }
                const sel = firstRow.querySelector('.producto-select');
                const qty = firstRow.querySelector('.producto-cantidad');
                hidProd.value = sel ? sel.value : '';
                hidQty.value = qty ? qty.value : '';
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

// Real-time listeners
document.addEventListener('DOMContentLoaded', function() {
    // Set precio_usd if empty
    const precioUsdInput = document.getElementById('precio_usd');
    if (precioUsdInput && precioUsdInput.value === '') {
        const productoSelect = document.getElementById('producto_id');
        if (productoSelect) {
            const selectedOption = productoSelect.options[productoSelect.selectedIndex];
            if (selectedOption && selectedOption.dataset.precioUsd) {
                precioUsdInput.value = selectedOption.dataset.precioUsd;
            }
        }
    }

    // Set precio_local if empty, calculate from precio_usd / tasa
    const precioLocalInput = document.getElementById('precio_local');
    if (precioLocalInput && precioLocalInput.value === '') {
        const precioUsdValue = parseFloat(precioUsdInput.value);
        if (!isNaN(precioUsdValue)) {
            const monedaSelect = document.getElementById('moneda');
            if (monedaSelect) {
                const selectedOption = monedaSelect.options[monedaSelect.selectedIndex];
                if (selectedOption && selectedOption.dataset.tasa) {
                    const tasa = parseFloat(selectedOption.dataset.tasa);
                    if (tasa > 0) {
                        precioLocalInput.value = (precioUsdValue / tasa).toFixed(2);
                    }
                }
            }
        }
    }
    // no-op: validation initialized from modular script
});
</script>

<script src="<?= RUTA_URL ?>js/pedidos-validation.js"></script>

<script>
// Dynamic products rows for editar.php
(function(){
    const productos = <?php echo json_encode(array_map(function($p){
        return [ 'id' => (int)$p['id'], 'nombre' => $p['nombre'], 'stock' => isset($p['stock_total']) ? (int)$p['stock_total'] : 0, 'precio_usd' => isset($p['precio_usd']) ? $p['precio_usd'] : null ];
    }, $productos)); ?>;

    const existingItems = <?php echo json_encode($pedido['productos'] ?? []); ?>;
    const productosContainer = document.getElementById('productosContainer');
    const btnAdd = document.getElementById('btnAddProducto');

    function escapeHtml(s) { if (!s) return ''; return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    function makeProductOptions(selectedId) {
        let opts = '<option value="">Selecciona un producto</option>';
        productos.forEach(p => {
            const sel = (selectedId && parseInt(selectedId) === parseInt(p.id)) ? ' selected' : '';
            opts += `<option value="${p.id}" data-stock="${p.stock}" data-precio-usd="${p.precio_usd ?? ''}"${sel}>${escapeHtml(p.nombre)}${p.stock !== null ? ' — Stock: ' + p.stock : ''}</option>`;
        });
        return opts;
    }

    function addProductRow(selectedId, qty) {
        const row = document.createElement('div');
        row.className = 'input-group mb-2 producto-row';
        row.innerHTML = `
            <select name="productos[][producto_id]" class="form-select producto-select" required>
                ${makeProductOptions(selectedId)}
            </select>
            <input type="number" name="productos[][cantidad]" class="form-control producto-cantidad" min="1" value="${qty ? qty : 1}" required>
            <button type="button" class="btn btn-outline-danger btnRemove">Eliminar</button>
        `;
        productosContainer.appendChild(row);
        row.querySelector('.btnRemove').addEventListener('click', () => row.remove());
    }

    // initialize rows from existingItems, or create one if empty
    document.addEventListener('DOMContentLoaded', function(){
        if (existingItems && existingItems.length > 0) {
            existingItems.forEach(it => {
                addProductRow(it.id_producto ?? it.id_producto ?? '', it.cantidad ?? 1);
            });
        } else {
            addProductRow('', 1);
        }
    });
    btnAdd.addEventListener('click', function(){ addProductRow('', 1); });
})();

// Dependent selects for editar: departamento -> municipio -> barrio
(function(){
    const deptSelect = document.getElementById('id_departamento');
    const paisSelect = document.getElementById('id_pais');
    const municipios = <?php echo json_encode($municipiosAll); ?>;
    const barrios = <?php echo json_encode($barriosAll); ?>;

    // create municipio and barrio selects if not present
    let munSelect = document.getElementById('id_municipio');
    let barrioSelect = document.getElementById('id_barrio');
    if (!munSelect) {
        const munWrapper = document.createElement('div');
        munWrapper.className = 'col-md-6 mb-3';
        munWrapper.innerHTML = `\n            <label for="id_municipio" class="form-label">Municipio</label>\n            <select class="form-select" id="id_municipio" name="id_municipio">\n                <option value="" selected>Selecciona un municipio</option>\n            </select>`;
        deptSelect.parentElement.parentElement.insertBefore(munWrapper, deptSelect.parentElement.nextSibling);
        munSelect = document.getElementById('id_municipio');
    }
    if (!barrioSelect) {
        const barrioWrapper = document.createElement('div');
        barrioWrapper.className = 'col-md-6 mb-3';
        barrioWrapper.innerHTML = `\n            <label for="id_barrio" class="form-label">Barrio</label>\n            <select class="form-select" id="id_barrio" name="id_barrio">\n                <option value="" selected>Selecciona un barrio</option>\n            </select>`;
        munSelect.parentElement.insertBefore(barrioWrapper, munSelect.nextSibling);
        barrioSelect = document.getElementById('id_barrio');
    }

    function populateMunicipios(depId, selectedMunId) {
        munSelect.innerHTML = '<option value="" selected>Selecciona un municipio</option>';
        municipios.forEach(m => {
            if (!depId || depId === '' || parseInt(m.id_departamento) === parseInt(depId)) {
                const opt = document.createElement('option');
                opt.value = m.id; opt.textContent = m.nombre; opt.setAttribute('data-id-departamento', m.id_departamento); if (selectedMunId && parseInt(selectedMunId) === parseInt(m.id)) opt.selected = true;
                munSelect.appendChild(opt);
            }
        });
        populateBarrios(munSelect.value, <?= json_encode($pedido['id_barrio'] ?? '') ?>);
    }

    function populateBarrios(munId, selectedBarrioId) {
        barrioSelect.innerHTML = '<option value="" selected>Selecciona un barrio</option>';
        barrios.forEach(b => {
            if (!munId || munId === '' || parseInt(b.id_municipio) === parseInt(munId)) {
                const opt = document.createElement('option'); opt.value = b.id; opt.textContent = b.nombre; opt.setAttribute('data-id-municipio', b.id_municipio); if (selectedBarrioId && parseInt(selectedBarrioId) === parseInt(b.id)) opt.selected = true; barrioSelect.appendChild(opt);
            }
        });
    }

    deptSelect.addEventListener('change', function(){
        const dep = deptSelect.value;
        populateMunicipios(dep, <?= json_encode($pedido['id_municipio'] ?? '') ?>);
    });
    munSelect.addEventListener('change', function(){ populateBarrios(munSelect.value); });

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




<?php include("vista/includes/footer.php"); ?>