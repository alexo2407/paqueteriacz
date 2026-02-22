<?php include("vista/includes/header.php") ?>

<?php
$usaDataTables = true;
// Instanciar el controlador y obtener la lista de clientes
$listarClientes = new ClientesController();
$clientes = $listarClientes->mostrarClientesController();
$totalClientes = 0;
$activos = 0;
$inactivos = 0;

if ($clientes) {
    foreach ($clientes as $c) {
        $totalClientes++;
        if (isset($c['activo']) && $c['activo'] == 1) $activos++;
        else $inactivos++;
    }
}
?>

<style>
.clientes-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.clientes-header {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    padding: 1.75rem 2rem;
}
.clientes-header h3 {
    margin: 0;
    font-weight: 600;
}
.stat-mini {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    text-align: center;
    backdrop-filter: blur(5px);
    min-width: 100px;
}
.stat-mini .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-mini .stat-label {
    font-size: 0.75rem;
    opacity: 0.9;
}
.btn-new-client {
    background: white;
    color: #4facfe;
    border: none;
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-new-client:hover {
    background: #f0f8ff;
    color: #00f2fe;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.btn-inactive {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.4);
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
}
.btn-inactive:hover {
    background: rgba(255,255,255,0.3);
    color: white;
}
.btn-action-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
#tblUsuarios thead th {
    background: #f8f9fa;
    font-weight: 600;
    color: #1a1a2e;
    border-bottom: 2px solid #e9ecef;
    padding: 1rem 0.75rem;
}
#tblUsuarios tbody tr:hover {
    background-color: #f8f9ff;
}
#tblUsuarios td {
    padding: 0.875rem 0.75rem;
    vertical-align: middle;
}
</style>

<div class="container-fluid py-3">
    <!-- Card Principal -->
    <div class="card clientes-card mb-4">
        <div class="clientes-header">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-people fs-3"></i>
                        </div>
                        <div>
                            <h3>Gestión de Clientes</h3>
                            <p class="mb-0 opacity-75">Administra tu base de datos de clientes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-md-end justify-content-center align-items-center gap-3">
                        <div class="stat-mini">
                            <div class="stat-value"><?= $activos ?></div>
                            <div class="stat-label">Activos</div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-value"><?= $inactivos ?></div>
                            <div class="stat-label">Inactivos</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= RUTA_URL ?>clientes/inactivos" class="btn btn-inactive">
                    <i class="bi bi-archive me-1"></i> Ver Inactivos
                </a>
                <a href="<?= RUTA_URL ?>clientes/crearCliente" class="btn btn-new-client">
                    <i class="bi bi-plus-circle-fill me-1"></i> Nuevo Cliente
                </a>
            </div>
        </div>
        
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="tblUsuarios" class="table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($clientes):
                            foreach ($clientes as $cliente): 
                                // Verifica que el cliente no sea null y que el atributo "activo" sea igual a 1
                                if ($cliente !== null && isset($cliente['activo']) && $cliente['activo'] == 1): ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border">#<?php echo htmlspecialchars($cliente['ID_Cliente']); ?></span></td>
                                        <td class="fw-bold text-primary"><?php echo htmlspecialchars($cliente['Nombre']); ?></td>
                                        <td>
                                            <?php if ($cliente['activo'] == 1): ?>
                                                <span class="badge rounded-pill bg-success p-2">
                                                    <i class="bi bi-check-lg" style="font-size: 1.2rem;"></i>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge rounded-pill bg-danger p-2">
                                                    <i class="bi bi-x-lg" style="font-size: 1.2rem;"></i>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="<?= RUTA_URL ?>clientes/editar/<?php echo $cliente['ID_Cliente']; ?>" class="btn btn-primary btn-square" title="Editar" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <!-- Botón para desactivar -->
                                                <a href="<?= RUTA_URL ?>clientes/desactivar/<?php echo $cliente['ID_Cliente']; ?>" class="btn btn-danger btn-square" title="Inactivar" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                endif;
                            endforeach;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
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
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            initComplete: function() {
                $('.dataTables_filter input').addClass('form-control form-control-sm border-2');
                $('.dataTables_length select').addClass('form-select form-select-sm border-2');
            }
        });
    });
</script>
