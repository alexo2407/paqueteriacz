<?php include("vista/includes/header.php"); ?>

<?php
// Obtener ID del usuario actual desde la sesión
$idUsuario = $_SESSION['user_id'] ?? 0;
$rolesNombresSession = $_SESSION['roles_nombres'] ?? [];
$isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombresSession, true);

$usuarioCtrl = new UsuariosController();
$usuario = $idUsuario > 0 ? $usuarioCtrl->verUsuario($idUsuario) : null;
$rolesDisponibles = UsuariosController::obtenerRolesDisponibles();
// Obtener roles actuales del usuario (multi-rol)
require_once __DIR__ . '/../../../modelo/usuario.php';
$um = new UsuarioModel();
$rolesUsuario = $idUsuario > 0 ? $um->obtenerRolesDeUsuario($idUsuario) : ['ids' => [], 'nombres' => []];
$rolesUsuarioIds = $rolesUsuario['ids'] ?? [];
?>

<div class="row">
	<div class="col-sm-8">
		<h3>Mi Perfil</h3>
		<?php if (!$usuario) : ?>
			<div class="alert alert-danger">Usuario no encontrado.</div>
		<?php else : ?>
			<form method="POST" action="<?= RUTA_URL ?>usuarios/actualizarPerfil">
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
				
				<?php if ($isAdmin): ?>
				<!-- Solo administradores pueden editar sus roles -->
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
				<?php else: ?>
				<!-- Usuarios no-admin ven sus roles pero no pueden editarlos -->
				<div class="mb-3">
					<label class="form-label">Roles Asignados</label>
					<div class="alert alert-info">
						<?php 
						$nombresRoles = $rolesUsuario['nombres'] ?? [];
						if (!empty($nombresRoles)) {
							foreach ($nombresRoles as $nombreRol) {
								echo '<span class="badge bg-secondary me-1">' . htmlspecialchars($nombreRol) . '</span>';
							}
						} else {
							echo '<span class="badge bg-secondary">Sin rol asignado</span>';
						}
						?>
					</div>
					<div class="form-text">Solo los administradores pueden modificar roles.</div>
				</div>
				<?php endif; ?>
				
				<div class="mb-3">
					<label class="form-label" for="contrasena">Nueva Contraseña</label>
					<input id="contrasena" name="contrasena" type="password" class="form-control" placeholder="Deja vacío para mantener la actual">
				</div>
				<div class="d-flex gap-2">
					<button class="btn btn-primary" type="submit">Guardar cambios</button>
					<a class="btn btn-secondary" href="javascript:history.back()">Cancelar</a>
				</div>
			</form>
		<?php endif; ?>
	</div>
</div>

<?php include("vista/includes/footer.php"); ?>
