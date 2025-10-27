<?php include("vista/includes/header.php") ?>

<?php
require_once __DIR__ . '/../../../utils/session.php';
$flashMessage = get_flash();
$highlightId = $_SESSION['last_created_provider_id'] ?? null;
if ($highlightId !== null) {
    $highlightId = (int) $highlightId;
    unset($_SESSION['last_created_provider_id']);
}
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
                    <tr data-provider-id="<?php echo htmlspecialchars($prov['id']); ?>" class="<?php echo ($highlightId !== null && (int)$prov['id'] === $highlightId) ? 'table-success' : ''; ?>">
                        <td><?php echo htmlspecialchars($prov['id']); ?></td>
                        <td><?php echo htmlspecialchars($prov['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($prov['email']); ?></td>
                        <td><?php echo htmlspecialchars($prov['telefono'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($prov['pais'] ?? ''); ?></td>
                        <td class="d-flex gap-2">
                            <a href="<?= RUTA_URL ?>proveedor/editar/<?php echo $prov['id']; ?>" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                            <form action="<?= RUTA_URL ?>proveedor/eliminar/<?php echo $prov['id']; ?>" method="POST" class="d-inline js-proveedor-delete-form">
                                <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
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
    $(document).ready(function () {
        const table = $('#tblProveedores').DataTable({
            order: [[0, 'desc']],
            pageLength: 25
        });

        const highlighted = document.querySelector('#tblProveedores tr.table-success');
        if (highlighted) {
            highlighted.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        document.querySelectorAll('.js-proveedor-delete-form').forEach(function(form){
            form.addEventListener('submit', function(event){
                event.preventDefault();

                const proceed = function(){
                    form.submit();
                };

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: '¿Eliminar proveedor?',
                        text: 'Esta acción no se puede deshacer.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then(function(result){
                        if (result.isConfirmed) {
                            proceed();
                        }
                    });
                } else if (confirm('¿Eliminar este proveedor?')) {
                    proceed();
                }
            });
        });
    });
</script>