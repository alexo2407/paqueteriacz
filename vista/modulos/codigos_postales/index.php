<?php
$loadBootstrap = false; // Vista Materialize pura — no necesita Bootstrap
include("vista/includes/header_materialize.php");
?>
<link rel="stylesheet" href="<?= RUTA_URL ?>vista/css/codigos_postales.css">

<?php
$ctrl    = new CodigosPostalesController();
$paisCtrl = new PaisesController();

$paises = $paisCtrl->listar();

// Filtros
$filtros = [
    'id_pais'       => $_GET['id_pais']       ?? '',
    'codigo_postal' => $_GET['codigo_postal']  ?? '',
    'activo'        => $_GET['activo']         ?? '',
    'parcial'       => $_GET['parcial']        ?? ''
];

$pagina  = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$limite  = 20;

$resultado = $ctrl->listar($filtros, $pagina, $limite);
$items   = $resultado['items'];
$total   = $resultado['total'];
$paginas = $resultado['paginas'];

// Roles
$rolesNombres  = $_SESSION['roles_nombres'] ?? [];
$puedeEditar   = in_array('Administrador', $rolesNombres, true) || in_array('Vendedor', $rolesNombres, true);
$puedeEliminar = in_array('Administrador', $rolesNombres, true);

// URL exportar
$exportParams = http_build_query(array_filter([
    'id_pais'       => $filtros['id_pais'],
    'codigo_postal' => $filtros['codigo_postal'],
    'activo'        => $filtros['activo'],
    'parcial'       => $filtros['parcial'],
]));
$exportUrl = RUTA_URL . 'codigos_postales/exportar' . ($exportParams ? '?' . $exportParams : '');
?>

