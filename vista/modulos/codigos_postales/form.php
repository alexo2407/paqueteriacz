<?php include("vista/includes/header.php"); ?>

<?php
$ctrl = new CodigosPostalesController();
$paisCtrl = new PaisesController();

$id = isset($parametros[0]) ? (int)$parametros[0] : 0;
$item = null;
if ($id > 0) {
    $item = $ctrl->ver($id);
}

// Repoblar si hubo error (desde sesión)
$old = $_SESSION['old_cp'] ?? null;
if (isset($_SESSION['old_cp'])) unset($_SESSION['old_cp']);

$paises = $paisCtrl->listar();

$esEditar = ($id > 0);
$titulo = $esEditar ? 'Editar Código Postal' : 'Nuevo Código Postal';
$accionUrl = RUTA_URL . 'codigos_postales/' . ($esEditar ? 'actualizar/' . $id : 'guardar');

// Valores por defecto
$id_pais = $old['id_pais'] ?? ($item['id_pais'] ?? '');
$cp = $old['codigo_postal'] ?? ($item['codigo_postal'] ?? '');
$id_dep = $old['id_departamento'] ?? ($item['id_departamento'] ?? '');
$id_mun = $old['id_municipio'] ?? ($item['id_municipio'] ?? '');
$id_bar = $old['id_barrio'] ?? ($item['id_barrio'] ?? '');
$localidad = $old['nombre_localidad'] ?? ($item['nombre_localidad'] ?? '');
$activo = $old['activo'] ?? ($item['activo'] ?? 1);
?>

