<?php
/**
 * Vista Standalone: Resumen de Stock
 * Accedida via: /stock/resumen_stock
 *
 * Lógica basada en pedidos (no en movimientos de stock):
 *   Entradas    = unidades en pedidos con estado 1  (En bodega)
 *   Salidas     = unidades en pedidos con estado 3  (Entregado) o 14 (Entregado-liquidado)
 *   En Proceso  = unidades en pedidos con cualquier otro estado activo
 *   Stock Final = Entradas − Salidas − En Proceso
 */
$usaDataTables = false;

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/conexion.php';

start_secure_session();
require_login();
require_role(['Administrador', 'Proveedor', 'Cliente']);

$isAdmin = isSuperAdmin() || in_array('Administrador', $_SESSION['roles_nombres'] ?? [], true);

// ── Filtros ──────────────────────────────────────────────────────────────────
$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$clienteId  = (int)($_GET['id_cliente'] ?? 0);
$export     = isset($_GET['export']) && $_GET['export'] === '1';

// ── Condición de fecha ────────────────────────────────────────────────────────
$db = (new Conexion())->conectar();

$params = [
    ':desde'  => $fechaDesde . ' 00:00:00',
    ':hasta'  => $fechaHasta . ' 23:59:59',
    ':desde2' => $fechaDesde . ' 00:00:00',
    ':hasta2' => $fechaHasta . ' 23:59:59',
    ':desde3' => $fechaDesde . ' 00:00:00',
    ':hasta3' => $fechaHasta . ' 23:59:59',
];

// ── Condición de cliente ──────────────────────────────────────────────────────
// El universo de productos se limita a los que el cliente tiene en algún pedido.
// Entradas (tabla stock) se muestran globales para ese producto.
// Salidas y En Proceso se filtran por cliente.
$whereClientePedido = '';   // para subconsultas de salidas/en_proceso
$whereProductoCliente = ''; // para restringir el universo de productos

if ($clienteId > 0) {
    $whereClientePedido   = 'AND pe.id_cliente = :id_cliente';
    $whereProductoCliente = "AND pr.id IN (
        SELECT DISTINCT pp_c.id_producto
        FROM pedidos_productos pp_c
        INNER JOIN pedidos p_c ON p_c.id = pp_c.id_pedido
        WHERE p_c.id_cliente = :id_cliente2
    )";
    $params[':id_cliente']  = $clienteId;
    $params[':id_cliente2'] = $clienteId;
} elseif (!$isAdmin) {
    $whereClientePedido   = 'AND pe.id_cliente = :id_cliente';
    $whereProductoCliente = "AND pr.id IN (
        SELECT DISTINCT pp_c.id_producto
        FROM pedidos_productos pp_c
        INNER JOIN pedidos p_c ON p_c.id = pp_c.id_pedido
        WHERE p_c.id_cliente = :id_cliente2
    )";
    $params[':id_cliente']  = $_SESSION['user_id'] ?? 0;
    $params[':id_cliente2'] = $_SESSION['user_id'] ?? 0;
}

// ── Query principal ───────────────────────────────────────────────────────────
// Entradas  → tabla stock, tipo_movimiento = 'entrada'  (interfaz / API / Excel masivo)
// Salidas   → pedidos entregados (id_estado 3 = Entregado, 14 = Entregado-liquidado)
// En Proceso→ pedidos con cualquier otro estado activo
// Stock Final = Entradas - Salidas - En Proceso
$sql = "
    SELECT
        pr.id                                                           AS id_producto,
        pr.nombre                                                       AS producto,
        pr.sku,

        -- ENTRADAS: movimientos tipo 'entrada' en la tabla stock (interfaz/API/Excel)
        COALESCE((
            SELECT SUM(s.cantidad)
            FROM stock s
            WHERE s.id_producto = pr.id
              AND s.tipo_movimiento = 'entrada'
              AND s.created_at BETWEEN :desde AND :hasta
        ), 0)                                                           AS entradas,

        -- SALIDAS: unidades en pedidos entregados (estado 3 o 14)
        COALESCE((
            SELECT SUM(pp.cantidad)
            FROM pedidos_productos pp
            INNER JOIN pedidos pe ON pe.id = pp.id_pedido
            WHERE pp.id_producto = pr.id
              AND pe.id_estado IN (3, 14)
              AND pe.fecha_ingreso BETWEEN :desde2 AND :hasta2
              $whereClientePedido
        ), 0)                                                           AS salidas,

        -- EN PROCESO: unidades en pedidos con otros estados activos
        COALESCE((
            SELECT SUM(pp.cantidad)
            FROM pedidos_productos pp
            INNER JOIN pedidos pe ON pe.id = pp.id_pedido
            WHERE pp.id_producto = pr.id
              AND pe.id_estado NOT IN (3, 14)
              AND pe.fecha_ingreso BETWEEN :desde3 AND :hasta3
              $whereClientePedido
        ), 0)                                                           AS en_proceso

    FROM productos pr
    WHERE pr.activo = 1
    $whereProductoCliente

    HAVING (entradas <> 0 OR salidas <> 0 OR en_proceso <> 0)
    ORDER BY pr.nombre ASC
