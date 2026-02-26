    </div><!-- /.mz-page-container -->
</main><!-- /.mz-main-content -->

<!-- ══════════════════ FOOTER ══════════════════ -->
<footer class="page-footer mz-footer">
    <div class="container">
        <div class="row">
            <div class="col l6 s12">
                <h5 class="white-text">App RutaEx-Latam</h5>
                <p class="grey-text text-lighten-4">Seguimiento y gestión de paquetería</p>
            </div>
            <div class="col l4 offset-l2 s12">
                <p class="grey-text text-lighten-4">
                    Desarrollado por <a href="#" class="grey-text text-lighten-2">@albertoCalero</a>
                </p>
            </div>
        </div>
    </div>
    <div class="footer-copyright">
        <div class="container">
            <a href="#" class="grey-text text-lighten-4 right">Volver arriba</a>
        </div>
    </div>
</footer>

<!-- ══════════════════ SCRIPTS ══════════════════ -->
<!-- jQuery (requerido por SweetAlert2 y AJAX heredado) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- MaterializeCSS JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<!-- SweetAlert2 (mismo que en footer.php original) -->
<script src="<?= RUTA_URL ?>vista/js/js/sweetalert2@11.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── Sidenav ────────────────────────────────────────────────────────────
    var sidenavEl = document.querySelector('.sidenav');
    if (sidenavEl) M.Sidenav.init(sidenavEl, { edge: 'left' });

    // ── Dropdown de usuario (navbar) ───────────────────────────────────────
    var dropdownEls = document.querySelectorAll('.dropdown-trigger');
    M.Dropdown.init(dropdownEls, {
        coverTrigger: false,
        constrainWidth: false
    });

    // ── Selects (IMPORTANTE: re-inicializar tras AJAX también) ─────────────
    var selects = document.querySelectorAll('select');
    M.FormSelect.init(selects);

    // ── Modales ────────────────────────────────────────────────────────────
    var modals = document.querySelectorAll('.modal');
    M.Modal.init(modals, { dismissible: true });

    // ── Tooltips ───────────────────────────────────────────────────────────
    var tooltips = document.querySelectorAll('.tooltipped');
    M.Tooltip.init(tooltips);

    // ── Collapsibles (sustituto de accordion) ─────────────────────────────
    var collapsibles = document.querySelectorAll('.collapsible');
    M.Collapsible.init(collapsibles);

    // ── Floating Action Button (FAB) ───────────────────────────────────────
    var fabs = document.querySelectorAll('.fixed-action-btn');
    M.FloatingActionButton.init(fabs);

    // ── Tabs ───────────────────────────────────────────────────────────────
    var tabs = document.querySelectorAll('.tabs');
    M.Tabs.init(tabs);

    // ── Datepickers ────────────────────────────────────────────────────────
    var datepickers = document.querySelectorAll('.datepicker');
    M.Datepicker.init(datepickers, {
        format: 'yyyy-mm-dd',
        i18n: {
            months: ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'],
            monthsShort: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
            weekdays: ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'],
            weekdaysShort: ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'],
            weekdaysAbbrev: ['D','L','M','M','J','V','S'],
            cancel: 'Cancelar',
            clear: 'Limpiar',
            done: 'Aceptar'
        }
    });

});

/**
 * Función global para reinicializar un select de Materialize tras rellenarlo via AJAX.
 * Uso: reinitSelect(document.getElementById('mi-select'));
 */
function reinitSelect(el) {
    if (!el) return;
    var instance = M.FormSelect.getInstance(el);
    if (instance) instance.destroy();
    M.FormSelect.init(el);
}

/**
 * Función global para abrir un modal de Materialize por ID.
 * Uso: openModal('modalImportarCp');
 */
function openModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    var instance = M.Modal.getInstance(el) || M.Modal.init(el);
    instance.open();
}
</script>

<?php
// ── Flash messages (SweetAlert2) — misma lógica que footer.php ────────────
require_once __DIR__ . '/../../utils/session.php';
$flash = get_flash();
if ($flash) {
    $type = $flash['type'] === 'success' ? 'success' : 'error';
    $msg  = addslashes($flash['message']);
    echo "<script>document.addEventListener('DOMContentLoaded', function(){ Swal.fire({icon: '$type', title: '', text: '$msg'}); });</script>";
}
?>

</body>
</html>
