<?php include("vista/includes/header.php"); ?>
<?php 
require_once __DIR__ . '/../../../controlador/pais.php';
$paisCtrl = new PaisesController();
$paises = $paisCtrl->listar();
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
            <form method="post" action="<?= RUTA_URL ?>departamentos/guardar" class="card form-card">
                <?php 
                require_once __DIR__ . '/../../../utils/csrf.php';
                echo csrf_field(); 
                ?>
                
                <div class="location-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0 fw-bold"><i class="bi bi-map me-2"></i> Nuevo Departamento</h3>
                        <p class="mb-0 opacity-75 small">Registrar una división administrativa de nivel 2</p>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold">País Perteneciente <span class="text-danger">*</span></label>
                        <select name="id_pais" class="form-select select2" required style="width: 100%;">
                            <option value="">-- Seleccione un País --</option>
                            <?php foreach ($paises as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">El departamento estará asociado a este país.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Nombre del Departamento <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light"><i class="bi bi-geo"></i></span>
                            <input class="form-control" name="nombre" placeholder="Ej: Antioquia, Cundinamarca, Managua..." required />
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2 border-top-0">
                    <a class="btn btn-outline-secondary px-4" href="<?= RUTA_URL ?>departamentos/listar">Cancelar</a>
                    <button class="btn btn-info px-4 fw-bold shadow-sm text-white" type="submit" style="background: #2193b0; border-color: #2193b0;">
                        <i class="bi bi-save me-1"></i> Guardar Departamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: "bootstrap-5",
            width: '100%',
            placeholder: "Seleccione un País",
            allowClear: true
        });
    });
</script>
