<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/departamento.php';
require_once __DIR__ . '/../../../controlador/pais.php';
$ctrl = new DepartamentosController();
$paisCtrl = new PaisesController();
$paises = $paisCtrl->listar();
$id = null;
if (isset($parametros) && isset($parametros[0])) {
    $id = (int)$parametros[0];
} else {
    $parts = explode('/', $_GET['enlace'] ?? '');
    $id = isset($parts[2]) ? (int)$parts[2] : null;
}
$d = $ctrl->ver($id);
if (!$d) { echo '<div class="container"><div class="alert alert-danger">Departamento no encontrado.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-warning text-white">
            <h3 class="card-title mb-0">Editar Departamento</h3>
        </div>
        <div class="card-body">
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
                <div class="d-flex justify-content-end">
                    <a class="btn btn-secondary me-2" href="<?= RUTA_URL ?>departamentos/listar">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include("vista/includes/footer.php"); ?>