<!-- ════ CABECERA ════ -->
<div class="mz-card-header" style="background:linear-gradient(135deg,#4b6cb7 0%,#182848 100%); border-radius:12px; margin-bottom:1.5rem;">
    <div class="cp-header">
        <div>
            <h4 class="white-text" style="margin:0;font-weight:700">
                <i class="material-icons left" style="vertical-align:middle">local_post_office</i>
                Homologación de CPs
            </h4>
            <p class="white-text" style="margin:4px 0 0;opacity:.75;font-size:.9rem">
                Administración de la fuente de verdad para direcciones
            </p>
        </div>
        <div class="cp-header-actions">
            <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-success-mz waves-effect waves-light" title="Exportar a Excel">
                <i class="material-icons left">download</i>Exportar Excel
            </a>
            <?php if ($puedeEditar): ?>
            <a href="#modalImportarCp" class="btn white blue-text waves-effect modal-trigger" style="font-weight:600">
                <i class="material-icons left">upload</i>Importar CPs
            </a>
            <a href="<?= RUTA_URL ?>codigos_postales/crear" class="btn white blue-text waves-effect" style="font-weight:600">
                <i class="material-icons left">add</i>Nuevo CP
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ════ FILTROS ════ -->
<div class="card mz-card-raised" style="margin-bottom:1.5rem">
    <div class="card-content" style="padding:1.25rem 1.5rem">
        <form method="GET" action="<?= RUTA_URL ?>codigos_postales" class="cp-filtros-form">
            <input type="hidden" name="enlace" value="codigos_postales">

            <div class="row" style="margin-bottom:0">

                <!-- País: s12 → full | m6 → 2 cols | l3 → 4 cols -->
                <div class="input-field col s12 l3">
                    <select name="id_pais" id="f-pais">
                        <option value="">Todos los países</option>
                        <?php foreach ($paises as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filtros['id_pais'] == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="f-pais">País</label>
                </div>

                <!-- Código Postal -->
                <div class="input-field col s12 l3">
                    <input type="text" name="codigo_postal" id="f-cp"
                           value="<?= htmlspecialchars($filtros['codigo_postal']) ?>">
                    <label for="f-cp" <?= $filtros['codigo_postal'] ? 'class="active"' : '' ?>>Código Postal</label>
                </div>

                <!-- Estado -->
                <div class="input-field col s6 l3">
                    <select name="activo" id="f-activo">
                        <option value=""  <?= $filtros['activo'] === ''  ? 'selected' : '' ?>>Todos</option>
                        <option value="1" <?= $filtros['activo'] === '1' ? 'selected' : '' ?>>Activos</option>
                        <option value="0" <?= $filtros['activo'] === '0' ? 'selected' : '' ?>>Inactivos</option>
                    </select>
                    <label for="f-activo">Estado</label>
                </div>

                <!-- Completitud -->
                <div class="input-field col s6 l3">
                    <select name="parcial" id="f-parcial">
                        <option value=""  <?= $filtros['parcial'] === ''  ? 'selected' : '' ?>>Todos</option>
                        <option value="1" <?= $filtros['parcial'] === '1' ? 'selected' : '' ?>>Solo Parciales</option>
                    </select>
                    <label for="f-parcial">Completitud</label>
                </div>

            </div><!-- /row campos -->

            <!-- Botones en fila propia para que nunca se aplasten con los inputs -->
            <div class="row" style="margin-bottom:0;margin-top:.25rem">
                <div class="col s9 m4 l2">
                    <button type="submit" class="btn btn-primary-mz waves-effect waves-light w-100">
                        <i class="material-icons left">filter_list</i>Filtrar
                    </button>
                </div>
                <div class="col s3 m2 l1" style="display:flex;align-items:center;padding-top:4px">
                    <a href="<?= RUTA_URL ?>codigos_postales"
                       class="btn-flat waves-effect tooltipped"
                       data-tooltip="Limpiar filtros"
                       style="padding:0 10px;height:36px;line-height:36px">
                        <i class="material-icons grey-text">close</i>
                    </a>
                </div>
            </div>

        </form>
    </div>
</div>


<!-- ════ TABLA ════ -->
<div class="card mz-card-raised">
    <div class="card-content" style="padding:0">
        <div class="cp-table-wrapper">
            <table class="responsive-table highlight cp-table" style="width:100%;margin:0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>País</th>
                        <th>CP</th>
                        <th>Ubicación (Depto / Muni / Barrio)</th>
                        <th class="center-align">Estado</th>
                        <th class="center-align">Actualizado</th>
                        <th class="right-align">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="7" class="center-align grey-text" style="padding:2rem">
                            <i class="material-icons medium grey-text">inbox</i><br>
                            No se encontraron registros
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($items as $item):
                        $esParcial = AddressService::isPartial($item);
                    ?>
                    <tr>
                        <!-- ID -->
                        <td data-label="ID" class="grey-text" style="font-size:.82rem">#<?= $item['id'] ?></td>

                        <!-- País -->
                        <td data-label="País"><?= htmlspecialchars($item['nombre_pais']) ?></td>

                        <!-- CP + badge parcial -->
                        <td data-label="CP">
                            <span class="chip chip-muted" style="font-family:monospace;font-size:.9rem">
                                <?= htmlspecialchars($item['codigo_postal']) ?>
                            </span>
                            <?php if ($esParcial): ?>
                            <span class="chip chip-warning" title="Faltan datos de ubicación">Parcial</span>
                            <?php endif; ?>
                        </td>

                        <!-- Ubicación -->
                        <td data-label="Ubicación" class="cp-col-ubicacion">
                            <span class="<?= !$item['id_departamento'] ? 'red-text' : '' ?>" style="font-size:.82rem">
                                <?= htmlspecialchars($item['nombre_departamento'] ?? '[Falta Depto]') ?>
                            </span>
                            <span class="grey-text"> / </span>
                            <span class="<?= !$item['id_municipio'] ? 'red-text' : '' ?>" style="font-size:.82rem">
                                <?= htmlspecialchars($item['nombre_municipio'] ?? ($item['nombre_localidad'] ?: '[Falta Muni]')) ?>
                            </span>
                            <span class="grey-text" style="font-size:.82rem"> / <?= htmlspecialchars($item['nombre_barrio'] ?? '-') ?></span>
                        </td>

                        <!-- Switch activo -->
                        <td data-label="Estado" class="center-align">
                            <div class="switch">
                                <label>
                                    <input type="checkbox" class="btn-toggle"
                                           data-id="<?= $item['id'] ?>"
                                           <?= $item['activo'] ? 'checked' : '' ?>
                                           <?= !$puedeEditar ? 'disabled' : '' ?>>
                                    <span class="lever"></span>
                                </label>
                            </div>
                        </td>

                        <!-- Fecha -->
                        <td data-label="Actualizado" class="center-align grey-text" style="font-size:.8rem">
                            <?= date('d/m/Y H:i', strtotime($item['updated_at'] ?? $item['created_at'])) ?>
                        </td>

                        <!-- Acciones -->
                        <td data-label="Acciones" class="right-align">
                            <?php if ($puedeEditar): ?>
                            <a href="<?= RUTA_URL ?>codigos_postales/editar/<?= $item['id'] ?>"
                               class="btn-floating btn-small waves-effect waves-light blue tooltipped"
                               data-tooltip="Editar">
                                <i class="material-icons">edit</i>
                            </a>
                            <?php endif; ?>
                            <?php if ($puedeEliminar): ?>
                            <button class="btn-floating btn-small waves-effect waves-light red btn-eliminar-cp"
                                    data-id="<?= $item['id'] ?>"
                                    data-cp="<?= htmlspecialchars($item['codigo_postal']) ?>"
                                    title="Eliminar">
                                <i class="material-icons">delete</i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ════ PAGINACIÓN ════ -->
        <?php if ($paginas > 1):
            $window     = 2;
            $baseParams = array_merge($filtros, ['enlace' => 'codigos_postales']);

            function cpPageUrl($baseParams, $p) {
                return '?' . http_build_query(array_merge($baseParams, ['pagina' => $p]));
            }

            $visible = [1];
            for ($i = max(2, $pagina - $window); $i <= min($paginas - 1, $pagina + $window); $i++) $visible[] = $i;
            $visible[] = $paginas;
            $visible = array_unique($visible);
            sort($visible);
        ?>
        <div style="padding:1rem 1.5rem 1.5rem">
            <ul class="mz-pagination">
                <!-- Anterior -->
                <li class="<?= $pagina <= 1 ? 'disabled' : '' ?>">
                    <a href="<?= cpPageUrl($baseParams, $pagina - 1) ?>">
                        <i class="material-icons" style="font-size:1rem;line-height:36px">chevron_left</i>
                    </a>
                </li>

                <?php $prev = null; foreach ($visible as $p):
                    if ($prev !== null && $p - $prev > 1): ?>
                    <li class="disabled"><span>…</span></li>
                    <?php endif; ?>
                    <li class="<?= $p == $pagina ? 'active' : '' ?>">
                        <a href="<?= cpPageUrl($baseParams, $p) ?>"><?= $p ?></a>
                    </li>
                <?php $prev = $p; endforeach; ?>

                <!-- Siguiente -->
                <li class="<?= $pagina >= $paginas ? 'disabled' : '' ?>">
                    <a href="<?= cpPageUrl($baseParams, $pagina + 1) ?>">
                        <i class="material-icons" style="font-size:1rem;line-height:36px">chevron_right</i>
                    </a>
                </li>
            </ul>
            <p class="center-align grey-text" style="font-size:.8rem;margin:8px 0 0">
                Página <?= $pagina ?> de <?= $paginas ?> &nbsp;·&nbsp; <?= number_format($total) ?> registros
            </p>
        </div>
        <?php endif; ?>

    </div><!-- /card-content -->
</div><!-- /card -->


<!-- ══════════════════════ MODAL IMPORTAR (Materialize) ══════════════════ -->
<?php if ($puedeEditar): ?>
<div id="modalImportarCp" class="modal modal-fixed-footer" style="max-width:1000px;border-radius:12px">

    <!-- HEADER -->
    <div class="modal-content" style="padding:0">
        <div style="background:linear-gradient(135deg,#4b6cb7,#182848);padding:1.25rem 1.5rem;border-radius:12px 12px 0 0">
            <h5 class="white-text" style="margin:0;font-weight:700">
                <i class="material-icons left" style="vertical-align:middle">upload</i>
                Importar Códigos Postales
            </h5>
        </div>

        <div style="padding:1.5rem">

            <!-- Stepper wizard -->
            <div class="mz-stepper">
                <div class="mz-step active" id="si-1">
                    <div class="mz-step-circle">1</div>
                    <span class="mz-step-label">Cargar archivo</span>
                </div>
                <div class="mz-step-connector"></div>
                <div class="mz-step" id="si-2">
                    <div class="mz-step-circle">2</div>
                    <span class="mz-step-label">Vista previa</span>
                </div>
                <div class="mz-step-connector"></div>
                <div class="mz-step" id="si-3">
                    <div class="mz-step-circle">3</div>
                    <span class="mz-step-label">Resultado</span>
                </div>
            </div>

            <!-- ══ PASO 1: Upload ══ -->
            <div class="wizard-step" id="wizardStep1">
                <form id="formCpPreview" enctype="multipart/form-data">
                    <input type="hidden" name="enlace" value="codigos_postales/import/preview">

                    <!-- Zona upload -->
                    <div style="background:#f8f9fa;border:2px dashed #dee2e6;border-radius:12px;padding:2rem;text-align:center;margin-bottom:1rem">
                        <i class="material-icons large blue-text" style="font-size:3rem">upload_file</i>
                        <p class="grey-text" style="margin:.5rem 0 1rem">Arrastra tu archivo o haz clic para seleccionarlo</p>
                        <label class="btn btn-primary-mz waves-effect waves-light" for="cpArchivoInput">
                            <i class="material-icons left">folder_open</i>Seleccionar archivo
                        </label>
                        <input type="file" id="cpArchivoInput" name="archivo" accept=".csv,.xlsx,.xls"
                               style="display:none">
                        <p class="grey-text" style="margin:.75rem 0 0;font-size:.8rem" id="cpFileName">Ningún archivo seleccionado</p>
                        <p class="grey-text" style="margin:4px 0 0;font-size:.72rem">CSV o XLSX · Máx 10 MB · Hasta 10,000 filas</p>
                    </div>

                    <!-- Alerta archivo -->
                    <div id="cpAlertaArchivo" class="mz-alert d-none"></div>

                    <!-- Opciones avanzadas (collapsible) -->
                    <ul class="collapsible" style="margin-bottom:1rem">
                        <li>
                            <div class="collapsible-header">
                                <i class="material-icons">tune</i>Opciones avanzadas
                            </div>
                            <div class="collapsible-body">
                                <div class="row" style="margin-bottom:0">
                                    <div class="input-field col s12 m4">
                                        <select name="modo" id="opt-modo">
                                            <option value="upsert" selected>Upsert (insertar y actualizar)</option>
                                            <option value="solo_nuevos">Solo nuevos (ignorar existentes)</option>
                                            <option value="sobrescribir_ubicacion">Sobrescribir ubicación</option>
                                        </select>
                                        <label>Modo de importación</label>
                                        <span class="helper-text">Cómo tratar los CPs que ya existen</span>
                                    </div>
                                    <div class="input-field col s12 m4">
                                        <select name="crear_geo" id="opt-crear-geo">
                                            <option value="1" selected>Sí, crear dpto/muni/barrio automáticamente</option>
                                            <option value="0">No, ignorar si no existe</option>
                                        </select>
                                        <label>Crear geografía faltante</label>
                                    </div>
                                    <div class="input-field col s12 m4">
                                        <select name="default_activo" id="opt-activo">
                                            <option value="1" selected>1 — Activo</option>
                                            <option value="0">0 — Inactivo</option>
                                        </select>
                                        <label>Activo por defecto</label>
                                        <span class="helper-text">Cuando la columna 'activo' está vacía</span>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <!-- Referencia de columnas -->
                    <div class="card-panel grey lighten-5" style="border-radius:8px;padding:1rem">
                        <p class="grey-text text-darken-2" style="font-size:.82rem;font-weight:700;margin:0 0 .5rem">
                            <i class="material-icons tiny left">table_chart</i>Columnas aceptadas
                        </p>
                        <table class="striped" style="font-size:.78rem">
                            <thead><tr><th>Campo</th><th>Sinónimos</th><th>Requerido</th></tr></thead>
                            <tbody>
                                <tr><td><code>id_pais</code></td><td>pais, country, id_country</td><td><span class="chip chip-danger">Sí</span></td></tr>
                                <tr><td><code>codigo_postal</code></td><td>cp, postal_code, zip</td><td><span class="chip chip-danger">Sí</span></td></tr>
                                <tr><td><code>departamento</code></td><td>provincia, state</td><td><span class="chip chip-muted">No</span></td></tr>
                                <tr><td><code>municipio</code></td><td>ciudad, city, county</td><td><span class="chip chip-muted">No</span></td></tr>
                                <tr><td><code>barrio</code></td><td>zona, neighborhood</td><td><span class="chip chip-muted">No</span></td></tr>
                                <tr><td><code>nombre_localidad</code></td><td>localidad, referencia</td><td><span class="chip chip-muted">No</span></td></tr>
                                <tr><td><code>activo</code></td><td>active, status</td><td><span class="chip chip-muted">No</span></td></tr>
                            </tbody>
                        </table>
                        <div style="margin-top:.75rem;display:flex;gap:1rem">
                            <a href="#" data-cp-plantilla="solo" style="font-size:.82rem">
                                <i class="material-icons tiny">description</i> Plantilla mínima (solo CP)
                            </a>
                            <a href="#" data-cp-plantilla="completa" style="font-size:.82rem">
                                <i class="material-icons tiny">description</i> Plantilla completa
                            </a>
                        </div>
                    </div>
                </form>
            </div><!-- /wizardStep1 -->

            <!-- ══ PASO 2: Vista previa ══ -->
            <div class="wizard-step" id="wizardStep2" style="display:none">
                <div id="cpAlertaPaso2" class="mz-alert d-none"></div>

                <!-- Tarjetas resumen -->
                <div class="row">
                    <div class="col s6 m3">
                        <div class="card-panel center-align">
                            <div style="font-size:2rem;font-weight:700" id="cpSumTotal">0</div>
                            <div class="grey-text" style="font-size:.82rem">Total filas</div>
                        </div>
                    </div>
                    <div class="col s6 m3">
                        <div class="card-panel center-align green lighten-5">
                            <div class="green-text" style="font-size:2rem;font-weight:700" id="cpSumValidas">0</div>
                            <div class="grey-text" style="font-size:.82rem">Válidas</div>
                        </div>
                    </div>
                    <div class="col s6 m3">
                        <div class="card-panel center-align red lighten-5">
                            <div class="red-text" style="font-size:2rem;font-weight:700" id="cpSumErrores">0</div>
                            <div class="grey-text" style="font-size:.82rem">Errores</div>
                        </div>
                    </div>
                    <div class="col s6 m3">
                        <div class="card-panel center-align yellow lighten-5">
                            <div class="orange-text" style="font-size:2rem;font-weight:700" id="cpSumWarn">0</div>
                            <div class="grey-text" style="font-size:.82rem">Advertencias</div>
                        </div>
                    </div>
                </div>

                <div id="cpErroresContainer" class="d-none">
                    <p class="red-text" style="font-weight:600"><i class="material-icons tiny">cancel</i> Errores encontrados</p>
                    <div id="cpErroresLista" style="max-height:150px;overflow-y:auto;border:1px solid #eee;border-radius:8px;padding:8px"></div>
                </div>
                <div id="cpWarnContainer" class="d-none" style="margin-top:.75rem">
                    <p class="orange-text" style="font-weight:600"><i class="material-icons tiny">warning</i> Advertencias</p>
                    <div id="cpAdvertenciasLista" style="max-height:120px;overflow-y:auto;border:1px solid #eee;border-radius:8px;padding:8px"></div>
                </div>

                <p style="font-weight:600;margin:1rem 0 .5rem"><i class="material-icons tiny">table_chart</i> Vista previa (primeras 50 filas)</p>
                <div style="overflow-x:auto">
                    <table class="striped highlight" id="cpPreviewTable" style="font-size:.78rem">
                        <thead class="blue darken-3 white-text">
                            <tr><th>#</th><th>Status</th><th>País</th><th>CP</th><th>Departamento</th><th>Municipio</th><th>Barrio</th><th>Localidad</th><th>Activo</th></tr>
                        </thead>
                        <tbody id="cpPreviewTbody">
                            <tr><td colspan="9" class="center-align grey-text">Cargando vista previa...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div><!-- /wizardStep2 -->

            <!-- ══ PASO 3: Resultado ══ -->
            <div class="wizard-step" id="wizardStep3" style="display:none">
                <div class="center-align" id="cpResEstado"></div>
                <div class="row">
                    <div class="col s6 m2"><div class="card-panel center-align"><div style="font-size:1.5rem;font-weight:700" id="cpResTotal">0</div><div class="grey-text" style="font-size:.8rem">Total</div></div></div>
                    <div class="col s6 m2"><div class="card-panel center-align green lighten-5"><div class="green-text" style="font-size:1.5rem;font-weight:700" id="cpResInsertadas">0</div><div class="grey-text" style="font-size:.8rem">Insertadas</div></div></div>
                    <div class="col s6 m2"><div class="card-panel center-align blue lighten-5"><div class="blue-text" style="font-size:1.5rem;font-weight:700" id="cpResActualizadas">0</div><div class="grey-text" style="font-size:.8rem">Actualizadas</div></div></div>
                    <div class="col s6 m2"><div class="card-panel center-align"><div class="grey-text" style="font-size:1.5rem;font-weight:700" id="cpResOmitidas">0</div><div class="grey-text" style="font-size:.8rem">Omitidas</div></div></div>
                    <div class="col s6 m2"><div class="card-panel center-align red lighten-5"><div class="red-text" style="font-size:1.5rem;font-weight:700" id="cpResFallidas">0</div><div class="grey-text" style="font-size:.8rem">Fallidas</div></div></div>
                    <div class="col s6 m2"><div class="card-panel center-align"><div style="font-size:1.3rem;font-weight:700" id="cpResTiempo">0s</div><div class="grey-text" style="font-size:.8rem">Tiempo</div></div></div>
                </div>
                <div id="cpLinkErrores" class="d-none"></div>
            </div><!-- /wizardStep3 -->

        </div><!-- /padding 1.5rem -->
    </div><!-- /modal-content -->

    <!-- FOOTER MODAL -->
    <div class="modal-footer" style="justify-content:space-between">
        <div>
            <a href="#!" id="btnCpVolver" class="btn-flat waves-effect grey-text" style="display:none">
                <i class="material-icons left">arrow_back</i>Volver
            </a>
        </div>
        <div class="d-flex gap-2">
            <a href="#!" class="modal-close btn-flat waves-effect grey-text">Cerrar</a>
            <button id="btnCpVistaPrev" class="btn btn-primary-mz waves-effect waves-light">
                <i class="material-icons left">visibility</i>Vista Previa
            </button>
            <button id="btnCpConfirmar" class="btn btn-success-mz waves-effect waves-light" disabled style="color:#1a73e8 !important">
                <i class="material-icons left">check_circle</i>Confirmar e Importar
            </button>
            <button id="btnCpNuevaImport" class="btn btn-primary-mz waves-effect waves-light" style="display:none">
                <i class="material-icons left">refresh</i>Nueva Importación
            </button>
        </div>
    </div>

</div><!-- /modal -->
<?php endif; ?>


<?php include("vista/includes/footer_materialize.php"); ?>


<!-- ════ JS: Toggle estado (activo/inactivo) ════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── Labels flotantes con valores GET precargados ───────────────────────
    // M.FormSelect ya fue inicializado en footer_materialize.php,
    // updateTextFields asegura que el label suba cuando el input tiene valor.
    M.updateTextFields();

    // ── Toggle activo ───────────────────────────────────────────────────────
    document.querySelectorAll('.btn-toggle').forEach(function(btn) {
        btn.addEventListener('change', function() {
            var id     = this.getAttribute('data-id');
            var status = this.checked;
            var self   = this;

            fetch('<?= RUTA_URL ?>codigos_postales/toggle/' + id, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.success) {
                    self.checked = !status;
                    Swal.fire('Error', res.message, 'error');
                }
            })
            .catch(function() {
                self.checked = !status;
                Swal.fire('Error', 'No se pudo comunicar con el servidor', 'error');
            });
        });
    });

    // ── Eliminar CP ─────────────────────────────────────────────────────────
    document.querySelectorAll('.btn-eliminar-cp').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id  = this.dataset.id;
            var cp  = this.dataset.cp;
            var row = this.closest('tr');

            Swal.fire({
                title: '¿Eliminar CP ' + cp + '?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e53935',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sí, eliminar',
            }).then(function(result) {
                if (!result.isConfirmed) return;
                fetch('<?= RUTA_URL ?>codigos_postales/eliminar/' + id, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        row.style.transition = 'opacity .3s';
                        row.style.opacity    = '0';
                        setTimeout(function() { row.remove(); }, 300);
                        Swal.fire({ icon: 'success', title: 'Eliminado', text: res.message, timer: 1800, showConfirmButton: false });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(function() { Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error'); });
            });
        });
    });

    // ── Control de botones del footer del modal según paso activo ──────────
    var modal = document.getElementById('modalImportarCp');
    if (!modal) return;

    var btnVP  = document.getElementById('btnCpVistaPrev');
    var btnCon = document.getElementById('btnCpConfirmar');
    var btnNue = document.getElementById('btnCpNuevaImport');
    var btnVol = document.getElementById('btnCpVolver');

    function showBtns(paso) {
        if (!btnVP || !btnCon || !btnNue || !btnVol) return;
        btnVP.style.display  = paso === 1 ? '' : 'none';
        btnCon.style.display = paso === 2 ? '' : 'none';
        btnNue.style.display = paso === 3 ? '' : 'none';
        btnVol.style.display = paso === 2 ? '' : 'none';
    }

    // Observar cambios en .wizard-step para detectar paso activo
    function detectarPasoActivo() {
        var pasos  = modal.querySelectorAll('.wizard-step');
        var activo = 1;
        pasos.forEach(function(p, i) {
            if (p.style.display !== 'none') activo = i + 1;
        });
        showBtns(activo);
    }

    var obs = new MutationObserver(detectarPasoActivo);
    modal.querySelectorAll('.wizard-step').forEach(function(el) {
        obs.observe(el, { attributes: true, attributeFilter: ['style'] });
    });

    // Reinicializar selects del modal cuando se abra
    modal.addEventListener('M_Modal_open', function() {
        showBtns(1);
        var selects = modal.querySelectorAll('select');
        selects.forEach(function(s) { reinitSelect(s); });
    });

});
</script>

<!-- JS del wizard (sin cambios — no tiene dependencias Bootstrap) -->
<script src="<?= RUTA_URL ?>vista/js/importar_cp.js"></script>
