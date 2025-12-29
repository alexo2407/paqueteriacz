<?php include("vista/includes/header.php"); ?>

<?php
$params = isset($parametros) ? $parametros : [];
$idUsuario = isset($params[0]) ? (int) $params[0] : 0;

$usuarioCtrl = new UsuariosController();
$usuario = $idUsuario > 0 ? $usuarioCtrl->verUsuario($idUsuario) : null;
$rolesDisponibles = UsuariosController::obtenerRolesDisponibles();
// Obtener roles actuales del usuario (multi-rol)
require_once __DIR__ . '/../../../modelo/usuario.php';
$um = new UsuarioModel();
$rolesUsuario = $idUsuario > 0 ? $um->obtenerRolesDeUsuario($idUsuario) : ['ids' => [], 'nombres' => []];
$rolesUsuarioIds = $rolesUsuario['ids'] ?? [];

// Cargar países para el select
require_once __DIR__ . '/../../../controlador/pais.php';
$paisesCtrl = new PaisesController();
$paises = $paisesCtrl->listar();
?>

<div class="row">
	<div class="col-sm-8">
		<h3>Editar Usuario</h3>
		<?php if (!$usuario) : ?>
			<div class="alert alert-danger">Usuario no encontrado.</div>
		<?php else : ?>
			<form method="POST" action="<?= RUTA_URL ?>usuarios/actualizar/<?= $idUsuario; ?>">
				<?php 
				require_once __DIR__ . '/../../../utils/csrf.php';
				echo csrf_field(); 
				?>
				<div class="mb-3">
					<label class="form-label" for="nombre">Nombre completo</label>
					<input id="nombre" name="nombre" class="form-control" required value="<?= htmlspecialchars($usuario['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
				</div>
				<div class="mb-3">
					<label class="form-label" for="email">Correo electrónico</label>
					<input id="email" name="email" type="email" class="form-control" required value="<?= htmlspecialchars($usuario['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
				</div>
				<div class="mb-3">
					<label class="form-label" for="telefono">Teléfono</label>
					<input id="telefono" name="telefono" class="form-control" value="<?= htmlspecialchars($usuario['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
				</div>
				<div class="mb-3">
					<label class="form-label" for="id_pais">País</label>
					<select id="id_pais" name="id_pais" class="form-control select2-searchable" data-placeholder="Seleccionar país...">
						<option value="">-- Seleccione País --</option>
						<?php foreach ($paises as $pais): ?>
							<option value="<?= $pais['id'] ?>" <?= (isset($usuario['id_pais']) && $usuario['id_pais'] == $pais['id']) ? 'selected' : '' ?>><?= htmlspecialchars($pais['nombre']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="mb-3">
					<label class="form-label">Roles</label>
					<div class="row">
						<?php foreach ($rolesDisponibles as $rolId => $rolNombre) : ?>
							<div class="col-md-6">
								<div class="form-check">
									<input class="form-check-input" type="checkbox" id="rol_<?= (int)$rolId ?>" name="roles[]" value="<?= (int)$rolId ?>" <?= in_array((int)$rolId, $rolesUsuarioIds, true) || ((int)($usuario['id_rol'] ?? 0) === (int)$rolId && empty($rolesUsuarioIds)) ? 'checked' : '' ?>>
									<label class="form-check-label" for="rol_<?= (int)$rolId ?>">
										<?= htmlspecialchars($rolNombre, ENT_QUOTES, 'UTF-8'); ?>
									</label>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="form-text">Selecciona uno o más roles. El primer rol marcado se usará como rol principal.</div>
				</div>
				<div class="mb-3">
					<label class="form-label" for="contrasena">Contraseña</label>
					<input id="contrasena" name="contrasena" type="password" class="form-control" placeholder="Deja vacío para mantener la actual">
				</div>
				<div class="d-flex gap-2">
					<button class="btn btn-primary" type="submit">Guardar cambios</button>
					<a class="btn btn-secondary" href="<?= RUTA_URL ?>usuarios/listar">Cancelar</a>
				</div>
			</form>
		<?php endif; ?>
	</div>
</div>

<?php include("vista/includes/footer.php"); ?>
