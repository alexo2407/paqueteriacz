<?php
ob_start();
/**
 * Informe: Tendencia Semanal de Órdenes
 * Ruta: GET /pedidos/informes/semana
 * Muestra tabla semanal (Lunes–Sábado) con cantidades y porcentajes por estado
 */

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/session.php';
require_once __DIR__ . '/../../../../utils/permissions.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';

start_secure_session();

// ── Autenticación dual: sesión normal O token público ─────────────────────────
$isPublicLink = isset($_GET['u']) && isset($_GET['t']);

if ($isPublicLink) {
    $pubU = (int)$_GET['u'];
    $pubT = (string)$_GET['t'];

    $dbPub   = (new Conexion())->conectar();
    $stmtPub = $dbPub->prepare("SELECT token_enlace_publico FROM usuarios WHERE id = :id");
    $stmtPub->execute([':id' => $pubU]);
    $dbToken = $stmtPub->fetchColumn();

    if (empty($dbToken) || !hash_equals($dbToken, $pubT)) {
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Enlace Inválido</title>
        <style>
            body{margin:0;display:flex;align-items:center;justify-content:center;height:100vh;background:#0f172a;font-family:system-ui,sans-serif}
            .card{background:#1e293b;padding:3rem 2.5rem;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.4);text-align:center;max-width:420px;width:90%;border-top:4px solid #ef4444}
            h1{font-size:1.4rem;color:#f1f5f9;font-weight:700;margin-bottom:.5rem}
            p{color:#94a3b8;font-size:.95rem;line-height:1.5}
        </style></head><body>
        <div class="card">
            <div style="font-size:3rem;margin-bottom:1rem">🔗</div>
            <h1>Acceso Denegado</h1>
            <p>El enlace público ya no es válido o ha sido revocado.</p>
        </div></body></html>';
        exit;
    }

    $stmtRol = $dbPub->prepare("SELECT id_rol FROM usuarios_roles WHERE id_usuario = :id LIMIT 1");
    $stmtRol->execute([':id' => $pubU]);
    $pubRol = (int)$stmtRol->fetchColumn();

    $isAdmin        = false;
    $isProveedorExt = ($pubRol == ROL_CLIENTE || $pubRol == ROL_PROVEEDOR);
    $currUserId     = $pubU;
    $db             = $dbPub;
} else {
    require_login();
    $isAdmin        = isSuperAdmin();
    $currRol        = $_SESSION['rol'] ?? 0;
    $isProveedorExt = ($currRol == ROL_CLIENTE || $currRol == ROL_PROVEEDOR);
    $currUserId     = getCurrentUserId();
    $db             = (new Conexion())->conectar();
}

// ── Token enlace público ──────────────────────────────────────────────────────
$stmtToken      = $db->prepare("SELECT token_enlace_publico, nombre FROM usuarios WHERE id = :id");
$stmtToken->execute([':id' => $currUserId]);
$tokenRow           = $stmtToken->fetch(PDO::FETCH_ASSOC);
$tokenEnlacePublico = $tokenRow['token_enlace_publico'] ?? '';
$currUserNombre     = $tokenRow['nombre'] ?? ($_SESSION['nombre'] ?? '');

// ── Filtros ───────────────────────────────────────────────────────────────────
// Por defecto: últimas 12 semanas
$defaultDesde = date('Y-m-d', strtotime('-11 weeks monday this week'));
$defaultHasta = date('Y-m-d', strtotime('saturday this week'));
$fechaDesde  = $_GET['fecha_desde'] ?? $defaultDesde;
$fechaHasta  = $_GET['fecha_hasta'] ?? $defaultHasta;
$idProveedor = (!$isAdmin && $isProveedorExt) ? $currUserId : (int)($_GET['id_proveedor'] ?? 0);
$export      = isset($_GET['export']) && $_GET['export'] === '1';

// ── Catálogo de proveedores (solo Admin) ──────────────────────────────────────
$proveedores = [];
if ($isAdmin) {
    $proveedores = $db->query(
        "SELECT DISTINCT u.id, u.nombre
         FROM usuarios u
         INNER JOIN usuarios_roles ur ON ur.id_usuario = u.id
         WHERE ur.id_rol IN (" . ROL_CLIENTE . ", " . ROL_PROVEEDOR . ") AND u.activo = 1
         ORDER BY u.nombre ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

// ── WHERE ─────────────────────────────────────────────────────────────────────
$where  = ['p.fecha_ingreso BETWEEN :desde AND :hasta'];
$params = [':desde' => $fechaDesde . ' 00:00:00', ':hasta' => $fechaHasta . ' 23:59:59'];

if ($isAdmin && $idProveedor > 0) {
    $where[]                 = 'p.id_proveedor = :id_proveedor';
    $params[':id_proveedor'] = $idProveedor;
} elseif ($isProveedorExt) {
    $where[]                 = 'p.id_proveedor = :id_proveedor';
    $params[':id_proveedor'] = $currUserId;
} elseif (!$isAdmin) {
    $where[] = '1 = 0';
}
$whereStr = 'WHERE ' . implode(' AND ', $where);

// ── Query semanal (Lunes–Sábado) ──────────────────────────────────────────────
// YEARWEEK(..., 1) = semana ISO, empieza lunes
$sqlSemana = "
    SELECT
        YEARWEEK(p.fecha_ingreso, 1)    AS semana_key,
        WEEK(p.fecha_ingreso, 1)        AS num_semana,
        YEAR(p.fecha_ingreso)           AS anio,
        DATE_SUB(
            DATE(MIN(p.fecha_ingreso)),
            INTERVAL WEEKDAY(MIN(p.fecha_ingreso)) DAY
        )                               AS lunes,
        DATE_ADD(
            DATE_SUB(
                DATE(MIN(p.fecha_ingreso)),
                INTERVAL WEEKDAY(MIN(p.fecha_ingreso)) DAY
            ), INTERVAL 5 DAY
        )                               AS sabado,
        COUNT(*)                        AS cantidad,
        SUM(CASE
            WHEN LOWER(ep.nombre_estado) LIKE '%entregado a bodega%' THEN 0
            WHEN LOWER(ep.nombre_estado) LIKE '%entregado%' THEN 1 ELSE 0
        END)                            AS entregados,
        SUM(CASE
            WHEN LOWER(ep.nombre_estado) LIKE '%rechazado%' THEN 1 ELSE 0
        END)                            AS rechazados,
        SUM(CASE
            WHEN LOWER(ep.nombre_estado) LIKE '%devuelto%'
              OR LOWER(ep.nombre_estado) LIKE '%devoluci%'
              OR LOWER(ep.nombre_estado) LIKE '%entregado a bodega%' THEN 1 ELSE 0
        END)                            AS devueltos,
        SUM(CASE
            WHEN LOWER(ep.nombre_estado) LIKE '%reprogramado%' THEN 1 ELSE 0
        END)                            AS reprogramados
    FROM pedidos p
    LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
    {$whereStr}
    GROUP BY YEARWEEK(p.fecha_ingreso, 1), WEEK(p.fecha_ingreso, 1), YEAR(p.fecha_ingreso)
    ORDER BY semana_key ASC
";
$stmtSem = $db->prepare($sqlSemana);
foreach ($params as $k => $v) $stmtSem->bindValue($k, $v);
$stmtSem->execute();
$semanas = $stmtSem->fetchAll(PDO::FETCH_ASSOC);

// ── Calcular en_proceso y porcentajes ─────────────────────────────────────────
$totalCantidad      = 0;
$totalEntregados    = 0;
$totalRechazados    = 0;
$totalDevueltos     = 0;
$totalEnProceso     = 0;
$totalReprogramados = 0;

foreach ($semanas as &$sem) {
    $sem['reprogramados']     = (int)($sem['reprogramados'] ?? 0);
    $sem['devueltos']         = (int)($sem['devueltos']     ?? 0);
    $sem['en_proceso']        = $sem['cantidad'] - $sem['entregados'] - $sem['rechazados'] - $sem['devueltos'] - $sem['reprogramados'];
    if ($sem['en_proceso'] < 0) $sem['en_proceso'] = 0;
    $sem['pct_entregados']    = $sem['cantidad'] > 0 ? round($sem['entregados']    / $sem['cantidad'] * 100) : 0;
    $sem['pct_rechazados']    = $sem['cantidad'] > 0 ? round($sem['rechazados']    / $sem['cantidad'] * 100) : 0;
    $sem['pct_devueltos']     = $sem['cantidad'] > 0 ? round($sem['devueltos']     / $sem['cantidad'] * 100) : 0;
    $sem['pct_en_proceso']    = $sem['cantidad'] > 0 ? round($sem['en_proceso']    / $sem['cantidad'] * 100) : 0;
    $sem['pct_reprogramados'] = $sem['cantidad'] > 0 ? round($sem['reprogramados'] / $sem['cantidad'] * 100) : 0;
    $totalCantidad      += $sem['cantidad'];
    $totalEntregados    += $sem['entregados'];
    $totalRechazados    += $sem['rechazados'];
    $totalDevueltos     += $sem['devueltos'];
    $totalEnProceso     += $sem['en_proceso'];
    $totalReprogramados += $sem['reprogramados'];
}
unset($sem);

// Índice de semana relativo para etiquetas del chart
$semanaNum = 1;

// ── Export Excel ──────────────────────────────────────────────────────────────
if ($export) {
    require_once __DIR__ . '/../../../../vendor/autoload.php';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Tendencia Semanal');

    $titulo = 'TENDENCIA SEMANAL — ' . date('d/m/Y', strtotime($fechaDesde)) . ' → ' . date('d/m/Y', strtotime($fechaHasta));
    $sheet->mergeCells('A1:G1');
    $sheet->setCellValue('A1', $titulo);
    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 13],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '0F172A']],
        'alignment' => ['horizontal' => 'center'],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(22);
    $sheet->getRowDimension(2)->setRowHeight(6);

    // Cabeceras (7 columnas: A-G)
    $headers   = ['FECHA (Semana)', 'CANTIDAD DE ORDENES', 'ENTREGADO', 'RECHAZADO', 'DEVUELTO', 'EN PROCESO', 'REPROGRAMADO'];
    $headerBg  = ['1E293B', '334155', '3CB043', '8E44AD', 'B71C1C', 'F5E400', 'F97316'];
    $headerTxt = ['FFFFFF', 'FFFFFF', 'FFFFFF', 'FFFFFF', 'FFFFFF', '3D3200', 'FFFFFF'];
    foreach ($headers as $ci => $h) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
        $sheet->setCellValue("{$col}3", $h);
        $sheet->getStyle("{$col}3")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => $headerTxt[$ci]]],
            'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => $headerBg[$ci]]],
            'alignment' => ['horizontal' => 'center'],
        ]);
    }

    $row        = 4;
    $xlsMesAct  = '';
    $xlsIdxMes  = 0;
    $mesesExcel = [
        1=>'ENERO', 2=>'FEBRERO', 3=>'MARZO', 4=>'ABRIL',
        5=>'MAYO', 6=>'JUNIO', 7=>'JULIO', 8=>'AGOSTO',
        9=>'SEPTIEMBRE', 10=>'OCTUBRE', 11=>'NOVIEMBRE', 12=>'DICIEMBRE'
    ];
    foreach ($semanas as $sem) {
        $numMesXls = (int)date('n', strtotime($sem['lunes']));
        $mesClave  = date('Y-n', strtotime($sem['lunes']));

        // Fila separadora de mes al cambiar de mes
        if ($mesClave !== $xlsMesAct) {
            $xlsMesAct = $mesClave;
            $xlsIdxMes = 1;
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", '📅 ' . $mesesExcel[$numMesXls] . ' ' . date('Y', strtotime($sem['lunes'])));
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E293B']],
                'alignment' => ['horizontal' => 'left'],
            ]);
            $row++;
        } else {
            $xlsIdxMes++;
        }

        $label = 'Semana ' . $xlsIdxMes . ' — ' . date('d/m/Y', strtotime($sem['lunes'])) . ' al ' . date('d/m/Y', strtotime($sem['sabado']));

        // Fila de cantidades (A=label, B=total, C=entregados, D=rechazados, E=devueltos, F=en_proceso, G=reprogramados)
        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("B{$row}", $sem['cantidad']);
        $sheet->setCellValue("C{$row}", $sem['entregados']);
        $sheet->setCellValue("D{$row}", $sem['rechazados']);
        $sheet->setCellValue("E{$row}", $sem['devueltos']);
        $sheet->setCellValue("F{$row}", $sem['en_proceso']);
        $sheet->setCellValue("G{$row}", $sem['reprogramados']);
        $sheet->getStyle("C{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'D4F5D6']], 'font' => ['color' => ['rgb' => '1D6B22'], 'bold' => true]]);
        $sheet->getStyle("D{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EDE9FE']], 'font' => ['color' => ['rgb' => '4A148C'], 'bold' => true]]);
        $sheet->getStyle("E{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FEE2E2']], 'font' => ['color' => ['rgb' => '7F1D1D'], 'bold' => true]]);
        $sheet->getStyle("F{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FDF8B0']], 'font' => ['color' => ['rgb' => '5A5000'], 'bold' => true]]);
        $sheet->getStyle("G{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFEDD5']], 'font' => ['color' => ['rgb' => '9A3412'], 'bold' => true]]);
        $row++;

        // Fila de porcentajes
        $sheet->setCellValue("A{$row}", 'EN PORCENTAJE');
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", $sem['pct_entregados'] . '%');
        $sheet->setCellValue("D{$row}", $sem['pct_rechazados'] . '%');
        $sheet->setCellValue("E{$row}", $sem['pct_devueltos'] . '%');
        $sheet->setCellValue("F{$row}", $sem['pct_en_proceso'] . '%');
        $sheet->setCellValue("G{$row}", $sem['pct_reprogramados'] . '%');
        $sheet->getStyle("A{$row}")->applyFromArray(['font' => ['bold' => true, 'italic' => true, 'color' => ['rgb' => '475569']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F1F5F9']]]);
        $sheet->getStyle("C{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '3CB043']], 'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true], 'alignment' => ['horizontal' => 'center']]);
        $sheet->getStyle("D{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8E44AD']], 'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true], 'alignment' => ['horizontal' => 'center']]);
        $sheet->getStyle("E{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'B71C1C']], 'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true], 'alignment' => ['horizontal' => 'center']]);
        $sheet->getStyle("F{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F5E400']], 'font' => ['color' => ['rgb' => '3D3200'], 'bold' => true], 'alignment' => ['horizontal' => 'center']]);
        $sheet->getStyle("G{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F97316']], 'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true], 'alignment' => ['horizontal' => 'center']]);
        $row++;
        $row++; // fila vacía entre semanas
    }

    // Totales
    $pctTotE  = $totalCantidad > 0 ? round($totalEntregados    / $totalCantidad * 100) : 0;
    $pctTotR  = $totalCantidad > 0 ? round($totalRechazados    / $totalCantidad * 100) : 0;
    $pctTotD  = $totalCantidad > 0 ? round($totalDevueltos     / $totalCantidad * 100) : 0;
    $pctTotP  = $totalCantidad > 0 ? round($totalEnProceso     / $totalCantidad * 100) : 0;
    $pctTotRp = $totalCantidad > 0 ? round($totalReprogramados / $totalCantidad * 100) : 0;
    $sheet->setCellValue("A{$row}", 'TOTALES');
    $sheet->setCellValue("B{$row}", $totalCantidad);
    $sheet->setCellValue("C{$row}", $totalEntregados . ' (' . $pctTotE . '%)');
    $sheet->setCellValue("D{$row}", $totalRechazados . ' (' . $pctTotR . '%)');
    $sheet->setCellValue("E{$row}", $totalDevueltos  . ' (' . $pctTotD . '%)');
    $sheet->setCellValue("F{$row}", $totalEnProceso  . ' (' . $pctTotP . '%)');
    $sheet->setCellValue("G{$row}", $totalReprogramados . ' (' . $pctTotRp . '%)');
    $sheet->getStyle("A{$row}:G{$row}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E2E8F0']]]);

    foreach (range('A', 'H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

    $filename = 'Tendencia_Semanal_' . date('Ymd', strtotime($fechaDesde)) . '_' . date('Ymd', strtotime($fechaHasta)) . '.xlsx';
    $tmpFile = tempnam(__DIR__ . '/../../../../tmp', 'rpt_');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmpFile);
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: max-age=0, no-store');
    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
}

// Nombres de meses en español
$meses = [
    1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril',
    5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto',
    9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'
];
$mesesCortos = [
    1=>'Ene', 2=>'Feb', 3=>'Mar', 4=>'Abr',
    5=>'May', 6=>'Jun', 7=>'Jul', 8=>'Ago',
    9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Dic'
];

// ── Datos para Chart.js ───────────────────────────────────────────────────────
$chartLabels        = [];
$chartEntregados    = [];
$chartRechazados    = [];
$chartDevueltos     = [];
$chartEnProceso     = [];
$chartReprogramados = [];
$semMesActual = '';
$semIdxMes    = 0;
foreach ($semanas as $sem) {
    $numMes    = (int)date('n', strtotime($sem['lunes']));
    $mesClave  = date('Y-n', strtotime($sem['lunes'])); // para detectar cambio de mes
    if ($mesClave !== $semMesActual) { $semMesActual = $mesClave; $semIdxMes = 1; }
    else $semIdxMes++;
    $chartLabels[]        = 'Sem ' . $semIdxMes . ' ' . $mesesCortos[$numMes];
    $chartEntregados[]    = $sem['entregados'];
    $chartRechazados[]    = $sem['rechazados'];
    $chartDevueltos[]     = $sem['devueltos'];
    $chartEnProceso[]     = $sem['en_proceso'];
    $chartReprogramados[] = $sem['reprogramados'];
}
$chartLabelsJson        = json_encode($chartLabels);
$chartEntregadosJson    = json_encode($chartEntregados);
$chartRechazadosJson    = json_encode($chartRechazados);
$chartDevueltosJson     = json_encode($chartDevueltos);
$chartEnProcesoJson     = json_encode($chartEnProceso);
$chartReprogramadosJson = json_encode($chartReprogramados);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tendencia Semanal — RutaEx</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --clr-entregado:    #3cb043;
            --clr-proceso:      #f5e400;
            --clr-rechazado:    #8e44ad;
            --clr-devuelto:     #b71c1c;
            --clr-reprogramado: #f97316;
        }
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }

        .rpt-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #0f4c81 100%);
            color: #fff; padding: 1.75rem 2rem; border-radius: 16px;
            margin-bottom: 1.5rem; box-shadow: 0 8px 32px rgba(15,23,42,.3);
        }
        .rpt-header h4 { font-weight: 800; font-size: 1.25rem; margin-bottom: .25rem; }

        /* KPI grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        @media (max-width: 1100px) { .kpi-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 700px)  { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px)  { .kpi-grid { grid-template-columns: 1fr; } }
        .kpi-card {
            border-radius: 14px; padding: 1.25rem 1rem; color: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,.12); transition: transform .2s, box-shadow .2s;
            position: relative; overflow: hidden;
        }
        .kpi-card::before {
            content: ''; position: absolute; top: -24px; right: -24px;
            width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,.08);
        }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0,0,0,.18); }
        .kpi-card.entregado    { background: var(--clr-entregado); }
        .kpi-card.en-proceso   { background: var(--clr-proceso); color: #3d3200; }
        .kpi-card.rechazado    { background: var(--clr-rechazado); }
        .kpi-card.devuelto     { background: var(--clr-devuelto); }
        .kpi-card.reprogramado { background: var(--clr-reprogramado); }
        .kpi-num { font-size: 2rem; font-weight: 800; line-height: 1; }
        .kpi-pct { font-size: .9rem; font-weight: 600; opacity: .85; }
        .kpi-lbl { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; opacity: .75; margin-top: .3rem; }

        /* Cards */
        .chart-card {
            background: #fff; border-radius: 14px; padding: 1.5rem;
            box-shadow: 0 2px 16px rgba(0,0,0,.06); margin-bottom: 1.5rem;
        }
        .chart-title { font-weight: 700; font-size: 1rem; color: #1e293b; margin-bottom: 1rem; }
        .filter-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.06); margin-bottom: 1.25rem; }

        /* Tabla semanal */
        .tabla-semana { font-size: .84rem; }
        .tabla-semana thead th {
            background: #1e293b; color: #fff; font-weight: 700;
            text-align: center; border: none; padding: .6rem .75rem;
        }
        .tabla-semana thead th.th-ent  { background: #3cb043; }
        .tabla-semana thead th.th-rec  { background: #8e44ad; }
        .tabla-semana thead th.th-proc { background: #f5e400; color: #3d3200; }
        .tabla-semana thead th.th-rep  { background: #f97316; }

        .tabla-semana tbody td { padding: .5rem .75rem; vertical-align: middle; border-color: #e2e8f0; }
        .tabla-semana tfoot td { font-weight: 700; background: #f1f5f9; border-color: #e2e8f0; padding: .6rem .75rem; text-align: center; }

        /* Filas de semana */
        .row-semana-header td { background: #f8fafc !important; font-weight: 700; color: #1e293b; border-top: 2px solid #cbd5e1 !important; }
        .row-semana-header .sem-label { font-weight: 800; color: #0f172a; font-size: .88rem; }
        .row-semana-header .sem-rango { font-size: .78rem; color: #64748b; font-weight: 400; }

        /* Fila de porcentajes */
        .row-pct td { background: #f8fafc !important; font-size: .8rem; color: #475569; }
        .row-pct .lbl-pct { font-style: italic; font-weight: 600; color: #64748b; }

        /* Celdas de cantidad */
        .cell-ent  { background: #d4f5d6 !important; color: #1d6b22 !important; font-weight: 700; text-align: center; }
        .cell-rec  { background: #f3e5f5 !important; color: #4a148c !important; font-weight: 700; text-align: center; }
        .cell-dev  { background: #fee2e2 !important; color: #7f1d1d !important; font-weight: 700; text-align: center; }
        .cell-proc { background: #fdf8b0 !important; color: #5a5000 !important; font-weight: 700; text-align: center; }
        .cell-rep  { background: #ffedd5 !important; color: #9a3412 !important; font-weight: 700; text-align: center; }
        .cell-num  { text-align: center; font-weight: 600; }

        /* Celdas de porcentaje con color sólido — especificidad mayor que .row-pct td */
        .row-pct .pct-ent  { background: var(--clr-entregado) !important;    color: #fff !important; font-weight: 700; text-align: center; }
        .row-pct .pct-rec  { background: var(--clr-rechazado) !important;    color: #fff !important; font-weight: 700; text-align: center; }
        .row-pct .pct-dev  { background: var(--clr-devuelto) !important;     color: #fff !important; font-weight: 700; text-align: center; }
        .row-pct .pct-proc { background: var(--clr-proceso) !important;      color: #3d3200 !important; font-weight: 700; text-align: center; }
        .row-pct .pct-rep  { background: var(--clr-reprogramado) !important; color: #fff !important; font-weight: 700; text-align: center; }
    </style>
</head>
<?php if (!$isPublicLink): ?>
<?php include __DIR__ . '/../../../includes/header.php'; ?>
<?php else: ?>
<body class="bg-light">
<?php endif; ?>

<div class="container-fluid py-4">

    <!-- Cabecera -->
    <div class="rpt-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4><i class="bi bi-calendar-week-fill me-2"></i>Tendencia Semanal</h4>
            <small class="opacity-75">
                Desempeño por semana (Lun–Sáb) · <?= htmlspecialchars($fechaDesde) ?> → <?= htmlspecialchars($fechaHasta) ?>
            </small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (!$isPublicLink): ?>
            <?php if (!empty($tokenEnlacePublico)): ?>
            <div class="dropdown">
                <button class="btn btn-warning btn-sm fw-bold shadow-sm dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-share fs-6"></i> Enlace
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><button class="dropdown-item fw-semibold text-primary" onclick="copiarEnlacePublico()">
                        <i class="bi bi-link-45deg me-2"></i>Copiar Enlace Público</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item text-danger fw-semibold" onclick="toggleEnlacePublico('deshabilitar')">
                        <i class="bi bi-trash me-2"></i>Revocar Acceso</button></li>
                </ul>
            </div>
            <?php else: ?>
            <button type="button" class="btn btn-outline-warning text-white btn-sm fw-bold border-2"
                    onclick="toggleEnlacePublico('habilitar')">
                <i class="bi bi-link-45deg fs-6"></i> Habilitar Enlace
            </button>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($semanas)): ?>
            <a href="<?= RUTA_URL ?>pedidos/informes/semana?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>"
               class="btn btn-success btn-sm fw-bold shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </a>
            <?php endif; ?>

            <?php if (!$isPublicLink): ?>
            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-bar-chart me-1"></i>Informes
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= RUTA_URL ?>pedidos/informes/estatus">
                        <i class="bi bi-pie-chart me-2"></i>Estatus de Órdenes</a></li>
                    <li><a class="dropdown-item" href="<?= RUTA_URL ?>pedidos/informes/region">
                        <i class="bi bi-map me-2"></i>Efectividad por Región</a></li>
                    <li><a class="dropdown-item" href="<?= RUTA_URL ?>pedidos/informes/producto">
                        <i class="bi bi-box-seam me-2"></i>Efectividad por Producto</a></li>
                    <li><a class="dropdown-item active" href="<?= RUTA_URL ?>pedidos/informes/semana">
                        <i class="bi bi-calendar-week me-2"></i>Tendencia Semanal</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= RUTA_URL ?>pedidos/reportes">
                        <i class="bi bi-table me-2"></i>Reporte de Pedidos</a></li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filter-card mb-3">
        <div class="card-body p-3">
            <form method="GET" action="<?= RUTA_URL ?>pedidos/informes/semana" class="row g-2 align-items-end" id="frmFiltroSemana">
                <?php if ($isPublicLink): ?>
                <input type="hidden" name="u" value="<?= htmlspecialchars($pubU) ?>">
                <input type="hidden" name="t" value="<?= htmlspecialchars($pubT) ?>">
                <?php endif; ?>
                <div class="col-md-3 col-6">
                    <label class="form-label small fw-semibold mb-1"><i class="bi bi-calendar3"></i> Desde</label>
                    <input type="date" name="fecha_desde" id="filtroDesde" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($fechaDesde) ?>">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small fw-semibold mb-1"><i class="bi bi-calendar3"></i> Hasta</label>
                    <input type="date" name="fecha_hasta" id="filtroHasta" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($fechaHasta) ?>">
                </div>
                <?php if ($isAdmin): ?>
                <div class="col-md-3 col-12">
                    <label class="form-label small fw-semibold mb-1"><i class="bi bi-person-badge"></i> Proveedor</label>
                    <select name="id_proveedor" class="form-select form-select-sm">
                        <option value="0">Todos los proveedores</option>
                        <?php foreach ($proveedores as $prov): ?>
                        <option value="<?= $prov['id'] ?>" <?= $idProveedor == $prov['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prov['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-auto col-12 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                        <i class="bi bi-search me-1"></i>Aplicar
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm fw-semibold" onclick="filtrarMesActual()" title="Ver solo el mes en curso">
                        <i class="bi bi-calendar-check me-1"></i>Mes en Curso
                    </button>
                    <a href="<?= RUTA_URL ?>pedidos/informes/semana<?= $isPublicLink ? '?u='.$pubU.'&t='.$pubT : '' ?>"
                       class="btn btn-outline-secondary btn-sm fw-semibold" title="Restablecer filtros">
                        <i class="bi bi-x-circle me-1"></i>Limpiar
                    </a>
                </div>
                <div class="col-auto ms-auto d-flex align-items-center">
                    <small class="text-muted"><?= number_format($totalCantidad) ?> pedidos · <?= count($semanas) ?> semanas</small>
                </div>
            </form>
        </div>
    </div>


    <?php if (empty($semanas)): ?>
    <div class="chart-card text-center py-5">
        <i class="bi bi-inbox" style="font-size:3rem;opacity:.25;display:block;margin-bottom:.75rem;color:#64748b"></i>
        <p class="fw-semibold text-muted mb-1">Sin datos en el rango seleccionado</p>
        <small class="text-muted">Ajusta el rango de fechas o los filtros.</small>
    </div>
    <?php else: ?>

    <!-- KPI Totales del período -->
    <?php
    $pctTotE  = $totalCantidad > 0 ? round($totalEntregados    / $totalCantidad * 100, 1) : 0;
    $pctTotR  = $totalCantidad > 0 ? round($totalRechazados    / $totalCantidad * 100, 1) : 0;
    $pctTotD  = $totalCantidad > 0 ? round($totalDevueltos     / $totalCantidad * 100, 1) : 0;
    $pctTotP  = $totalCantidad > 0 ? round($totalEnProceso     / $totalCantidad * 100, 1) : 0;
    $pctTotRp = $totalCantidad > 0 ? round($totalReprogramados / $totalCantidad * 100, 1) : 0;
    $kpisTotal = [
        ['key' => 'ENTREGADO',    'cls' => 'entregado',    'icon' => 'bi-check-circle-fill',       'val' => $totalEntregados,    'pct' => $pctTotE],
        ['key' => 'RECHAZADO',    'cls' => 'rechazado',    'icon' => 'bi-x-circle-fill',            'val' => $totalRechazados,    'pct' => $pctTotR],
        ['key' => 'DEVUELTO',     'cls' => 'devuelto',     'icon' => 'bi-arrow-return-left',        'val' => $totalDevueltos,     'pct' => $pctTotD],
        ['key' => 'EN PROCESO',   'cls' => 'en-proceso',   'icon' => 'bi-arrow-repeat',             'val' => $totalEnProceso,     'pct' => $pctTotP],
        ['key' => 'REPROGRAMADO', 'cls' => 'reprogramado', 'icon' => 'bi-calendar2-check-fill',    'val' => $totalReprogramados, 'pct' => $pctTotRp],
    ];
    ?>
    <div class="kpi-grid">
        <?php foreach ($kpisTotal as $k): ?>
        <div class="kpi-card <?= $k['cls'] ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-num"><?= number_format($k['val']) ?></div>
                    <div class="kpi-pct"><?= $k['pct'] ?>%</div>
                    <div class="kpi-lbl"><?= $k['key'] ?></div>
                </div>
                <i class="bi <?= $k['icon'] ?>" style="font-size:2.2rem;opacity:.3"></i>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Gráfica de líneas -->
    <div class="chart-card">
        <div class="chart-title"><i class="bi bi-graph-up me-2 text-primary"></i>Tendencia por Semana</div>
        <div style="position:relative; height:180px; width:100%;">
            <canvas id="semanaChart"></canvas>
        </div>
    </div>

    <!-- Tabla semanal -->
    <div class="chart-card">
        <div class="chart-title"><i class="bi bi-table me-2 text-primary"></i>Detalle por Semana</div>
        <div class="table-responsive">
            <table class="table tabla-semana table-bordered mb-0">
                <thead>
                    <tr>
                        <th style="vertical-align:middle; min-width:200px">FECHA</th>
                        <th style="vertical-align:middle; text-align:center">CANTIDAD<br>DE ORDENES</th>
                        <th class="th-ent text-center">ENTREGADO</th>
                        <th class="th-rec text-center">RECHAZADO</th>
                        <th class="text-center" style="background:#b71c1c;color:#fff;font-weight:700">DEVUELTO</th>
                        <th class="th-proc text-center">EN PROCESO</th>
                        <th class="th-rep text-center">REPROGRAMADO</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $mesActual  = '';
                $semIdxMes  = 0;
                $colSpanTotal = 7; // FECHA + CANTIDAD + 5 estados
                foreach ($semanas as $sem):
                    $numMes   = (int)date('n', strtotime($sem['lunes']));
                    $nomMes   = $meses[$numMes];
                    $mesClave = date('Y-n', strtotime($sem['lunes']));
                    // Nuevo mes: insertar fila separadora y reiniciar contador
                    if ($mesClave !== $mesActual):
                        $mesActual = $mesClave;
                        $semIdxMes = 1;
                ?>
                <tr>
                    <td colspan="7" style="background:#1e293b;color:#fff;font-weight:800;font-size:.9rem;padding:.6rem 1rem;letter-spacing:.04em;text-transform:uppercase;border:none">
                        <i class="bi bi-calendar3 me-2 opacity-75"></i><?= $nomMes ?> <?= date('Y', strtotime($sem['lunes'])) ?>
                    </td>
                </tr>
                <?php else: $semIdxMes++; endif; ?>
                <!-- Fila cantidades -->
                <tr class="row-semana-header">
                    <td>
                        <span class="sem-label">Semana <?= $semIdxMes ?> &mdash; <?= $nomMes ?></span><br>
                        <span class="sem-rango">
                            <?= date('d/m/Y', strtotime($sem['lunes'])) ?> &ndash; <?= date('d/m/Y', strtotime($sem['sabado'])) ?>
                        </span>
                    </td>
                    <td class="cell-num"><?= number_format($sem['cantidad']) ?></td>
                    <td class="cell-ent"><?= number_format($sem['entregados']) ?></td>
                    <td class="cell-rec"><?= number_format($sem['rechazados']) ?></td>
                    <td class="cell-dev"><?= number_format($sem['devueltos']) ?></td>
                    <td class="cell-proc"><?= number_format($sem['en_proceso']) ?></td>
                    <td class="cell-rep"><?= number_format($sem['reprogramados']) ?></td>
                </tr>
                <!-- Fila porcentajes -->
                <tr class="row-pct">
                    <td class="lbl-pct ps-3">EN PORCENTAJE</td>
                    <td></td>
                    <td class="pct-ent"><?= $sem['pct_entregados'] ?>%</td>
                    <td class="pct-rec"><?= $sem['pct_rechazados'] ?>%</td>
                    <td class="pct-dev"><?= $sem['pct_devueltos'] ?>%</td>
                    <td class="pct-proc"><?= $sem['pct_en_proceso'] ?>%</td>
                    <td class="pct-rep"><?= $sem['pct_reprogramados'] ?>%</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="text-start fw-bold">TOTALES</td>
                        <td><?= number_format($totalCantidad) ?></td>
                        <td class="cell-ent"><?= number_format($totalEntregados) ?> (<?= $pctTotE ?>%)</td>
                        <td class="cell-rec"><?= number_format($totalRechazados) ?> (<?= $pctTotR ?>%)</td>
                        <td class="cell-dev"><?= number_format($totalDevueltos) ?> (<?= $pctTotD ?>%)</td>
                        <td class="cell-proc"><?= number_format($totalEnProceso) ?> (<?= $pctTotP ?>%)</td>
                        <td class="cell-rep"><?= number_format($totalReprogramados) ?> (<?= $pctTotRp ?>%)</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="<?= RUTA_URL ?>vista/js/chart.umd.min.js"></script>
<script>
<?php if (!empty($semanas)): ?>
(function() {
    const ctx = document.getElementById('semanaChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= $chartLabelsJson ?>,
            datasets: [
                {
                    label: 'Entregado',
                    data: <?= $chartEntregadosJson ?>,
                    borderColor: '#3cb043', backgroundColor: 'rgba(60,176,67,.12)',
                    borderWidth: 2.5, pointRadius: 4, pointHoverRadius: 6,
                    fill: false, tension: 0.3,
                },
                {
                    label: 'Rechazado',
                    data: <?= $chartRechazadosJson ?>,
                    borderColor: '#8e44ad', backgroundColor: 'rgba(142,68,173,.12)',
                    borderWidth: 2.5, pointRadius: 4, pointHoverRadius: 6,
                    fill: false, tension: 0.3,
                },
                {
                    label: 'Devuelto',
                    data: <?= $chartDevueltosJson ?>,
                    borderColor: '#b71c1c', backgroundColor: 'rgba(183,28,28,.12)',
                    borderWidth: 2.5, pointRadius: 4, pointHoverRadius: 6,
                    fill: false, tension: 0.3,
                },
                {
                    label: 'En Proceso',
                    data: <?= $chartEnProcesoJson ?>,
                    borderColor: '#d4a800', backgroundColor: 'rgba(245,228,0,.12)',
                    borderWidth: 2.5, pointRadius: 4, pointHoverRadius: 6,
                    fill: false, tension: 0.3,
                },
                {
                    label: 'Reprogramado',
                    data: <?= $chartReprogramadosJson ?>,
                    borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,.12)',
                    borderWidth: 2.5, pointRadius: 4, pointHoverRadius: 6,
                    fill: false, tension: 0.3,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { size: 12, weight: '600', family: 'Inter' }, usePointStyle: true, pointStyleWidth: 10 }
                },
                tooltip: {
                    callbacks: {
                        footer: function(items) {
                            const total = items.reduce((s, i) => s + i.parsed.y, 0);
                            return 'Total semana: ' + total.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11, family: 'Inter' } } },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,.05)' },
                    ticks: { font: { size: 11, family: 'Inter' } }
                }
            },
            animation: { duration: 700 }
        }
    });
})();
<?php endif; ?>

function filtrarMesActual() {
    const now   = new Date();
    const y     = now.getFullYear();
    const m     = String(now.getMonth() + 1).padStart(2, '0');
    const lastD = new Date(y, now.getMonth() + 1, 0).getDate();
    document.getElementById('filtroDesde').value = `${y}-${m}-01`;
    document.getElementById('filtroHasta').value = `${y}-${m}-${lastD}`;
    document.getElementById('frmFiltroSemana').submit();
}
function toggleEnlacePublico(action) {
    const actionText = action === 'habilitar'
        ? 'Esto generará un enlace público compartible.'
        : 'Los enlaces previos dejarán de funcionar.';
    Swal.fire({
        title: action === 'habilitar' ? '¿Activar enlace público?' : '¿Revocar acceso?',
        text: actionText, icon: 'warning', showCancelButton: true,
        confirmButtonColor: action === 'habilitar' ? '#3085d6' : '#d33',
        cancelButtonColor: '#adb5bd', confirmButtonText: 'Sí, ' + action, cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;
        Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData(); fd.append('action', action);
        fetch('<?= RUTA_URL ?>ajax/enlaces_publicos.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: '¡Listo!', text: data.message, showConfirmButton: false, timer: 1500 })
                        .then(() => window.location.reload());
                } else { Swal.fire('Error', data.message || 'Error desconocido', 'error'); }
            }).catch(() => Swal.fire('Error', 'Error en la conexión.', 'error'));
    });
}
function copiarEnlacePublico() {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('u', '<?= $currUserId ?>'); urlParams.set('t', '<?= $tokenEnlacePublico ?>');
    urlParams.delete('export');
    const finalUrl = '<?= RUTA_URL ?>pedidos/informes/semana?' + urlParams.toString();
    navigator.clipboard.writeText(finalUrl).then(() => {
        Swal.fire({ icon: 'success', title: 'Enlace copiado', text: 'Cualquier persona con este enlace podrá ver el informe.', confirmButtonText: 'Entendido' });
    }).catch(() => {
        Swal.fire({ icon: 'warning', title: 'Copia manual requerida', text: 'Copia este enlace:', input: 'text', inputValue: finalUrl, confirmButtonText: 'Cerrar' });
    });
}
</script>
<?php if (!$isPublicLink): ?>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
<?php else: ?>
</body>
</html>
<?php endif; ?>
