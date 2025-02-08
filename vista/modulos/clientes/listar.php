<?php include("vista/includes/header.php") ?>


<div class="row">
    <div class="col-sm-6">
        <h3>Lista de Clientes</h3>
    </div>
    <div class="col-sm-3">
    <a href="<?= RUTA_URL ?>clientes/inactivos" class="btn btn-secondary ml-2">Ver Clientes Inactivos</a>

    </div>
    <div class="col-sm-3">
            <a href="<?= RUTA_URL ?>clientes/crearCliente" class="btn btn-success w-100"><i class="bi bi-plus-circle-fill"></i> Nuevo Cliente</a>
        </div>
</div>
<div class="row mt-2 caja">
        <div class="col-sm-12">
            <table id="tblUsuarios" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>

                    <?php

                    $listarClientes = new ClientesController();
                    $clientes = $listarClientes->mostrarClientesController();


                    foreach ($clientes as $cliente): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cliente->ID_Cliente); ?></td>
                            <td><?php echo htmlspecialchars($cliente->Nombre); ?></td>
                            <td><?php echo ($cliente->activo == 1) ? 'Activo' : 'Inactivo'; ?></td>
                            <td>
                                <a href="<?= RUTA_URL ?>clientes/editar/<?php echo $cliente->ID_Cliente; ?>" class="btn btn-warning btn-sm">Editar</a>
                                <a href="<?= RUTA_URL ?>clientes/cambiarEstado/<?php echo $cliente->ID_Cliente; ?>" class="btn btn-info btn-sm">
                                    <?php echo ($cliente->activo == 1) ? 'Marcar Inactivo' : 'Marcar Activo'; ?>
                                </a>
                                <a href="index.php?url=clientes/eliminar/<?php echo $cliente->ID_Cliente; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de eliminar este cliente?')">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>
</div>

    <?php include("vista/includes/footer.php") ?>

    <script>
        $(document).ready(function() {
            $('#tblUsuarios').DataTable();
        });
    </script>