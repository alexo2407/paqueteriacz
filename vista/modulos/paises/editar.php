<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/pais.php';
$ctrl = new PaisesController();
$id = isset($ruta[2]) ? (int)$ruta[2] : null;
$p = $ctrl->ver($id);
if (!$p) { echo '<div class="container"><div class="alert alert-danger">País no encontrado.</div></div>'; include("vista/includes/footer.php"); exit; }
?>
<div class="container">
    <h2>Editar País</h2>
    <form method="post" action="<?= RUTA_URL ?>paises/actualizar/<?= urlencode($p['id']) ?>">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="nombre" value="<?= htmlspecialchars($p['nombre']) ?>" required />
        </div>
        <div class="mb-3">
            <label class="form-label">Código ISO</label>
            <input class="form-control" name="codigo_iso" value="<?= htmlspecialchars($p['codigo_iso'] ?? '') ?>" />
        </div>
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>paises/listar">Cancelar</a>
    </form>
</div>
<?php include("vista/includes/footer.php"); ?>
