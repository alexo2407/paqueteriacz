<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/moneda.php';
$ctrl = new MonedasController();
$id = isset($ruta[2]) ? (int)$ruta[2] : null;
$m = $ctrl->ver($id);
if (!$m) { echo '<div class="container"><div class="alert alert-danger">Moneda no encontrada.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container">
    <h2>Moneda: <?= htmlspecialchars($m['nombre']) ?></h2>
    <dl class="row">
        <dt class="col-sm-3">ID</dt><dd class="col-sm-9"><?= $m['id'] ?></dd>
        <dt class="col-sm-3">Código</dt><dd class="col-sm-9"><?= htmlspecialchars($m['codigo']) ?></dd>
        <dt class="col-sm-3">Tasa USD</dt><dd class="col-sm-9"><?= htmlspecialchars($m['tasa_usd'] ?? '') ?></dd>
    </dl>
    <a class="btn btn-secondary" href="<?= RUTA_URL ?>monedas/listar">Volver</a>
</div>

<?php include("vista/includes/footer.php"); ?>
