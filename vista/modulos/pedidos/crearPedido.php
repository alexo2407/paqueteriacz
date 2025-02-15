<?php include("vista/includes/header.php"); ?>


<div class="row">
    <div class="col-sm-12">
        <h3>Nuevo Pedido</h3>
    </div>
</div>

<div class="row mt-2 caja">
    <div class="col-sm-12">
        <form action="<?= RUTA_URL ?>pedidos/guardarPedido" method="POST" onsubmit="return validarFormulario()">
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
                        <input type="text" class="form-control" id="destinatario" name="destinatario" pattern="[A-Za-z\s]+" required>
                        <div class="invalid-feedback">Por favor, ingresa un nombre válido (solo letras).</div>
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" pattern="[0-9]{8,15}" required>
                        <div class="invalid-feedback">Por favor, ingresa un número de teléfono válido (solo números, de 8 a 15 dígitos).</div>
                    </div>
                    <div class="mb-3">
                        <label for="producto" class="form-label">Producto</label>
                        <input type="text" class="form-control" id="producto" name="producto" required>
                        <div class="invalid-feedback">Por favor, especifica el producto.</div>
                    </div>
                    <div class="mb-3">
                        <label for="cantidad" class="form-label">Cantidad</label>
                        <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" required>
                        <div class="invalid-feedback">La cantidad debe ser al menos 1.</div>
                    </div>
                </div>

                <!-- Segunda Columna -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado" required>
                            <option value="" disabled selected>Selecciona un estado</option>
                            <option value="1">En bodega</option>
                            <option value="2">En ruta o proceso</option>
                            <option value="3">Entregado</option>
                            <option value="4">Reprogramado</option>
                        </select>
                        <div class="invalid-feedback">Por favor, selecciona un estado.</div>
                    </div>
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Usuario Asignado</label>
                        <select class="form-select" id="usuario" name="usuario" required>
                            <option value="" disabled selected>Selecciona un usuario</option>
                            <option value="1">Usuario 1</option>
                            <option value="2">Usuario 2</option>
                        </select>
                        <div class="invalid-feedback">Por favor, selecciona un usuario.</div>
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


<script src="https://maps.googleapis.com/maps/api/js?key=<?=API_MAP?>&callback=initMap" async defer></script>
<script>
    let map, marker;

    function initMap() {
        // Coordenadas iniciales (Managua, Nicaragua)
        const initialPosition = { lat: 12.13282, lng: -86.2504 };

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
            const newPosition = { lat: lat, lng: lng };
            marker.setPosition(newPosition);
            map.setCenter(newPosition);
        }
    }
</script>



<?php include("vista/includes/footer.php"); ?>


