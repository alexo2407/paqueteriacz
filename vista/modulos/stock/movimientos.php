<?php
/**
 * Vista Standalone: Reporte Movimientos de Stock
 * Accedida via: /stock/movimientos
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

$isAdmin = isSuperAdmin();

// ── Filtros ──────────────────────────────────────────────────────────────────
$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$tipoFiltro = $_GET['tipo']        ?? '';
$clienteId  = (int)($_GET['id_cliente'] ?? 0);
$export     = isset($_GET['export']) && $_GET['export'] === '1';

// ── Query ────────────────────────────────────────────────────────────────────
$db = (new Conexion())->conectar();

$where  = ['s.created_at BETWEEN :desde AND :hasta'];
$params = [
    ':desde' => $fechaDesde . ' 00:00:00',
    ':hasta' => $fechaHasta . ' 23:59:59',
];

if ($tipoFiltro !== '') {
    $where[]         = 's.tipo_movimiento = :tipo';
    $params[':tipo'] = $tipoFiltro;
}

if ($clienteId > 0) {
    $where[]              = 'p.id_cliente = :id_cliente';
    $params[':id_cliente'] = $clienteId;
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT
        s.id,
        s.created_at       AS fecha,
        pr.nombre          AS producto,
        s.cantidad,
        s.tipo_movimiento,
        s.referencia_tipo,
        s.referencia_id,
        s.motivo,
        s.ubicacion_origen,
        s.ubicacion_destino,
        u.nombre           AS usuario,
        p.numero_orden     AS orden_referencia,
        uc.nombre          AS cliente
    FROM stock s
    LEFT JOIN productos  pr ON pr.id = s.id_producto
    LEFT JOIN usuarios    u ON  u.id = s.id_usuario
    LEFT JOIN pedidos     p ON  p.id = s.referencia_id AND s.referencia_tipo = 'pedido'
    LEFT JOIN usuarios   uc ON uc.id = p.id_cliente
    $whereStr
    ORDER BY s.created_at DESC
    LIMIT 2000
";

$stmt = $db->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Clientes para filtro (solo admin) ────────────────────────────────────────
$clientes = [];
if ($isAdmin) {
    $stmtC = $db->query("SELECT id, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre ASC");
    $clientes = $stmtC->fetchAll(PDO::FETCH_ASSOC);
}

// ── Export Excel ─────────────────────────────────────────────────────────────
if ($export) {
    require_once __DIR__ . '/../../../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Movimientos de Stock');

    $headers = ['ID','Fecha','Producto','Cantidad','Tipo','Ref. Tipo','Ref. ID','Motivo','Origen','Destino','Usuario','# Orden','Cliente'];
    foreach ($headers as $col => $h) {
        $sheet->setCellValueByColumnAndRow($col + 1, 1, $h);
    }
    foreach ($movimientos as $row => $m) {
        $data = [
            $m['id'], $m['fecha'], $m['producto'], $m['cantidad'],
            $m['tipo_movimiento'], $m['referencia_tipo'], $m['referencia_id'],
            $m['motivo'], $m['ubicacion_origen'], $m['ubicacion_destino'],
            $m['usuario'], $m['orden_referencia'], $m['cliente'],
        ];
        foreach ($data as $col => $val) {
            $sheet->setCellValueByColumnAndRow($col + 1, $row + 2, $val);
        }
    }
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
    foreach (range(1, count($headers)) as $i) $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="movimientos_stock_' . date('Ymd') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Movimientos de Stock - App RutaEx-Latam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <style>
        .mov-header {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            color: white;
            padding: 1.75rem 2rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(44,83,100,0.25);
        }
        .filter-card { border: none; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,.06); }
        .table-card  { border: none; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,.05); overflow: hidden; }
        .badge-type  { padding: .45em .8em; border-radius: 6px; font-size: .8rem; font-weight: 600; }
    </style>
</head>
<body class="bg-light">

<?php include __DIR__ . '/../../includes/header_materialize.php'; ?>

<div class="container-fluid py-4">

    <!-- Cabecera -->
    <div class="mov-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-journal-arrow-down me-2"></i>Reporte Movimientos de Stock</h4>
            <small class="opacity-75">Historial de entradas, salidas y devoluciones</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= RUTA_URL ?>stock/saldo" class="btn btn-outline-light btn-sm">
                <i class="bi bi-bar-chart-steps me-1"></i>Saldo por Producto
            </a>
            <a href="<?= RUTA_URL ?>stock/movimientos?<?= http_build_query(array_merge($_GET, ['export'=>'1'])) ?>"
               class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card filter-card mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= RUTA_URL ?>stock/movimientos" class="row g-2 align-items-end">
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold mb-1">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($fechaDesde) ?>">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold mb-1">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($fechaHasta) ?>">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold mb-1">Tipo</label>
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach(['entrada','salida','ajuste','devolucion','transferencia'] as $t): ?>
                        <option value="<?= $t ?>" <?= $tipoFiltro === $t ? 'selected' : '' ?>>
                            <?= ucfirst($t) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($isAdmin && !empty($clientes)): ?>
                <div class="col-md-3 col-6">
                    <label class="form-label small fw-semibold mb-1">Cliente</label>
                    <select name="id_cliente" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php foreach($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $clienteId == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2 col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-funnel me-1"></i>Filtrar
                    </button>
                    <a href="<?= RUTA_URL ?>stock/movimientos" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card table-card">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2">
            <span class="fw-semibold small">
                <?= count($movimientos) ?> movimiento<?= count($movimientos) !== 1 ? 's' : '' ?>
                &mdash; <?= date('d/m/Y', strtotime($fechaDesde)) ?> al <?= date('d/m/Y', strtotime($fechaHasta)) ?>
            </span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($movimientos)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                No hay movimientos para los filtros seleccionados.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table id="tablaMovimientosStock" class="table table-hover table-sm mb-0 align-middle small" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Fecha</th>
                            <th>Producto</th>
                            <th class="text-center">Cantidad</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Referencia</th>
                            <th>Cliente</th>
                            <th>Usuario</th>
                            <th>Origen → Destino</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($movimientos as $m):
                        $pos = $m['cantidad'] > 0;
                        $badgeClass = match($m['tipo_movimiento']) {
                            'entrada'       => 'success',
                            'salida'        => 'danger',
                            'devolucion'    => 'warning',
                            'ajuste'        => 'info',
                            'transferencia' => 'primary',
                            default         => 'secondary',
                        };
                    ?>
                    <tr>
                        <td class="ps-3 text-nowrap"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($m['producto'] ?? '—') ?></td>
                        <td class="text-center fw-bold <?= $pos ? 'text-success' : 'text-danger' ?>">
                            <?= $pos ? '+' : '' ?><?= $m['cantidad'] ?>
                        </td>
                        <td><span class="badge bg-<?= $badgeClass ?> badge-type"><?= ucfirst($m['tipo_movimiento']) ?></span></td>
                        <td class="text-muted"><?= htmlspecialchars($m['motivo'] ?? '—') ?></td>
                        <td class="text-nowrap">
                            <?php if ($m['referencia_tipo'] === 'pedido' && $m['orden_referencia']): ?>
                                <a href="<?= RUTA_URL ?>pedidos/ver/<?= $m['referencia_id'] ?>" class="text-decoration-none">
                                    #<?= $m['orden_referencia'] ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted"><?= htmlspecialchars($m['referencia_tipo'] ?? '—') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($m['cliente'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($m['usuario'] ?? '—') ?></td>
                        <td class="text-muted">
                            <?= htmlspecialchars($m['ubicacion_origen'] ?? '—') ?> → <?= htmlspecialchars($m['ubicacion_destino'] ?? '—') ?>
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
        $('#tablaMovimientosStock').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            order: [[0, 'desc']],
            pageLength: 25,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-2"lf>rt<"d-flex justify-content-between align-items-center mt-2"ip>',
        });
    }
});
</script>
</body>
</html>
