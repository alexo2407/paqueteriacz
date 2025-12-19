<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/departamento.php';
$depCtrl = new DepartamentosController();
$departamentos = $depCtrl->listar();
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h3 class="card-title mb-0">Crear Municipio</h3>
        </div>
        <div class="card-body">
            <form method="post" action="<?= RUTA_URL ?>municipios/guardar">
                <?php 
                require_once __DIR__ . '/../../../utils/csrf.php';
                echo csrf_field(); 
                ?>
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
                <div class="d-flex justify-content-end">
                    <a class="btn btn-secondary me-2" href="<?= RUTA_URL ?>municipios/listar">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include("vista/includes/footer.php"); ?>
