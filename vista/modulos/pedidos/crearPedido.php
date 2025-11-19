<?php include("vista/includes/header.php"); ?>

<?php
$pedidosController = new PedidosController();

try {
    $estados = $pedidosController->obtenerEstados();
} catch (Exception $e) {
    $estados = [];
}

try {
    // Listar usuarios con rol Repartidor para asignación
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

// Cargar países y departamentos para los selects de dirección
require_once __DIR__ . '/../../../modelo/pais.php';
require_once __DIR__ . '/../../../modelo/departamento.php';
require_once __DIR__ . '/../../../utils/session.php';
start_secure_session();

// If the previous non-AJAX submit failed, the router stored the payload in
// $_SESSION['old_pedido'] — grab it so we can prefill the form and product rows.
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
    // Listar todos los departamentos (se filtrarán client-side por país)
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

<div class="row">
    <div class="col-sm-12">
        <h3>Nuevo Pedido</h3>
    </div>
</div>

<div class="row mt-2 caja">
    <div class="col-sm-12">
        <div id="formErrors" class="alert alert-danger d-none" role="alert" tabindex="-1" style="display:block">
            <ul id="formErrorsList" class="mb-0"></ul>
        </div>

        <form id="formCrearPedido" action="<?= RUTA_URL ?>pedidos/guardarPedido" method="POST">
            <div class="row">
                <!-- Primera Columna -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="numero_orden" class="form-label">Número de Orden</label>
                        <input type="number" class="form-control" id="numero_orden" name="numero_orden" min="1" required value="<?= htmlspecialchars($old_posted['numero_orden'] ?? '') ?>">
                        <div class="invalid-feedback">Por favor, ingresa un número de orden válido.</div>
                    </div>
                    <div class="mb-3">
                        <label for="destinatario" class="form-label">Destinatario</label>
                        <!-- Se eliminó el patrón estricto para permitir acentos y caracteres internacionales -->
                        <input type="text" class="form-control" id="destinatario" name="destinatario" required value="<?= htmlspecialchars($old_posted['destinatario'] ?? '') ?>">
                        <div class="invalid-feedback">Por favor, ingresa un nombre válido.</div>
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" pattern="[0-9]{8,15}" required value="<?= htmlspecialchars($old_posted['telefono'] ?? '') ?>">
                        <div class="invalid-feedback">Por favor, ingresa un número de teléfono válido (solo números, de 8 a 15 dígitos).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Productos</label>
                        <div id="productosContainer">
                            <!-- Product rows will be injected here -->
                        </div>
                        <button type="button" id="btnAddProducto" class="btn btn-sm btn-outline-primary mt-2">Agregar producto</button>
                        <div class="form-text" id="productoAyuda">Puedes agregar múltiples productos. Si JS está deshabilitado, use el campo Producto y Cantidad individuales.</div>
                    </div>
                    <div class="mb-3">
                        <label for="precio_local" class="form-label">Precio (moneda seleccionada)</label>
                        <input type="number" step="0.01" class="form-control" id="precio_local" name="precio_local" min="0" value="<?= htmlspecialchars($old_posted['precio_local'] ?? '') ?>">
                        <div class="form-text">Ingresa el valor en la moneda seleccionada para calcular el equivalente en USD.</div>
                    </div>
                    <div class="mb-3">
                        <label for="precio_usd" class="form-label">Precio en USD</label>
                        <input type="number" step="0.01" class="form-control" id="precio_usd" name="precio_usd" readonly value="<?= htmlspecialchars($old_posted['precio_usd'] ?? '') ?>">
                        <div class="form-text">Se calcula automáticamente con la tasa de cambio registrada.</div>
                    </div>
                </div>

                <!-- Segunda Columna -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado" required>
                            <option value="" disabled selected>Selecciona un estado</option>
                            <?php foreach ($estados as $estado): ?>
                                <option value="<?= $estado['id']; ?>" <?= (isset($old_posted['estado']) && (int)$old_posted['estado'] === (int)$estado['id']) ? 'selected' : '' ?>><?= htmlspecialchars($estado['nombre_estado']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Por favor, selecciona un estado.</div>
                    </div>
                    <div class="mb-3">
                        <label for="vendedor" class="form-label">Usuario Asignado</label>
                        <select class="form-select" id="vendedor" name="vendedor" required>
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
                        <div class="invalid-feedback">Por favor, selecciona un usuario.</div>
                    </div>
                    <div class="mb-3">
                        <label for="proveedor" class="form-label">Proveedor</label>
                        <select class="form-select" id="proveedor" name="proveedor" required>
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
                    </div>
                    <div class="mb-3">
                        <label for="moneda" class="form-label">Moneda</label>
                        <select class="form-select" id="moneda" name="moneda" required>
                            <option value="" disabled selected>Selecciona una moneda</option>
                            <?php foreach ($monedas as $moneda): ?>
                                <option value="<?= $moneda['id']; ?>" data-tasa="<?= htmlspecialchars($moneda['tasa_usd']); ?>" <?= (isset($old_posted['moneda']) && (int)$old_posted['moneda'] === (int)$moneda['id']) ? 'selected' : '' ?> >
                                    <?= htmlspecialchars($moneda['nombre']); ?> (<?= htmlspecialchars($moneda['codigo']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Por favor, selecciona una moneda.</div>
                    </div>
                    <div class="mb-3">
                        <label for="comentario" class="form-label">Comentario</label>
                        <textarea class="form-control" id="comentario" name="comentario" maxlength="500"><?= htmlspecialchars($old_posted['comentario'] ?? '') ?></textarea>
                        <div class="form-text">Máximo 500 caracteres.</div>
                    </div>
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <textarea class="form-control" id="direccion" name="direccion" required><?= htmlspecialchars($old_posted['direccion'] ?? '') ?></textarea>
                        <div class="invalid-feedback">Por favor, proporciona una dirección válida.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="id_pais" class="form-label">País</label>
                            <select class="form-select" id="id_pais" name="id_pais">
                                <option value="" selected>Selecciona un país</option>
                                <?php foreach ($paises as $p): ?>
                                            <option value="<?= (int)$p['id'] ?>" <?= (isset($old_posted['id_pais']) && (int)$old_posted['id_pais'] === (int)$p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="id_departamento" class="form-label">Departamento</label>
                            <select class="form-select" id="id_departamento" name="id_departamento">
                                <option value="" selected>Selecciona un departamento</option>
                                <?php foreach ($departamentosAll as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>" data-id-pais="<?= (int)($d['id_pais'] ?? 0) ?>" <?= (isset($old_posted['id_departamento']) && (int)$old_posted['id_departamento'] === (int)$d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Campos de Coordenadas -->
            <div class="row">
                    <div class="col-md-6">
                        <label for="latitud" class="form-label">Latitud</label>
                        <input type="text" class="form-control" id="latitud" name="latitud" pattern="-?\d+(\.\d+)?" required value="<?= htmlspecialchars($old_posted['latitud'] ?? '') ?>">
                    <div class="invalid-feedback">Por favor, ingresa una latitud válida (número decimal).</div>
                </div>
                <div class="col-md-6">
                    <label for="longitud" class="form-label">Longitud</label>
                    <input type="text" class="form-control" id="longitud" name="longitud" pattern="-?\d+(\.\d+)?" required value="<?= htmlspecialchars($old_posted['longitud'] ?? '') ?>">
                    <div class="invalid-feedback">Por favor, ingresa una longitud válida (número decimal).</div>
                </div>
            </div>

            <!-- Mapa -->
            <div class="mb-3 mt-3">
                <div id="map" style="height: 400px; width: 100%;"></div>
            </div>

            <!-- Botones -->
            <div class="text-end">
                <!-- Legacy hidden fields for non-JS fallback compatibility (kept updated by JS) -->
                <input type="hidden" id="producto_id" name="producto_id" value="">
                <input type="hidden" id="cantidad_producto" name="cantidad_producto" value="">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Guardar</button>
                <a href="<?= RUTA_URL ?>pedidos" class="btn btn-secondary"><i class="bi bi-arrow-left-circle"></i> Cancelar</a>
            </div>
        </form>
    </div>
</div>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= API_MAP ?>&callback=initMap" async defer></script>
<script src="<?= RUTA_URL ?>js/pedidos-validation.js"></script>
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
// Dynamic products rows for crearPedido
(function(){
    const productos = <?php echo json_encode(array_map(function($p){
        return [
            'id' => (int)$p['id'],
            'nombre' => $p['nombre'],
            'stock' => isset($p['stock_total']) ? (int)$p['stock_total'] : 0,
            'precio_usd' => isset($p['precio_usd']) ? $p['precio_usd'] : null
        ];
    }, $productos)); ?>;
    const oldPosted = <?php echo json_encode($old_posted ?? null); ?>;

    const productosContainer = document.getElementById('productosContainer');
    const btnAdd = document.getElementById('btnAddProducto');

    function makeProductOptions(selectedId) {
        let opts = '<option value="">Selecciona un producto</option>';
        productos.forEach(p => {
            const sel = (selectedId && selectedId == p.id) ? ' selected' : '';
            opts += `<option value="${p.id}" data-stock="${p.stock}" data-precio-usd="${p.precio_usd ?? ''}"${sel}>${escapeHtml(p.nombre)}${p.stock !== null ? ' — Stock: ' + p.stock : ''}</option>`;
        });
        return opts;
    }

    function escapeHtml(s) {
        if (!s) return '';
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function addProductRow(selectedId, qty) {
        // Prevent adding another empty row if the last row still has no product selected.
        const last = productosContainer.querySelector('.producto-row:last-child');
        if (last) {
            const lastSel = last.querySelector('.producto-select');
            const lastQty = last.querySelector('.producto-cantidad');
            if (lastSel && lastSel.value === '') {
                // focus the select to nudge the user to fill it instead of creating more empty rows
                lastSel.focus();
                return;
            }
        }

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

        const btnRemove = row.querySelector('.btnRemove');
        btnRemove.addEventListener('click', () => row.remove());
    }

    // Initialize rows from previous failed submit (if any) or with one empty row
    document.addEventListener('DOMContentLoaded', function(){
        if (oldPosted && Array.isArray(oldPosted.productos) && oldPosted.productos.length > 0) {
            oldPosted.productos.forEach(function(it){
                const pid = it.producto_id ?? it.producto ?? '';
                const qty = it.cantidad ?? 1;
                addProductRow(pid, qty);
            });
        } else {
            addProductRow('', 1);
        }
        // Also, if oldPosted contains simple legacy producto_id/cantidad_producto values,
        // ensure they are represented when there were no productos[] array.
        if ((!oldPosted || !oldPosted.productos || oldPosted.productos.length === 0) && oldPosted && (oldPosted.producto_id || oldPosted.cantidad_producto)) {
            // replace first row with legacy values
            const first = document.querySelector('#productosContainer .producto-row');
            if (first) {
                const sel = first.querySelector('.producto-select');
                const qty = first.querySelector('.producto-cantidad');
                if (sel && oldPosted.producto_id) sel.value = oldPosted.producto_id;
                if (qty && oldPosted.cantidad_producto) qty.value = oldPosted.cantidad_producto;
            }
        }
    });

    btnAdd.addEventListener('click', function(){ addProductRow('', 1); });
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
        <select class="form-select" id="id_municipio" name="id_municipio">
            <option value="" selected>Selecciona un municipio</option>
        </select>`;
    // insert before barrio placeholder (append to the row after departamento)
    deptSelect.parentElement.parentElement.insertBefore(munWrapper, deptSelect.parentElement.nextSibling);

    // create barrio select below municipio
    const barrioWrapper = document.createElement('div');
    barrioWrapper.className = 'col-md-6 mb-3';
    barrioWrapper.innerHTML = `
        <label for="id_barrio" class="form-label">Barrio</label>
        <select class="form-select" id="id_barrio" name="id_barrio">
            <option value="" selected>Selecciona un barrio</option>
        </select>`;
    munWrapper.parentElement.insertBefore(barrioWrapper, munWrapper.nextSibling);

    const munSelect = document.getElementById('id_municipio');
    const barrioSelect = document.getElementById('id_barrio');
    const initialMun = (OLD_POSTED && OLD_POSTED.id_municipio) ? OLD_POSTED.id_municipio : null;
    const initialBarrio = (OLD_POSTED && OLD_POSTED.id_barrio) ? OLD_POSTED.id_barrio : null;

    function populateMunicipios(depId, selectedMunId) {
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
        populateBarrios(munSelect.value, initialBarrio);
    }

    function populateBarrios(munId, selectedBarrioId) {
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
    }

    // Events
    deptSelect.addEventListener('change', function(){
        const dep = deptSelect.value;
        populateMunicipios(dep);
    });
    munSelect.addEventListener('change', function(){
        populateBarrios(munSelect.value);
    });

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

<?php include("vista/includes/footer.php"); ?>


