<?php
include("vista/includes/header.php");

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/
// Obtener el ID del pedido desde la URL

$ruta = isset($_GET['enlace']) ? $_GET['enlace'] : null;

// Dividimos la URL en partes
$pedidoID = explode("/", $ruta);

// Instanciar el controlador
$pedidoController = new PedidosController();



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

// Obtener los datos actualizados del pedido
$pedido = $pedidoController->obtenerPedido($pedidoID);
$estados = $pedidoController->obtenerEstados();
$vendedores = $pedidoController->obtenerVendedores();


if (!$pedido) {
    echo "<div class='alert alert-danger'>Order not found.</div>";
    exit;
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
                    <input type="text" class="form-control" id="numero_orden" name="numero_orden" value="<?= htmlspecialchars($pedido['numero_orden']) ?>" readonly>
                </div>
                <div class="mb-3">
                    <label for="destinatario" class="form-label">Destinatario</label>
                    <input type="text" class="form-control" id="destinatario" name="destinatario" value="<?= htmlspecialchars($pedido['destinatario']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="telefono" class="form-label">Télefono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($pedido['telefono']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="pais" class="form-label">País</label>
                    <textarea class="form-control" id="pais" name="pais" rows="2" required><?= htmlspecialchars($pedido['pais']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="departamento" class="form-label">Departamento</label>
                    <textarea class="form-control" id="departamento" name="departamento" rows="2" required><?= htmlspecialchars($pedido['departamento']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="barrio" class="form-label">Barrio</label>
                    <textarea class="form-control" id="barrio" name="barrio" rows="2" required><?= htmlspecialchars($pedido['barrio']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="zona" class="form-label">Zona</label>
                    <textarea class="form-control" id="zona" name="zona" rows="2" required><?= htmlspecialchars($pedido['zona']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="direccion" class="form-label">Dirección</label>
                    <textarea class="form-control" id="direccion" name="direccion" rows="2" required><?= htmlspecialchars($pedido['direccion']) ?></textarea>
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label for="producto" class="form-label">Producto</label>
                    <input type="text" class="form-control" id="producto" name="producto" value="<?= htmlspecialchars($pedido['producto']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="cantidad" class="form-label">Cantidad</label>
                    <input type="number" class="form-control" id="cantidad" name="cantidad" value="<?= htmlspecialchars($pedido['cantidad'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="precio" class="form-label">Precio</label>
                    <input type="text" class="form-control" id="precio" name="precio" value="<?= htmlspecialchars($pedido['precio'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="municipio" class="form-label">Municipio</label>
                    <textarea class="form-control" id="municipio" name="municipio" rows="2"><?= htmlspecialchars($pedido['municipio'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="estado" class="form-label">Status</label>
                    <select class="form-control" id="estado" name="estado" required>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= $estado['id'] ?>" <?= $pedido['id_estado'] == $estado['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado['nombre_estado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="vendedor" class="form-label">Seller</label>
                    <select class="form-control" id="vendedor" name="vendedor" required>
                        <?php foreach ($vendedores as $vendedor): ?>
                            <option value="<?= $vendedor['id'] ?>" <?= $pedido['id_vendedor'] == $vendedor['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($vendedor['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="comentario" class="form-label">Comentario</label>
                    <textarea class="form-control" id="comentario" name="comentario" rows="2"><?= htmlspecialchars($pedido['comentario']) ?></textarea>
                </div>


                <!-- Mapa y Coordenadas -->
                <div class="row">
                    
                    <div class="col-md-6">
                        <label for="latitud" class="form-label">Latitud</label>
                        <input type="text" class="form-control" id="latitud" name="latitud" value="<?= htmlspecialchars($pedido['latitud']) ?>" required>
                        <div class="invalid-feedback">Please enter a valid latitude (decimal number).</div>
                    </div>
                    <div class="col-md-6">
                        <label for="longitud" class="form-label">Longitud</label>
                        <input type="text" class="form-control" id="longitud" name="longitud" value="<?= htmlspecialchars($pedido['longitud']) ?>" required>
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
        {id:'producto', fn: v => v.trim().length > 0, msg: 'Por favor, especifica el producto.'},
        {id:'cantidad', fn: v => v === '' || (Number.isInteger(Number(v)) && Number(v) >= 1), msg: 'La cantidad debe ser al menos 1 si se proporciona.'},
        {id:'precio', fn: v => v === '' || validarDecimalEd(v), msg: 'Precio inválido.'},
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
    // no-op: validation initialized from modular script
});
</script>

<script src="<?= RUTA_URL ?>js/pedidos-validation.js"></script>



<?php include("vista/includes/footer.php"); ?>