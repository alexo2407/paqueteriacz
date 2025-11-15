<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/barrio.php';
require_once __DIR__ . '/../../../controlador/municipio.php';
$ctrl = new BarriosController();
$munCtrl = new MunicipiosController();
$municipios = $munCtrl->listar();
$id = isset($ruta[2]) ? (int)$ruta[2] : null;
$b = $ctrl->ver($id);
if (!$b) { echo '<div class="container"><div class="alert alert-danger">Barrio no encontrado.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container">
    <h2>Editar Barrio</h2>
    <form method="post" action="<?= RUTA_URL ?>barrios/actualizar/<?= urlencode($b['id']) ?>">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="nombre" value="<?= htmlspecialchars($b['nombre']) ?>" required />
        </div>
        <div class="mb-3">
            <label class="form-label">Municipio</label>
            <select name="id_municipio" class="form-control" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($municipios as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $m['id']==$b['id_municipio']? 'selected':'' ?>><?= htmlspecialchars($m['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>barrios/listar">Cancelar</a>
    </form>
</div>
<?php include("vista/includes/footer.php"); ?>
