<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/pais.php';
$paisCtrl = new PaisesController();
$paises = $paisCtrl->listar();
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h3 class="card-title mb-0">Crear Departamento</h3>
        </div>
        <div class="card-body">
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
                <div class="d-flex justify-content-end">
                    <a class="btn btn-secondary me-2" href="<?= RUTA_URL ?>departamentos/listar">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include("vista/includes/footer.php"); ?>
