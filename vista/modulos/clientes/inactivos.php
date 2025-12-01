<?php include("vista/includes/header.php"); ?>

<div class="container mt-5">
    <h2 class="mb-4">Listado de Clientes Inactivos</h2>

    <?php
    // Instanciar el controlador
    $clienteController = new ClientesController();
   
    // Obtener la lista de clientes inactivos
    $clientesInactivos = $clienteController->listarClientesInactivos();
    ?>

    <?php if (empty($clientesInactivos)): ?>
        <div class="alert alert-info" role="alert">
            No se encontraron clientes inactivos.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientesInactivos as $cliente): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cliente['ID_Cliente']); ?></td>
                        <td><?php echo htmlspecialchars($cliente['Nombre']); ?></td>
                        <td><?php echo ($cliente['activo'] == 1) ? 'Activo' : 'Inactivo'; ?></td>
                        <td>
                            <!-- Activar cliente -->
                            <a href="<?=RUTA_URL?>clientes/activar/<?php echo $cliente['ID_Cliente']; ?>" class="btn btn-success btn-sm">
                                Activar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <a href="<?=RUTA_URL?>clientes/listar" class="btn btn-primary">Ver Clientes Activos</a>
</div>

<?php include("vista/includes/footer.php"); ?>
