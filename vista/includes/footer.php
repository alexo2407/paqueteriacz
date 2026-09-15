</div><!-- /.bs-page-container -->

<!-- ══════ FOOTER ══════ -->
<footer class="bs-footer">
    <div class="container-fluid px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Seguimiento de App RutaEx-Latam</span>
        <span>Desarrollado por <a href="#">@albertoCalero</a></span>
    </div>
</footer>

</main><!-- /.bs-main -->

</div><!-- /.bs-body-row -->

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


<script>
/* ── Fix: Centrado del recuadro de enfoque del escáner ────────────────────
   Usa MutationObserver para detectar cuando aparece el video del escáner
   y centra el overlay de enfoque (sea cual sea su ID/clase) relativo al
   contenedor del video, usando dimensiones reales (no porcentajes CSS).
───────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    /** Centra todos los hijos absolute dentro del contenedor del video */
    function centerScanOverlays(videoEl) {
        var container = videoEl.parentElement;
        if (!container) return;

        var cW = container.offsetWidth;
        var cH = container.offsetHeight;
        if (cW === 0 || cH === 0) return;

        var children = Array.from(container.children);
        children.forEach(function (child) {
            if (child === videoEl) return;

            var pos = window.getComputedStyle(child).position;
            if (pos !== 'absolute' && pos !== 'fixed') return;

            var oW = child.offsetWidth;
            var oH = child.offsetHeight;
            if (oW === 0 || oH === 0) return;

            var newLeft = Math.max(0, (cW - oW) / 2);
            var newTop  = Math.max(0, (cH - oH) / 2);

            child.style.setProperty('left',      newLeft + 'px', 'important');
            child.style.setProperty('top',       newTop  + 'px', 'important');
            child.style.setProperty('transform', 'none',         'important');
        });

        /* También arregla #qr-shaded-region si usa border-width como ventana */
        var region = container.querySelector('#qr-shaded-region, [id*="shaded"], [id*="region"]');
        if (region) {
            var bt = parseFloat(region.style.borderTopWidth)    || 0;
            var bb = parseFloat(region.style.borderBottomWidth) || 0;
            var bl = parseFloat(region.style.borderLeftWidth)   || 0;
            var br = parseFloat(region.style.borderRightWidth)  || 0;
            var boxW = cW - bl - br;
            var boxH = cH - bt - bb;
            if (boxW > 0 && boxH > 0) {
                var newBL = Math.max(0, (cW - boxW) / 2);
                var newBT = Math.max(0, (cH - boxH) / 2);
                region.style.setProperty('border-left-width',   newBL + 'px', 'important');
                region.style.setProperty('border-right-width',  newBL + 'px', 'important');
                region.style.setProperty('border-top-width',    newBT + 'px', 'important');
                region.style.setProperty('border-bottom-width', newBT + 'px', 'important');
            }
        }
    }

    var _handled = new WeakSet();

    function handleVideo(video) {
        if (_handled.has(video)) return;
        _handled.add(video);

        /* Solo actuar en contexto de modal / escáner */
        var inModal = video.closest(
            '.modal-body, .modal-content, [id*="visor"], [class*="visor"], ' +
            '[id*="scanner"], [class*="scanner"], [id*="cam"], [class*="cam"], ' +
            '[id*="reader"], [id*="lector"], [id*="qr"]'
        );
        if (!inModal) return;

        function tryCenter() { centerScanOverlays(video); }

        tryCenter();
        [200, 500, 1000, 2000].forEach(function (ms) { setTimeout(tryCenter, ms); });

        video.addEventListener('loadeddata', tryCenter);
        video.addEventListener('play',       tryCenter);
        video.addEventListener('resize',     tryCenter);
    }

    /* Observar creación de nuevos elementos */
    var _obs = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            m.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) return;
                if (node.tagName === 'VIDEO') {
                    handleVideo(node);
                } else if (node.querySelectorAll) {
                    node.querySelectorAll('video').forEach(handleVideo);
                }
            });
        });
    });

    _obs.observe(document.body, { childList: true, subtree: true });

    /* Videos ya en el DOM al cargar */
    document.querySelectorAll('video').forEach(handleVideo);
})();
</script>

</body>
</html>
