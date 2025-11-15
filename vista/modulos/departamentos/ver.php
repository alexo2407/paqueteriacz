<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/departamento.php';
require_once __DIR__ . '/../../../controlador/pais.php';
$ctrl = new DepartamentosController();
$paisCtrl = new PaisesController();
$id = isset($ruta[2]) ? (int)$ruta[2] : null;
$d = $ctrl->ver($id);
$pais = $paisCtrl->ver($d['id_pais'] ?? null);
if (!$d) { echo '<div class="container"><div class="alert alert-danger">Departamento no encontrado.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container">
    <h2>Departamento: <?= htmlspecialchars($d['nombre']) ?></h2>
    <dl class="row">
        <dt class="col-sm-3">ID</dt><dd class="col-sm-9"><?= $d['id'] ?></dd>
        <dt class="col-sm-3">País</dt><dd class="col-sm-9"><?= htmlspecialchars($pais['nombre'] ?? '') ?></dd>
    </dl>
    <a class="btn btn-secondary" href="<?= RUTA_URL ?>departamentos/listar">Volver</a>
</div>
<?php include("vista/includes/footer.php"); ?>
