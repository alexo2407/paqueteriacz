<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/departamento.php';
$depCtrl = new DepartamentosController();
$departamentos = $depCtrl->listar();
?>
<div class="container">
    <h2>Crear Municipio</h2>
    <form method="post" action="<?= RUTA_URL ?>municipios/guardar">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="nombre" required />
        </div>
        <div class="mb-3">
            <label class="form-label">Departamento</label>
            <select name="id_departamento" class="form-control" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($departamentos as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>municipios/listar">Cancelar</a>
    </form>
</div>
<?php include("vista/includes/footer.php"); ?>
