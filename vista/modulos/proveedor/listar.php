<?php include("vista/includes/header.php") ?>

<?php
require_once __DIR__ . '/../../../utils/session.php';
$flashMessage = get_flash();
if ($flashMessage): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            icon: '<?= $flashMessage["type"] === "success" ? "success" : "error" ?>',
            title: '<?= $flashMessage["type"] === "success" ? "Éxito" : "Error" ?>',
            text: '<?= $flashMessage["message"] ?>',
        });
    });
</script>
<?php endif; ?>

<div class="row">
    <div class="col-sm-6">
        <h3>Lista de Proveedores</h3>
    </div>   
    <div class="col-sm-4 offset-2">
        <a href="<?=RUTA_URL?>proveedor/crear" class="btn btn-success w-100"><i class="bi bi-plus-circle-fill"></i> Nuevo Proveedor</a>
    </div>    
</div>
<div class="row mt-2 caja">
    <div class="col-sm-12">
            <table id="tblProveedores" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>País</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>

                    <?php
                        $provCtrl = new ProveedorController();
                        $proveedores = $provCtrl->listarProveedores();

                        if (!empty($proveedores)) {
                            foreach ($proveedores as $prov) :
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($prov['id']); ?></td>
                        <td><?php echo htmlspecialchars($prov['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($prov['email']); ?></td>
                        <td><?php echo htmlspecialchars($prov['telefono'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($prov['pais'] ?? ''); ?></td>
                        <td>
                            <a href="<?= RUTA_URL ?>proveedor/editar/<?php echo $prov['id']; ?>" class="btn btn-warning"><i class="bi bi-pencil-fill"></i></a>
                        </td>
                    </tr>
                    <?php
                            endforeach;
                        } else {
                    ?>
                    <tr>
                        <td colspan="6" class="text-center">No hay proveedores registrados.</td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
    </div>
</div>

<?php include("vista/includes/footer.php") ?>

<script>
    $(document).ready( function () {
        $('#tblProveedores').DataTable();
    });
</script>