";

$stmt = $db->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular stock_final en PHP: Entradas - Salidas - En Proceso
foreach ($filas as &$f) {
    $f['stock_final'] = (int)$f['entradas'] - (int)$f['salidas'] - (int)$f['en_proceso'];
}
unset($f);

// Totales
$totalStockFinal = array_sum(array_column($filas, 'stock_final'));
$totalEntradas   = array_sum(array_column($filas, 'entradas'));
$totalSalidas    = array_sum(array_column($filas, 'salidas'));
$totalEnProceso  = array_sum(array_column($filas, 'en_proceso'));


// ── Lista de clientes (solo admin) ───────────────────────────────────────────
$clientes = [];
if ($isAdmin) {
    $stmtC = $db->query("
        SELECT DISTINCT u.id, u.nombre
        FROM usuarios u
        INNER JOIN pedidos p ON p.id_cliente = u.id
        WHERE u.activo = 1
        ORDER BY u.nombre ASC
    ");
    $clientes = $stmtC->fetchAll(PDO::FETCH_ASSOC);
}

// ── Export Excel ─────────────────────────────────────────────────────────────
if ($export) {
    require_once __DIR__ . '/../../../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Resumen Stock');

    // Estilos de cabecera
    $headers = ['Producto', 'SKU', 'Stock Final', 'Entradas (En bodega)', 'Salidas (Entregado)', 'En Proceso (Demás estados)'];
    foreach ($headers as $col => $h) {
        $sheet->setCellValueByColumnAndRow($col + 1, 1, $h);
    }
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8BBD9']],
    ];
    $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

    foreach ($filas as $row => $f) {
        $data = [
            $f['producto'],
            $f['sku'] ?? '-',
            (int)$f['stock_final'],
            (int)$f['entradas'],
            (int)$f['salidas'],
            (int)$f['en_proceso'],
        ];
        foreach ($data as $col => $val) {
            $sheet->setCellValueByColumnAndRow($col + 1, $row + 2, $val);
        }
    }

    // Fila de totales
    $lastRow = count($filas) + 2;
    $sheet->setCellValueByColumnAndRow(1, $lastRow, 'TOTAL');
    $sheet->setCellValueByColumnAndRow(3, $lastRow, $totalStockFinal);
    $sheet->setCellValueByColumnAndRow(4, $lastRow, $totalEntradas);
    $sheet->setCellValueByColumnAndRow(5, $lastRow, $totalSalidas);
    $sheet->setCellValueByColumnAndRow(6, $lastRow, $totalEnProceso);
    $totalStyle = ['font' => ['bold' => true]];
    $sheet->getStyle("A{$lastRow}:F{$lastRow}")->applyFromArray($totalStyle);

    foreach (range(1, count($headers)) as $i) $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="resumen_stock_' . date('Ymd') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<style>
