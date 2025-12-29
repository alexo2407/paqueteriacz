<?php include("vista/includes/header.php"); ?>

<?php
require_once __DIR__ . '/../../../controlador/municipio.php';
require_once __DIR__ . '/../../../controlador/departamento.php';
$depCtrl = new DepartamentosController();
$departamentos = $depCtrl->listar();
$ctrl = new MunicipiosController();
$municipios = $ctrl->listar();

// Check if user is admin
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
$isAdmin = in_array('Administrador', $rolesNombres, true);
$deleteDisabled = !$isAdmin ? 'disabled' : '';
?>

<style>
.location-header {
    background: linear-gradient(135deg, #005C97 0%, #363795 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(33, 147, 176, 0.2);
}
.table-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    overflow: hidden;
}
.btn-delete.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>

<div class="container-fluid py-4">
    <div class="location-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1 fw-bold"><i class="bi bi-geo-alt me-2"></i> Municipios</h2>
            <p class="mb-0 opacity-75">Gestión de áreas locales y ciudades (Nivel 3)</p>
        </div>
        <div>
            <a href="<?= RUTA_URL ?>municipios/crear" class="btn btn-light text-info fw-bold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Nuevo Municipio
            </a>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100 tablas">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Municipio</th>
                            <th>Departamento Perteneciente</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($municipios)): ?>
                            <?php foreach ($municipios as $key => $value): ?>
                                <?php
                                $depName = '';
                                foreach ($departamentos as $d) {
                                    if ($d['id'] == $value['id_departamento']) {
                                        $depName = $d['nombre'];
                                        break;
                                    }
                                }
                                ?>
                                <tr>
                                    <td class="fw-bold text-muted text-center"><?= ($key + 1) ?></td>
                                    <td>
                                        <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($value["nombre"]) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><i class="bi bi-map me-1"></i> <?= htmlspecialchars($depName) ?></span>
                                    </td>
                                    <td class="text-end">
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="<?= RUTA_URL ?>municipios/ver/<?= urlencode($value['id']) ?>" class="btn btn-info btn-square text-white" title="Ver detalles" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= RUTA_URL ?>municipios/editar/<?= urlencode($value['id']) ?>" class="btn btn-primary btn-square" title="Editar" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($isAdmin): ?>
                                            <form method="post" action="<?= RUTA_URL ?>municipios/eliminar/<?= urlencode($value['id']) ?>" style="display:inline" class="d-inline" onsubmit="return confirm('¿Eliminar municipio?');">
                                                <button class="btn btn-danger btn-square btn-delete" type="submit" title="Eliminar" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-square disabled" disabled title="Solo administradores" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No hay municipios registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>

<script>
    $(document).ready(function() {
        $('.tablas').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
            },
            dom: '<"d-flex justify-content-between align-items-center mb-3"f>t<"d-flex justify-content-between align-items-center mt-3"ip>',
        });
    });
</script>
