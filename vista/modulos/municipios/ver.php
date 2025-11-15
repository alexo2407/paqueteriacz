<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/municipio.php';
require_once __DIR__ . '/../../../controlador/departamento.php';
$ctrl = new MunicipiosController();
$depCtrl = new DepartamentosController();
$id = isset($ruta[2]) ? (int)$ruta[2] : null;
$m = $ctrl->ver($id);
$d = $depCtrl->ver($m['id_departamento'] ?? null);
if (!$m) { echo '<div class="container"><div class="alert alert-danger">Municipio no encontrado.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container">
    <h2>Municipio: <?= htmlspecialchars($m['nombre']) ?></h2>
    <dl class="row">
        <dt class="col-sm-3">ID</dt><dd class="col-sm-9"><?= $m['id'] ?></dd>
        <dt class="col-sm-3">Departamento</dt><dd class="col-sm-9"><?= htmlspecialchars($d['nombre'] ?? '') ?></dd>
    </dl>
    <a class="btn btn-secondary" href="<?= RUTA_URL ?>municipios/listar">Volver</a>
</div>
<?php include("vista/includes/footer.php"); ?>
