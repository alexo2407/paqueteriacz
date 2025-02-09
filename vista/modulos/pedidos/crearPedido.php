<?php include("vista/includes/header.php"); ?>

<div class="container mt-5">
    <h3>Crear Nuevo Pedido</h3>
    <form action="<?= RUTA_URL ?>pedidos/guardarPedido" method="POST">
        <div class="mb-3">
            <label for="Numero_Orden" class="form-label">Número de Orden</label>
            <input type="text" class="form-control" id="Numero_Orden" name="Numero_Orden" required>
        </div>
        <div class="mb-3">
            <label for="ID_Cliente" class="form-label">Cliente</label>
            <input type="number" class="form-control" id="ID_Cliente" name="ID_Cliente" required>
        </div>
        <div class="mb-3">
            <label for="ID_Usuario" class="form-label">Usuario</label>
            <input type="number" class="form-control" id="ID_Usuario" name="ID_Usuario" required>
        </div>
        <div class="mb-3">
            <label for="Fecha_Ingreso" class="form-label">Fecha de Ingreso</label>
            <input type="date" class="form-control" id="Fecha_Ingreso" name="Fecha_Ingreso" required>
        </div>
        <!-- Agregar más campos aquí para Zona, Departamento, Municipio, etc. -->
        <div class="mb-3">
            <label for="ID_Estado" class="form-label">Estado</label>
            <select class="form-control" id="ID_Estado" name="ID_Estado" required>
                <option value="1">En Bodega</option>
                <option value="2">En Ruta</option>
                <option value="3">Entregado</option>
                <option value="4">Devuelto</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Guardar Pedido</button>
    </form>
</div>

<?php include("vista/includes/footer.php"); ?>
