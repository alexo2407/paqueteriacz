<?php include("vista/includes/header.php"); ?>


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
<script src="<?= RUTA_URL ?>js/pedidos-validation.js"></script>

function validarFormulario() {
    let valid = true;
    const fields = [
        {id:'numero_orden', fn: v => v !== '' && Number(v) > 0, msg: 'Por favor ingresa un número de orden válido.'},
        {id:'destinatario', fn: v => v.trim().length >= 2, msg: 'Por favor, ingresa un nombre válido.'},
        {id:'telefono', fn: v => validarTelefono(v), msg: 'Teléfono inválido (8-15 dígitos).'},
        {id:'producto', fn: v => v.trim().length > 0, msg: 'Por favor, especifica el producto.'},
        {id:'cantidad', fn: v => Number.isInteger(Number(v)) && Number(v) >= 1, msg: 'La cantidad debe ser al menos 1.'},
        {id:'direccion', fn: v => v.trim().length > 5, msg: 'Dirección demasiado corta.'},
        {id:'latitud', fn: v => validarDecimal(v), msg: 'Latitud inválida.'},
        {id:'longitud', fn: v => validarDecimal(v), msg: 'Longitud inválida.'}
    ];

    for (const f of fields) {
        const el = document.getElementById(f.id);
        const val = el ? el.value : '';
        if (!f.fn(val)) {
            setInvalid(el, f.msg);
            if (valid) el.focus();
            valid = false;
        } else {
            clearInvalid(el);
        }
    }

    return valid;
}

// Validación en tiempo real: añadir listeners
document.addEventListener('DOMContentLoaded', function() {
    const watch = ['numero_orden','destinatario','telefono','producto','cantidad','direccion','latitud','longitud'];
    watch.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', () => {
            // small inline checks
            let ok = true;
            if (id === 'telefono') ok = validarTelefono(el.value);
            else if (id === 'cantidad') ok = Number.isInteger(Number(el.value)) && Number(el.value) >= 1;
            else if (id === 'latitud' || id === 'longitud') ok = validarDecimal(el.value);
            else ok = el.value.trim().length > 0;

            if (ok) clearInvalid(el); else setInvalid(el);
        });
    });
});
</script>



<?php include("vista/includes/footer.php"); ?>


