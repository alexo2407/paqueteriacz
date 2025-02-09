<?php include("vista/includes/header.php"); ?>

<div class="row">
    <div class="col-sm-6">
        <h3>Lista Extendida de Pedidos</h3>
    </div>
    <div class="col-sm-6 text-end">
        <a href="<?= RUTA_URL ?>pedidos/crearPedido" class="btn btn-success"><i class="bi bi-plus-circle-fill"></i> Nuevo Pedido</a>
    </div>
</div>
<div class="row mt-2 caja">
    <div class="col-sm-12">
        <table id="tblPedidos" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>ID Pedido</th>
                    <th>Número de Orden</th>
                    <th>Cliente</th>
                    <th>Usuario</th>
                    <th>Fecha de Ingreso</th>
                    <th>Zona</th>
                    <th>Departamento</th>
                    <th>Municipio</th>
                    <th>Barrio</th>
                    <th>Dirección</th>
                    <th>Comentario</th>
                    <th>Coordenadas</th>
                    <th>Estado</th>
                    <th>Fecha de Creación</th>
                    <th>Última Actualización</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $listarPedidos = new PedidosController();
                $pedidos = $listarPedidos->listarPedidosExtendidos();

                foreach ($pedidos as $pedido): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pedido['ID_Pedido']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Numero_Orden']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['ID_Cliente']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['ID_Usuario']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Fecha_Ingreso']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Zona']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Departamento']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Municipio']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Barrio']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Direccion_Completa']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Comentario']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['COORDINATES']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Estado']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['updated_at']); ?></td>
                        <td>
                            <!-- Select dinámico para cambiar el estado -->
                            <select class="form-select cambiarEstado" data-id="<?php echo $pedido['ID_Pedido']; ?>">
                                <option value="1" <?= $pedido['ID_Estado'] == 1 ? 'selected' : ''; ?>>En Bodega</option>
                                <option value="2" <?= $pedido['ID_Estado'] == 2 ? 'selected' : ''; ?>>En Ruta o Proceso</option>
                                <option value="3" <?= $pedido['ID_Estado'] == 3 ? 'selected' : ''; ?>>Entregado</option>
                                <option value="4" <?= $pedido['ID_Estado'] == 4 ? 'selected' : ''; ?>>Reprogramado</option>
                                <option value="5" <?= $pedido['ID_Estado'] == 5 ? 'selected' : ''; ?>>Domicilio Cerrado</option>
                                <option value="6" <?= $pedido['ID_Estado'] == 6 ? 'selected' : ''; ?>>No hay quien reciba en domicilio</option>
                                <option value="7" <?= $pedido['ID_Estado'] == 7 ? 'selected' : ''; ?>>Domicilio no encontrado</option>
                                <option value="8" <?= $pedido['ID_Estado'] == 8 ? 'selected' : ''; ?>>Rechazado</option>
                                <option value="9" <?= $pedido['ID_Estado'] == 9 ? 'selected' : ''; ?>>No puede pagar recaudo</option>
                                <option value="10" <?= $pedido['ID_Estado'] == 10 ? 'selected' : ''; ?>>Devuelto</option>
                            </select>
                            <a href="<?= RUTA_URL ?>pedidos/editar/<?php echo $pedido['ID_Pedido']; ?>" class="btn btn-warning btn-sm">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>

<script>
    // Manejar el cambio de estado con Ajax
    document.querySelectorAll('.cambiarEstado').forEach(select => {
        select.addEventListener('change', function() {
            const idPedido = this.getAttribute('data-id');
            const nuevoEstado = this.value;

            // Enviar la solicitud Ajax
            fetch('<?= RUTA_URL ?>cambiarEstadoAjax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idPedido: idPedido,
                    nuevoEstado: nuevoEstado
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Estado actualizado correctamente.');
                } else {
                    alert('Error al actualizar el estado.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Hubo un problema al intentar cambiar el estado.');
            });
        });
    });
</script>
