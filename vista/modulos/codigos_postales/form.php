<?php
$loadBootstrap = false; // Vista Materialize pura — no necesita Bootstrap
include("vista/includes/header_materialize.php");
?>
<link rel="stylesheet" href="<?= RUTA_URL ?>vista/css/codigos_postales.css">

<?php
$ctrl    = new CodigosPostalesController();
$paisCtrl = new PaisesController();

$id   = isset($parametros[0]) ? (int)$parametros[0] : 0;
$item = null;
if ($id > 0) {
    $item = $ctrl->ver($id);
}

// Repoblar si hubo error de validación (desde sesión)
$old = $_SESSION['old_cp'] ?? null;
if (isset($_SESSION['old_cp'])) unset($_SESSION['old_cp']);

$paises = $paisCtrl->listar();

$esEditar  = ($id > 0);
$titulo    = $esEditar ? 'Editar Código Postal' : 'Nuevo Código Postal';
$subtitulo = $esEditar ? 'Actualiza la información del código postal homologado' : 'Registra un nuevo código postal en la fuente de verdad';
$accionUrl = RUTA_URL . 'codigos_postales/' . ($esEditar ? 'actualizar/' . $id : 'guardar');

// Valores actuales (edición o repoblación por error)
$id_pais   = $old['id_pais']           ?? ($item['id_pais']           ?? '');
$cp        = $old['codigo_postal']     ?? ($item['codigo_postal']     ?? '');
$id_dep    = $old['id_departamento']   ?? ($item['id_departamento']   ?? '');
$id_mun    = $old['id_municipio']      ?? ($item['id_municipio']      ?? '');
$id_bar    = $old['id_barrio']         ?? ($item['id_barrio']         ?? '');
$localidad = $old['nombre_localidad']  ?? ($item['nombre_localidad']  ?? '');
$activo    = $old['activo']            ?? ($item['activo']            ?? 1);
?>

<!-- ════ CABECERA ════ -->
<div class="mz-card-header" style="background:linear-gradient(135deg,#1d976c 0%,#093028 100%);border-radius:12px;margin-bottom:1.5rem">
    <div class="d-flex align-center gap-2">
        <i class="material-icons white-text" style="font-size:2.5rem;opacity:.9">
            <?= $esEditar ? 'edit' : 'add_location' ?>
        </i>
        <div>
            <h4 class="white-text" style="margin:0;font-weight:700"><?= $titulo ?></h4>
            <p class="white-text" style="margin:4px 0 0;opacity:.75;font-size:.9rem"><?= $subtitulo ?></p>
        </div>
    </div>
</div>

