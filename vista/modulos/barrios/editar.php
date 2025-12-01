<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/barrio.php';
require_once __DIR__ . '/../../../controlador/municipio.php';
$ctrl = new BarriosController();
$munCtrl = new MunicipiosController();
$municipios = $munCtrl->listar();
$id = null;
if (isset($parametros) && isset($parametros[0])) {
    $id = (int)$parametros[0];
} else {
    $parts = explode('/', $_GET['enlace'] ?? '');
    $id = isset($parts[2]) ? (int)$parts[2] : null;
}
$b = $ctrl->ver($id);
if (!$b) { echo '<div class="container"><div class="alert alert-danger">Barrio no encontrado.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-warning text-white">
            <h3 class="card-title mb-0">Editar Barrio</h3>
        </div>
        <div class="card-body">
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
                <div class="d-flex justify-content-end">
                    <a class="btn btn-secondary me-2" href="<?= RUTA_URL ?>barrios/listar">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include("vista/includes/footer.php"); ?>
