<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/moneda.php';
$ctrl = new MonedasController();
$id = null;
if (isset($parametros) && isset($parametros[0])) {
    $id = (int)$parametros[0];
} else {
    $parts = explode('/', $_GET['enlace'] ?? '');
    $id = isset($parts[2]) ? (int)$parts[2] : null;
}
$m = $ctrl->ver($id);
if (!$m) { echo '<div class="container"><div class="alert alert-danger">Moneda no encontrada.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-warning text-white">
            <h3 class="card-title mb-0">Editar Moneda</h3>
        </div>
        <div class="card-body">
            <form method="post" action="<?= RUTA_URL ?>monedas/actualizar/<?= urlencode($m['id']) ?>">
                <div class="mb-3">
                    <label class="form-label">Código (ISO)</label>
                    <input class="form-control" name="codigo" value="<?= htmlspecialchars($m['codigo']) ?>" required />
                </div>
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input class="form-control" name="nombre" value="<?= htmlspecialchars($m['nombre']) ?>" required />
                </div>
                <div class="mb-3">
                    <label class="form-label">Tasa de Cambio (respecto a USD)</label>
                    <input type="number" step="0.0001" class="form-control" name="tasa_usd" value="<?= htmlspecialchars($m['tasa_usd'] ?? '') ?>" />
                </div>
                <div class="d-flex justify-content-end">
                    <a class="btn btn-secondary me-2" href="<?= RUTA_URL ?>monedas/listar">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
