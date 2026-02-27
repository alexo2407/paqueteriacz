<?php include("vista/includes/header_materialize.php"); ?>
<?php 
require_once __DIR__ . '/../../../controlador/departamento.php';
$depCtrl = new DepartamentosController();
$departamentos = $depCtrl->listar();
?>

<style>
.location-header {
    background: linear-gradient(135deg, #005C97 0%, #363795 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px 12px 0 0;
}
.form-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    overflow: hidden;
}
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <form method="post" action="<?= RUTA_URL ?>municipios/guardar" class="card form-card">
                <?php 
                require_once __DIR__ . '/../../../utils/csrf.php';
                echo csrf_field(); 
                ?>
                
                <div class="location-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0 fw-bold"><i class="bi bi-geo-alt me-2"></i> Nuevo Municipio</h3>
                        <p class="mb-0 opacity-75 small">Registrar una ciudad o municipio (Nivel 3)</p>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Departamento Perteneciente <span class="text-danger">*</span></label>
                        <select name="id_departamento" class="form-select select2" required style="width: 100%;">
                            <option value="">-- Seleccione un Departamento --</option>
                            <?php foreach ($departamentos as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">El municipio estará asociado a este departamento.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Nombre del Municipio <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light"><i class="bi bi-geo"></i></span>
                            <input class="form-control" name="nombre" placeholder="Ej: Medellín, Guadalajara, Estelí..." required />
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Código Postal</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-mailbox"></i></span>
                            <input class="form-control" name="codigo_postal" placeholder="Ej: 17008, 10101..." />
                        </div>
                        <div class="form-text">Opcional. Se usará si el país define CP a nivel de municipio.</div>
                    </div>
                </div>

                <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2 border-top-0">
                    <a class="btn btn-outline-secondary px-4" href="<?= RUTA_URL ?>municipios/listar">Cancelar</a>
                    <button class="btn btn-info px-4 fw-bold shadow-sm text-white" type="submit" style="background: #2193b0; border-color: #2193b0;">
                        <i class="bi bi-save me-1"></i> Guardar Municipio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("vista/includes/footer_materialize.php"); ?>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: "bootstrap-5",
            width: '100%',
            placeholder: "Seleccione un Departamento",
            allowClear: true
        });
    });
</script>
