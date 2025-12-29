<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/usuario.php';
require_once __DIR__ . '/../../../controlador/pais.php';

$paisesCtrl = new PaisesController();
$paises = $paisesCtrl->listar();

$roles = UsuariosController::obtenerRolesDisponibles();
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-success text-white">
            <h3 class="card-title mb-0">Crear Nuevo Usuario</h3>
        </div>
        <div class="card-body">
            <form method="post">
                <?php 
                require_once __DIR__ . '/../../../utils/csrf.php';
                echo csrf_field(); 
                ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre" required placeholder="Ingrese nombre completo">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required placeholder="ejemplo@correo.com">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required placeholder="Ingrese contraseña">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" class="form-control" name="telefono" placeholder="Opcional">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">País</label>
                        <select class="form-control select2-searchable" name="id_pais" data-placeholder="Buscar país...">
                            <option value="">-- Seleccione País --</option>
                            <?php foreach ($paises as $pais): ?>
                                <option value="<?= $pais['id'] ?>"><?= htmlspecialchars($pais['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Roles</label>
                        <div class="border p-2 rounded" style="max-height: 150px; overflow-y: auto;">
                            <?php foreach ($roles as $idRol => $nombreRol): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="roles[]" value="<?= $idRol ?>" id="rol_<?= $idRol ?>">
                                    <label class="form-check-label" for="rol_<?= $idRol ?>">
                                        <?= htmlspecialchars($nombreRol) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="activo" name="activo" checked>
                        <label class="form-check-label" for="activo">Usuario Activo</label>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <a href="<?= RUTA_URL ?>usuarios/listar" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-success">Guardar Usuario</button>
                </div>

                <?php
                $crearUsuario = new UsuariosController();
                $crearUsuario->crearUsuarioController();
                ?>
            </form>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
