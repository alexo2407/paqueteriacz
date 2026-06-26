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
$isAdmin     = isSuperAdmin();
$currRol     = $_SESSION['rol'] ?? 0;
// En este sistema, ID 4 se llama "Cliente" en BD y ID 5 se llama "Proveedor"
$isRealCliente   = ($currRol == 4); 
$isRealProveedor = ($currRol == 5);
$currUserId      = getCurrentUserId();
$db              = (new Conexion())->conectar();

// Traer el token_enlace_publico del usuario
$stmtToken = $db->prepare("SELECT token_enlace_publico FROM usuarios WHERE id = :id");
$stmtToken->execute([':id' => $currUserId]);
$tokenEnlacePublico = $stmtToken->fetchColumn();

// ── Leer filtros de la URL ────────────────────────────────────────────────────
// Default: primer día del mes actual → hoy
$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$idCliente   = ($isRealCliente)   ? $currUserId : (int)($_GET['id_cliente']  ?? 0);
$idProveedor = ($isRealProveedor) ? $currUserId : (int)($_GET['id_proveedor'] ?? 0);
$idEstado    = (int)($_GET['id_estado']   ?? 0);
$idProducto  = (int)($_GET['id_producto'] ?? 0);
$export      = isset($_GET['export']) && $_GET['export'] === '1';

// Token_enlace_publico ya cargado previamente

