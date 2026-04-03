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

$isAdmin     = false;

$errorHtml = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Denegado</title>
    <style>
        body { margin: 0; padding: 0; display: flex; align-items: center; justify-content: center; height: 100vh; background-color: #f8f9fa; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .error-card { background: #fff; padding: 3rem 2.5rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); text-align: center; max-width: 420px; width: 90%; margin: auto; }
        .icon { font-size: 4rem; margin-bottom: 1rem; display: inline-block; }
        h1 { font-size: 1.5rem; color: #212529; margin-bottom: 0.75rem; font-weight: 700; }
        p { color: #6c757d; font-size: 1rem; line-height: 1.5; margin-bottom: 0; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="icon">🔗</div>
        <h1>Enlace Inválido</h1>
        <p>El enlace al que intentas acceder está incompleto o mal formado. Por favor, verifica la URL.</p>
    </div>
</body>
</html>
HTML;

if (!isset($_GET['u']) || !isset($_GET['t'])) {
    die($errorHtml);
}

$u = (int)$_GET['u'];
$t = (string)$_GET['t'];

$db = (new Conexion())->conectar();

$stmtToken = $db->prepare("SELECT token_enlace_publico FROM usuarios WHERE id = :id");
$stmtToken->execute([':id' => $u]);
$dbToken = $stmtToken->fetchColumn();

$errorRevocadoHtml = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enlace Revocado</title>
    <style>
        body { margin: 0; padding: 0; display: flex; align-items: center; justify-content: center; height: 100vh; background-color: #f8f9fa; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .error-card { background: #fff; padding: 3rem 2.5rem; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.06); text-align: center; max-width: 420px; width: 90%; margin: auto; border-top: 5px solid #dc3545; }
        .icon-circle { width: 80px; height: 80px; background: #fff5f5; color: #dc3545; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 1.5rem; }
        h1 { font-size: 1.5rem; color: #212529; margin-bottom: 0.75rem; font-weight: 700; }
        p { color: #6c757d; font-size: 1rem; line-height: 1.6; margin-bottom: 0; }
        .footer-text { margin-top: 2rem; font-size: 0.8rem; color: #adb5bd; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="icon-circle">
            <svg xmlns="http://www.w3.org/.0" width="40" height="40" fill="currentColor" viewBox="0 0 16 16">
              <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
              <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
            </svg>
        </div>
        <h1>Acceso Denegado</h1>
        <p>El enlace público de este inventario <strong>ya no es válido</strong> o ha sido revocado por el propietario.</p>
        <div class="footer-text">Sistema de Logística Segura &copy;</div>
    </div>
</body>
</html>
HTML;

if (empty($dbToken) || !hash_equals($dbToken, $t)) {
    die($errorRevocadoHtml);
}

// Mockear el estado del usuario como si hubiera iniciado sesión
$stmt = $db->prepare("SELECT id_rol FROM usuarios_roles WHERE id_usuario = :id");
$stmt->execute([':id' => $u]);
$rol = $stmt->fetchColumn();

$currRol = $rol ? (int)$rol : 0;
$isRealCliente   = ($currRol == ROL_PROVEEDOR); // 4
$isRealProveedor = ($currRol == ROL_CLIENTE);   // 5
$currUserId      = $u;

// Inyectar en variable global para que permisos.php lo tome
$GLOBALS['API_USER_ID'] = $u;
$GLOBALS['API_USER_ROLE'] = $currRol;

// ── Leer filtros de la URL ────────────────────────────────────────────────────
$fechaDesde  = $_GET['fecha_desde']  ?? date('Y-m-01');
// Default "hasta": fecha del último movimiento en stock (no el fin de mes actual)
$ultimoMovRow = $db->query('SELECT DATE(MAX(created_at)) AS ultima FROM stock')->fetch(PDO::FETCH_ASSOC);
$fechaHasta   = $_GET['fecha_hasta']  ?? ($ultimoMovRow['ultima'] ?? date('Y-m-d'));
$idCliente   = ($isRealCliente)   ? $currUserId : (int)($_GET['id_cliente']  ?? 0);
$idProveedor = ($isRealProveedor) ? $currUserId : (int)($_GET['id_proveedor'] ?? 0);
$idEstado    = (int)($_GET['id_estado']   ?? 0);
$idProducto  = (int)($_GET['id_producto'] ?? 0);
$export      = isset($_GET['export']) && $_GET['export'] === '1';

// Token para generar el enlace público
$publicUrlToken = hash('sha256', $currUserId . JWT_SECRET_KEY . 'P4Q-L1NK');

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

$whereStr = 'WHERE ' . implode(' AND ', $where);

// Si hay filtros de pedido (cliente/proveedor/estado), forzamos JOIN INNER en pedidos
// para que los ajustes manuales sin referencia no contaminen el reporte.
// EXCEPCIÓN: Si el filtro es automático por ser Cliente/Proveedor, usamos LEFT JOIN
// para que vean sus productos aunque no tengan pedidos asociados aún.
$joinType = ($idCliente > 0 || $idProveedor > 0 || $idEstado > 0)
    ? (($isAdmin) ? 'INNER JOIN' : 'LEFT JOIN')
    : 'LEFT JOIN';

$sqlMovs = "
    SELECT
        DATE(s.created_at)                                              AS fecha,
        pr.id                                                           AS id_producto,
        pr.nombre                                                       AS producto,
        COALESCE(s.tipo_movimiento, 'otro')                             AS tipo_movimiento,
        SUM(CASE WHEN s.cantidad > 0 THEN  s.cantidad ELSE 0 END)      AS entradas,
        SUM(CASE WHEN s.cantidad < 0 THEN -s.cantidad ELSE 0 END)      AS salidas
    FROM stock s
    JOIN productos pr ON pr.id = s.id_producto
    {$joinType} pedidos ped
        ON ped.id = s.referencia_id AND s.referencia_tipo = 'pedido'
    {$whereStr}
    GROUP BY DATE(s.created_at), pr.id, pr.nombre, s.tipo_movimiento
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
    $pivot[$fecha][$pid]['tipos'][$tipo] = [
        'e' => (int)$m['entradas'],
        's' => (int)$m['salidas'],
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
        $rowEntry['data'][$pid] = ['total' => $e, 'tipos' => array_filter(
            array_map(fn($t) => $t['e'], $tipos)
        )];
        $rowSalid['data'][$pid] = ['total' => $s, 'tipos' => array_filter(
            array_map(fn($t) => $t['s'], $tipos)
        )];
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
        $sheet->setCellValue("A{$excelRow}", 'TOTAL PRODUCTO');
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

<div class="container-fluid py-4" style="max-width: 1400px; margin: 0 auto;">

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
            <a href="<?= RUTA_URL ?>stock/inventario_publico?<?= http_build_query(array_merge($_GET, ['export'=>'1'])) ?>"
               class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card filter-card mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= RUTA_URL ?>stock/inventario_publico"
                  class="row g-2 align-items-end">
                <input type="hidden" name="u" value="<?= $u ?>">
                <input type="hidden" name="t" value="<?= $t ?>">
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
                    <a href="<?= RUTA_URL ?>stock/inventario_publico?u=<?= $u ?>&t=<?= $t ?>"
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
        <span class="text-muted">Filas atenuadas = sin movimiento ese día · <span style="color:#aaa">—</span> = sin actividad</span>
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
            $nombreEstadoFiltro = 'Despacho / Salida'; // Por defecto universal
            $iconoEstadoFiltro = 'bi-box-seam';
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
                'devolucion' => ['label' => 'Devuelto/Rechaz.',   'bg' => '#1565c0', 'fg' => '#fff', 'icon' => 'bi-reply-fill'],
                'ajuste'     => ['label' => 'Ajuste manual',      'bg' => '#6a1b9a', 'fg' => '#fff', 'icon' => 'bi-wrench-adjustable'],
                'otro'       => ['label' => 'Movimiento',         'bg' => '#546e7a', 'fg' => '#fff', 'icon' => 'bi-circle-fill'],
            ];
            ?>

            <?php foreach ($filas as $grupo):
                $sinMov = !$grupo['tiene_mov'];
                $sMov   = $sinMov ? ' fila-sin-mov' : '';
            ?>
                <!-- Fila Entradas -->
                <tr class="fila-entrada<?= $sMov ?>">
                    <td class="col-fecha text-nowrap">
                        <i class="bi bi-arrow-up-circle-fill me-1" style="color:#2e7d32"></i>
                        <?= date('d/m/Y', strtotime($grupo['entry']['fecha'])) ?>
                    </td>
                    <?php foreach ($colsList as $pid => $pn):
                        $cell = $grupo['entry']['data'][$pid] ?? ['total' => 0, 'tipos' => []];
                    ?>
                    <td style="min-width:80px;vertical-align:middle">
                    <?php if ($cell['total'] > 0): ?>
                        <?php foreach ($cell['tipos'] as $tipo => $cant): ?>
                        <?php $m = $tipoMeta[$tipo] ?? $tipoMeta['otro']; ?>
                        <span class="badge d-inline-flex align-items-center gap-1 mb-1"
                              style="background:<?= $m['bg'] ?>;color:<?= $m['fg'] ?>;font-size:.72rem"
                              title="<?= $m['label'] ?>: <?= $cant ?> unidades">
                            <i class="bi <?= $m['icon'] ?>"></i>
                            <span><?= $cant ?></span>
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
                <tr class="fila-salida<?= $sMov ?>">
                    <td class="col-fecha text-nowrap text-muted" style="font-size:.7rem">
                        <i class="bi bi-arrow-down-circle-fill me-1" style="color:#bf360c"></i>
                        <?= date('d/m/Y', strtotime($grupo['salida']['fecha'])) ?>
                    </td>
                    <?php foreach ($colsList as $pid => $pn):
                        $cell = $grupo['salida']['data'][$pid] ?? ['total' => 0, 'tipos' => []];
                    ?>
                    <td style="min-width:80px;vertical-align:middle">
                    <?php if ($cell['total'] > 0): ?>
                        <?php foreach ($cell['tipos'] as $tipo => $cant): ?>
                        <?php $m = $tipoMeta[$tipo] ?? $tipoMeta['otro']; ?>
                        <span class="badge d-inline-flex align-items-center gap-1 mb-1"
                              style="background:<?= $m['bg'] ?>;color:<?= $m['fg'] ?>;font-size:.72rem"
                              title="<?= $m['label'] ?>: <?= $cant ?> unidades">
                            <i class="bi <?= $m['icon'] ?>"></i>
                            <span><?= $cant ?></span>
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

</div>

</body>
</html>
