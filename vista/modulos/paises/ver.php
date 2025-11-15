<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/pais.php';
$ctrl = new PaisesController();
$id = isset($ruta[2]) ? (int)$ruta[2] : null;
$p = $ctrl->ver($id);
if (!$p) { echo '<div class="container"><div class="alert alert-danger">País no encontrado.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container">
    <h2>País: <?= htmlspecialchars($p['nombre']) ?></h2>
    <dl class="row">
        <dt class="col-sm-3">ID</dt><dd class="col-sm-9"><?= $p['id'] ?></dd>
        <dt class="col-sm-3">Código ISO</dt><dd class="col-sm-9"><?= htmlspecialchars($p['codigo_iso'] ?? '') ?></dd>
    </dl>
    <a class="btn btn-secondary" href="<?= RUTA_URL ?>paises/listar">Volver</a>
</div>
<?php include("vista/includes/footer.php"); ?>
