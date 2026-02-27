<?php include("vista/includes/header_materialize.php"); ?>

<?php
$usaDataTables = true;
$ctrl = new MonedasController();
$monedas = $ctrl->listar();

// Check if user is admin
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
$isAdmin = in_array('Administrador', $rolesNombres, true);
$deleteDisabled = !$isAdmin ? 'disabled' : '';
?>

<style>
.currency-header {
    background: linear-gradient(135deg, #FF512F 0%, #DD2476 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(247, 151, 30, 0.2);
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
    <div class="currency-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1 fw-bold"><i class="bi bi-currency-exchange me-2"></i> Monedas</h2>
            <p class="mb-0 opacity-75">Gestión de tipos de cambio y divisas</p>
        </div>
        <div>
            <a href="<?= RUTA_URL ?>monedas/crear" class="btn btn-light text-warning fw-bold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Nueva Moneda
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
                            <th>Moneda</th>
                            <th>Código</th>
                            <th>Tasa USD</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monedas as $key => $value): ?>
                            <tr>
                                <td class="fw-bold text-muted text-center"><?= ($key + 1) ?></td>
                                <td>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($value["nombre"]) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($value["codigo"]) ?></span>
                                </td>
                                <td>
                                    <span class="text-success fw-bold"><?= htmlspecialchars($value["tasa_usd"]) ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="<?= RUTA_URL ?>monedas/editar/<?= $value["id"] ?>" class="btn btn-primary btn-square" title="Editar" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($isAdmin): ?>
                                            <form method="post" action="<?= RUTA_URL ?>monedas/eliminar/<?= $value["id"] ?>" style="display:inline" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar esta moneda?');">
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
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer_materialize.php"); ?>

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
