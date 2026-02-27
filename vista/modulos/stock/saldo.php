<?php
/**
 * Vista Standalone: Saldo por Producto
 * Accedida via: /stock/saldo
 * Patrón: autocontenida, igual que stock/listar.php
 */
$usaDataTables = true;  // footer.php cargará jQuery, Bootstrap JS y DataTables

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/conexion.php';

start_secure_session();
require_login();
require_role(['Administrador', 'Proveedor']);

$export = isset($_GET['export']) && $_GET['export'] === '1';

// ── Query ────────────────────────────────────────────────────────────────────
$db = (new Conexion())->conectar();

$sql = "
    SELECT
        pr.id,
        pr.nombre          AS producto,
        pr.sku,
        COALESCE(inv.cantidad_disponible, 0)                                    AS disponible,
        COALESCE(inv.cantidad_reservada,  0)                                    AS reservado,
        COALESCE(inv.cantidad_disponible, 0) - COALESCE(inv.cantidad_reservada, 0) AS neto_libre,
        inv.costo_promedio,
        inv.ubicacion,
        inv.ultima_entrada,
        inv.ultima_salida
    FROM productos pr
    LEFT JOIN inventario inv ON inv.id_producto = pr.id AND inv.ubicacion = 'Principal'
    WHERE pr.activo = 1
    ORDER BY pr.nombre ASC
";

$saldos = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ── Export Excel ─────────────────────────────────────────────────────────────
if ($export) {
    require_once __DIR__ . '/../../../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Saldo por Producto');

    $headers = ['ID','Producto','SKU','Disponible','Reservado','Neto Libre','Costo Prom.','Ubicación','Últ. Entrada','Últ. Salida'];
    foreach ($headers as $col => $h) $sheet->setCellValueByColumnAndRow($col + 1, 1, $h);

    foreach ($saldos as $row => $s) {
        $data = [
            $s['id'], $s['producto'], $s['sku'], $s['disponible'], $s['reservado'],
            $s['neto_libre'], $s['costo_promedio'], $s['ubicacion'],
            $s['ultima_entrada'], $s['ultima_salida'],
        ];
        foreach ($data as $col => $val) $sheet->setCellValueByColumnAndRow($col + 1, $row + 2, $val);
    }
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
    foreach (range(1, count($headers)) as $i) $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="saldo_productos_' . date('Ymd') . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saldo por Producto - App RutaEx-Latam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <style>
        .saldo-header {
            background: linear-gradient(135deg, #093028 0%, #237A57 100%);
            color: white;
            padding: 1.75rem 2rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(9,48,40,0.25);
        }
        .table-card { border: none; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,.06); overflow: hidden; }
        .table-danger td { background-color: #fff5f5 !important; }
        .table-warning td { background-color: #fffdf0 !important; }
    </style>
</head>
<body class="bg-light">

<?php include __DIR__ . '/../../includes/header_materialize.php'; ?>

<div class="container-fluid py-4">

    <!-- Cabecera -->
    <div class="saldo-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-bar-chart-steps me-2"></i>Saldo por Producto</h4>
            <small class="opacity-75">Stock disponible, reservado y neto libre — actualizado: <?= date('d/m/Y H:i') ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= RUTA_URL ?>stock/movimientos" class="btn btn-outline-light btn-sm">
                <i class="bi bi-journal-arrow-down me-1"></i>Movimientos
            </a>
            <a href="<?= RUTA_URL ?>stock/saldo?export=1" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </a>
        </div>
    </div>

    <!-- Leyenda -->
    <div class="d-flex gap-3 mb-3 small text-muted flex-wrap">
        <span><span class="badge bg-danger me-1">&nbsp;</span>Sin stock neto (≤0)</span>
        <span><span class="badge bg-warning text-dark me-1">&nbsp;</span>Stock bajo (1–5)</span>
        <span><span class="badge bg-warning text-dark me-1">N</span>Cantidad reservada para pedido en bodega</span>
    </div>

    <!-- Tabla -->
    <div class="card table-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="fw-semibold small"><?= count($saldos) ?> productos activos</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($saldos)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                No hay productos activos con inventario registrado.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table id="tablaSaldoProducto" class="table table-hover table-sm mb-0 align-middle small" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Producto</th>
                            <th>SKU</th>
                            <th class="text-center">Disponible</th>
                            <th class="text-center">Reservado</th>
                            <th class="text-center fw-bold">Neto Libre</th>
                            <th class="text-end">Costo Prom.</th>
                            <th>Ubicación</th>
                            <th>Últ. Entrada</th>
                            <th>Últ. Salida</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($saldos as $s):
                        $neto = (int)$s['neto_libre'];
                        $rowClass = $neto <= 0 ? 'table-danger' : ($neto <= 5 ? 'table-warning' : '');
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="ps-3 fw-semibold"><?= htmlspecialchars($s['producto']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($s['sku'] ?? '—') ?></td>
                        <td class="text-center"><?= (int)$s['disponible'] ?></td>
                        <td class="text-center">
                            <?php if ((int)$s['reservado'] > 0): ?>
                            <span class="badge bg-warning text-dark"><?= (int)$s['reservado'] ?></span>
                            <?php else: ?>
                            <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-bold <?= $neto <= 0 ? 'text-danger' : ($neto <= 5 ? 'text-warning' : 'text-success') ?>">
                            <?= $neto ?>
                        </td>
                        <td class="text-end">
                            <?= $s['costo_promedio'] ? '$' . number_format((float)$s['costo_promedio'], 2) : '—' ?>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($s['ubicacion'] ?? 'Principal') ?></td>
                        <td class="text-muted text-nowrap">
                            <?= $s['ultima_entrada'] ? date('d/m/Y', strtotime($s['ultima_entrada'])) : '—' ?>
                        </td>
                        <td class="text-muted text-nowrap">
                            <?= $s['ultima_salida'] ? date('d/m/Y', strtotime($s['ultima_salida'])) : '—' ?>
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

<?php include __DIR__ . '/../../includes/footer_materialize.php'; ?>

<script>
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('#tablaSaldoProducto').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            order: [[4, 'asc']],
            pageLength: 50,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-2"lf>rt<"d-flex justify-content-between align-items-center mt-2"ip>',
        });
    }
});
</script>
</body>
</html>
