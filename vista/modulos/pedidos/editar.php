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
                    <label for="producto_id" class="form-label">Producto</label>
                    <select class="form-control" id="producto_id" name="producto_id" required>
                        <option value="">Selecciona un producto</option>
                        <?php foreach ($productos as $producto): ?>
                            <option value="<?= $producto['id'] ?>"
                                    data-stock="<?= htmlspecialchars($producto['stock_total']) ?>"
                                    data-precio-usd="<?= htmlspecialchars($producto['precio_usd']) ?>"
                                    <?= (!empty($pedido['productos'][0]['id_producto']) && (int)$pedido['productos'][0]['id_producto'] === (int)$producto['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($producto['nombre']) ?> (Stock: <?= htmlspecialchars($producto['stock_total']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="cantidad_producto" class="form-label">Cantidad</label>
                    <input type="number" class="form-control" id="cantidad_producto" name="cantidad_producto" min="1" value="<?= htmlspecialchars($pedido['productos'][0]['cantidad'] ?? '') ?>" required>
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

        // Manejar clics en el mapa para mover el marcador
        map.addListener("click", (event) => {
            const clickedPosition = event.latLng;
            marker.setPosition(clickedPosition);
            document.getElementById("latitud").value = clickedPosition.lat();
            document.getElementById("longitud").value = clickedPosition.lng();
        });

        // Actualizar el mapa cuando se editen las coordenadas manualmente
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



<?php include("vista/includes/footer.php"); ?>