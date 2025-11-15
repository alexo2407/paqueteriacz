<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/municipio.php';
require_once __DIR__ . '/../../../controlador/departamento.php';
$ctrl = new MunicipiosController();
$depCtrl = new DepartamentosController();
$departamentos = $depCtrl->listar();
$id = isset($ruta[2]) ? (int)$ruta[2] : null;
$m = $ctrl->ver($id);
if (!$m) { echo '<div class="container"><div class="alert alert-danger">Municipio no encontrado.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container">
    <h2>Editar Municipio</h2>
    <form method="post" action="<?= RUTA_URL ?>municipios/actualizar/<?= urlencode($m['id']) ?>">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="nombre" value="<?= htmlspecialchars($m['nombre']) ?>" required />
        </div>
        <div class="mb-3">
            <label class="form-label">Departamento</label>
            <select name="id_departamento" class="form-control" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($departamentos as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $d['id']==$m['id_departamento']? 'selected':'' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>municipios/listar">Cancelar</a>
    </form>
</div>
<?php include("vista/includes/footer.php"); ?>
