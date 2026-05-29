<?php
/**
 * Informe: Efectividad por Producto
 * Ruta: GET /pedidos/informes/producto
 * Muestra tabla + barras horizontales por producto
 */

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/session.php';
require_once __DIR__ . '/../../../../utils/permissions.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';

start_secure_session();

// ── Autenticación dual ────────────────────────────────────────────────────────
$isPublicLink = isset($_GET['u']) && isset($_GET['t']);

if ($isPublicLink) {
    $pubU  = (int)$_GET['u'];
    $pubT  = (string)$_GET['t'];
    $dbPub = (new Conexion())->conectar();
    $stmtPub = $dbPub->prepare("SELECT token_enlace_publico FROM usuarios WHERE id = :id");
    $stmtPub->execute([':id' => $pubU]);
    $dbToken = $stmtPub->fetchColumn();
    if (empty($dbToken) || !hash_equals($dbToken, $pubT)) {
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Enlace Inválido</title>
        <style>body{margin:0;display:flex;align-items:center;justify-content:center;height:100vh;background:#0f172a;font-family:system-ui,sans-serif}
        .card{background:#1e293b;padding:3rem 2.5rem;border-radius:16px;text-align:center;max-width:420px;width:90%;border-top:4px solid #ef4444}
        h1{font-size:1.4rem;color:#f1f5f9;font-weight:700}p{color:#94a3b8;font-size:.95rem}</style></head><body>
        <div class="card"><div style="font-size:3rem;margin-bottom:1rem">🔗</div>
        <h1>Acceso Denegado</h1><p>El enlace público ya no es válido o ha sido revocado.</p></div></body></html>';
        exit;
    }
    $stmtRol = $dbPub->prepare("SELECT id_rol FROM usuarios_roles WHERE id_usuario = :id LIMIT 1");
    $stmtRol->execute([':id' => $pubU]);
    $pubRol         = (int)$stmtRol->fetchColumn();
    $isAdmin        = false;
    $isProveedorExt = ($pubRol == ROL_CLIENTE);
    $currUserId     = $pubU;
    $db             = $dbPub;
} else {
    require_login();
    $isAdmin        = isSuperAdmin();
    $currRol        = $_SESSION['rol'] ?? 0;
    $isProveedorExt = ($currRol == ROL_CLIENTE);
    $currUserId     = getCurrentUserId();
    $db             = (new Conexion())->conectar();
}

// ── Token + nombre ────────────────────────────────────────────────────────────
$stmtToken = $db->prepare("SELECT token_enlace_publico, nombre FROM usuarios WHERE id = :id");
$stmtToken->execute([':id' => $currUserId]);
$tokenRow           = $stmtToken->fetch(PDO::FETCH_ASSOC);
$tokenEnlacePublico = $tokenRow['token_enlace_publico'] ?? '';
$currUserNombre     = $tokenRow['nombre'] ?? ($_SESSION['nombre'] ?? '');

// ── Filtros ───────────────────────────────────────────────────────────────────
$fechaDesde  = $_GET['fecha_desde'] ?? date('Y-m-01');
$fechaHasta  = $_GET['fecha_hasta'] ?? date('Y-m-d');
$idProveedor = (!$isAdmin && $isProveedorExt) ? $currUserId : (int)($_GET['id_proveedor'] ?? 0);
$export      = isset($_GET['export']) && $_GET['export'] === '1';

// ── Catálogo de proveedores ───────────────────────────────────────────────────
$proveedores = [];
if ($isAdmin) {
    $proveedores = $db->query(
        "SELECT DISTINCT u.id, u.nombre FROM usuarios u
         INNER JOIN usuarios_roles ur ON ur.id_usuario = u.id
         WHERE ur.id_rol = " . ROL_CLIENTE . " AND u.activo = 1 ORDER BY u.nombre"
    )->fetchAll(PDO::FETCH_ASSOC);
}

// ── WHERE ─────────────────────────────────────────────────────────────────────
$where  = ['p.fecha_ingreso BETWEEN :desde AND :hasta'];
$params = [':desde' => $fechaDesde, ':hasta' => $fechaHasta];

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

// ── Query: efectividad por producto ──────────────────────────────────────────
// Usamos pedidos_productos para el vínculo pedido → producto
$sqlProducto = "
    SELECT
        pr.nombre AS producto,
        COUNT(DISTINCT p.id) AS cantidad,
        SUM(CASE WHEN LOWER(ep.nombre_estado) LIKE '%entregado a bodega%' THEN 0
                  WHEN LOWER(ep.nombre_estado) LIKE '%entregado%' THEN 1 ELSE 0 END) AS entregados,
        SUM(CASE WHEN LOWER(ep.nombre_estado) LIKE '%rechazado%'
                   OR LOWER(ep.nombre_estado) LIKE '%devuelto%'
                   OR LOWER(ep.nombre_estado) LIKE '%devoluci%'
                   OR LOWER(ep.nombre_estado) LIKE '%entregado a bodega%' THEN 1 ELSE 0 END) AS rechazados,
        SUM(CASE WHEN LOWER(ep.nombre_estado) LIKE '%reprogramado%' THEN 1 ELSE 0 END) AS reprogramados
    FROM pedidos p
    INNER JOIN pedidos_productos pp  ON pp.id_pedido  = p.id
    INNER JOIN productos pr          ON pr.id          = pp.id_producto
    LEFT  JOIN estados_pedidos ep    ON ep.id          = p.id_estado
    {$whereStr}
    GROUP BY pr.id, pr.nombre
    ORDER BY cantidad DESC
";
$stmtProd = $db->prepare($sqlProducto);
foreach ($params as $k => $v) $stmtProd->bindValue($k, $v);
$stmtProd->execute();
$productos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

// Calcular en_proceso y totales
$totalCantidad      = 0;
$totalEntregados    = 0;
$totalRechazados    = 0;
$totalEnProceso     = 0;
$totalReprogramados = 0;

foreach ($productos as &$prod) {
    $prod['reprogramados']     = (int)($prod['reprogramados'] ?? 0);
    $prod['en_proceso']        = $prod['cantidad'] - $prod['entregados'] - $prod['rechazados'] - $prod['reprogramados'];
    $prod['pct_entregados']    = $prod['cantidad'] > 0 ? round($prod['entregados']    / $prod['cantidad'] * 100) : 0;
    $prod['pct_rechazados']    = $prod['cantidad'] > 0 ? round($prod['rechazados']    / $prod['cantidad'] * 100) : 0;
    $prod['pct_en_proceso']    = $prod['cantidad'] > 0 ? round($prod['en_proceso']    / $prod['cantidad'] * 100) : 0;
    $prod['pct_reprogramados'] = $prod['cantidad'] > 0 ? round($prod['reprogramados'] / $prod['cantidad'] * 100) : 0;
    $totalCantidad      += $prod['cantidad'];
    $totalEntregados    += $prod['entregados'];
    $totalRechazados    += $prod['rechazados'];
    $totalEnProceso     += $prod['en_proceso'];
    $totalReprogramados += $prod['reprogramados'];
}
unset($prod);

// ── Export Excel ──────────────────────────────────────────────────────────────
if ($export) {
    require_once __DIR__ . '/../../../../vendor/autoload.php';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Efectividad Producto');

    $titulo  = 'EFECTIVIDAD POR PRODUCTO — ' . date('d/m/Y', strtotime($fechaDesde)) . ' → ' . date('d/m/Y', strtotime($fechaHasta));
    $headers = ['PRODUCTO', 'CANTIDAD', 'ENTREGADO', '%', 'RECHAZADO', '%', 'EN PROCESO', '%', 'REPROGRAMADO', '%'];

    $sheet->mergeCells('A1:J1');
    $sheet->setCellValue('A1', $titulo);
    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 13],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '0F172A']],
        'alignment' => ['horizontal' => 'center'],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(22);
    $sheet->getRowDimension(2)->setRowHeight(6);

    $headerColors = ['FFFFFF', 'FFFFFF', 'FFFFFF', 'FFFFFF', 'FFFFFF', 'FFFFFF', '3D3200', '3D3200', 'FFFFFF', 'FFFFFF'];
    $headerBg     = ['1E293B', '1E293B', '3CB043', '3CB043', 'D42B2B', 'D42B2B', 'F5E400', 'F5E400', 'F97316', 'F97316'];
    foreach ($headers as $ci => $h) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
        $sheet->setCellValue("{$col}3", $h);
        $sheet->getStyle("{$col}3")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => $headerColors[$ci]]],
            'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => $headerBg[$ci]]],
            'alignment' => ['horizontal' => 'center'],
        ]);
    }

    $row = 4;
    foreach ($productos as $prod) {
        $sheet->setCellValue("A{$row}", $prod['producto']);
        $sheet->setCellValue("B{$row}", $prod['cantidad']);
        $sheet->setCellValue("C{$row}", $prod['entregados']); $sheet->setCellValue("D{$row}", $prod['pct_entregados'] . '%');
        $sheet->setCellValue("E{$row}", $prod['rechazados']); $sheet->setCellValue("F{$row}", $prod['pct_rechazados'] . '%');
        $sheet->setCellValue("G{$row}", $prod['en_proceso']);  $sheet->setCellValue("H{$row}", $prod['pct_en_proceso'] . '%');
        $sheet->setCellValue("I{$row}", $prod['reprogramados']); $sheet->setCellValue("J{$row}", $prod['pct_reprogramados'] . '%');
        $sheet->getStyle("C{$row}:D{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '3CB043']], 'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true]]);
        $sheet->getStyle("E{$row}:F{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'D42B2B']], 'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true]]);
        $sheet->getStyle("G{$row}:H{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F5E400']], 'font' => ['color' => ['rgb' => '3D3200'], 'bold' => true]]);
        $sheet->getStyle("I{$row}:J{$row}")->applyFromArray(['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F97316']], 'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true]]);
        $row++;
    }

    $pctTotE  = $totalCantidad > 0 ? round($totalEntregados    / $totalCantidad * 100) : 0;
    $pctTotR  = $totalCantidad > 0 ? round($totalRechazados    / $totalCantidad * 100) : 0;
    $pctTotP  = $totalCantidad > 0 ? round($totalEnProceso     / $totalCantidad * 100) : 0;
    $pctTotRp = $totalCantidad > 0 ? round($totalReprogramados / $totalCantidad * 100) : 0;
    $sheet->setCellValue("A{$row}", 'TOTALES'); $sheet->setCellValue("B{$row}", $totalCantidad);
    $sheet->setCellValue("C{$row}", $totalEntregados); $sheet->setCellValue("D{$row}", $pctTotE . '%');
    $sheet->setCellValue("E{$row}", $totalRechazados); $sheet->setCellValue("F{$row}", $pctTotR . '%');
    $sheet->setCellValue("G{$row}", $totalEnProceso);  $sheet->setCellValue("H{$row}", $pctTotP . '%');
    $sheet->setCellValue("I{$row}", $totalReprogramados); $sheet->setCellValue("J{$row}", $pctTotRp . '%');
    $sheet->getStyle("A{$row}:J{$row}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E2E8F0']]]);

    foreach (range('A', 'J') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

    $filename = 'Efectividad_Producto_' . date('Ymd', strtotime($fechaDesde)) . '_' . date('Ymd', strtotime($fechaHasta)) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

// ── Datos para Chart.js ───────────────────────────────────────────────────────
$chartLabels        = json_encode(array_column($productos, 'producto'));
$chartEntregados    = json_encode(array_column($productos, 'entregados'));
$chartRechazados    = json_encode(array_column($productos, 'rechazados'));
$chartEnProceso     = json_encode(array_column($productos, 'en_proceso'));
$chartReprogramados = json_encode(array_column($productos, 'reprogramados'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Efectividad por Producto — RutaEx</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .rpt-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #0f4c81 100%);
            color: #fff; padding: 1.75rem 2rem; border-radius: 16px;
            margin-bottom: 1.5rem; box-shadow: 0 8px 32px rgba(15,23,42,.3);
        }
        .rpt-header h4 { font-weight: 800; font-size: 1.25rem; margin-bottom: .25rem; }
        .chart-card {
            background: #fff; border-radius: 14px; padding: 1.5rem;
            box-shadow: 0 2px 16px rgba(0,0,0,.06); margin-bottom: 1.5rem;
        }
        .chart-title { font-weight: 700; font-size: 1rem; color: #1e293b; margin-bottom: 1rem; }
        .filter-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.06); margin-bottom: 1.25rem; }

        /* Tabla */
        .tabla-prod { font-size: .84rem; }
        .tabla-prod thead tr:first-child th { background: #1e293b; color: #fff; font-weight: 700; text-align: center; border: none; padding: .55rem .75rem; }
        .tabla-prod thead tr:last-child th.th-ent  { background: #3cb043; color: #fff; font-weight: 600; text-align: center; font-size: .78rem; }
        .tabla-prod thead tr:last-child th.th-rec  { background: #d42b2b; color: #fff; font-weight: 600; text-size: .78rem; }
        .tabla-prod thead tr:last-child th.th-proc { background: #f5e400; color: #3d3200; font-weight: 600; text-align: center; font-size: .78rem; }
        .tabla-prod thead tr:last-child th.th-rep  { background: #f97316; color: #fff; font-weight: 600; text-align: center; font-size: .78rem; }
        .tabla-prod tbody td { padding: .5rem .75rem; vertical-align: middle; border-color: #e2e8f0; }
        .tabla-prod tfoot td { font-weight: 700; background: #f1f5f9; border-color: #e2e8f0; padding: .55rem .75rem; }

        .cell-ent  { background: #d4f5d6 !important; color: #1d6b22 !important; font-weight: 700; text-align: center; }
        .cell-rec  { background: #fad4d4 !important; color: #8b1a1a !important; font-weight: 700; text-align: center; }
        .cell-proc { background: #fdf8b0 !important; color: #5a5000 !important; font-weight: 700; text-align: center; }
        .cell-rep  { background: #ffedd5 !important; color: #9a3412 !important; font-weight: 700; text-align: center; }
        .cell-num  { text-align: center; font-weight: 600; }
        .prod-name { font-weight: 600; color: #1e293b; }

        /* Rank badge */
        .rank-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 22px; height: 22px; border-radius: 50%;
            background: #e2e8f0; color: #475569;
            font-size: .7rem; font-weight: 700; margin-right: .4rem;
        }
        .rank-badge.top-1 { background: #fbbf24; color: #78350f; }
        .rank-badge.top-2 { background: #94a3b8; color: #1e293b; }
        .rank-badge.top-3 { background: #b45309; color: #fff; }
    </style>
</head>
<?php if (!$isPublicLink): ?>
<?php include __DIR__ . '/../../../includes/header.php'; ?>
<body>
<?php else: ?>
<body class="bg-light">
<?php endif; ?>

<div class="container-fluid py-4">

    <!-- Cabecera -->
    <div class="rpt-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4><i class="bi bi-box-seam me-2"></i>Efectividad por Producto</h4>
            <small class="opacity-75">
                Desempeño por producto · <?= htmlspecialchars($fechaDesde) ?> → <?= htmlspecialchars($fechaHasta) ?>
            </small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (!$isPublicLink): ?>
            <?php if (!empty($tokenEnlacePublico)): ?>
            <div class="dropdown">
                <button class="btn btn-warning btn-sm fw-bold shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
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

            <?php if (!empty($productos)): ?>
            <a href="<?= RUTA_URL ?>pedidos/informes/producto?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>"
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
                    <li><a class="dropdown-item active" href="<?= RUTA_URL ?>pedidos/informes/producto">
                        <i class="bi bi-box-seam me-2"></i>Efectividad por Producto</a></li>
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
            <form method="GET" action="<?= RUTA_URL ?>pedidos/informes/producto" class="row g-2 align-items-end">
                <?php if ($isPublicLink): ?>
                <input type="hidden" name="u" value="<?= htmlspecialchars($pubU) ?>">
                <input type="hidden" name="t" value="<?= htmlspecialchars($pubT) ?>">
                <?php endif; ?>
                <div class="col-md-3 col-6">
                    <label class="form-label small fw-semibold mb-1"><i class="bi bi-calendar3"></i> Desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaDesde) ?>">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small fw-semibold mb-1"><i class="bi bi-calendar3"></i> Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaHasta) ?>">
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
                <div class="col-md-2 col-12">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                        <i class="bi bi-search me-1"></i>Aplicar
                    </button>
                </div>
                <div class="col-auto ms-auto d-flex align-items-center">
                    <small class="text-muted"><?= number_format($totalCantidad) ?> pedidos · <?= count($productos) ?> productos</small>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($productos)): ?>
    <div class="chart-card text-center py-5">
        <i class="bi bi-inbox" style="font-size:3rem;opacity:.25;display:block;margin-bottom:.75rem;color:#64748b"></i>
        <p class="fw-semibold text-muted mb-1">Sin datos en el rango seleccionado</p>
        <small class="text-muted">Ajusta el rango de fechas o los filtros. Asegúrate que los pedidos tienen productos vinculados.</small>
    </div>
    <?php else: ?>

    <!-- Gráfica horizontal -->
    <div class="chart-card">
        <div class="chart-title"><i class="bi bi-bar-chart-horizontal me-2 text-primary"></i>Volumen por Producto</div>
        <div style="position:relative; min-height:<?= max(250, count($productos) * 40) ?>px">
            <canvas id="productoChart"></canvas>
        </div>
    </div>

    <!-- Tabla -->
    <div class="chart-card">
        <div class="chart-title"><i class="bi bi-table me-2 text-primary"></i>Efectividad por Producto</div>
        <div class="table-responsive">
            <table class="table tabla-prod table-bordered mb-0">
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align:middle">#</th>
                        <th rowspan="2" style="vertical-align:middle">PRODUCTO</th>
                        <th rowspan="2" class="text-center" style="vertical-align:middle">CANTIDAD</th>
                        <th colspan="2" class="text-center" style="background:#3cb043;color:#fff">ENTREGADO</th>
                        <th colspan="2" class="text-center" style="background:#d42b2b;color:#fff">RECHAZADO</th>
                        <th colspan="2" class="text-center" style="background:#f5e400;color:#3d3200">EN PROCESO</th>
                        <th colspan="2" class="text-center" style="background:#f97316;color:#fff">REPROGRAMADO</th>
                    </tr>
                    <tr>
                        <th class="th-ent">Cant.</th><th class="th-ent">%</th>
                        <th class="th-rec">Cant.</th><th class="th-rec">%</th>
                        <th class="th-proc">Cant.</th><th class="th-proc">%</th>
                        <th class="th-rep">Cant.</th><th class="th-rep">%</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($productos as $i => $prod):
                    $rank = $i + 1;
                    $rankCls = $rank === 1 ? 'top-1' : ($rank === 2 ? 'top-2' : ($rank === 3 ? 'top-3' : ''));
                ?>
                <tr>
                    <td class="text-center text-muted" style="font-size:.78rem; width: 36px">
                        <span class="rank-badge <?= $rankCls ?>"><?= $rank ?></span>
                    </td>
                    <td class="prod-name"><?= htmlspecialchars($prod['producto']) ?></td>
                    <td class="cell-num"><?= number_format($prod['cantidad']) ?></td>
                    <td class="cell-ent"><?= number_format($prod['entregados']) ?></td>
                    <td class="cell-ent"><?= $prod['pct_entregados'] ?>%</td>
                    <td class="cell-rec"><?= number_format($prod['rechazados']) ?></td>
                    <td class="cell-rec"><?= $prod['pct_rechazados'] ?>%</td>
                    <td class="cell-proc"><?= number_format($prod['en_proceso']) ?></td>
                    <td class="cell-proc"><?= $prod['pct_en_proceso'] ?>%</td>
                    <td class="cell-rep"><?= number_format($prod['reprogramados']) ?></td>
                    <td class="cell-rep"><?= $prod['pct_reprogramados'] ?>%</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php
                    $pctTotE  = $totalCantidad > 0 ? round($totalEntregados    / $totalCantidad * 100) : 0;
                    $pctTotR  = $totalCantidad > 0 ? round($totalRechazados    / $totalCantidad * 100) : 0;
                    $pctTotP  = $totalCantidad > 0 ? round($totalEnProceso     / $totalCantidad * 100) : 0;
                    $pctTotRp = $totalCantidad > 0 ? round($totalReprogramados / $totalCantidad * 100) : 0;
                    ?>
                    <tr>
                        <td colspan="2">TOTALES</td>
                        <td class="text-center"><?= number_format($totalCantidad) ?></td>
                        <td class="text-center"><?= number_format($totalEntregados) ?></td>
                        <td class="text-center"><?= $pctTotE ?>%</td>
                        <td class="text-center"><?= number_format($totalRechazados) ?></td>
                        <td class="text-center"><?= $pctTotR ?>%</td>
                        <td class="text-center"><?= number_format($totalEnProceso) ?></td>
                        <td class="text-center"><?= $pctTotP ?>%</td>
                        <td class="text-center"><?= number_format($totalReprogramados) ?></td>
                        <td class="text-center"><?= $pctTotRp ?>%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
<?php if (!empty($productos)): ?>
(function() {
    const ctx = document.getElementById('productoChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= $chartLabels ?>,
            datasets: [
                { label: 'Entregado',     data: <?= $chartEntregados ?>,    backgroundColor: 'rgba(60,176,67,.8)',   borderColor: '#3cb043', borderWidth: 1.5, borderRadius: 4 },
                { label: 'Rechazado',     data: <?= $chartRechazados ?>,    backgroundColor: 'rgba(212,43,43,.8)',   borderColor: '#d42b2b', borderWidth: 1.5, borderRadius: 4 },
                { label: 'En Proceso',    data: <?= $chartEnProceso ?>,     backgroundColor: 'rgba(245,228,0,.8)',   borderColor: '#f5e400', borderWidth: 1.5, borderRadius: 4 },
                { label: 'Reprogramado',  data: <?= $chartReprogramados ?>, backgroundColor: 'rgba(249,115,22,.8)', borderColor: '#f97316', borderWidth: 1.5, borderRadius: 4 }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: { position: 'top', labels: { font: { size: 12, weight: '600', family: 'Inter' }, usePointStyle: true, pointStyleWidth: 10 } },
                tooltip: {
                    mode: 'index',
                    callbacks: {
                        footer: (items) => 'Total: ' + items.reduce((s, i) => s + i.parsed.x, 0).toLocaleString()
                    }
                }
            },
            scales: {
                x: { beginAtZero: true, stacked: false, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { size: 11, family: 'Inter' } } },
                y: { grid: { display: false }, ticks: { font: { size: 11, family: 'Inter' } } }
            },
            animation: { duration: 700 }
        }
    });
})();
<?php endif; ?>

function toggleEnlacePublico(action) {
    Swal.fire({ title: action === 'habilitar' ? '¿Activar enlace público?' : '¿Revocar acceso?',
        text: action === 'habilitar' ? 'Esto generará un enlace público compartible.' : 'Los enlaces previos dejarán de funcionar.',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: action === 'habilitar' ? '#3085d6' : '#d33', cancelButtonColor: '#adb5bd',
        confirmButtonText: 'Sí, ' + action, cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;
        Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData(); fd.append('action', action);
        fetch('<?= RUTA_URL ?>ajax/enlaces_publicos.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) { Swal.fire({ icon: 'success', title: '¡Listo!', text: data.message, showConfirmButton: false, timer: 1500 }).then(() => window.location.reload()); }
                else { Swal.fire('Error', data.message || 'Error desconocido', 'error'); }
            }).catch(() => Swal.fire('Error', 'Error en la conexión.', 'error'));
    });
}
function copiarEnlacePublico() {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('u', '<?= $currUserId ?>'); urlParams.set('t', '<?= $tokenEnlacePublico ?>');
    urlParams.delete('export');
    const finalUrl = '<?= RUTA_URL ?>pedidos/informes/producto?' + urlParams.toString();
    navigator.clipboard.writeText(finalUrl).then(() => {
        Swal.fire({ icon: 'success', title: 'Enlace copiado', text: 'Cualquier persona con este enlace podrá ver el informe.', confirmButtonText: 'Entendido' });
    }).catch(() => {
        Swal.fire({ icon: 'warning', title: 'Copia manual requerida', text: 'Copia este enlace:', input: 'text', inputValue: finalUrl, confirmButtonText: 'Cerrar' });
    });
}
</script>
</body>
</html>
