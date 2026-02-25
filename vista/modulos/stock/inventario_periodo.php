<?php
/**
 * Vista Standalone: Inventario por Período — vista matricial (pivot)
 * Ruta: /stock/inventario-periodo
 * Patrón: autocontenida, igual que listar.php
 *
 * Muestra entradas/salidas/saldo acumulado por día y por producto en formato
 * de tabla cruzada (fechas en filas, productos en columnas) con filtros por
 * cliente, proveedor, estado del pedido y producto específico.
 */
$usaDataTables = false;  // tabla manual — demasiadas columnas dinámicas para DataTables

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/conexion.php';

start_secure_session();
require_login();
require_role(['Administrador', 'Proveedor']);

$isAdmin = isSuperAdmin();
$db      = (new Conexion())->conectar();

// ── Leer filtros de la URL ────────────────────────────────────────────────────
$fechaDesde  = $_GET['fecha_desde']  ?? date('Y-m-01');
$fechaHasta  = $_GET['fecha_hasta']  ?? date('Y-m-t');   // último día del mes
$idCliente   = (int)($_GET['id_cliente']  ?? 0);
$idProveedor = (int)($_GET['id_proveedor'] ?? 0);
$idEstado    = (int)($_GET['id_estado']   ?? 0);
$idProducto  = (int)($_GET['id_producto'] ?? 0);
$export      = isset($_GET['export']) && $_GET['export'] === '1';

