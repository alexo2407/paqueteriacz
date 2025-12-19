<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/municipio.php';
require_once __DIR__ . '/../../../controlador/departamento.php';
$ctrl = new MunicipiosController();
$depCtrl = new DepartamentosController();
$departamentos = $depCtrl->listar();
$id = null;
if (isset($parametros) && isset($parametros[0])) {
    $id = (int)$parametros[0];
} else {
    $parts = explode('/', $_GET['enlace'] ?? '');
    $id = isset($parts[2]) ? (int)$parts[2] : null;
}
$m = $ctrl->ver($id);
if (!$m) { echo '<div class="container"><div class="alert alert-danger">Municipio no encontrado.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-warning text-white">
            <h3 class="card-title mb-0">Editar Municipio</h3>
        </div>
        <div class="card-body">
            <form method="post" action="<?= RUTA_URL ?>municipios/actualizar/<?= urlencode($m['id']) ?>">
                <?php 
                require_once __DIR__ . '/../../../utils/csrf.php';
                echo csrf_field(); 
                ?>
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
                <div class="d-flex justify-content-end">
                    <a class="btn btn-secondary me-2" href="<?= RUTA_URL ?>municipios/listar">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include("vista/includes/footer.php"); ?>
