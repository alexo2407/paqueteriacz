<?php include("vista/includes/header.php") ?>


<div class="row">
    <div class="col-sm-6">
        <h3>Lista de Proveedor</h3>
    </div>   
    <div class="col-sm-4 offset-2">
        <a href="<?=RUTA_URL?>crearUsuario" class="btn btn-success w-100"><i class="bi bi-plus-circle-fill"></i> Nuevo Usuario</a>
    </div>    
</div>
<div class="row mt-2 caja">
    <div class="col-sm-12">
            <table id="tblUsuarios" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Fecha de Creación</th>                       
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>

                    <?php
                        $provCtrl = new ProveedorController();
                        $proveedores = $provCtrl->listarProveedores();
                        foreach ($proveedores as $prov) :
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($prov['id']); ?></td>
                        <td><?php echo htmlspecialchars($prov['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($prov['email']); ?></td>
                        <td><?php echo htmlspecialchars($prov['telefono'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($prov['creado_en'] ?? ''); ?></td>
                        <td>
                            <a href="<?= RUTA_URL ?>prooveedor/editar/<?php echo $prov['id']; ?>" class="btn btn-warning"><i class="bi bi-pencil-fill"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                                           
                </tbody>       
            </table>
    </div>
</div>

<?php include("vista/includes/footer.php") ?>

<script>
    $(document).ready( function () {
        $('#tblUsuarios').DataTable();
    });
</script>