// ── Cargar catálogos para los dropdowns ──────────────────────────────────────
$estados = $db->query(
    'SELECT id, nombre_estado FROM estados_pedidos ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);

$clientes = $db->query(
    "SELECT id, nombre FROM usuarios
     WHERE activo = 1 ORDER BY nombre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$proveedores = $db->query(
    "SELECT DISTINCT u.id, u.nombre
     FROM usuarios u
     INNER JOIN usuarios_roles ur ON ur.id_usuario = u.id
     WHERE ur.id_rol = " . ROL_PROVEEDOR . " AND u.activo = 1
     ORDER BY u.nombre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$productos = $db->query(
    "SELECT id, nombre FROM productos WHERE activo = 1 ORDER BY nombre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// ── Query principal: entradas y salidas por día × producto ───────────────────
/*
 * Lógica de JOIN:
 *   - stock siempre tiene id_producto
 *   - si referencia_tipo = 'pedido', unimos con pedidos para filtrar
 *     por id_cliente (dueño del producto/pedido),
 *     id_proveedor (mensajero) e id_estado
 *   - si referencia_tipo != 'pedido' (ej. ajuste manual), incluimos
 *     el movimiento solo cuando NO hay filtros de pedido activos
 */
$where  = ['s.created_at BETWEEN :desde AND :hasta', 'pr.activo = 1'];
$params = [
    ':desde' => $fechaDesde . ' 00:00:00',
    ':hasta' => $fechaHasta . ' 23:59:59',
];

if ($idCliente > 0) {
    $where[]              = 'ped.id_cliente = :id_cliente';
    $params[':id_cliente'] = $idCliente;
}
if ($idProveedor > 0) {
    $where[]                = 'ped.id_proveedor = :id_proveedor';
    $params[':id_proveedor'] = $idProveedor;
}
if ($idEstado > 0) {
    $where[]             = 'ped.id_estado = :id_estado';
    $params[':id_estado'] = $idEstado;
}
if ($idProducto > 0) {
    $where[]               = 's.id_producto = :id_producto';
    $params[':id_producto'] = $idProducto;
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

// Si hay filtros de pedido (cliente/proveedor/estado), forzamos JOIN INNER en pedidos
// para que los ajustes manuales sin referencia no contaminen el reporte
$joinType = ($idCliente > 0 || $idProveedor > 0 || $idEstado > 0)
    ? 'INNER JOIN'
    : 'LEFT JOIN';

$sqlMovs = "
    SELECT
        DATE(s.created_at)                                              AS fecha,
        pr.id                                                           AS id_producto,
        pr.nombre                                                       AS producto,
        SUM(CASE WHEN s.cantidad > 0 THEN  s.cantidad ELSE 0 END)      AS entradas,
        SUM(CASE WHEN s.cantidad < 0 THEN -s.cantidad ELSE 0 END)      AS salidas
    FROM stock s
    JOIN productos pr ON pr.id = s.id_producto
    {$joinType} pedidos ped
        ON ped.id = s.referencia_id AND s.referencia_tipo = 'pedido'
    {$whereStr}
    GROUP BY DATE(s.created_at), pr.id, pr.nombre
    ORDER BY fecha ASC, pr.nombre ASC
";

$stmt = $db->prepare($sqlMovs);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$rawMovs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Saldo inicial ANTES del rango ─────────────────────────────────────────────
// Para cada producto que aparece, calculamos el saldo acumulado anterior al rango
$productoIds = array_unique(array_column($rawMovs, 'id_producto'));

$saldosIniciales = [];
if (!empty($productoIds)) {
    $placeholders = implode(',', array_fill(0, count($productoIds), '?'));
    $sqlSaldo = "
        SELECT s.id_producto, COALESCE(SUM(s.cantidad), 0) AS saldo
        FROM stock s
        WHERE s.id_producto IN ({$placeholders}) AND s.created_at < ?
        GROUP BY s.id_producto
    ";
    $stmtS = $db->prepare($sqlSaldo);
    $bindVals = array_merge($productoIds, [$fechaDesde . ' 00:00:00']);
    $stmtS->execute($bindVals);
    foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $saldosIniciales[(int)$r['id_producto']] = (int)$r['saldo'];
    }
}

// ── Construir pivot ───────────────────────────────────────────────────────────
// pivot[$fecha][$idProducto] = ['entradas' => int, 'salidas' => int]
$pivot    = [];
$colsList = [];  // [id_producto => nombre]

foreach ($rawMovs as $m) {
    $pid = (int)$m['id_producto'];
    $pivot[$m['fecha']][$pid] = [
        'entradas' => (int)$m['entradas'],
        'salidas'  => (int)$m['salidas'],
    ];
    $colsList[$pid] = $m['producto'];
}

// Ordenar columnas alfabéticamente
asort($colsList);

// Generar todas las fechas del rango (días consecutivos)
$dateRange = [];
$cur = strtotime($fechaDesde);
$end = strtotime($fechaHasta);
while ($cur <= $end) {
    $dateRange[] = date('Y-m-d', $cur);
    $cur = strtotime('+1 day', $cur);
}

// Calcular saldos acumulados por producto, día a día
$saldosAcum = $saldosIniciales;  // comienza con el saldo inicial

// Estructura final: filas del reporte
$filas = [];
foreach ($dateRange as $fecha) {
    $rowEntry = ['fecha' => $fecha, 'tipo' => 'entrada', 'data' => []];
    $rowSalid = ['fecha' => $fecha, 'tipo' => 'salida',  'data' => []];
    $rowTotal = ['fecha' => $fecha, 'tipo' => 'total',   'data' => []];

    $tieneMovimiento = false;

    foreach ($colsList as $pid => $pnombre) {
        $e = $pivot[$fecha][$pid]['entradas'] ?? 0;
        $s = $pivot[$fecha][$pid]['salidas']  ?? 0;

        if ($e > 0 || $s > 0) $tieneMovimiento = true;

        $saldosAcum[$pid] = ($saldosAcum[$pid] ?? 0) + $e - $s;

        $rowEntry['data'][$pid] = $e;
        $rowSalid['data'][$pid] = $s;
        $rowTotal['data'][$pid] = $saldosAcum[$pid];
    }

    $filas[] = ['entry' => $rowEntry, 'salida' => $rowSalid, 'total' => $rowTotal, 'tiene_mov' => $tieneMovimiento];
}

// ── Export Excel ──────────────────────────────────────────────────────────────
if ($export && !empty($colsList)) {
    require_once __DIR__ . '/../../../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Inventario por Período');

    $totalCols     = count($colsList) + 2;  // col A = fecha, col B = tipo, luego productos
    $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);

    // ── Fila 1: título con rango de fechas ────────────────────────────────────
    $titulo = 'INVENTARIO '
        . date('d/m/Y', strtotime($fechaDesde))
        . ' → '
        . date('d/m/Y', strtotime($fechaHasta));
    $sheet->mergeCells("A1:{$lastColLetter}1");
    $sheet->setCellValue('A1', $titulo);
    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFFFF'], 'size' => 13],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '1A3A4A']],
        'alignment' => ['horizontal' => 'center'],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(22);

    // ── Fila 2: cabeceras de columnas ─────────────────────────────────────────
    $sheet->setCellValue('A2', 'FECHA');
    $sheet->setCellValue('B2', 'TIPO');
    $colIdx = 3;
    foreach ($colsList as $pnombre) {
        $sheet->setCellValueByColumnAndRow($colIdx, 2, strtoupper($pnombre));
        $colIdx++;
    }
    $sheet->getStyle("A2:{$lastColLetter}2")->applyFromArray([
        'font' => ['color' => ['rgb' => 'FFFFFFFF'], 'bold' => true],
        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2D6A4F']],
        'alignment' => ['horizontal' => 'center'],
    ]);

    // ── Filas de datos: entrada + salida por día ──────────────────────────────
    $excelRow = 3;
    foreach ($filas as $grupo) {
        $fechaLabel = date('d/m/Y', strtotime($grupo['entry']['fecha']));

        // Fila entrada
        $sheet->setCellValue("A{$excelRow}", $fechaLabel);
        $sheet->setCellValue("B{$excelRow}", 'Entrada');
        $c = 3;
        foreach ($colsList as $pid => $pn) {
            $sheet->setCellValueByColumnAndRow($c, $excelRow, $grupo['entry']['data'][$pid] ?? 0);
            $c++;
        }
        $sheet->getStyle("A{$excelRow}:{$lastColLetter}{$excelRow}")->applyFromArray([
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8F5E9']],
            'font' => ['color' => ['rgb' => '2E7D32']],
        ]);
        $excelRow++;

        // Fila salida
        $sheet->setCellValue("A{$excelRow}", $fechaLabel);
        $sheet->setCellValue("B{$excelRow}", 'Salida');
        $c = 3;
        foreach ($colsList as $pid => $pn) {
            $sheet->setCellValueByColumnAndRow($c, $excelRow, $grupo['salida']['data'][$pid] ?? 0);
            $c++;
        }
        $sheet->getStyle("A{$excelRow}:{$lastColLetter}{$excelRow}")->applyFromArray([
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF3E0']],
            'font' => ['color' => ['rgb' => 'BF360C']],
        ]);
        $excelRow++;
    }

    // ── Fila Total — saldo acumulado final (solo una vez) ─────────────────────
    $ultimoGrupo = end($filas);
    if ($ultimoGrupo) {
        $sheet->mergeCells("A{$excelRow}:B{$excelRow}");
        $sheet->setCellValue("A{$excelRow}", 'TOTAL PRODUCTO');
        $c = 3;
        foreach ($colsList as $pid => $pn) {
            $sheet->setCellValueByColumnAndRow($c, $excelRow, $ultimoGrupo['total']['data'][$pid] ?? 0);
            $c++;
        }
        $sheet->getStyle("A{$excelRow}:{$lastColLetter}{$excelRow}")->applyFromArray([
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFD600']],
            'font' => ['bold' => true, 'size' => 11],
        ]);
        $sheet->getRowDimension($excelRow)->setRowHeight(18);
    }

    // ── Auto-size columnas ────────────────────────────────────────────────────
    foreach (range(1, $totalCols) as $ci) {
        $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
    }

    // ── Freeze panes: congela fila 2 y columnas A-B ───────────────────────────
    $sheet->freezePane('C3');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $filename = 'inventario_' . $fechaDesde . '_' . $fechaHasta . '.xlsx';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
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
    <title>Inventario por Período - App RutaEx-Latam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .inv-header {
            background: linear-gradient(135deg, #1a3a4a 0%, #2d6a4f 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(29,106,79,.25);
        }
        .filter-card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.06); }

        /* Tabla matricial */
        .tabla-matriz { font-size: .78rem; white-space: nowrap; }
        .tabla-matriz thead th {
            background: #2d6a4f;
            color: #fff;
            padding: .4rem .6rem;
            font-weight: 700;
            text-align: center;
            border: 1px solid rgba(255,255,255,.15);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .tabla-matriz thead th:first-child { left: 0; z-index: 11; min-width: 100px; }
        .tabla-matriz tbody td {
            padding: .3rem .6rem;
            border: 1px solid #dee2e6;
            text-align: center;
            vertical-align: middle;
        }
        /* Fila entrada — verde claro */
        .fila-entrada td { background: #e8f5e9; color: #2e7d32; }
        .fila-entrada td:first-child { font-weight: 600; background: #c8e6c9; }
        /* Fila salida — rojo muy suave */
        .fila-salida  td { background: #fff3e0; color: #bf360c; }
        .fila-salida  td:first-child { background: #ffe0b2; }
        /* Fila total — amarillo brillante */
        .fila-total   td { background: #ffff00; color: #000; font-weight: 700; }
        .fila-total   td:first-child { font-style: italic; background: #ffd600; }
        /* Columna fecha sticky */
        .col-fecha { position: sticky; left: 0; z-index: 5; }
        /* Sin movimientos — gris muy suave */
        .fila-sin-mov td { opacity: .45; }

        .tabla-wrapper { overflow-x: auto; max-height: 70vh; overflow-y: auto; border-radius: 10px; border: 1px solid #dee2e6; }
    </style>
</head>
<body class="bg-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4">

    <!-- Cabecera -->
    <div class="inv-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-table me-2"></i>Inventario por Período
            </h4>
            <small class="opacity-75">
                Vista matricial · Entradas, salidas y saldo por día y producto
            </small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= RUTA_URL ?>stock/movimientos" class="btn btn-outline-light btn-sm">
                <i class="bi bi-journal-arrow-down me-1"></i>Movimientos
            </a>
            <a href="<?= RUTA_URL ?>stock/saldo" class="btn btn-outline-light btn-sm">
                <i class="bi bi-bar-chart-steps me-1"></i>Saldo
            </a>
            <a href="<?= RUTA_URL ?>stock/inventario_periodo?<?= http_build_query(array_merge($_GET, ['export'=>'1'])) ?>"
               class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card filter-card mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= RUTA_URL ?>stock/inventario_periodo"
                  class="row g-2 align-items-end">
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-calendar3"></i> Desde
                    </label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($fechaDesde) ?>">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-calendar3"></i> Hasta
                    </label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($fechaHasta) ?>">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-person"></i> Cliente
                    </label>
                    <select name="id_cliente" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>"
                                <?= $idCliente == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-truck"></i> Proveedor
                    </label>
                    <select name="id_proveedor" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php foreach ($proveedores as $p): ?>
                        <option value="<?= $p['id'] ?>"
                                <?= $idProveedor == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-flag"></i> Estado pedido
                    </label>
                    <select name="id_estado" class="form-select form-select-sm">
                        <option value="0">Todos los estados</option>
                        <?php foreach ($estados as $e): ?>
                        <option value="<?= $e['id'] ?>"
                                <?= $idEstado == $e['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nombre_estado']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 col-6">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-box"></i> Producto
                    </label>
                    <select name="id_producto" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php foreach ($productos as $pr): ?>
                        <option value="<?= $pr['id'] ?>"
                                <?= $idProducto == $pr['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pr['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 col-12 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="<?= RUTA_URL ?>stock/inventario_periodo"
                       class="btn btn-outline-secondary btn-sm" title="Limpiar">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($colsList)): ?>
    <!-- Sin datos -->
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
        No hay movimientos de stock para el período y filtros seleccionados.<br>
        <small>Prueba ampliar el rango de fechas o ajustar los filtros.</small>
    </div>

    <?php else: ?>

    <!-- Leyenda -->
    <div class="d-flex gap-4 mb-2 small flex-wrap px-1">
        <span class="d-flex align-items-center gap-1">
            <span class="badge" style="background:#2e7d32">+</span>
            Entradas del día
        </span>
        <span class="d-flex align-items-center gap-1">
            <span class="badge" style="background:#bf360c">−</span>
            Salidas del día
        </span>
        <span class="d-flex align-items-center gap-1">
            <span class="badge" style="background:#ffd600;color:#000">T</span>
            Saldo acumulado (Total producto)
        </span>
        <span class="text-muted">Filas atenuadas = sin movimiento ese día</span>
    </div>

    <!-- Tabla matricial -->
    <div class="tabla-wrapper">
        <table class="table table-bordered tabla-matriz mb-0">
            <thead>
                <tr>
                    <th class="col-fecha">FECHA</th>
                    <?php foreach ($colsList as $pnombre): ?>
                    <th><?= htmlspecialchars(strtoupper($pnombre)) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $grupo):
                $sinMov = !$grupo['tiene_mov'];
                $sMov   = $sinMov ? ' fila-sin-mov' : '';
            ?>
                <!-- Fila Entradas -->
                <tr class="fila-entrada<?= $sMov ?>">
                    <td class="col-fecha text-nowrap">
                        <?= date('d/m/Y', strtotime($grupo['entry']['fecha'])) ?>
                    </td>
                    <?php foreach ($colsList as $pid => $pn): ?>
                    <td><?= $grupo['entry']['data'][$pid] > 0
                            ? '+' . $grupo['entry']['data'][$pid]
                            : '0' ?></td>
                    <?php endforeach; ?>
                </tr>

                <!-- Fila Salidas -->
                <tr class="fila-salida<?= $sMov ?>">
                    <td class="col-fecha text-nowrap text-muted" style="font-size:.7rem">
                        <?= date('d/m/Y', strtotime($grupo['salida']['fecha'])) ?>
                    </td>
                    <?php foreach ($colsList as $pid => $pn): ?>
                    <td><?= $grupo['salida']['data'][$pid] > 0
                            ? '−' . $grupo['salida']['data'][$pid]
                            : '0' ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>

                <!-- Fila Total — saldo acumulado FINAL (una sola vez al final) -->
                <?php $ultimoGrupo = end($filas); if ($ultimoGrupo): ?>
                <tr class="fila-total">
                    <td class="col-fecha">Total producto</td>
                    <?php foreach ($colsList as $pid => $pn): ?>
                    <td><?= number_format($ultimoGrupo['total']['data'][$pid] ?? 0) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="text-muted small mt-2 px-1">
        Mostrando <?= count($dateRange) ?> días ·
        <?= count($colsList) ?> producto<?= count($colsList) !== 1 ? 's' : '' ?> ·
        <?= date('d/m/Y', strtotime($fechaDesde)) ?> → <?= date('d/m/Y', strtotime($fechaHasta)) ?>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

</body>
</html>
