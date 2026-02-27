<?php include("vista/includes/header_materialize.php"); ?>

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
}
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <form method="post" action="<?= RUTA_URL ?>paises/guardar" class="card form-card">
                <?php 
                require_once __DIR__ . '/../../../utils/csrf.php';
                echo csrf_field(); 
                ?>
                
                <div class="location-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0 fw-bold"><i class="bi bi-globe-americas me-2"></i> Nuevo País</h3>
                        <p class="mb-0 opacity-75 small">Registrar una nueva ubicación nacional</p>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Nombre del País <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light"><i class="bi bi-geo-alt"></i></span>
                            <input class="form-control" name="nombre" placeholder="Ej: México, Colombia, España..." required />
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Código ISO</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-upc-scan"></i></span>
                            <input class="form-control text-uppercase" name="codigo_iso" placeholder="Ej: MX, CO, ES" maxlength="5" />
                        </div>
                        <div class="form-text">Código internacional de 2 o 3 letras (Opcional).</div>
                    </div>
                </div>

                <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2 border-top-0">
                    <a class="btn btn-outline-secondary px-4" href="<?= RUTA_URL ?>paises/listar">Cancelar</a>
                    <button class="btn btn-info px-4 fw-bold shadow-sm text-white" type="submit" style="background: #2193b0; border-color: #2193b0;">
                        <i class="bi bi-save me-1"></i> Guardar País
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("vista/includes/footer_materialize.php"); ?>
