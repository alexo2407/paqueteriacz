<?php
/**
 * Informe: Estatus de Órdenes
 * Ruta: GET /pedidos/informes/estatus
 * Muestra gráfica dona + tabla con ENTREGADO / EN PROCESO / RECHAZADO / REPROGRAMADO
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

// ── Token enlace público ──────────────────────────────────────────────────────
$stmtToken      = $db->prepare("SELECT token_enlace_publico, nombre FROM usuarios WHERE id = :id");
$stmtToken->execute([':id' => $currUserId]);
$tokenRow           = $stmtToken->fetch(PDO::FETCH_ASSOC);
$tokenEnlacePublico = $tokenRow['token_enlace_publico'] ?? '';
$currUserNombre     = $tokenRow['nombre'] ?? ($_SESSION['nombre'] ?? '');

// ── Filtros ───────────────────────────────────────────────────────────────────
$fechaDesde  = $_GET['fecha_desde'] ?? date('Y-m-01');
$fechaHasta  = $_GET['fecha_hasta'] ?? date('Y-m-d');
$idProveedor = (!$isAdmin && $isProveedorExt) ? $currUserId : (int)($_GET['id_proveedor'] ?? 0);
$export      = isset($_GET['export']) && $_GET['export'] === '1';

// ── Catálogo de proveedores (solo Admin) ──────────────────────────────────────
$proveedores = [];
if ($isAdmin) {
    $proveedores = $db->query(
        "SELECT DISTINCT u.id, u.nombre
         FROM usuarios u
         INNER JOIN usuarios_roles ur ON ur.id_usuario = u.id
         WHERE ur.id_rol = " . ROL_CLIENTE . " AND u.activo = 1
         ORDER BY u.nombre ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

// ── Construcción de WHERE ─────────────────────────────────────────────────────
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

// ── Query principal: agrupación por categoría de estado ───────────────────────
$sqlEstatus = "
    SELECT
        CASE
            WHEN LOWER(ep.nombre_estado) LIKE '%rechazado%'
              OR LOWER(ep.nombre_estado) LIKE '%devuelto%'
              OR LOWER(ep.nombre_estado) LIKE '%devoluci%'
              OR LOWER(ep.nombre_estado) LIKE '%entregado a bodega%' THEN 'RECHAZADO'
            WHEN LOWER(ep.nombre_estado) LIKE '%entregado%' THEN 'ENTREGADO'
            WHEN LOWER(ep.nombre_estado) LIKE '%reprogramado%' THEN 'REPROGRAMADO'
            ELSE 'EN PROCESO'
        END AS categoria,
        COUNT(*) AS total
    FROM pedidos p
    LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
    {$whereStr}
    GROUP BY categoria
    ORDER BY FIELD(categoria, 'ENTREGADO', 'EN PROCESO', 'RECHAZADO', 'REPROGRAMADO')
";
$stmtEst = $db->prepare($sqlEstatus);
foreach ($params as $k => $v) $stmtEst->bindValue($k, $v);
$stmtEst->execute();
$rows = $stmtEst->fetchAll(PDO::FETCH_ASSOC);

// Normalizar a las 4 categorías (aunque alguna tenga 0)
$data = ['ENTREGADO' => 0, 'EN PROCESO' => 0, 'RECHAZADO' => 0, 'REPROGRAMADO' => 0];
foreach ($rows as $r) {
    $data[$r['categoria']] = (int)$r['total'];
}
$totalGeneral = array_sum($data);

// ── Export Excel ──────────────────────────────────────────────────────────────
if ($export) {
    require_once __DIR__ . '/../../../../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Estatus de Órdenes');

    // Título
    $titulo = 'ESTATUS DE ÓRDENES — ' . date('d/m/Y', strtotime($fechaDesde)) . ' → ' . date('d/m/Y', strtotime($fechaHasta));
    $sheet->mergeCells('A1:D1');
    $sheet->setCellValue('A1', $titulo);
    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 13],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '0F172A']],
        'alignment' => ['horizontal' => 'center'],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(22);

    // Cabeceras
    $headers = ['Status', 'Contador', 'Porcentaje', '% Num'];
    foreach ($headers as $ci => $h) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
        $sheet->setCellValue("{$col}2", $h);
    }
    $sheet->getStyle('A2:D2')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E293B']],
        'alignment' => ['horizontal' => 'center'],
    ]);

    // Colores por categoría
    $colores = [
        'ENTREGADO'    => ['bg' => '3CB043', 'text' => 'FFFFFF'],
        'EN PROCESO'   => ['bg' => 'F5E400', 'text' => '3D3200'],
        'RECHAZADO'    => ['bg' => 'D42B2B', 'text' => 'FFFFFF'],
        'REPROGRAMADO' => ['bg' => 'F97316', 'text' => 'FFFFFF'],
    ];

    $row = 3;
    foreach ($data as $cat => $cnt) {
        $pct = $totalGeneral > 0 ? round($cnt / $totalGeneral * 100, 1) : 0;
        $clr = $colores[$cat];
        $sheet->setCellValue("A{$row}", $cat);
        $sheet->setCellValue("B{$row}", $cnt);
        $sheet->setCellValue("C{$row}", $pct . '%');
        $sheet->setCellValue("D{$row}", $pct / 100);
        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $clr['bg']]],
            'font' => ['color' => ['rgb' => $clr['text']], 'bold' => true],
        ]);
        $row++;
    }

    // Totales
    $sheet->setCellValue("A{$row}", 'Totales');
    $sheet->setCellValue("B{$row}", $totalGeneral);
    $sheet->setCellValue("C{$row}", '100%');
    $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E2E8F0']],
    ]);

    foreach (['A','B','C','D'] as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = 'Estatus_Ordenes_' . date('Ymd', strtotime($fechaDesde)) . '_' . date('Ymd', strtotime($fechaHasta)) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
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
    <title>Estatus de Órdenes — RutaEx</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --clr-entregado:    #3cb043;
            --clr-proceso:      #f5e400;
            --clr-rechazado:    #d42b2b;
            --clr-reprogramado: #f97316;
            --clr-dark:         #0f172a;
            --clr-card:         #1e293b;
            --clr-surface:      #f8fafc;
        }
        body { background: var(--clr-surface); font-family: 'Inter', sans-serif; }

        /* Header */
        .rpt-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #0f4c81 100%);
            color: #fff;
            padding: 1.75rem 2rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 32px rgba(15,23,42,.3);
        }
        .rpt-header h4 { font-weight: 800; font-size: 1.25rem; margin-bottom: .25rem; }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        @media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .kpi-grid { grid-template-columns: 1fr; } }
        .kpi-card {
            border-radius: 14px;
            padding: 1.5rem 1.25rem;
            color: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,.12);
            transition: transform .2s, box-shadow .2s;
            position: relative;
            overflow: hidden;
        }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: -30px; right: -30px;
            width: 100px; height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,.08);
        }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0,0,0,.18); }
        .kpi-card.entregado     { background: #3cb043; color: #fff; }
        .kpi-card.en-proceso    { background: #f5e400; color: #3d3200; }
        .kpi-card.rechazado     { background: #d42b2b; color: #fff; }
        .kpi-card.reprogramado  { background: #f97316; color: #fff; }
        .kpi-num  { font-size: 2.5rem; font-weight: 800; line-height: 1; }
        .kpi-pct  { font-size: 1rem; font-weight: 600; opacity: .85; }
        .kpi-lbl  { font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; opacity: .75; margin-top: .35rem; }

        /* Chart card */
        .chart-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.5rem;
            box-shadow: 0 2px 16px rgba(0,0,0,.06);
            margin-bottom: 1.5rem;
        }
        .chart-title { font-weight: 700; font-size: 1rem; color: #1e293b; margin-bottom: 1rem; }

        /* Tabla */
        .tabla-estatus { font-size: .9rem; }
        .tabla-estatus thead th {
            background: #1e293b;
            color: #fff;
            font-weight: 700;
            text-align: center;
            border: none;
            padding: .65rem 1rem;
        }
        .tabla-estatus tbody td {
            padding: .65rem 1rem;
            vertical-align: middle;
            border-color: #e2e8f0;
        }
        .tabla-estatus tfoot td {
            font-weight: 700;
            background: #f1f5f9;
            border-color: #e2e8f0;
            padding: .65rem 1rem;
        }

        /* Badge de estado */
        .badge-cat {
            display: inline-block;
            padding: .35em .85em;
            border-radius: 20px;
            font-size: .82rem;
            font-weight: 700;
            letter-spacing: .02em;
        }
        .badge-entregado     { background: #d4f5d6; color: #1d6b22; }
        .badge-proceso       { background: #fdf8b0; color: #5a5000; }
        .badge-rechazado     { background: #fad4d4; color: #8b1a1a; }
        .badge-reprogramado  { background: #ffedd5; color: #9a3412; }

        /* Progress bar */
        .prog-bar-wrap { height: 10px; background: #e2e8f0; border-radius: 99px; overflow: hidden; min-width: 80px; }
        .prog-bar-fill { height: 100%; border-radius: 99px; transition: width .6s ease; }
        .prog-entregado     { background: var(--clr-entregado); }
        .prog-proceso       { background: var(--clr-proceso); }
        .prog-rechazado     { background: var(--clr-rechazado); }
        .prog-reprogramado  { background: var(--clr-reprogramado); }

        /* Filtros */
        .filter-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.06); margin-bottom: 1.25rem; }
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
            <h4><i class="bi bi-pie-chart-fill me-2"></i>Estatus de Órdenes</h4>
            <small class="opacity-75">
                Distribución por estado · <?= htmlspecialchars($fechaDesde) ?> → <?= htmlspecialchars($fechaHasta) ?>
            </small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (!$isPublicLink): ?>
            <!-- Enlace Público -->
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

            <?php if ($totalGeneral > 0): ?>
            <a href="<?= RUTA_URL ?>pedidos/informes/estatus?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>"
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
                    <li><a class="dropdown-item active" href="<?= RUTA_URL ?>pedidos/informes/estatus">
                        <i class="bi bi-pie-chart me-2"></i>Estatus de Órdenes</a></li>
                    <li><a class="dropdown-item" href="<?= RUTA_URL ?>pedidos/informes/region">
                        <i class="bi bi-map me-2"></i>Efectividad por Región</a></li>
                    <li><a class="dropdown-item" href="<?= RUTA_URL ?>pedidos/informes/producto">
                        <i class="bi bi-box-seam me-2"></i>Efectividad por Producto</a></li>
                    <li><a class="dropdown-item" href="<?= RUTA_URL ?>pedidos/informes/semana">
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
            <form method="GET" action="<?= RUTA_URL ?>pedidos/informes/estatus" class="row g-2 align-items-end">
                <?php if ($isPublicLink): ?>
                <input type="hidden" name="u" value="<?= htmlspecialchars($pubU) ?>">
                <input type="hidden" name="t" value="<?= htmlspecialchars($pubT) ?>">
                <?php endif; ?>

                <div class="col-md-3 col-6">
                    <label class="form-label small fw-semibold mb-1"><i class="bi bi-calendar3"></i> Desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($fechaDesde) ?>">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small fw-semibold mb-1"><i class="bi bi-calendar3"></i> Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm"
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

                <div class="col-md-2 col-12">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                        <i class="bi bi-search me-1"></i>Aplicar
                    </button>
                </div>
                <div class="col-auto ms-auto d-flex align-items-center">
                    <small class="text-muted"><?= number_format($totalGeneral) ?> pedidos en rango</small>
                </div>
            </form>
        </div>
    </div>

    <?php if ($totalGeneral === 0): ?>
    <div class="chart-card text-center py-5">
        <i class="bi bi-inbox" style="font-size:3rem;opacity:.25;display:block;margin-bottom:.75rem;color:#64748b"></i>
        <p class="fw-semibold text-muted mb-1">Sin datos en el rango seleccionado</p>
        <small class="text-muted">Ajusta el rango de fechas o los filtros.</small>
    </div>
    <?php else: ?>

    <!-- KPI Cards -->
    <?php
    $categorias = [
        ['key' => 'ENTREGADO',    'cls' => 'entregado',    'icon' => 'bi-check-circle-fill'],
        ['key' => 'EN PROCESO',   'cls' => 'en-proceso',   'icon' => 'bi-arrow-repeat'],
        ['key' => 'RECHAZADO',    'cls' => 'rechazado',    'icon' => 'bi-x-circle-fill'],
        ['key' => 'REPROGRAMADO', 'cls' => 'reprogramado', 'icon' => 'bi-calendar2-check-fill'],
    ];
    ?>
    <div class="kpi-grid">
        <?php foreach ($categorias as $cat):
            $cnt = $data[$cat['key']];
            $pct = $totalGeneral > 0 ? round($cnt / $totalGeneral * 100, 1) : 0;
        ?>
        <div class="kpi-card <?= $cat['cls'] ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-num"><?= number_format($cnt) ?></div>
                    <div class="kpi-pct"><?= $pct ?>%</div>
                    <div class="kpi-lbl"><?= $cat['key'] ?></div>
                </div>
                <i class="bi <?= $cat['icon'] ?>" style="font-size:2.5rem;opacity:.3"></i>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Gráfica + Tabla -->
    <div class="row g-3">
        <!-- Dona -->
        <div class="col-lg-5">
            <div class="chart-card h-100 d-flex flex-column">
                <div class="chart-title"><i class="bi bi-pie-chart me-2 text-primary"></i>Distribución de Estados</div>
                <div class="d-flex justify-content-center flex-grow-1 align-items-center" style="min-height:300px">
                    <canvas id="donaChart" style="max-width:320px;max-height:320px"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="col-lg-7">
            <div class="chart-card h-100">
                <div class="chart-title"><i class="bi bi-table me-2 text-primary"></i>Resumen por Estatus</div>
                <div class="table-responsive">
                    <table class="table tabla-estatus align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-center">Contador</th>
                                <th class="text-center">Porcentaje</th>
                                <th>Distribución</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($categorias as $cat):
                            $cnt = $data[$cat['key']];
                            $pct = $totalGeneral > 0 ? round($cnt / $totalGeneral * 100, 1) : 0;
                            if ($cat['key'] === 'ENTREGADO')    { $clsB = 'entregado'; }
                            elseif ($cat['key'] === 'EN PROCESO') { $clsB = 'proceso'; }
                            elseif ($cat['key'] === 'RECHAZADO')  { $clsB = 'rechazado'; }
                            else                                   { $clsB = 'reprogramado'; }
                            $progCls = 'prog-' . $clsB;
                        ?>
                        <tr>
                            <td>
                                <span class="badge-cat badge-<?= $clsB ?>"><?= $cat['key'] ?></span>
                            </td>
                            <td class="text-center fw-bold fs-5"><?= number_format($cnt) ?></td>
                            <td class="text-center fw-semibold"><?= $pct ?>%</td>
                            <td>
                                <div class="prog-bar-wrap">
                                    <div class="prog-bar-fill <?= $progCls ?>" style="width:<?= $pct ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Totales</td>
                                <td class="text-center"><?= number_format($totalGeneral) ?></td>
                                <td class="text-center">100%</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Gráfica Dona ──────────────────────────────────────────────────────────────
<?php if ($totalGeneral > 0): ?>
(function() {
    const ctx = document.getElementById('donaChart');
    if (!ctx) return;

    const labels = ['ENTREGADO', 'EN PROCESO', 'RECHAZADO', 'REPROGRAMADO'];
    const values = [<?= $data['ENTREGADO'] ?>, <?= $data['EN PROCESO'] ?>, <?= $data['RECHAZADO'] ?>, <?= $data['REPROGRAMADO'] ?>];
    const total  = values.reduce((a, b) => a + b, 0);
    const colors = ['#3cb043', '#f5e400', '#d42b2b', '#f97316'];

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 3,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 18,
                        font: { size: 12, weight: '600', family: 'Inter' },
                        usePointStyle: true,
                        pointStyleWidth: 10,
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                            return ` ${ctx.label}: ${ctx.parsed.toLocaleString()} (${pct}%)`;
                        }
                    }
                },
                // Plugin custom: texto central
            },
            animation: { animateRotate: true, duration: 800 }
        },
        plugins: [{
            id: 'centerText',
            afterDraw(chart) {
                const { ctx: c, chartArea: { left, top, right, bottom } } = chart;
                const cx = (left + right) / 2;
                const cy = (top + bottom) / 2;
                c.save();
                c.textAlign = 'center';
                c.textBaseline = 'middle';
                c.font = 'bold 2rem Inter, sans-serif';
                c.fillStyle = '#1e293b';
                c.fillText(total.toLocaleString(), cx, cy - 10);
                c.font = '600 .75rem Inter, sans-serif';
                c.fillStyle = '#64748b';
                c.fillText('TOTAL', cx, cy + 16);
                c.restore();
            }
        }]
    });
})();
<?php endif; ?>

