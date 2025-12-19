<?php
include("vista/includes/header.php");
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h3 class="card-title mb-0">Crear País</h3>
        </div>
        <div class="card-body">
            <form method="post" action="<?= RUTA_URL ?>paises/guardar">
                <?php 
                require_once __DIR__ . '/../../../utils/csrf.php';
                echo csrf_field(); 
                ?>
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input class="form-control" name="nombre" placeholder="Ej: Nicaragua" required />
                </div>
                <div class="mb-3">
                    <label class="form-label">Código ISO</label>
                    <input class="form-control" name="codigo_iso" placeholder="Ej: NI" />
                </div>
                <div class="d-flex justify-content-end">
                    <a class="btn btn-secondary me-2" href="<?= RUTA_URL ?>paises/listar">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include("vista/includes/footer.php"); ?>
