<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../utils/session.php';
start_secure_session();

if (empty($_SESSION['registrado'])) {
    header('Location: ' . RUTA_URL . 'login');
    exit;
}

$id = $parametros[0] ?? null;
if (!$id) {
    echo "<div class='alert alert-danger'>No se proporcionó ID de pedido.</div>";
    include("vista/includes/footer.php");
    exit;
}

$ctrl = new PedidosController();
$pedido = $ctrl->obtenerPedido((int)$id);
if (!$pedido) {
    echo "<div class='alert alert-danger'>Pedido no encontrado.</div>";
    include("vista/includes/footer.php");
    exit;
}

// Estados disponibles
try { $estados = $ctrl->obtenerEstados(); } catch (Exception $e) { $estados = []; }

$lat = (float)($pedido['latitud'] ?? 12.13282);
$lng = (float)($pedido['longitud'] ?? -86.2504);
?>
<div class="container mt-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h2>Pedido #<?= htmlspecialchars($pedido['numero_orden']) ?></h2>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>seguimiento/listar">Volver al listado</a>
    </div>

    <div class="row g-3">
        <div class="col-md-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Datos del pedido</h5>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Destinatario</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($pedido['destinatario']) ?></dd>

                        <dt class="col-sm-4">Teléfono</dt>
                        <dd class="col-sm-8"><a href="tel:<?= htmlspecialchars($pedido['telefono']) ?>"><?= htmlspecialchars($pedido['telefono']) ?></a></dd>

                        <dt class="col-sm-4">Dirección</dt>
                        <dd class="col-sm-8"><?= nl2br(htmlspecialchars($pedido['direccion'])) ?></dd>

                        <dt class="col-sm-4">Estado</dt>
                        <dd class="col-sm-8">
                            <select id="estado" class="form-select form-select-sm" aria-label="Cambiar estado">
                                <option value="">Selecciona un estado</option>
                                <?php foreach ($estados as $e): ?>
                                    <option value="<?= (int)$e['id'] ?>" <?= ((int)$pedido['id_estado'] === (int)$e['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['nombre_estado']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" id="id_pedido" value="<?= (int)$pedido['id'] ?>" />
                            <div id="estadoMsg" class="form-text"></div>
                        </dd>

                        <?php if (!empty($pedido['comentario'])): ?>
                        <dt class="col-sm-4">Comentario</dt>
                        <dd class="col-sm-8"><?= nl2br(htmlspecialchars($pedido['comentario'])) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($pedido['precio_local']) || !empty($pedido['precio_usd'])): ?>
                        <dt class="col-sm-4">Precio</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($pedido['precio_local'])): ?>
                                <span class="badge bg-primary me-1">Local: <?= htmlspecialchars($pedido['precio_local']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($pedido['precio_usd'])): ?>
                                <span class="badge bg-success">USD: <?= htmlspecialchars($pedido['precio_usd']) ?></span>
                            <?php endif; ?>
                        </dd>
                        <?php endif; ?>

                        <?php if (!empty($pedido['productos'])): $pp = $pedido['productos'][0]; ?>
                        <dt class="col-sm-4">Producto</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars(($pp['nombre'] ?? 'Producto')) ?> x <?= (int)($pp['cantidad'] ?? 0) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <div class="alert alert-warning mt-3 mb-0">
                Solo puedes cambiar el estado. Resto de campos son de solo lectura.
            </div>
        </div>

        <div class="col-md-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-3">Ubicación de entrega</h5>
                    <div id="map" style="width: 100%; height: 420px; border: 1px solid #ddd;"></div>
                    <div class="mt-2">
                        <button id="btnRuta" class="btn btn-sm btn-outline-primary">Trazar ruta desde mi ubicación</button>
                        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=<?= $lat ?>,<?= $lng ?>">Abrir en Google Maps</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://maps.googleapis.com/maps/api/js?key=<?= API_MAP ?>&callback=initMap" async defer></script>
<script>
    let map, marker, directionsService, directionsRenderer;

    function initMap() {
        const destino = { lat: <?= $lat ?>, lng: <?= $lng ?> };
        map = new google.maps.Map(document.getElementById('map'), {
            center: destino,
            zoom: 15
        });
        marker = new google.maps.Marker({ position: destino, map });
        directionsService = new google.maps.DirectionsService();
        directionsRenderer = new google.maps.DirectionsRenderer();
        directionsRenderer.setMap(map);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const selectEstado = document.getElementById('estado');
        const idPedido = document.getElementById('id_pedido').value;
        const estadoMsg = document.getElementById('estadoMsg');

        selectEstado.addEventListener('change', async function() {
            const nuevoEstado = this.value;
            if (!nuevoEstado) return;
            try {
                const resp = await fetch('<?= RUTA_URL ?>cambiarEstados', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new URLSearchParams({ id_pedido: idPedido, estado: nuevoEstado })
                });
                const data = await resp.json();
                if (data && data.success) {
                    estadoMsg.textContent = 'Estado actualizado.';
                    estadoMsg.className = 'form-text text-success';
                } else {
                    estadoMsg.textContent = (data && data.message) ? data.message : 'No fue posible actualizar el estado.';
                    estadoMsg.className = 'form-text text-danger';
                }
            } catch (e) {
                estadoMsg.textContent = 'Error de red al actualizar estado.';
                estadoMsg.className = 'form-text text-danger';
            }
        });

        document.getElementById('btnRuta').addEventListener('click', function(e) {
            e.preventDefault();
            if (!navigator.geolocation) {
                alert('La geolocalización no está soportada en este navegador.');
                return;
            }
            navigator.geolocation.getCurrentPosition(function(pos) {
                const origen = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                const destino = { lat: <?= $lat ?>, lng: <?= $lng ?> };
                directionsService.route({
                    origin: origen,
                    destination: destino,
                    travelMode: google.maps.TravelMode.DRIVING
                }, function(result, status) {
                    if (status === google.maps.DirectionsStatus.OK) {
                        directionsRenderer.setDirections(result);
                    } else {
                        alert('No se pudo trazar la ruta: ' + status);
                    }
                });
            }, function(err) {
                alert('No se pudo obtener tu ubicación: ' + err.message);
            });
        });
    });
</script>

<?php include("vista/includes/footer.php"); ?>
