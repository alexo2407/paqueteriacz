<?php include("vista/includes/header_materialize.php"); ?>
<?php 
$ctrl = new MonedasController();
?>

<style>
.form-header {
    background: linear-gradient(135deg, #FF512F 0%, #DD2476 100%);
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
            <form method="post" action="<?= RUTA_URL ?>monedas/guardar" class="card form-card">
                <?php 
                require_once __DIR__ . '/../../../utils/csrf.php';
                echo csrf_field(); 
                ?>
                
                <div class="form-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0 fw-bold"><i class="bi bi-currency-exchange me-2"></i> Crear Moneda</h3>
                        <p class="mb-0 opacity-75 small">Registrar una nueva divisa y su tasa de cambio</p>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Código (ISO) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light"><i class="bi bi-upc-scan"></i></span>
                            <input class="form-control text-uppercase" name="codigo" placeholder="Ej: USD, EUR" required maxlength="5" />
                        </div>
                        <div class="form-text">Código internacional de 3 letras (ej. USD para Dólar)</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Nombre de la Moneda <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-type"></i></span>
                            <input class="form-control" name="nombre" placeholder="Ej: Dólar Estadounidense" required />
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Tasa de Cambio (vs USD)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-graph-up-arrow"></i></span>
                            <input type="number" step="0.0001" min="0" class="form-control" name="tasa_usd" placeholder="Ej: 1.0000" />
                        </div>
                        <div class="form-text">Valor de 1 unidad de esta moneda en USD (Dólares).</div>
                    </div>
                </div>

                <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2 border-top-0">
                    <a class="btn btn-outline-secondary px-4" href="<?= RUTA_URL ?>monedas/listar">Cancelar</a>
                    <button class="btn btn-warning px-4 fw-bold shadow-sm text-white" type="submit">
                        <i class="bi bi-save me-1"></i> Guardar Moneda
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("vista/includes/footer_materialize.php"); ?>
