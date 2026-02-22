<?php
$usaDataTables = true;
include("vista/includes/header.php");
require_once __DIR__ . '/../../../utils/session.php';
start_secure_session();

if (empty($_SESSION['registrado'])) {
    header('Location: ' . RUTA_URL . 'login');
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['nombre'] ?? '';

$pedidoController = new PedidosController();
$asignados = [];
if ($userId) {
    $asignados = $pedidoController->listarPedidosAsignados((int)$userId);
}
?>
<div class="container mt-4">
    <h2>Seguimiento de mis pedidos asignados</h2>
    <p class="text-muted">Usuario: <?= htmlspecialchars($userName) ?></p>

    <?php if (empty($asignados)): ?>
        <div class="alert alert-info">No tienes pedidos asignados.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Número</th>
                        <th>Destinatario</th>
                        <th>Estado</th>
                        <th>Dirección</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asignados as $idx => $p): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><?= htmlspecialchars($p['numero_orden']) ?></td>
                            <td><?= htmlspecialchars($p['destinatario']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($p['nombre_estado'] ?? 'N/D') ?></span></td>
                            <td><?= htmlspecialchars($p['direccion']) ?></td>
                            <td><?= htmlspecialchars($p['fecha_ingreso']) ?></td>
                            <td>
                                <a class="btn btn-sm btn-primary" href="<?= RUTA_URL ?>seguimiento/ver/<?= (int)$p['id'] ?>">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include("vista/includes/footer.php"); ?>
<script>
    $(document).ready(function() {
        $('.table').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
            }
        });
    });
</script>
