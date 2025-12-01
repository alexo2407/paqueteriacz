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
        <div class="table-responsive">
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
                    // Instanciar el controlador y obtener la lista de clientes
                    $listarClientes = new ClientesController();
                    $clientes = $listarClientes->mostrarClientesController();
                             

                    foreach ($clientes as $cliente): 
                        // Verifica que el cliente no sea null y que el atributo "activo" sea igual a 1
                        if ($cliente !== null && isset($cliente['activo']) && $cliente['activo'] == 1): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cliente['ID_Cliente']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['Nombre']); ?></td>
                                <td><?php echo ($cliente['activo'] == 1) ? 'Activo' : 'Inactivo'; ?></td>
                                <td>
                                    <a href="<?= RUTA_URL ?>clientes/editar/<?php echo $cliente['ID_Cliente']; ?>" class="btn btn-warning btn-sm">Editar</a>
                                    <!-- Botón para desactivar -->
                                    <a href="<?= RUTA_URL ?>clientes/desactivar/<?php echo $cliente['ID_Cliente']; ?>" class="btn btn-danger btn-sm">
                                        Marcar Inactivo
                                    </a>
                                </td>
                            </tr>
                        <?php 
                        endif;
                    endforeach;
                    ?>

                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php") ?>

<script>
    $(document).ready(function() {
        $('#tblUsuarios').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
            }
        });
    });
</script>
