<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/moneda.php';
$ctrl = new MonedasController();
$id = isset($ruta[2]) ? (int)$ruta[2] : null;
$m = $ctrl->ver($id);
if (!$m) { echo '<div class="container"><div class="alert alert-danger">Moneda no encontrada.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container">
    <h2>Editar Moneda</h2>
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
            <label class="form-label">Tasa USD</label>
            <input class="form-control" name="tasa_usd" value="<?= htmlspecialchars($m['tasa_usd'] ?? '') ?>" />
        </div>
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>monedas/listar">Cancelar</a>
    </form>
</div>

<?php include("vista/includes/footer.php"); ?>
