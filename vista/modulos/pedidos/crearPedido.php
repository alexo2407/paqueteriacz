<?php include("vista/includes/header.php"); ?>

<?php
$pedidosController = new PedidosController();

try {
    $estados = $pedidosController->obtenerEstados();
} catch (Exception $e) {
    $estados = [];
}

try {
    $vendedores = $pedidosController->obtenerVendedores();
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
                        <input type="number" class="form-control" id="numero_orden" name="numero_orden" min="1" required>
                        <div class="invalid-feedback">Por favor, ingresa un número de orden válido.</div>
                    </div>
                    <div class="mb-3">
                        <label for="destinatario" class="form-label">Destinatario</label>
                        <!-- Se eliminó el patrón estricto para permitir acentos y caracteres internacionales -->
                        <input type="text" class="form-control" id="destinatario" name="destinatario" required>
                        <div class="invalid-feedback">Por favor, ingresa un nombre válido.</div>
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" pattern="[0-9]{8,15}" required>
                        <div class="invalid-feedback">Por favor, ingresa un número de teléfono válido (solo números, de 8 a 15 dígitos).</div>
                    </div>
                    <div class="mb-3">
                        <label for="producto_id" class="form-label">Producto</label>
                        <select class="form-select" id="producto_id" name="producto_id" required>
                            <option value="" selected>Selecciona un producto</option>
                            <?php foreach ($productos as $producto): ?>
                                <option value="<?= (int) $producto['id']; ?>"
                                        data-stock="<?= (int) ($producto['stock_total'] ?? 0); ?>"
                                        data-precio-usd="<?= $producto['precio_usd'] !== null ? htmlspecialchars($producto['precio_usd']) : ''; ?>">
                                    <?= htmlspecialchars($producto['nombre']); ?><?= isset($producto['stock_total']) ? ' — Stock: ' . (int) $producto['stock_total'] : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" id="productoAyuda">Selecciona un producto para ver el stock disponible.</div>
                        <div class="invalid-feedback">Por favor, selecciona un producto.</div>
                    </div>
                    <div class="mb-3">
                        <label for="cantidad_producto" class="form-label">Cantidad</label>
                        <input type="number" class="form-control" id="cantidad_producto" name="cantidad_producto" min="1" required>
                        <div class="invalid-feedback">La cantidad debe ser al menos 1.</div>
                    </div>
                    <div class="mb-3">
                        <label for="precio_local" class="form-label">Precio (moneda seleccionada)</label>
                        <input type="number" step="0.01" class="form-control" id="precio_local" name="precio_local" min="0">
                        <div class="form-text">Ingresa el valor en la moneda seleccionada para calcular el equivalente en USD.</div>
                    </div>
                    <div class="mb-3">
                        <label for="precio_usd" class="form-label">Precio en USD</label>
                        <input type="number" step="0.01" class="form-control" id="precio_usd" name="precio_usd" readonly>
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
                                <option value="<?= $estado['id']; ?>"><?= htmlspecialchars($estado['nombre_estado']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Por favor, selecciona un estado.</div>
                    </div>
                    <div class="mb-3">
                        <label for="vendedor" class="form-label">Usuario Asignado</label>
                        <select class="form-select" id="vendedor" name="vendedor" required>
                            <option value="" disabled selected>Selecciona un usuario</option>
                            <?php foreach ($vendedores as $vendedor): ?>
                                <option value="<?= $vendedor['id']; ?>"><?= htmlspecialchars($vendedor['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Por favor, selecciona un usuario.</div>
                    </div>
                    <div class="mb-3">
                        <label for="proveedor" class="form-label">Proveedor</label>
                        <select class="form-select" id="proveedor" name="proveedor" required>
                            <option value="" disabled selected>Selecciona un proveedor</option>
                            <?php foreach ($proveedores as $proveedor): ?>
                                <option value="<?= $proveedor['id']; ?>"><?= htmlspecialchars($proveedor['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Por favor, selecciona un proveedor.</div>
                    </div>
                    <div class="mb-3">
                        <label for="moneda" class="form-label">Moneda</label>
                        <select class="form-select" id="moneda" name="moneda" required>
                            <option value="" disabled selected>Selecciona una moneda</option>
                            <?php foreach ($monedas as $moneda): ?>
                                <option value="<?= $moneda['id']; ?>" data-tasa="<?= htmlspecialchars($moneda['tasa_usd']); ?>">
                                    <?= htmlspecialchars($moneda['nombre']); ?> (<?= htmlspecialchars($moneda['codigo']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Por favor, selecciona una moneda.</div>
                    </div>
                    <div class="mb-3">
                        <label for="comentario" class="form-label">Comentario</label>
                        <textarea class="form-control" id="comentario" name="comentario" maxlength="500"></textarea>
                        <div class="form-text">Máximo 500 caracteres.</div>
                    </div>
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <textarea class="form-control" id="direccion" name="direccion" required></textarea>
                        <div class="invalid-feedback">Por favor, proporciona una dirección válida.</div>
                    </div>
                </div>
            </div>

            <!-- Campos de Coordenadas -->
            <div class="row">
                <div class="col-md-6">
                    <label for="latitud" class="form-label">Latitud</label>
                    <input type="text" class="form-control" id="latitud" name="latitud" pattern="-?\d+(\.\d+)?" required>
                    <div class="invalid-feedback">Por favor, ingresa una latitud válida (número decimal).</div>
                </div>
                <div class="col-md-6">
                    <label for="longitud" class="form-label">Longitud</label>
                    <input type="text" class="form-control" id="longitud" name="longitud" pattern="-?\d+(\.\d+)?" required>
                    <div class="invalid-feedback">Por favor, ingresa una longitud válida (número decimal).</div>
                </div>
            </div>

            <!-- Mapa -->
            <div class="mb-3 mt-3">
                <div id="map" style="height: 400px; width: 100%;"></div>
            </div>

            <!-- Botones -->
            <div class="text-end">
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



<?php include("vista/includes/footer.php"); ?>


