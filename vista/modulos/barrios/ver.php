<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/barrio.php';
require_once __DIR__ . '/../../../controlador/municipio.php';
$ctrl = new BarriosController();
$munCtrl = new MunicipiosController();
$id = isset($ruta[2]) ? (int)$ruta[2] : null;
$b = $ctrl->ver($id);
$m = $munCtrl->ver($b['id_municipio'] ?? null);
if (!$b) { echo '<div class="container"><div class="alert alert-danger">Barrio no encontrado.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container">
    <h2>Barrio: <?= htmlspecialchars($b['nombre']) ?></h2>
    <dl class="row">
        <dt class="col-sm-3">ID</dt><dd class="col-sm-9"><?= $b['id'] ?></dd>
        <dt class="col-sm-3">Municipio</dt><dd class="col-sm-9"><?= htmlspecialchars($m['nombre'] ?? '') ?></dd>
    </dl>
    <a class="btn btn-secondary" href="<?= RUTA_URL ?>barrios/listar">Volver</a>
</div>
<?php include("vista/includes/footer.php"); ?>