/* ══ Resumen Stock ══════════════════════════════════════════════════════════ */
.rs-page { background: #f5f6fa; min-height: 100vh; padding: 1.5rem; }

.rs-header-card {
    background: linear-gradient(135deg, #1a3a6b 0%, #0b2340 100%);
    border-radius: 16px;
    padding: 1.5rem 2rem;
    color: #fff;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.rs-header-card h4 { font-weight: 700; margin: 0; }
.rs-header-card small { opacity: .75; }

.rs-filter-card {
    background: #fff;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    border: 1px solid #e8ecf1;
}

/* Tabla */
.rs-table-wrap {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.07);
    overflow: hidden;
}

.rs-table { width: 100%; border-collapse: collapse; }

/* Cabeceras */
.rs-table thead tr th {
    padding: .75rem 1rem;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    border-bottom: 2px solid rgba(0,0,0,0.06);
}
.th-product  { background: #f8d7e8; color: #6b1e44; text-align: left; }
.th-final    { background: #b2ebf2; color: #00606d; text-align: right; }
.th-entradas { background: #c8e6c9; color: #1b5e20; text-align: right; }
.th-salidas  { background: #ffccbc; color: #8b2500; text-align: right; }
.th-procesar { background: #fff9c4; color: #6d5500; text-align: right; }

/* Filas */
.rs-table tbody tr {
    border-bottom: 1px solid #f0f2f5;
    transition: background .15s;
}
.rs-table tbody tr:hover { background: #f8f9fc; }
.rs-table tbody td { padding: .65rem 1rem; font-size: .88rem; }
.td-product { font-weight: 500; color: #222; }

/* Celdas de valor con color de fondo suave */
.td-final    { background: rgba(178,235,242,.28); color: #00838f; font-weight: 600; text-align: right; }
.td-entradas { background: rgba(200,230,201,.28); color: #2e7d32; font-weight: 600; text-align: right; }
.td-salidas  { background: rgba(255,204,188,.28); color: #bf360c; font-weight: 600; text-align: right; }
.td-procesar { background: rgba(255,249,196,.28); color: #f57f17; font-weight: 600; text-align: right; }

/* Fila de totales */
.rs-table tfoot tr td {
    padding: .75rem 1rem;
    font-size: .85rem;
    font-weight: 700;
    border-top: 2px solid #dee2e6;
    background: #f8f9fc;
    text-align: right;
}
.tfoot-label { text-align: left !important; color: #555; }

/* Badges de totales en el header */
.stat-badge {
    display: inline-flex; flex-direction: column; align-items: center;
    background: rgba(255,255,255,0.12); border-radius: 10px; padding: .5rem .9rem;
    min-width: 90px;
}
.stat-badge .stat-val  { font-size: 1.4rem; font-weight: 700; line-height: 1; }
.stat-badge .stat-lbl  { font-size: .65rem; opacity: .8; margin-top: 2px; text-transform: uppercase; letter-spacing: .04em; }
.stat-final    { border: 1px solid rgba(178,235,242,.5); }
.stat-entradas { border: 1px solid rgba(200,230,201,.5); }
.stat-salidas  { border: 1px solid rgba(255,204,188,.5); }
.stat-procesar { border: 1px solid rgba(255,249,196,.5); }

/* Botón Excel */
.btn-excel {
    background: #1d6f42; color: #fff; border: none;
    border-radius: 8px; padding: .4rem .9rem; font-size: .82rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 5px; text-decoration: none;
    transition: background .2s;
}
.btn-excel:hover { background: #155234; color: #fff; }

/* Empty state */
.rs-empty { text-align: center; padding: 3rem 1rem; color: #aaa; }
.rs-empty i { font-size: 3rem; display: block; margin-bottom: .5rem; opacity: .4; }
</style>

<div class="rs-page">

    <!-- ── Header card ─────────────────────────────────────────────────── -->
    <div class="rs-header-card">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h4><i class="bi bi-bar-chart-line-fill me-2"></i>Resumen de Stock</h4>
                <small>Basado en estados de pedidos: En bodega (entrada), Entregado (salida), demás estados (en proceso)</small>
            </div>
            <!-- Stats rápidos -->
            <div class="d-flex gap-2 flex-wrap">
                <div class="stat-badge stat-final">
                    <span class="stat-val"><?= number_format($totalStockFinal) ?></span>
                    <span class="stat-lbl">Stock Final</span>
                </div>
                <div class="stat-badge stat-entradas">
                    <span class="stat-val"><?= number_format($totalEntradas) ?></span>
                    <span class="stat-lbl">Entradas</span>
                </div>
                <div class="stat-badge stat-salidas">
                    <span class="stat-val"><?= number_format($totalSalidas) ?></span>
                    <span class="stat-lbl">Salidas</span>
                </div>
                <div class="stat-badge stat-procesar">
                    <span class="stat-val"><?= number_format($totalEnProceso) ?></span>
                    <span class="stat-lbl">En Proceso</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Filtros ─────────────────────────────────────────────────────── -->
    <div class="rs-filter-card">
        <form method="GET" action="<?= RUTA_URL ?>stock/resumen_stock" class="row g-2 align-items-end">
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
            <?php if ($isAdmin): ?>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Cliente</label>
                <select name="id_cliente" class="form-select form-select-sm">
                    <option value="">Todos los clientes</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $clienteId == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary px-3">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="<?= RUTA_URL ?>stock/resumen_stock" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i>Limpiar
                </a>
                <a href="<?= RUTA_URL ?>stock/resumen_stock?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>"
                   class="btn-excel">
                    <i class="bi bi-file-earmark-excel"></i>Excel
                </a>
            </div>
        </form>
    </div>

    <!-- ── Tabla ───────────────────────────────────────────────────────── -->
    <div class="rs-table-wrap">
        <?php if (empty($filas)): ?>
        <div class="rs-empty">
            <i class="bi bi-inbox"></i>
            No hay datos de stock para el período seleccionado.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="rs-table">
                <thead>
                    <tr>
                        <th class="th-product">Producto</th>
                        <th class="th-final">Stock Final</th>
                        <th class="th-entradas">Entradas <small class="fw-normal opacity-75">(En bodega)</small></th>
                        <th class="th-salidas">Salidas <small class="fw-normal opacity-75">(Entregado)</small></th>
                        <th class="th-procesar">En Proceso <small class="fw-normal opacity-75">(Demás estados)</small></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $f): ?>
                    <tr>
                        <td class="td-product">
                            <?= htmlspecialchars($f['producto']) ?>
                            <?php if (!empty($f['sku'])): ?>
                            <small class="text-muted ms-1">(<?= htmlspecialchars($f['sku']) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td class="td-final"><?= number_format((int)$f['stock_final']) ?></td>
                        <td class="td-entradas"><?= number_format((int)$f['entradas']) ?></td>
                        <td class="td-salidas"><?= number_format((int)$f['salidas']) ?></td>
                        <td class="td-procesar"><?= number_format((int)$f['en_proceso']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="tfoot-label">Total: <?= count($filas) ?> productos</td>
                        <td>Total: <?= number_format($totalStockFinal) ?></td>
                        <td>Total: <?= number_format($totalEntradas) ?></td>
                        <td>Total: <?= number_format($totalSalidas) ?></td>
                        <td>Total: <?= number_format($totalEnProceso) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
