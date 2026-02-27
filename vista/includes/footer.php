</div><!-- /.bs-page-container -->
</main><!-- /.bs-main -->

</div><!-- /.bs-body-row -->

<!-- ══════ FOOTER ══════ -->
<footer class="bs-footer">
    <div class="container-fluid px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Seguimiento de App RutaEx-Latam</span>
        <span>Desarrollado por <a href="#">@albertoCalero</a></span>
    </div>
</footer>

<?php
$paginaActual = isset($_GET['enlace']) ? explode("/", $_GET['enlace'])[0] : "inicio";
?>

<!-- ══════ SCRIPTS ══════ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="<?= RUTA_URL ?>vista/js/select2-init.js"></script>
<script src="<?= RUTA_URL ?>vista/js/js/sweetalert2@11.js"></script>

<?php if (!empty($usaDataTables)): ?>
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<?php endif; ?>

<?php if ($paginaActual === "editar"): ?>
<script>
    let map, marker;
    function initMap() {
        const initialPosition = { lat: 12.13282, lng: -86.2504 };
        map = new google.maps.Map(document.getElementById("map"), { center: initialPosition, zoom: 15 });
        marker = new google.maps.Marker({ position: initialPosition, map: map, draggable: true });
        marker.addListener("dragend", (e) => {
            document.getElementById("latitud").value = e.latLng.lat();
            document.getElementById("longitud").value = e.latLng.lng();
        });
        map.addListener("click", (e) => {
            marker.setPosition(e.latLng);
            document.getElementById("latitud").value = e.latLng.lat();
            document.getElementById("longitud").value = e.latLng.lng();
        });
    }
</script>
<?php endif; ?>

<?php
// ── Flash messages (SweetAlert2) ──────────────────────────────────────────
require_once __DIR__ . '/../../utils/session.php';
$flash = get_flash();
if ($flash) {
    $type = $flash['type'] === 'success' ? 'success' : 'error';
    $msg  = addslashes($flash['message']);
    echo "<script>document.addEventListener('DOMContentLoaded',function(){ Swal.fire({icon:'$type',title:'',text:'$msg'}); });</script>";
}
?>

</body>
</html>