<!-- ════ FORMULARIO ════ -->
<div class="row">
    <div class="col s12 m10 offset-m1 l10 offset-l1">
        <div class="card mz-card-raised cp-form-card">
            <div class="card-content" style="padding:2rem">

                <form action="<?= $accionUrl ?>" method="POST" id="formCP">

                    <!-- ── Datos básicos ─────────────────────────────────── -->
                    <p class="grey-text text-uppercase" style="font-size:.75rem;font-weight:700;letter-spacing:1px;margin:0 0 .75rem">
                        Datos básicos
                    </p>

                    <div class="row" style="margin-bottom:0">
                        <!-- País -->
                        <div class="input-field col s12 l6">
                            <select name="id_pais" id="id_pais" required>
                                <option value="" disabled <?= !$id_pais ? 'selected' : '' ?>>Selecciona un país</option>
                                <?php foreach ($paises as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $id_pais == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <label>País <span class="mz-required">*</span></label>
                        </div>

                        <!-- Código Postal -->
                        <div class="input-field col s12 l6">
                            <input type="text" name="codigo_postal" id="codigo_postal" required
                                   value="<?= htmlspecialchars($cp) ?>"
                                   <?= $cp ? 'class="active"' : '' ?>>
                            <label for="codigo_postal" <?= $cp ? 'class="active"' : '' ?>>
                                Código Postal <span class="mz-required">*</span>
                            </label>
                            <span class="helper-text">Se normalizará: MAYÚSCULAS, sin espacios ni guiones</span>
                        </div>
                    </div>

                    <div class="divider" style="margin:1.25rem 0"></div>

                    <!-- ── Ubicación geográfica (selects en cascada) ──────── -->
                    <p class="grey-text text-uppercase" style="font-size:.75rem;font-weight:700;letter-spacing:1px;margin:0 0 .75rem">
                        Ubicación geográfica
                    </p>

                    <!-- Fila 1: Departamento + Municipio -->
                    <div class="row" style="margin-bottom:0">
                        <div class="input-field col s12 l6">
                            <select name="id_departamento" id="id_departamento">
                                <option value=""><?= $id_pais ? 'Selecciona un departamento' : 'Selecciona un país primero' ?></option>
                            </select>
                            <label for="id_departamento">Departamento / Provincia</label>
                        </div>

                        <div class="input-field col s12 l6">
                            <select name="id_municipio" id="id_municipio">
                                <option value=""><?= $id_dep ? 'Selecciona un municipio' : 'Selecciona un departamento primero' ?></option>
                            </select>
                            <label for="id_municipio">Municipio / Ciudad</label>
                        </div>
                    </div>

                    <!-- Fila 2: Barrio + Nombre Localidad -->
                    <div class="row" style="margin-bottom:0">
                        <div class="input-field col s12 l6">
                            <select name="id_barrio" id="id_barrio">
                                <option value=""><?= $id_mun ? 'Selecciona un barrio' : 'Selecciona un municipio primero' ?></option>
                            </select>
                            <label for="id_barrio">Barrio / Zona (Opcional)</label>
                        </div>

                        <div class="input-field col s12 l6">
                            <input type="text" name="nombre_localidad" id="nombre_localidad"
                                   value="<?= htmlspecialchars($localidad) ?>"
                                   <?= $localidad ? 'class="active"' : '' ?>>
                            <label for="nombre_localidad" <?= $localidad ? 'class="active"' : '' ?>>
                                Nombre Localidad (Referencia)
                            </label>
                            <span class="helper-text">Texto libre complementario a los campos de arriba</span>
                        </div>
                    </div>

                    <div class="divider" style="margin:1.25rem 0"></div>

                    <!-- ── Estado ─────────────────────────────────────────── -->
                    <div class="switch" style="margin-bottom:1.5rem">
                        <label>
                            <input type="checkbox" name="activo" value="1" id="activo" <?= $activo ? 'checked' : '' ?>>
                            <span class="lever"></span>
                            Registro activo y disponible para homologación
                        </label>
                    </div>

                    <!-- ── Botones ─────────────────────────────────────────────────────── -->
                    <div class="cp-form-actions">
                        <a href="<?= RUTA_URL ?>codigos_postales" class="btn-flat waves-effect grey-text">
                            <i class="material-icons left">arrow_back</i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-success-mz waves-effect waves-light" style="padding:0 2rem">
                            <i class="material-icons left"><?= $esEditar ? 'save' : 'check_circle' ?></i>
                            <?= $esEditar ? 'Actualizar Registro' : 'Guardar Código Postal' ?>
                        </button>
                    </div>

                </form>
            </div><!-- /card-content -->
        </div><!-- /card -->
    </div>
</div>

<?php include("vista/includes/footer_materialize.php"); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── Referencias a los 4 selects ────────────────────────────────────────
    const sPais  = document.getElementById('id_pais');
    const sDepto = document.getElementById('id_departamento');
    const sMuni  = document.getElementById('id_municipio');
    const sBarrio= document.getElementById('id_barrio');

    /**
     * Valores precargados (edición / repoblación por error).
     * Rellenan los selects en cascada al cargar la página.
     */
    const initial = {
        depto:  "<?= addslashes($id_dep) ?>",
        muni:   "<?= addslashes($id_mun) ?>",
        barrio: "<?= addslashes($id_bar) ?>"
    };

    // ── Helpers ────────────────────────────────────────────────────────────

    /** Vacía un select y pone un placeholder */
    function clearSelect(sel, placeholder) {
        sel.innerHTML = `<option value="">${placeholder}</option>`;
        reinitSelect(sel);  // función global de footer_materialize.php
    }

    /** Puebla un select con un array [{id, nombre}] y reinicializa Materialize */
    function fillSelect(sel, items, selectedId) {
        let html = `<option value="">Selecciona...</option>`;
        items.forEach(function(item) {
            const sel_ = String(item.id) === String(selectedId) ? 'selected' : '';
            html += `<option value="${item.id}" ${sel_}>${item.nombre}</option>`;
        });
        sel.innerHTML = html;
        reinitSelect(sel);
    }

    // ── Carga de departamentos ─────────────────────────────────────────────
    function loadDeptos(paisId, selectedId) {
        if (!paisId) { clearSelect(sDepto, 'Selecciona un país primero'); return; }
        fetch(`<?= RUTA_URL ?>api/geoinfo/departamentos?id_pais=${paisId}`)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                fillSelect(sDepto, data, selectedId || '');
                if (selectedId) loadMunis(selectedId, initial.muni);
            });
    }

    // ── Carga de municipios ────────────────────────────────────────────────
    function loadMunis(deptoId, selectedId) {
        if (!deptoId) { clearSelect(sMuni, 'Selecciona un departamento primero'); return; }
        fetch(`<?= RUTA_URL ?>api/geoinfo/municipios?id_departamento=${deptoId}`)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                fillSelect(sMuni, data, selectedId || '');
                if (selectedId) loadBarrios(selectedId, initial.barrio);
            });
    }

    // ── Carga de barrios ───────────────────────────────────────────────────
    function loadBarrios(muniId, selectedId) {
        if (!muniId) { clearSelect(sBarrio, 'Selecciona un municipio primero'); return; }
        fetch(`<?= RUTA_URL ?>api/geoinfo/barrios?id_municipio=${muniId}`)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                fillSelect(sBarrio, data, selectedId || '');
            });
    }

    // ── Listeners de cambio ────────────────────────────────────────────────
    sPais.addEventListener('change', function() {
        clearSelect(sMuni,  'Selecciona un departamento primero');
        clearSelect(sBarrio,'Selecciona un municipio primero');
        loadDeptos(this.value, '');
    });

    sDepto.addEventListener('change', function() {
        clearSelect(sBarrio, 'Selecciona un municipio primero');
        loadMunis(this.value, '');
    });

    sMuni.addEventListener('change', function() {
        loadBarrios(this.value, '');
    });

    // ── Carga inicial en modo edición / repoblación ────────────────────────
    // Materialize renderiza el select DESPUÉS de M.FormSelect.init(),
    // por eso escuchamos el evento 'change' en el select de país ya inicializado.
    if (sPais.value) {
        loadDeptos(sPais.value, initial.depto);
    }

});
</script>
