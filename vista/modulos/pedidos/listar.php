<?php include("vista/includes/header.php"); ?>

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
                $pedidos = $listarPedidos->listarPedidosExtendidos();
                $estados = $listarPedidos->obtenerEstados(); // Obtener lista de estados

                foreach ($pedidos as $pedido): ?>
                    <tr data-id="<?= $pedido['ID_Pedido'] ?>">
                        <td><?= htmlspecialchars($pedido['ID_Pedido']) ?></td>
                        <td><?= htmlspecialchars($pedido['Numero_Orden']) ?></td>
                        <td><?= htmlspecialchars($pedido['Cliente']) ?></td>
                        <td><?= htmlspecialchars($pedido['Comentario']) ?></td>
                        
                        <!-- Celda Editable para Estado -->
                        <td class="editable" data-campo="estado">
                        <select class="form-select actualizarEstado w-10" data-id="<?= $pedido['ID_Pedido']; ?>">
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= $estado['id']; ?>" <?= $pedido['Estado'] == $estado['nombre_estado'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($estado['nombre_estado']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                        </td>

                        <td>
                            <a href="<?= RUTA_URL ?>pedidos/ver/<?php echo $pedido['ID_Pedido']; ?>" class="btn btn-primary btn-sm">Ver</a>
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
                // Configuración básica
                dom: 'Bfrtip', // Controles para exportar y buscar
                buttons: [
                    'excel', // Botón para exportar a Excel
                    'pdf', // Botón para exportar a PDF
                    'print' // Botón para imprimir
                ],
                order: [
                    [1, 'asc']
                ], // Orden inicial: Columna 1 (Número de Orden) ascendente
                language: { // Traducción al español
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
                let idPedido = $(this).data("id");
                let nuevoEstado = $(this).val();

                $.post("<?= RUTA_URL ?>pedidos/listar", {
                        id_pedido: idPedido,
                        estado: nuevoEstado
                    },
                    function(response) {
                       // console.log(response); // Muestra la respuesta en la consola

                        try {
                            let data = JSON.parse(response);
                            if (data.success) {
                                alert("Estado actualizado correctamente.");
                            } else {
                                alert("Error: " + data.message);
                            }
                        } catch (e) {
                            console.error("Error procesando JSON: ", e);
                        }
                    });
            });
        });
    </script>

