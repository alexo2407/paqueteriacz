<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/municipio.php';
$munCtrl = new MunicipiosController();
$municipios = $munCtrl->listar();
?>
<div class="container">
    <h2>Crear Barrio</h2>
    <form method="post" action="<?= RUTA_URL ?>barrios/guardar">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="nombre" required />
        </div>
        <div class="mb-3">
            <label class="form-label">Municipio</label>
            <select name="id_municipio" class="form-control" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($municipios as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>barrios/listar">Cancelar</a>
    </form>
</div>
<?php include("vista/includes/footer.php"); ?>
