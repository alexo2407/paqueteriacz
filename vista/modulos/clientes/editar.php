<?php include("vista/includes/header.php") ?>

<style>
.editar-cliente-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.editar-cliente-header {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    padding: 1.5rem 2rem;
}
.editar-cliente-header h3 {
    margin: 0;
    font-weight: 600;
}
.form-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}
.btn-save-client {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    border: none;
    padding: 0.75rem 2rem;
    font-weight: 600;
    border-radius: 10px;
    font-size: 1rem;
    color: white;
}
.btn-save-client:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(79, 172, 254, 0.4);
    color: white;
}
</style>

<div class="container-fluid py-4">
    <div class="card editar-cliente-card">
        <div class="editar-cliente-header">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white bg-opacity-25 rounded-circle p-3">
                    <i class="bi bi-pencil-square fs-3"></i>
                </div>
                <div>
                    <h3>Editar Cliente</h3>
                    <p class="mb-0 opacity-75">Modifica los datos del cliente</p>
                </div>
            </div>
        </div>
        
        <div class="card-body p-4">

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

                    <div class="form-section">
                        <div class="mb-3">
                            <label for="nombre" class="form-label fw-bold">Nombre del Cliente</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($cliente->Nombre) ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="estado" class="form-label fw-bold">Estado</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-toggle-on"></i></span>
                                <select class="form-select" id="estado" name="activo">
                                    <option value="1" <?= $cliente->activo == 1 ? 'selected' : '' ?>>Activo</option>
                                    <option value="0" <?= $cliente->activo == 0 ? 'selected' : '' ?>>Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= RUTA_URL ?>clientes" class="btn btn-secondary px-4">
                            <i class="bi bi-arrow-left me-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-save-client">
                            <i class="bi bi-check-lg me-1"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i> Cliente no encontrado.
                </div>
                <a href="<?= RUTA_URL ?>clientes" class="btn btn-secondary">Volver</a>
            <?php endif; ?>
        </div>
    </div>
</div>


<?php include("vista/includes/footer.php") ?>