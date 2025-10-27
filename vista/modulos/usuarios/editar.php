<?php include("vista/includes/header.php"); ?>

<?php
$params = isset($parametros) ? $parametros : [];
$idUsuario = isset($params[0]) ? (int) $params[0] : 0;

$usuarioCtrl = new UsuariosController();
$usuario = $idUsuario > 0 ? $usuarioCtrl->verUsuario($idUsuario) : null;
$rolesDisponibles = UsuariosController::obtenerRolesDisponibles();
?>

<div class="row">
	<div class="col-sm-8">
		<h3>Editar Usuario</h3>
		<?php if (!$usuario) : ?>
			<div class="alert alert-danger">Usuario no encontrado.</div>
		<?php else : ?>
			<form method="POST" action="<?= RUTA_URL ?>usuarios/actualizar/<?= $idUsuario; ?>">
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
					<label class="form-label" for="id_rol">Rol</label>
					<select id="id_rol" name="id_rol" class="form-select" required>
						<option value="">Seleccione un rol</option>
						<?php foreach ($rolesDisponibles as $rolId => $rolNombre) : ?>
							<option value="<?= $rolId; ?>" <?= (int) ($usuario['id_rol'] ?? 0) === (int) $rolId ? 'selected' : ''; ?>>
								<?= htmlspecialchars($rolNombre, ENT_QUOTES, 'UTF-8'); ?>
							</option>
						<?php endforeach; ?>
					</select>
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