// ── Cargar catálogos para los dropdowns ──────────────────────────────────────
$estados = $db->query(
    'SELECT id, nombre_estado FROM estados_pedidos ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);

$clientes = $db->query(
    "SELECT DISTINCT u.id, u.nombre
     FROM usuarios u
     INNER JOIN usuarios_roles ur ON ur.id_usuario = u.id
     INNER JOIN roles r ON r.id = ur.id_rol
     WHERE r.nombre_rol = 'Cliente' AND u.activo = 1"
    . ($isRealCliente ? " AND u.id = " . (int)$currUserId : "") . "
     ORDER BY u.nombre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$proveedores = $db->query(
    "SELECT DISTINCT u.id, u.nombre
     FROM usuarios u
     INNER JOIN usuarios_roles ur ON ur.id_usuario = u.id
     INNER JOIN roles r ON r.id = ur.id_rol
     " . ($isRealCliente ? " INNER JOIN pedidos ped ON ped.id_proveedor = u.id " : "") . "
     WHERE r.nombre_rol = 'Proveedor' AND u.activo = 1 " 
     . ($isRealCliente ? " AND ped.id_cliente = " . (int)$currUserId : "")
     . ($isRealProveedor ? " AND u.id = " . (int)$currUserId : "") . "
     ORDER BY u.nombre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$productos = $db->query(
    "SELECT id, nombre FROM productos WHERE activo = 1 " 
    . ((!$isAdmin && ($isRealCliente || $isRealProveedor)) ? " AND id_usuario_creador = " . (int)$currUserId : "") . "
     ORDER BY nombre ASC"
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
// Condiciones que van en el ON del LEFT JOIN (sobre la tabla pedidos)
// para que movimientos sin pedido (ajustes manuales) no sean excluidos.
$joinOnExtras = [];

if ($idProducto > 0) {
    $where[]               = 's.id_producto = :id_producto';
    $params[':id_producto'] = $idProducto;
}
if (!$isAdmin && ($isRealCliente || $isRealProveedor)) {
    // Restringimos por creador del producto usando el filtro dinámico de permisos
    $filtroArray = getIdUsuarioCreadorFilter();
    if (is_array($filtroArray) && !empty($filtroArray)) {
        $in = implode(',', array_map('intval', $filtroArray));
        $where[] = "pr.id_usuario_creador IN ($in)";
    } else {
        $where[] = 'pr.id_usuario_creador = :curr_user_id';
        $params[':curr_user_id'] = $currUserId;
    }
}

// ── Determinar tipo de JOIN y dónde poner los filtros de pedido ──────────────
// SIEMPRE usamos LEFT JOIN para no excluir movimientos manuales (sin referencia a pedido).
// Cuando hay filtros de pedido (cliente/proveedor/estado), la condición se pone en el WHERE
// pero con una cláusula OR que preserva los movimientos que NO son de pedido (entradas manuales).
$hayFiltrosPedido = ($idCliente > 0 || $idProveedor > 0 || $idEstado > 0);
// Siempre LEFT JOIN — los ajustes/entradas manuales no tienen referencia_id de pedido
// y deben aparecer siempre, independientemente del filtro de cliente.
$joinType = 'LEFT JOIN';

// Condiciones de filtro sobre pedidos: se aplican en el WHERE pero permiten
// pasar los movimientos que no están ligados a un pedido (referencia_tipo IS NULL o != 'pedido')
$pedidoFilterConditions = [];
if ($idCliente > 0) {
    $joinOnExtras[]        = 'ped.id_cliente = :id_cliente';
    $params[':id_cliente'] = $idCliente;
    $pedidoFilterConditions[] = 'ped.id_cliente = :id_cliente_w';
    $params[':id_cliente_w']  = $idCliente;
}
if ($idProveedor > 0) {
    $joinOnExtras[]           = 'ped.id_proveedor = :id_proveedor';
    $params[':id_proveedor']  = $idProveedor;
    $pedidoFilterConditions[] = 'ped.id_proveedor = :id_proveedor_w';
    $params[':id_proveedor_w'] = $idProveedor;
}
if ($idEstado > 0) {
    $joinOnExtras[]       = 'ped.id_estado = :id_estado';
    $params[':id_estado'] = $idEstado;
    $pedidoFilterConditions[] = 'ped.id_estado = :id_estado_w';
    $params[':id_estado_w']   = $idEstado;
}

// Si hay filtros de pedido, añadir condición al WHERE que incluye tanto
// los movimientos de pedidos que cumplen el filtro COMO los movimientos manuales (sin pedido).
if (!empty($pedidoFilterConditions)) {
    $pedidoCond = implode(' AND ', $pedidoFilterConditions);
    // Incluir movimiento si: (cumple filtros de pedido) O (no es un movimiento de pedido)
    $where[] = "( ({$pedidoCond}) OR (s.referencia_tipo IS NULL OR s.referencia_tipo != 'pedido') )";
}

$whereStr  = 'WHERE ' . implode(' AND ', $where);
$joinOnStr = 'ped.id = s.referencia_id AND s.referencia_tipo = \'pedido\'';
if (!empty($joinOnExtras)) {
    $joinOnStr .= ' AND ' . implode(' AND ', $joinOnExtras);
}

$sqlMovs = "
    SELECT
        DATE(s.created_at)                                              AS fecha,
        pr.id                                                           AS id_producto,
        pr.nombre                                                       AS producto,
        COALESCE(s.tipo_movimiento, 'otro')                             AS tipo_movimiento,
        CASE WHEN s.cantidad > 0 THEN  s.cantidad ELSE 0 END          AS entradas,
        CASE WHEN s.cantidad < 0 THEN -s.cantidad ELSE 0 END          AS salidas,
        s.referencia_id,
        s.referencia_tipo
    FROM stock s
    JOIN productos pr ON pr.id = s.id_producto
    {$joinType} pedidos ped
        ON {$joinOnStr}
    {$whereStr}
    ORDER BY fecha ASC, pr.nombre ASC, s.created_at ASC
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

// ── Reservas activas (pedidos En Bodega — nuevo flujo PedidoService) ───────────
// Unidades comprometidas a pedidos en estado 1 (En Bodega) con reserva no liberada.
// Estas NO tienen salida física en stock todavía, pero tampoco son stock libre.
$reservasActivas = [];
if (!empty($productoIds)) {
    $placeholdersR = implode(',', array_fill(0, count($productoIds), '?'));
    $sqlRes = "
        SELECT prs.id_producto, SUM(prs.cantidad) AS total_reservado
        FROM pedido_reservas_stock prs
        JOIN pedidos ped ON ped.id = prs.id_pedido
        WHERE prs.id_producto IN ({$placeholdersR})
          AND prs.liberada = 0
          AND ped.id_estado = 1
        GROUP BY prs.id_producto
    ";
    $stmtR = $db->prepare($sqlRes);
    $stmtR->execute(array_values($productoIds));
    foreach ($stmtR->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $reservasActivas[(int)$r['id_producto']] = (int)$r['total_reservado'];
    }
}

// ── Construir pivot ───────────────────────────────────────────────────────────
// pivot[$fecha][$idProducto] = [
//   'entradas' => int,  'salidas' => int,
//   'tipos'    => ['salida' => ['e'=>0,'s'=>N], 'devolucion' => [...], ...]
// ]
$pivot    = [];
$colsList = [];  // [id_producto => nombre]

foreach ($rawMovs as $m) {
    $pid  = (int)$m['id_producto'];
    $tipo = $m['tipo_movimiento'];
    $fecha = $m['fecha'];

    if (!isset($pivot[$fecha][$pid])) {
        $pivot[$fecha][$pid] = ['entradas' => 0, 'salidas' => 0, 'tipos' => []];
    }
    $pivot[$fecha][$pid]['entradas'] += (int)$m['entradas'];
    $pivot[$fecha][$pid]['salidas']  += (int)$m['salidas'];
    $pivot[$fecha][$pid]['tipos'][] = [
        'tipo' => $tipo,
        'e' => (int)$m['entradas'],
        's' => (int)$m['salidas'],
        'ref_id' => (int)($m['referencia_id'] ?? 0),
        'ref_tipo' => $m['referencia_tipo'] ?? '',
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
        $e    = $pivot[$fecha][$pid]['entradas'] ?? 0;
        $s    = $pivot[$fecha][$pid]['salidas']  ?? 0;
        $tipos = $pivot[$fecha][$pid]['tipos']   ?? [];

        if ($e > 0 || $s > 0) $tieneMovimiento = true;

        $saldosAcum[$pid] = ($saldosAcum[$pid] ?? 0) + $e - $s;

        // Pasar totales + desglose por tipo_movimiento a las filas
        $rowEntry['data'][$pid] = [
            'total' => $e,
            'tipos' => array_filter(
                array_map(fn($t) => [
                    'tipo' => $t['tipo'], 
                    'cant' => $t['e'],
                    'ref_id' => $t['ref_id'],
                    'ref_tipo' => $t['ref_tipo']
                ], $tipos),
                fn($t) => $t['cant'] > 0
            )
        ];
        $rowSalid['data'][$pid] = [
            'total' => $s,
            'tipos' => array_filter(
                array_map(fn($t) => [
                    'tipo' => $t['tipo'], 
                    'cant' => $t['s'],
                    'ref_id' => $t['ref_id'],
                    'ref_tipo' => $t['ref_tipo']
                ], $tipos),
                fn($t) => $t['cant'] > 0
            )
        ];
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
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx) . '2', strtoupper($pnombre));
        $colIdx++;
    }
    $sheet->getStyle("A2:{$lastColLetter}2")->applyFromArray([
        'font' => ['color' => ['rgb' => 'FFFFFFFF'], 'bold' => true],
        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2D6A4F']],
        'alignment' => ['horizontal' => 'center'],
    ]);

    // ── Fila 3: Saldo Inicial (antes del período) ──────────────────────────────
    $excelRow = 3;
    if (!empty($saldosIniciales)) {
        $sheet->mergeCells("A{$excelRow}:B{$excelRow}");
        $sheet->setCellValue("A{$excelRow}",
            'SALDO INICIAL (antes del ' . date('d/m/Y', strtotime($fechaDesde)) . ')');
        $c = 3;
        foreach ($colsList as $pid => $pn) {
            $si = $saldosIniciales[$pid] ?? 0;
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $excelRow;
            $sheet->setCellValue($colLetter, $si);
            $sheet->getStyle($colLetter)->getNumberFormat()->setFormatCode('+#,##0;-#,##0;0');
            $c++;
        }
        $sheet->getStyle("A{$excelRow}:{$lastColLetter}{$excelRow}")->applyFromArray([
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'BBDEFB']],
            'font' => ['color' => ['rgb' => '1565C0'], 'bold' => true],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $excelRow++;
    }

    // ── Filas de datos: entrada + salida por día ──────────────────────────────
    foreach ($filas as $grupo) {
        $fechaLabel = date('d/m/Y', strtotime($grupo['entry']['fecha']));

        // Fila entrada — valores positivos con formato +N
        $sheet->setCellValue("A{$excelRow}", $fechaLabel);
        $sheet->setCellValue("B{$excelRow}", 'Entrada');
        $c = 3;
        foreach ($colsList as $pid => $pn) {
            $val = (int)($grupo['entry']['data'][$pid]['total'] ?? 0);
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $excelRow;
            $sheet->setCellValue($colLetter, $val);
            // Formato: muestra + para positivos, - para negativos, 0 para cero
            $sheet->getStyle($colLetter)->getNumberFormat()->setFormatCode('+#,##0;-#,##0;0');
            $c++;
        }
        $sheet->getStyle("A{$excelRow}:{$lastColLetter}{$excelRow}")->applyFromArray([
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8F5E9']],
            'font' => ['color' => ['rgb' => '2E7D32']],
        ]);
        $excelRow++;

        // Fila salida — valores guardados como negativos (para sumar correctamente)
        $sheet->setCellValue("A{$excelRow}", $fechaLabel);
        $sheet->setCellValue("B{$excelRow}", 'Salida');
        $c = 3;
        foreach ($colsList as $pid => $pn) {
            $val = (int)($grupo['salida']['data'][$pid]['total'] ?? 0);
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $excelRow;
            // Guardamos negativo para que las fórmulas de Excel sumen correctamente
            $sheet->setCellValue($colLetter, $val > 0 ? -$val : 0);
            $sheet->getStyle($colLetter)->getNumberFormat()->setFormatCode('+#,##0;-#,##0;0');
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
        $sheet->setCellValue("A{$excelRow}", 'SALDO FINAL');
        $c = 3;
        foreach ($colsList as $pid => $pn) {
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $excelRow, $ultimoGrupo['total']['data'][$pid] ?? 0);
            $c++;
        }
        $sheet->getStyle("A{$excelRow}:{$lastColLetter}{$excelRow}")->applyFromArray([
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFD600']],
            'font' => ['bold' => true, 'size' => 11],
        ]);
        $sheet->getRowDimension($excelRow)->setRowHeight(18);
        $excelRow++;

        // ── Fila En Bodega (Reservado) ─────────────────────────────────────────
        $hayReservasExcel = !empty(array_filter($reservasActivas, fn($v) => $v > 0));
        if ($hayReservasExcel) {
            $sheet->mergeCells("A{$excelRow}:B{$excelRow}");
            $sheet->setCellValue("A{$excelRow}", 'EN BODEGA (RESERVADO)');
            $c = 3;
            foreach ($colsList as $pid => $pn) {
                $res = $reservasActivas[$pid] ?? 0;
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $excelRow;
                $sheet->setCellValue($colLetter, $res > 0 ? -$res : 0);
                $sheet->getStyle($colLetter)->getNumberFormat()->setFormatCode('+#,##0;-#,##0;0');
                $c++;
            }
            $sheet->getStyle("A{$excelRow}:{$lastColLetter}{$excelRow}")->applyFromArray([
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C5CAE9']],
                'font' => ['color' => ['rgb' => '283593'], 'bold' => true, 'italic' => true],
                'alignment' => ['horizontal' => 'center'],
            ]);
            $excelRow++;

            // ── Fila Stock libre real ──────────────────────────────────────────
            $sheet->mergeCells("A{$excelRow}:B{$excelRow}");
            $sheet->setCellValue("A{$excelRow}", 'STOCK LIBRE REAL');
            $c = 3;
            foreach ($colsList as $pid => $pn) {
                $saldoF = $ultimoGrupo['total']['data'][$pid] ?? 0;
                $resF   = $reservasActivas[$pid] ?? 0;
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $excelRow;
                $sheet->setCellValue($colLetter, $saldoF - $resF);
                $c++;
            }
            $sheet->getStyle("A{$excelRow}:{$lastColLetter}{$excelRow}")->applyFromArray([
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1B5E20']],
                'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => 'center'],
            ]);
            $sheet->getRowDimension($excelRow)->setRowHeight(18);
        }
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
    // Limpiar cualquier HTML que la template haya puesto en el buffer antes de escribir el binario
    while (ob_get_level() > 0) ob_end_clean();
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}
?>
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
            <?php if (!empty($tokenEnlacePublico)): ?>
            <div class="dropdown">
                <button class="btn btn-warning btn-sm fw-bold shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-share fs-6"></i> Opciones de Enlace
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><button class="dropdown-item fw-semibold text-primary" onclick="copiarEnlacePublico()"><i class="bi bi-link-45deg me-2"></i>Copiar Enlace Público</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item text-danger fw-semibold" onclick="toggleEnlacePublico('deshabilitar')"><i class="bi bi-trash me-2"></i>Revocar Acceso</button></li>
                </ul>
            </div>
            <?php else: ?>
            <button type="button" class="btn btn-outline-warning text-white btn-sm fw-bold border-2" onclick="toggleEnlacePublico('habilitar')" title="Habilita un enlace público permanente para tus clientes">
                <i class="bi bi-link-45deg fs-6"></i> Habilitar Enlace Público
            </button>
            <?php endif; ?>
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
                <?php if ($isRealCliente): ?>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-person"></i> Cliente
                    </label>
                    <input type="text" class="form-control form-control-sm bg-light" 
                           value="<?= htmlspecialchars($_SESSION['nombre'] ?? 'Mi Usuario') ?>" readonly disabled>
                    <input type="hidden" name="id_cliente" value="<?= $currUserId ?>">
                </div>
                <?php else: ?>
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
                <?php endif; ?>
                <?php if ($isRealProveedor): ?>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-truck"></i> Proveedor
                    </label>
                    <input type="text" class="form-control form-control-sm bg-light" 
                           value="<?= htmlspecialchars($_SESSION['nombre'] ?? 'Mi Usuario') ?>" readonly disabled>
                    <input type="hidden" name="id_proveedor" value="<?= $currUserId ?>">
                </div>
                <?php else: ?>
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
                <?php endif; ?>
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
    <div class="mb-3 p-2 border rounded bg-light small shadow-sm">
        <div class="d-flex gap-4 flex-wrap align-items-center mb-1">
            <span class="d-flex align-items-center gap-1">
                <span class="badge" style="background:#2e7d32"><i class="bi bi-arrow-up-short"></i> Entradas</span>
                Unidades ingresadas ese día
            </span>
            <span class="d-flex align-items-center gap-1">
                <span class="badge" style="background:#bf360c"><i class="bi bi-arrow-down-short"></i> Salidas</span>
                Unidades despachadas ese día
            </span>
            <span class="d-flex align-items-center gap-1">
                <span class="badge" style="background:#ffd600;color:#000">T</span>
                Saldo acumulado al cierre del día
            </span>
            <span class="ms-auto">
                <button id="btnToggleSinMov" class="btn btn-sm btn-outline-secondary" onclick="toggleSinMovimiento()">
                    <i class="bi bi-eye-slash me-1"></i>Solo días con actividad
                </button>
            </span>
        </div>
        <div class="text-muted border-top pt-1 mt-1 d-flex gap-3 align-items-center flex-wrap" style="font-size: 0.85em;">
            <span><i class="bi bi-info-circle me-1"></i><strong>Agrupación de despachos:</strong> Los envíos regulares del día se consolidan (ej: <code>10 En Ruta</code>).</span>
            <span><i class="bi bi-arrow-right-short"></i><strong>Reintentos:</strong> Los reintentos del mismo pedido se desglosan individualmente indicando el ID del pedido y número de intento (ej: <code>1 (#8842 - 2° int.) En Ruta</code>) para un mejor control.</span>
            <span class="ms-sm-auto"><i class="bi bi-diagram-3-fill me-1"></i><a href="#collapseReglasStock" class="text-decoration-none text-primary fw-bold" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="collapseReglasStock">Ver lógica de estados de stock <i class="bi bi-chevron-down"></i></a></span>
        </div>

        <!-- Tabla Colapsable de Reglas de Stock por Estado -->
        <div class="collapse mt-2" id="collapseReglasStock">
            <div class="table-responsive border rounded bg-white p-2">
                <table class="table table-sm table-hover mb-0 text-dark align-middle" style="font-size:0.8rem;">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 20%;">Estado del Pedido</th>
                            <th style="width: 20%;">Movimiento de Stock</th>
                            <th style="width: 25%;">Impacto en Inventario</th>
                            <th>Explicación / Regla de Negocio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge bg-secondary">1. En bodega</span></td>
                            <td><strong class="text-warning"><i class="bi bi-bookmark-fill"></i> Reserva</strong></td>
                            <td>
                                Incrementa la cantidad reservada.
                                <div class="mt-1">
                                    <span class="badge bg-success" style="font-size:.7rem;">
                                        <i class="bi bi-eye me-1"></i>Aparece en esta vista
                                    </span>
                                </div>
                            </td>
                            <td>El stock físico permanece intacto, pero se compromete para evitar que sea vendido a otros pedidos.</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-primary">2. En ruta o proceso</span></td>
                            <td><strong class="text-danger"><i class="bi bi-arrow-down-circle-fill"></i> Salida Física</strong></td>
                            <td>
                                Resta stock físico y libera la reserva.
                                <div class="mt-1">
                                    <span class="badge bg-success" style="font-size:.7rem;">
                                        <i class="bi bi-eye me-1"></i>Aparece en esta vista
                                    </span>
                                </div>
                            </td>
                            <td>Se registra el despacho real del producto. Evita doble salida física en reintentos aplicando límites basados en devoluciones previas.</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-success">3. Entregado</span></td>
                            <td><span class="text-muted">Ninguno</span></td>
                            <td>
                                Sin cambios en inventario.
                                <div class="mt-1">
                                    <span class="badge bg-secondary" style="font-size:.7rem;">
                                        <i class="bi bi-eye-slash me-1"></i>No aparece en esta vista
                                    </span>
                                </div>
                            </td>
                            <td>El producto ya fue descontado del stock físico al pasar a estar <em>En ruta</em>. Si el pedido se entregó sin pasar por En ruta, la salida se aplica automáticamente.</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-danger">5. Cancelado</span></td>
                            <td><strong class="text-success"><i class="bi bi-unlock-fill"></i> Libera Reserva</strong></td>
                            <td>
                                Resta la cantidad reservada.
                                <div class="mt-1">
                                    <span class="badge bg-success" style="font-size:.7rem;">
                                        <i class="bi bi-eye me-1"></i>Aparece en esta vista
                                    </span>
                                </div>
                            </td>
                            <td>Las unidades reservadas se liberan inmediatamente, volviendo a estar disponibles para otros pedidos.</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-warning text-dark">7. Devuelto</span></td>
                            <td><span class="text-muted">Ninguno</span></td>
                            <td>
                                Sin cambios en esta etapa.
                                <div class="mt-1">
                                    <span class="badge bg-secondary" style="font-size:.7rem;">
                                        <i class="bi bi-eye-slash me-1"></i>No aparece en esta vista
                                    </span>
                                </div>
                            </td>
                            <td>Indica la notificación/acuse de devolución en ruta. El reingreso del producto al inventario no ocurre hasta la recepción física confirmada en la bodega (estado 15).</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-dark">9. Rechazado</span></td>
                            <td><span class="text-muted">Ninguno</span></td>
                            <td>
                                Sin cambios en esta etapa.
                                <div class="mt-1">
                                    <span class="badge bg-secondary" style="font-size:.7rem;">
                                        <i class="bi bi-eye-slash me-1"></i>No aparece en esta vista
                                    </span>
                                </div>
                            </td>
                            <td>El destinatario rechazó el pedido, pero el producto sigue físicamente en posesión del repartidor en ruta hasta que retorne a bodega. Usar estado 15 cuando llegue físicamente.</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-info text-dark">15. Devuelto a bodega</span></td>
                            <td><strong class="text-success"><i class="bi bi-arrow-up-circle-fill"></i> Entrada Física</strong></td>
                            <td>
                                Suma stock físico.
                                <div class="mt-1">
                                    <span class="badge bg-success" style="font-size:.7rem;">
                                        <i class="bi bi-eye me-1"></i>Aparece en esta vista
                                    </span>
                                </div>
                            </td>
                            <td>Confirma que el producto físicamente regresó y fue reingresado al inventario de la bodega.</td>
                        </tr>
                        <tr class="table-secondary">
                            <td>
                                <span class="badge" style="background:#6b7280">Otros estados</span>
                                <div class="mt-1" style="font-size:.75rem;color:#6b7280">
                                    Reprogramado, Pendiente de asignación, En espera, etc.
                                </div>
                            </td>
                            <td><span class="text-muted"><i class="bi bi-dash-circle me-1"></i>Ninguno</span></td>
                            <td>
                                <span class="text-secondary fw-semibold">Sin cambios en inventario.</span>
                                <div class="mt-1">
                                    <span class="badge bg-warning text-dark" style="font-size:.7rem;">
                                        <i class="bi bi-eye-slash me-1"></i>No aparece en esta vista
                                    </span>
                                </div>
                            </td>
                            <td>
                                Los pedidos en estos estados <strong>existen en el sistema</strong> pero <strong>no generan ningún movimiento de stock</strong>
                                (ni entrada, ni salida, ni reserva). Por eso <strong>no aparecen en el inventario de período</strong>.
                                Son pedidos activos en gestión que aún no han impactado el inventario físico.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    // Activar "solo días con actividad" por defecto si hay algún filtro aplicado
    $hayFiltros = ($idCliente > 0 || $idProveedor > 0 || $idEstado > 0 || $idProducto > 0);
    ?>

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
                <!-- Fila Saldo Inicial — acumulado ANTES del período -->
                <?php if (!empty($saldosIniciales)): ?>
                <tr style="background:#e3f2fd;font-weight:600;font-size:.8rem">
                    <td class="col-fecha" style="color:#1565c0">
                        <i class="bi bi-bookmark-star-fill me-1"></i>
                        Saldo inicial<br>
                        <small class="fw-normal" style="font-size:.68rem;opacity:.75">
                            Antes del <?= date('d/m/Y', strtotime($fechaDesde)) ?>
                        </small>
                    </td>
                    <?php foreach ($colsList as $pid => $pn):
                        $si = $saldosIniciales[$pid] ?? 0;
                        if ($si > 0)      { $siColor = '#1565c0'; $siBg = '#bbdefb'; }
                        elseif ($si < 0)  { $siColor = '#b71c1c'; $siBg = '#ffcdd2'; }
                        else              { $siColor = '#546e7a'; $siBg = '#eceff1'; }
                    ?>
                    <td style="background:<?= $siBg ?>;color:<?= $siColor ?>;text-align:center">
                        <?= number_format($si) ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endif; ?>
            <?php
            // Sumatorias del período por producto
            $totalEntradasPeriodo = [];
            $totalSalidasPeriodo  = [];
            foreach ($filas as $grupo) {
                foreach ($colsList as $pid => $pn) {
                    $totalEntradasPeriodo[$pid] = ($totalEntradasPeriodo[$pid] ?? 0) + (int)($grupo['entry']['data'][$pid]['total'] ?? 0);
                    $totalSalidasPeriodo[$pid]  = ($totalSalidasPeriodo[$pid]  ?? 0) + (int)($grupo['salida']['data'][$pid]['total'] ?? 0);
                }
            }
            ?>

            <?php
            // Obtener el nombre del estado seleccionado (si existe)
            $nombreEstadoFiltro = 'En Ruta'; // Por defecto
            $iconoEstadoFiltro = 'bi-truck';
            if ($idEstado > 0) {
                foreach ($estados as $e) {
                    if ($e['id'] == $idEstado) {
                        $nombreEstadoFiltro = $e['nombre_estado'];
                        if (stripos($nombreEstadoFiltro, 'entregado') !== false) {
                            $iconoEstadoFiltro = 'bi-check-circle-fill';
                        } elseif (stripos($nombreEstadoFiltro, 'ruta') !== false) {
                            $iconoEstadoFiltro = 'bi-truck';
                        }
                        break;
                    }
                }
            }

            // Lookup: tipo_movimiento -> label, color, icono
            $tipoMeta = [
                'entrada'    => ['label' => 'Ingreso',            'bg' => '#2e7d32', 'fg' => '#fff', 'icon' => 'bi-plus-circle-fill'],
                'salida'     => ['label' => $nombreEstadoFiltro,  'bg' => '#bf360c', 'fg' => '#fff', 'icon' => $iconoEstadoFiltro],
                'devolucion' => ['label' => 'Dev. a bodega',      'bg' => '#1565c0', 'fg' => '#fff', 'icon' => 'bi-house-check-fill'],
                'ajuste'     => ['label' => 'Ajuste manual',      'bg' => '#6a1b9a', 'fg' => '#fff', 'icon' => 'bi-wrench-adjustable'],
                'otro'       => ['label' => 'Movimiento',         'bg' => '#546e7a', 'fg' => '#fff', 'icon' => 'bi-circle-fill'],
            ];
            ?>

            <?php foreach ($filas as $grupo):
                $sinMov = !$grupo['tiene_mov'];
                $sMov   = $sinMov ? ' fila-sin-mov' : '';
                $dataSin = $sinMov ? ' data-sin-mov="1"' : '';
            ?>
                <!-- Fila Entradas -->
                <tr class="fila-entrada<?= $sMov ?>"<?= $dataSin ?>>
                    <td class="col-fecha text-nowrap">
                        <i class="bi bi-arrow-up-circle-fill me-1" style="color:#2e7d32"></i>
                        <?= date('d/m/Y', strtotime($grupo['entry']['fecha'])) ?>
                    </td>
                    <?php foreach ($colsList as $pid => $pn):
                        $cell = $grupo['entry']['data'][$pid] ?? ['total' => 0, 'tipos' => []];
                    ?>
                    <td style="min-width:80px;vertical-align:middle">
                    <?php if ($cell['total'] > 0): ?>
                        <?php 
                        // Contar cuántos movimientos de cada tipo tiene cada pedido (referencia_id)
                        $countsByRef = [];
                        foreach ($cell['tipos'] as $t) {
                            if ($t['ref_tipo'] === 'pedido' && $t['ref_id'] > 0) {
                                $key = $t['tipo'] . '_' . $t['ref_id'];
                                $countsByRef[$key] = ($countsByRef[$key] ?? 0) + 1;
                            }
                        }

                        // Separar movimientos agrupados e individuales
                        $normalByType = [];
                        $individualBadges = [];
                        $currentIndices = [];

                        foreach ($cell['tipos'] as $t) {
                            $tipo = $t['tipo'];
                            $cant = $t['cant'];
                            $refId = $t['ref_id'];
                            $refTipo = $t['ref_tipo'];
                            
                            $key = $tipo . '_' . $refId;
                            $isMultIntento = ($refTipo === 'pedido' && $refId > 0 && ($countsByRef[$key] ?? 0) > 1);
                            
                            if ($isMultIntento) {
                                if (!isset($currentIndices[$key])) {
                                    $currentIndices[$key] = 0;
                                }
                                $currentIndices[$key]++;
                                
                                $suffixText = ($tipo === 'salida') ? 'int.' : (($tipo === 'devolucion') ? 'dev.' : 'mov.');
                                $individualBadges[] = [
                                    'tipo' => $tipo,
                                    'cant' => $cant,
                                    'extraLabel' => " (#{$refId} - {$currentIndices[$key]}° {$suffixText})",
                                ];
                            } else {
                                $normalByType[$tipo] = ($normalByType[$tipo] ?? 0) + $cant;
                            }
                        }

                        // Combinar badges
                        $renderBadges = [];
                        foreach ($normalByType as $tipo => $cant) {
                            $renderBadges[] = [
                                'tipo' => $tipo,
                                'cant' => $cant,
                                'extraLabel' => '',
                            ];
                        }
                        foreach ($individualBadges as $ib) {
                            $renderBadges[] = $ib;
                        }

                        foreach ($renderBadges as $badge):
                            $tipo = $badge['tipo'];
                            $cant = $badge['cant'];
                            $extraLabel = $badge['extraLabel'];
                            $m = $tipoMeta[$tipo] ?? $tipoMeta['otro'];
                        ?>
                        <span class="badge d-inline-flex align-items-center gap-1 mb-1"
                              style="background:<?= $m['bg'] ?>;color:<?= $m['fg'] ?>;font-size:.72rem"
                              title="<?= $m['label'] ?>: <?= $cant ?> unidades">
                            <i class="bi <?= $m['icon'] ?>"></i>
                            <span><?= $cant ?><?= $extraLabel ?></span>
                            <small style="opacity:.8;font-size:.65rem"><?= $m['label'] ?></small>
                        </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted" style="opacity:.35">—</span>
                    <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>

                <!-- Fila Salidas -->
                <tr class="fila-salida<?= $sMov ?>"<?= $dataSin ?>>
                    <td class="col-fecha text-nowrap text-muted" style="font-size:.7rem">
                        <i class="bi bi-arrow-down-circle-fill me-1" style="color:#bf360c"></i>
                        <?= date('d/m/Y', strtotime($grupo['salida']['fecha'])) ?>
                    </td>
                    <?php foreach ($colsList as $pid => $pn):
                        $cell = $grupo['salida']['data'][$pid] ?? ['total' => 0, 'tipos' => []];
                    ?>
                    <td style="min-width:80px;vertical-align:middle">
                    <?php if ($cell['total'] > 0): ?>
                        <?php 
                        // Contar cuántos movimientos de cada tipo tiene cada pedido (referencia_id)
                        $countsByRef = [];
                        foreach ($cell['tipos'] as $t) {
                            if ($t['ref_tipo'] === 'pedido' && $t['ref_id'] > 0) {
                                $key = $t['tipo'] . '_' . $t['ref_id'];
                                $countsByRef[$key] = ($countsByRef[$key] ?? 0) + 1;
                            }
                        }

                        // Separar movimientos agrupados e individuales
                        $normalByType = [];
                        $individualBadges = [];
                        $currentIndices = [];

                        foreach ($cell['tipos'] as $t) {
                            $tipo = $t['tipo'];
                            $cant = $t['cant'];
                            $refId = $t['ref_id'];
                            $refTipo = $t['ref_tipo'];
                            
                            $key = $tipo . '_' . $refId;
                            $isMultIntento = ($refTipo === 'pedido' && $refId > 0 && ($countsByRef[$key] ?? 0) > 1);
                            
                            if ($isMultIntento) {
                                if (!isset($currentIndices[$key])) {
                                    $currentIndices[$key] = 0;
                                }
                                $currentIndices[$key]++;
                                
                                $suffixText = ($tipo === 'salida') ? 'int.' : (($tipo === 'devolucion') ? 'dev.' : 'mov.');
                                $individualBadges[] = [
                                    'tipo' => $tipo,
                                    'cant' => $cant,
                                    'extraLabel' => " (#{$refId} - {$currentIndices[$key]}° {$suffixText})",
                                ];
                            } else {
                                $normalByType[$tipo] = ($normalByType[$tipo] ?? 0) + $cant;
                            }
                        }

                        // Combinar badges
                        $renderBadges = [];
                        foreach ($normalByType as $tipo => $cant) {
                            $renderBadges[] = [
                                'tipo' => $tipo,
                                'cant' => $cant,
                                'extraLabel' => '',
                            ];
                        }
                        foreach ($individualBadges as $ib) {
                            $renderBadges[] = $ib;
                        }

                        foreach ($renderBadges as $badge):
                            $tipo = $badge['tipo'];
                            $cant = $badge['cant'];
                            $extraLabel = $badge['extraLabel'];
                            $m = $tipoMeta[$tipo] ?? $tipoMeta['otro'];
                        ?>
                        <span class="badge d-inline-flex align-items-center gap-1 mb-1"
                              style="background:<?= $m['bg'] ?>;color:<?= $m['fg'] ?>;font-size:.72rem"
                              title="<?= $m['label'] ?>: <?= $cant ?> unidades">
                            <i class="bi <?= $m['icon'] ?>"></i>
                            <span><?= $cant ?><?= $extraLabel ?></span>
                            <small style="opacity:.8;font-size:.65rem"><?= $m['label'] ?></small>
                        </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted" style="opacity:.35">—</span>
                    <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>

                <!-- Fila Total — saldo acumulado FINAL -->
                <?php $ultimoGrupo = end($filas); if ($ultimoGrupo): ?>
                <tr class="fila-total">
                    <td class="col-fecha"><i class="bi bi-layers-fill me-1"></i>Saldo final</td>
                    <?php foreach ($colsList as $pid => $pn):
                        $saldo = $ultimoGrupo['total']['data'][$pid] ?? 0;
                        if ($saldo <= 0)      { $bg = '#d32f2f'; $color = '#fff'; $icon = 'bi-exclamation-triangle-fill'; }
                        elseif ($saldo <= 10) { $bg = '#f57f17'; $color = '#000'; $icon = 'bi-dash-circle-fill'; }
                        else                  { $bg = '#2e7d32'; $color = '#fff'; $icon = 'bi-check-circle-fill'; }
                    ?>
                    <td style="background:<?= $bg ?>;color:<?= $color ?>;font-weight:700">
                        <i class="bi <?= $icon ?> me-1"></i><?= number_format($saldo) ?>
                    </td>
                    <?php endforeach; ?>
                </tr>

                <!-- Fila En Bodega (Reservado) — unidades comprometidas a pedidos En Bodega -->
                <?php
                $hayReservas = !empty(array_filter($reservasActivas, fn($v) => $v > 0));
                if ($hayReservas):
                ?>
                <tr class="fila-reserva">
                    <td class="col-fecha text-nowrap">
                        <i class="bi bi-lock-fill me-1"></i>En Bodega
                        <br><small class="fw-normal" style="font-size:.68rem;opacity:.75">reservado / no despachado</small>
                    </td>
                    <?php foreach ($colsList as $pid => $pn):
                        $res = $reservasActivas[$pid] ?? 0;
                    ?>
                    <td>
                        <?php if ($res > 0): ?>
                        <span class="badge d-inline-flex align-items-center gap-1"
                              style="background:#283593;color:#fff;font-size:.72rem"
                              title="<?= $res ?> unidades reservadas para pedidos en bodega">
                            <i class="bi bi-lock-fill"></i>
                            <span>−<?= number_format($res) ?></span>
                        </span>
                        <?php else: ?>
                        <span class="text-muted" style="opacity:.35">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>

                <!-- Fila Stock libre real = Saldo final - Reservas activas -->
                <tr class="fila-libre">
                    <td class="col-fecha text-nowrap">
                        <i class="bi bi-check2-circle me-1"></i>Stock libre
                        <br><small class="fw-normal" style="font-size:.68rem;opacity:.8">disponible para nuevos pedidos</small>
                    </td>
                    <?php foreach ($colsList as $pid => $pn):
                        $saldoFinal = $ultimoGrupo['total']['data'][$pid] ?? 0;
                        $reservado  = $reservasActivas[$pid] ?? 0;
                        $libre      = $saldoFinal - $reservado;
                        if ($libre <= 0)      { $liberoBg = '#b71c1c'; $liberoIcon = 'bi-exclamation-triangle-fill'; }
                        elseif ($libre <= 10) { $liberoBg = '#e65100'; $liberoIcon = 'bi-dash-circle-fill'; }
                        else                  { $liberoBg = '#1b5e20'; $liberoIcon = 'bi-check-circle-fill'; }
                    ?>
                    <td style="background:<?= $liberoBg ?>;font-weight:700;color:#fff">
                        <i class="bi <?= $liberoIcon ?> me-1"></i><?= number_format($libre) ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endif; ?>

                <!-- Fila resumen del período -->
                <tr style="background:#f0f4ff;font-size:.75rem;color:#37474f">
                    <td class="col-fecha fw-semibold"><i class="bi bi-calendar-range me-1"></i>Resumen período</td>
                    <?php foreach ($colsList as $pid => $pn): ?>
                    <td>
                        <?php if (($totalEntradasPeriodo[$pid] ?? 0) > 0): ?>
                        <span class="d-block" style="color:#2e7d32">
                            <i class="bi bi-arrow-up-short"></i><?= $totalEntradasPeriodo[$pid] ?>
                        </span>
                        <?php endif; ?>
                        <?php if (($totalSalidasPeriodo[$pid] ?? 0) > 0): ?>
                        <span class="d-block" style="color:#bf360c">
                            <i class="bi bi-arrow-down-short"></i><?= $totalSalidasPeriodo[$pid] ?>
                        </span>
                        <?php endif; ?>
                        <?php if (($totalEntradasPeriodo[$pid] ?? 0) === 0 && ($totalSalidasPeriodo[$pid] ?? 0) === 0): ?>
                        <span class="text-muted" style="opacity:.4">—</span>
                        <?php endif; ?>
                    </td>
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

<script>
// ── Toggle: ocultar/mostrar días sin movimiento ────────────────────────────
let _ocultandoSinMov = false;

function toggleSinMovimiento() {
    _ocultandoSinMov = !_ocultandoSinMov;
    const filas = document.querySelectorAll('tr[data-sin-mov="1"]');
    filas.forEach(tr => tr.style.display = _ocultandoSinMov ? 'none' : '');
    const btn = document.getElementById('btnToggleSinMov');
    if (_ocultandoSinMov) {
        btn.classList.replace('btn-outline-secondary', 'btn-secondary');
        btn.innerHTML = '<i class="bi bi-eye me-1"></i>Mostrar todos los días';
    } else {
        btn.classList.replace('btn-secondary', 'btn-outline-secondary');
        btn.innerHTML = '<i class="bi bi-eye-slash me-1"></i>Solo días con actividad';
    }
}

// Auto-activar si hay filtros aplicados (cliente/proveedor/estado/producto)
<?php if ($hayFiltros): ?>
document.addEventListener('DOMContentLoaded', () => { toggleSinMovimiento(); });
<?php endif; ?>

function toggleEnlacePublico(action) {
    const actionText = action === 'habilitar' 
        ? 'Esto generará un enlace público que podrás compartir con clientes externos.' 
        : 'Los enlaces previos dejarán de funcionar permanentemente.';
        
    Swal.fire({
        title: action === 'habilitar' ? '¿Activar enlace público?' : '¿Revocar acceso?',
        text: actionText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: action === 'habilitar' ? '#3085d6' : '#d33',
        cancelButtonColor: '#adb5bd',
        confirmButtonText: 'Sí, ' + action,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            
            const formData = new FormData();
            formData.append('action', action);

            fetch('<?= RUTA_URL ?>ajax/enlaces_publicos.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Operación exitosa!',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', data.message || 'Error desconocido', 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Ocurrió un error en la conexión.', 'error'));
        }
    });
}

function copiarEnlacePublico() {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('u', '<?= $currUserId ?>');
    urlParams.set('t', '<?= $tokenEnlacePublico ?>');
    urlParams.delete('export'); // Nunca autodescargar
    
    const baseUrl = '<?= RUTA_URL ?>' + 'stock/inventario_publico';
    const finalUrl = baseUrl + '?' + urlParams.toString();
    
    navigator.clipboard.writeText(finalUrl).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Enlace copiado',
            text: 'Cualquier persona que abra este enlace podrá ver esta tabla con las fechas seleccionadas pero no podrá modificar ni acceder a otros datos.',
            confirmButtonText: 'Entendido'
        });
    }).catch(err => {
        // Fallback por si navigator.clipboard falla en HTTP 
        const textArea = document.createElement("textarea");
        textArea.value = finalUrl;
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            Swal.fire({
                icon: 'success',
                title: 'Enlace copiado',
                text: 'El enlace público ha sido copiado al portapapeles.',
                timer: 2000,
                showConfirmButton: false
            });
        } catch (err) {
            Swal.fire({
                icon: 'warning',
                title: 'Copia manual requerida',
                text: 'No se pudo copiar automáticamente. Este es tu enlace:',
                input: 'text',
                inputValue: finalUrl,
                confirmButtonText: 'Cerrar'
            });
        }
        document.body.removeChild(textArea);
    });
}
</script>
