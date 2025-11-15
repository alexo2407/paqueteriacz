<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/departamento.php';
require_once __DIR__ . '/../../../controlador/pais.php';
$ctrl = new DepartamentosController();
$paisCtrl = new PaisesController();
$paises = $paisCtrl->listar();
$id = isset($ruta[2]) ? (int)$ruta[2] : null;
$d = $ctrl->ver($id);
if (!$d) { echo '<div class="container"><div class="alert alert-danger">Departamento no encontrado.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container">
    <h2>Editar Departamento</h2>
    <form method="post" action="<?= RUTA_URL ?>departamentos/actualizar/<?= urlencode($d['id']) ?>">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="nombre" value="<?= htmlspecialchars($d['nombre']) ?>" required />
        </div>
        <div class="mb-3">
            <label class="form-label">País</label>
            <select name="id_pais" class="form-control" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($paises as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $p['id']==$d['id_pais']? 'selected':'' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>departamentos/listar">Cancelar</a>
    </form>
</div>
<?php include("vista/includes/footer.php"); ?>
