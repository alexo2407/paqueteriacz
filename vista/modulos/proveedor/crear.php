<?php include("vista/includes/header.php"); ?>

<?php
require_once __DIR__ . '/../../utils/session.php';
$flashMessage = get_flash();
if ($flashMessage): ?>
<script>
    Swal.fire({
        icon: '<?= $flashMessage["type"] === "success" ? "success" : "error" ?>',
        title: '<?= $flashMessage["type"] === "success" ? "Éxito" : "Error" ?>',
        text: '<?= $flashMessage["message"] ?>',
    });
</script>
<?php endif; ?>

<div class="row">
    <div class="col-sm-12">
        <h3>Nuevo Proveedor</h3>
    </div>
</div>

<div class="row mt-2 caja">
    <div class="col-sm-12">
        <form action="<?= RUTA_URL ?>proveedor/guardar" method="POST">
            <div class="row">
                <!-- Primera Columna -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                        <div class="invalid-feedback">Por favor, ingresa un nombre válido.</div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="invalid-feedback">Por favor, ingresa un email válido.</div>
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" pattern="[0-9]{8,15}" required>
                        <div class="invalid-feedback">Por favor, ingresa un número de teléfono válido (8 a 15 dígitos).</div>
                    </div>
                </div>

                <!-- Segunda Columna -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="pais" class="form-label">País</label>
                        <input type="text" class="form-control" id="pais" name="pais" required>
                        <div class="invalid-feedback">Por favor, ingresa un país válido.</div>
                    </div>
                    <div class="mb-3">
                        <label for="contrasena" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="contrasena" name="contrasena" required>
                        <div class="invalid-feedback">Por favor, ingresa una contraseña válida.</div>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="text-end">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Guardar</button>
                <a href="<?= RUTA_URL ?>proveedor/listar" class="btn btn-secondary"><i class="bi bi-arrow-left-circle"></i> Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
