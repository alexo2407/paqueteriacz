<?php include("vista/includes/header_materialize.php"); ?>

<?php
$usaDataTables = true;
// Instanciar el controlador
$clienteController = new ClientesController();

// Obtener la lista de clientes inactivos
$clientesInactivos = $clienteController->listarClientesInactivos();
$totalInactivos = $clientesInactivos ? count($clientesInactivos) : 0;
?>

<style>
.inactivos-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.inactivos-header {
    background: linear-gradient(135deg, #434343 0%, #000000 100%);
    color: white;
    padding: 1.5rem 2rem;
}
.inactivos-header h3 {
    margin: 0;
    font-weight: 600;
}
.btn-back-active {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.4);
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
}
.btn-back-active:hover {
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
#tblInactivos thead th {
    background: #f8f9fa;
    font-weight: 600;
    color: #1a1a2e;
    border-bottom: 2px solid #e9ecef;
    padding: 1rem 0.75rem;
}
#tblInactivos tbody tr:hover {
    background-color: #f8f9ff;
}
#tblInactivos td {
    padding: 0.875rem 0.75rem;
    vertical-align: middle;
}
</style>

<div class="container-fluid py-4">
    <div class="card inactivos-card mb-4">
        <div class="inactivos-header">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-archive fs-3"></i>
                        </div>
                        <div>
                            <h3>Clientes Inactivos</h3>
                            <p class="mb-0 opacity-75">Historial de clientes desactivados</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-md-end text-center">
                    <span class="badge bg-white text-dark py-2 px-3 rounded-pill fs-6">
                        Total Inactivos: <?= $totalInactivos ?>
                    </span>
                </div>
            </div>
            
            <div class="d-flex justify-content-end mt-3">
                <a href="<?=RUTA_URL?>clientes/listar" class="btn btn-back-active">
                    <i class="bi bi-arrow-left me-1"></i> Volver a Activos
                </a>
            </div>
        </div>
        
        <div class="card-body p-4">
            <?php if (empty($clientesInactivos)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-folder2-open display-1 text-muted mb-3 opacity-25"></i>
                    <h4 class="text-muted">No hay clientes inactivos</h4>
                    <p class="text-muted opacity-75">Todos tus clientes están activos actualmente.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tblInactivos" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientesInactivos as $cliente): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border">#<?php echo htmlspecialchars($cliente['ID_Cliente']); ?></span></td>
                                <td class="fw-bold text-secondary"><?php echo htmlspecialchars($cliente['Nombre']); ?></td>
                                <td><span class="badge bg-secondary">Inactivo</span></td>
                                <td class="text-end">
                                    <a href="<?=RUTA_URL?>clientes/activar/<?php echo $cliente['ID_Cliente']; ?>" class="btn btn-success btn-sm btn-action-sm">
                                        <i class="bi bi-check-circle"></i> Activar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include("vista/includes/footer_materialize.php"); ?>

<script>
    $(document).ready(function() {
        $('#tblInactivos').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
            }
        });
    });
</script>