// ── Enlace público ────────────────────────────────────────────────────────────
function toggleEnlacePublico(action) {
    const actionText = action === 'habilitar'
        ? 'Esto generará un enlace público compartible.'
        : 'Los enlaces previos dejarán de funcionar.';
    Swal.fire({
        title: action === 'habilitar' ? '¿Activar enlace público?' : '¿Revocar acceso?',
        text: actionText, icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: action === 'habilitar' ? '#3085d6' : '#d33',
        cancelButtonColor: '#adb5bd',
        confirmButtonText: 'Sí, ' + action,
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;
        Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData();
        fd.append('action', action);
        fetch('<?= RUTA_URL ?>ajax/enlaces_publicos.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: '¡Listo!', text: data.message,
                        showConfirmButton: false, timer: 1500 })
                        .then(() => window.location.reload());
                } else {
                    Swal.fire('Error', data.message || 'Error desconocido', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error en la conexión.', 'error'));
    });
}

function copiarEnlacePublico() {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('u', '<?= $currUserId ?>');
    urlParams.set('t', '<?= $tokenEnlacePublico ?>');
    urlParams.delete('export');
    const finalUrl = '<?= RUTA_URL ?>pedidos/informes/estatus?' + urlParams.toString();
    navigator.clipboard.writeText(finalUrl).then(() => {
        Swal.fire({ icon: 'success', title: 'Enlace copiado',
            text: 'Cualquier persona con este enlace podrá ver el informe con los filtros actuales.',
            confirmButtonText: 'Entendido' });
    }).catch(() => {
        Swal.fire({ icon: 'warning', title: 'Copia manual requerida',
            text: 'Copia este enlace:', input: 'text', inputValue: finalUrl, confirmButtonText: 'Cerrar' });
    });
}
</script>
</body>
</html>
