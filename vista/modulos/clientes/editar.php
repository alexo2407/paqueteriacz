<?php include("vista/includes/header.php") ?>



<div class="container mt-4">
    <h1>Editar Cliente</h1>

    <?php
    // Inicializar mensaje
    $mensaje = "";

    // Verificar si se envió el formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Obtener datos del formulario
        $idCliente = intval($_POST['id']);
        $nombre = htmlspecialchars(trim($_POST['nombre']));
        $activo = intval($_POST['activo']);

        // Instanciar el controlador
        $clienteController = new ClientesController();

        // Intentar actualizar el cliente
        if ($clienteController->actualizarCliente($idCliente, $nombre, $activo)) {
            $mensaje = "<div class='alert alert-success'>Cliente actualizado correctamente.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al actualizar el cliente. Inténtalo de nuevo.</div>";
        }
    }

    // Obtener los datos del cliente si no se ha enviado el formulario
    if (!isset($idCliente)) {
        $idCliente = isset($parametros[0]) ? intval($parametros[0]) : 0;
    }
    $clienteController = new ClientesController();
    $cliente = $clienteController->obtenerClientePorId($idCliente);
    ?>

    <!-- Mostrar mensaje -->
    <?= $mensaje ?>

    <?php if ($cliente): ?>
        <!-- Formulario de edición -->
        <form action="" method="POST">
            <input type="hidden" name="id" value="<?= $cliente->ID_Cliente ?>">

            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($cliente->Nombre) ?>" required>
            </div>
            <div class="mb-3">
                <label for="estado" class="form-label">Estado</label>
                <select class="form-control" id="estado" name="activo">
                    <option value="1" <?= $cliente->activo == 1 ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= $cliente->activo == 0 ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </form>
    <?php else: ?>
        <div class="alert alert-warning">Cliente no encontrado.</div>
    <?php endif; ?>
</div>


<?php include("vista/includes/footer.php") ?>