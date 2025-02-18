<?php

include("vista/includes/header.php");


?>



<div class="row mt-2 caja">
    <div class="col-sm-12">
        <table id="tblPedidos" class="table table-striped">
            <thead>
                <tr>
                    <th>ID Pedido</th>
                    <th>Número de Orden</th>
                    <th>Cliente</th>
                    <th>Comentario</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $listarPedidos = new PedidosController();
                $estados = $listarPedidos->obtenerEstados(); // Obtener lista de estados
                $pedidos = $listarPedidos->listarPedidosExtendidos();

                foreach ($pedidos as $pedido): ?>
                    <tr data-id="<?= $pedido['ID_Pedido'] ?>">
                        <td><?= htmlspecialchars($pedido['ID_Pedido']) ?></td>
                        <td><?= htmlspecialchars($pedido['Numero_Orden']) ?></td>
                        <td><?= htmlspecialchars($pedido['Cliente']) ?></td>
                        <td><?= htmlspecialchars($pedido['Comentario']) ?></td>

                        <!-- Celda Editable para Estado -->
                        <td class="editable" data-campo="estado">
                            <select class="form-select actualizarEstado" data-id="<?= $pedido['ID_Pedido']; ?>">
                                <?php foreach ($estados as $estado): ?>
                                    <option value="<?= $estado['id']; ?>" <?= $pedido['Estado'] == $estado['nombre_estado'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($estado['nombre_estado']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <td>
                            <!-- <a href="<?= RUTA_URL ?>pedidos/ver/<?php echo $pedido['ID_Pedido']; ?>" class="btn btn-primary btn-sm">Ver</a> -->
                            <a href="<?= RUTA_URL ?>pedidos/editar/<?php echo $pedido['ID_Pedido']; ?>" class="btn btn-warning btn-sm">Editar</a>
                            <?php if (!empty($pedido['latitud']) && !empty($pedido['longitud'])): ?>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $pedido['latitud'] ?>,<?= $pedido['longitud'] ?>&travelmode=driving"
                                    target="_blank" class="btn btn-success btn-sm">
                                    <i class="bi bi-geo-alt"></i> Ir a Ruta
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm" disabled>
                                    <i class="bi bi-geo-alt"></i> Sin Coordenadas
                                </button>
                            <?php endif; ?>


                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>



<?php include("vista/includes/footer.php"); ?>



<script>
    $(document).ready(function() {
    $('#tblPedidos').DataTable({
        responsive: true, // Activa la capacidad responsive
        dom: 'Bfrtip', // Controles de exportación
        buttons: [
            'excel', 'pdf', 'print'
        ],
        order: [
            [1, 'asc']
        ],
        language: {
            search: "Buscar por Número de Orden o Cliente:",
            lengthMenu: "Mostrar _MENU_ registros por página",
            zeroRecords: "No se encontraron resultados",
            info: "Mostrando página _PAGE_ de _PAGES_",
            infoEmpty: "No hay registros disponibles",
            infoFiltered: "(filtrado de _MAX_ registros totales)",
            paginate: {
                first: "Primero",
                last: "Último",
                next: "Siguiente",
                previous: "Anterior"
            }
        }
    });
});

</script>

<script>
    $(document).ready(function() {
        $(".actualizarEstado").change(function() {
            let select = $(this); // Guardamos la referencia al select
            let idPedido = select.data("id");
            let nuevoEstado = select.val();
            let estadoAnterior = select.data("estado"); // Guarda el estado anterior

            // Deshabilita el select mientras se procesa la petición
            select.prop("disabled", true);

            $.ajax({
                url: "cambiarEstados",
                type: "POST",
                data: {
                    id_pedido: idPedido,
                    estado: nuevoEstado
                },
                dataType: "json",
                success: function(response) {
                    //console.log("Respuesta recibida:", response);

                    if (response.success) {
                        Swal.fire({
                            title: "¡Éxito!",
                            text: "Estado actualizado correctamente.",
                            icon: "success",
                            confirmButtonText: "OK"
                        });
                        select.data("estado", nuevoEstado);
                        select.val(nuevoEstado);
                    } else {
                        Swal.fire({
                            title: "Error",
                            text: response.message,
                            icon: "error",
                            confirmButtonText: "OK"
                        });
                        select.val(estadoAnterior);
                    }
                },

                error: function(xhr, status, error) {
                   // console.error("Error AJAX:", status, error);
                   Swal.fire({
                            title: "Error",
                            text: status.error,
                            icon: "error",
                            confirmButtonText: "OK"
                        });
                    // alert("Error de conexión. Intenta nuevamente.");
                    select.val(estadoAnterior);
                },
                complete: function() {
                    select.prop("disabled", false);
                }
            });

        });
    });
</script>