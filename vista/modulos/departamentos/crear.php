<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/pais.php';
$paisCtrl = new PaisesController();
$paises = $paisCtrl->listar();
?>
<div class="container">
    <h2>Crear Departamento</h2>
    <form method="post" action="<?= RUTA_URL ?>departamentos/guardar">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="nombre" required />
        </div>
        <div class="mb-3">
            <label class="form-label">País</label>
            <select name="id_pais" class="form-control" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($paises as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>departamentos/listar">Cancelar</a>
    </form>
</div>
<?php include("vista/includes/footer.php"); ?>
