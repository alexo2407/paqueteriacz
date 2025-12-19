<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/pais.php';
$ctrl = new PaisesController();
$id = null;
if (isset($parametros) && isset($parametros[0])) {
    $id = (int)$parametros[0];
} else {
    $parts = explode('/', $_GET['enlace'] ?? '');
    $id = isset($parts[2]) ? (int)$parts[2] : null;
}
$p = $ctrl->ver($id);
if (!$p) { echo '<div class="container"><div class="alert alert-danger">País no encontrado.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-warning text-white">
            <h3 class="card-title mb-0">Editar País</h3>
        </div>
        <div class="card-body">
            <form method="post" action="<?= RUTA_URL ?>paises/actualizar/<?= urlencode($p['id']) ?>">
                <?php 
                require_once __DIR__ . '/../../../utils/csrf.php';
                echo csrf_field(); 
                ?>
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input class="form-control" name="nombre" value="<?= htmlspecialchars($p['nombre']) ?>" required />
                </div>
                <div class="mb-3">
                    <label class="form-label">Código ISO</label>
                    <input class="form-control" name="codigo_iso" value="<?= htmlspecialchars($p['codigo_iso'] ?? '') ?>" />
                </div>
                <div class="d-flex justify-content-end">
                    <a class="btn btn-secondary me-2" href="<?= RUTA_URL ?>paises/listar">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include("vista/includes/footer.php"); ?>