<style>
.form-header {
    background: linear-gradient(135deg, #1d976c 0%, #93f9b9 100%);
    color: white;
    padding: 1.5rem 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}
.card-form {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="form-header d-flex align-items-center gap-3">
                <div class="bg-white bg-opacity-25 rounded-circle p-3">
                    <i class="bi <?= $esEditar ? 'bi-pencil-square' : 'bi-plus-circle' ?> fs-3"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $titulo ?></h3>
                    <p class="mb-0 opacity-75"><?= $esEditar ? 'Actualiza la información del código postal homologado' : 'Registra un nuevo código postal en la fuente de verdad' ?></p>
                </div>
            </div>

            <div class="card card-form">
                <div class="card-body p-4">
                    <form action="<?= $accionUrl ?>" method="POST" id="formCP">
                        <div class="row g-3">
                            <!-- País -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">País <span class="text-danger">*</span></label>
                                <select name="id_pais" id="id_pais" class="form-select" required>
                                    <option value="">Selecciona un país</option>
                                    <?php foreach ($paises as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $id_pais == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Código Postal -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Código Postal <span class="text-danger">*</span></label>
                                <input type="text" name="codigo_postal" id="codigo_postal" class="form-control" 
                                       placeholder="Ej: 10101" required value="<?= htmlspecialchars($cp) ?>">
                                <div class="form-text small">Se normalizará automáticamente (Mayúsculas, sin espacios/guiones).</div>
                            </div>

                            <hr class="my-3">
                            <h5 class="text-muted small text-uppercase fw-bold">Ubicación Geográfica</h5>

                            <!-- Departamento -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Departamento / Provincia</label>
                                <select name="id_departamento" id="id_departamento" class="form-select">
                                    <option value="">Selecciona un departamento</option>
                                </select>
                            </div>

                            <!-- Municipio -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Municipio / Ciudad</label>
                                <select name="id_municipio" id="id_municipio" class="form-select">
                                    <option value="">Selecciona un municipio</option>
                                </select>
                            </div>

                            <!-- Barrio (Opcional) -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Barrio / Zona (Opcional)</label>
                                <select name="id_barrio" id="id_barrio" class="form-select">
                                    <option value="">Selecciona un barrio</option>
                                </select>
                            </div>

                            <!-- Localidad Texto -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Nombre Localidad (Referencia)</label>
                                <input type="text" name="nombre_localidad" class="form-control" 
                                       placeholder="Ej: Santa Elena, Zona 10..." value="<?= htmlspecialchars($localidad) ?>">
                            </div>

                            <!-- Estado -->
                            <div class="col-12">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" <?= $activo ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="activo">Registro activo y disponible para homologación</label>
                                </div>
                            </div>

                            <div class="col-12 mt-4 d-flex justify-content-between border-top pt-3">
                                <a href="<?= RUTA_URL ?>codigos_postales" class="btn btn-outline-secondary px-4">
                                    <i class="bi bi-x-circle me-1"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary px-5 shadow">
                                    <i class="bi bi-check-circle me-1"></i> <?= $esEditar ? 'Actualizar Registro' : 'Guardar Código Postal' ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sPais = document.getElementById('id_pais');
    const sDepto = document.getElementById('id_departamento');
    const sMuni = document.getElementById('id_municipio');
    const sBarrio = document.getElementById('id_barrio');

    const initialValues = {
        depto: "<?= $id_dep ?>",
        muni: "<?= $id_mun ?>",
        barrio: "<?= $id_bar ?>"
    };

    function clearSelect(select, placeholder) {
        select.innerHTML = `<option value="">${placeholder}</option>`;
    }

    // Cargar Departamentos
    function loadDeptos(paisId, selectedId = '') {
        if (!paisId) {
            clearSelect(sDepto, 'Selecciona un país primero');
            return;
        }
        fetch(`<?= RUTA_URL ?>api/geoinfo/departamentos?id_pais=${paisId}`)
            .then(r => r.json())
            .then(data => {
                clearSelect(sDepto, 'Selecciona un departamento');
                data.forEach(d => {
                    const sel = (selectedId == d.id) ? 'selected' : '';
                    sDepto.innerHTML += `<option value="${d.id}" ${sel}>${d.nombre}</option>`;
                });
                if (selectedId) loadMunis(selectedId, initialValues.muni);
            });
    }

    // Cargar Municipios
    function loadMunis(deptoId, selectedId = '') {
        if (!deptoId) {
            clearSelect(sMuni, 'Selecciona un departamento primero');
            return;
        }
        fetch(`<?= RUTA_URL ?>api/geoinfo/municipios?id_departamento=${deptoId}`)
            .then(r => r.json())
            .then(data => {
                clearSelect(sMuni, 'Selecciona un municipio');
                data.forEach(m => {
                    const sel = (selectedId == m.id) ? 'selected' : '';
                    sMuni.innerHTML += `<option value="${m.id}" ${sel}>${m.nombre}</option>`;
                });
                if (selectedId) loadBarrios(selectedId, initialValues.barrio);
            });
    }

    // Cargar Barrios
    function loadBarrios(muniId, selectedId = '') {
        if (!muniId) {
            clearSelect(sBarrio, 'Selecciona un municipio primero');
            return;
        }
        fetch(`<?= RUTA_URL ?>api/geoinfo/barrios?id_municipio=${muniId}`)
            .then(r => r.json())
            .then(data => {
                clearSelect(sBarrio, 'Selecciona un barrio');
                data.forEach(b => {
                    const sel = (selectedId == b.id) ? 'selected' : '';
                    sBarrio.innerHTML += `<option value="${b.id}" ${sel}>${b.nombre}</option>`;
                });
            });
    }

    // Event Listeners
    sPais.addEventListener('change', function() {
        loadDeptos(this.value);
        clearSelect(sMuni, 'Selecciona un departamento primero');
        clearSelect(sBarrio, 'Selecciona un municipio primero');
    });

    sDepto.addEventListener('change', function() {
        loadMunis(this.value);
        clearSelect(sBarrio, 'Selecciona un municipio primero');
    });

    sMuni.addEventListener('change', function() {
        loadBarrios(this.value);
    });

    // Carga inicial (edición o repoblación por error)
    if (sPais.value) {
        loadDeptos(sPais.value, initialValues.depto);
    }
});
</script>
