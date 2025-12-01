<?php
include("vista/includes/header.php");
$ctrl = new MonedasController();
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h3 class="card-title mb-0">Crear Moneda</h3>
        </div>
        <div class="card-body">
            <form method="post" action="<?= RUTA_URL ?>monedas/guardar">
                <div class="mb-3">
                    <label class="form-label">Código (ISO)</label>
                    <input class="form-control" name="codigo" placeholder="Ej: USD, EUR, NIO" required />
                </div>
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input class="form-control" name="nombre" placeholder="Ej: Dólar Estadounidense" required />
                </div>
                <div class="mb-3">
                    <label class="form-label">Tasa de Cambio (respecto a USD)</label>
                    <input type="number" step="0.0001" class="form-control" name="tasa_usd" placeholder="Ej: 1.0000" />
                </div>
                <div class="d-flex justify-content-end">
                    <a class="btn btn-secondary me-2" href="<?= RUTA_URL ?>monedas/listar">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
