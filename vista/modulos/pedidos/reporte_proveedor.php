<?php
/**
 * Vista Standalone: Reporte de Pedidos por Proveedor
 * Ruta: GET /pedidos/reportes
 * Muestra historial dinámico de estados en pares de columnas: Estado N | Fecha N
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/conexion.php';

start_secure_session();

// ── Autenticación: sesión normal O token público ──────────────────────────────
$isPublicLink = isset($_GET['u']) && isset($_GET['t']);

if ($isPublicLink) {
    // Acceso por enlace público — validar token sin sesión
    $pubU = (int)$_GET['u'];
    $pubT = (string)$_GET['t'];

    $dbPub = (new Conexion())->conectar();
    $stmtPub = $dbPub->prepare("SELECT token_enlace_publico FROM usuarios WHERE id = :id");
    $stmtPub->execute([':id' => $pubU]);
    $dbToken = $stmtPub->fetchColumn();

    if (empty($dbToken) || !hash_equals($dbToken, $pubT)) {
        // Token inválido o revocado
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Enlace Inválido</title>
        <style>
            body{margin:0;display:flex;align-items:center;justify-content:center;height:100vh;background:#f8f9fa;font-family:system-ui,sans-serif}
            .card{background:#fff;padding:3rem 2.5rem;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);text-align:center;max-width:420px;width:90%;border-top:5px solid #dc3545}
            h1{font-size:1.4rem;color:#212529;font-weight:700;margin-bottom:.5rem}
            p{color:#6c757d;font-size:.95rem;line-height:1.5}
        </style></head><body>
        <div class="card">
            <div style="font-size:3rem;margin-bottom:1rem">🔗</div>
            <h1>Acceso Denegado</h1>
            <p>El enlace público ya no es válido o ha sido revocado por el propietario.</p>
        </div></body></html>';
        exit;
    }

    // Token válido: simular contexto de usuario
    // Determinar tipo por rol en BD
    $stmtRol = $dbPub->prepare("SELECT id_rol FROM usuarios_roles WHERE id_usuario = :id LIMIT 1");
    $stmtRol->execute([':id' => $pubU]);
    $pubRol  = (int)$stmtRol->fetchColumn();

    $isAdmin        = false;
    $isProveedorExt = ($pubRol == ROL_CLIENTE);   // rol 5 en BD → aparece en id_proveedor
    $isClienteExt   = ($pubRol == ROL_PROVEEDOR); // rol 4 en BD → aparece en id_cliente
    $currUserId     = $pubU;
    $db             = $dbPub;

} else {
    // Acceso normal con sesión
    require_login();
    $isAdmin        = isSuperAdmin();
    $currRol        = $_SESSION['rol'] ?? 0;
    $isProveedorExt = ($currRol == ROL_CLIENTE);   // rol 5 en BD ("Proveedor") → id_proveedor en pedidos
    $isClienteExt   = ($currRol == ROL_PROVEEDOR); // rol 4 en BD ("Cliente")   → id_cliente en pedidos
    $currUserId     = getCurrentUserId();
    $db             = (new Conexion())->conectar();
}

// ── Token enlace público del usuario ─────────────────────────────────────────
$stmtToken = $db->prepare("SELECT token_enlace_publico, nombre FROM usuarios WHERE id = :id");
$stmtToken->execute([':id' => $currUserId]);
$tokenRow           = $stmtToken->fetch(PDO::FETCH_ASSOC);
$tokenEnlacePublico = $tokenRow['token_enlace_publico'] ?? '';
$currUserNombre     = $tokenRow['nombre'] ?? ($_SESSION['nombre'] ?? '');

// ── Filtros ───────────────────────────────────────────────────────────────────
// Default: 3 meses atrás para que los usuarios vean datos históricos recientes
$fechaDesde    = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-3 months'));
$fechaHasta    = $_GET['fecha_hasta'] ?? date('Y-m-d');
$idEstado      = (int)($_GET['id_estado'] ?? 0);
$buscarOrden   = trim($_GET['numero_orden'] ?? '');   // ← nuevo: búsqueda por nº orden
// Scoping: forzar filtro al usuario actual si no es admin
$idProveedor   = (!$isAdmin && $isProveedorExt) ? $currUserId : (int)($_GET['id_proveedor'] ?? 0);
$idCliente     = (!$isAdmin && $isClienteExt)   ? $currUserId : 0;
$export        = isset($_GET['export']) && $_GET['export'] === '1';

// ── Paginación ────────────────────────────────────────────────────────────────
define('PER_PAGE', 50);
$pagina    = max(1, (int)($_GET['pagina'] ?? 1));
$offset    = ($pagina - 1) * PER_PAGE;

// ── Catálogo de estados para el filtro dropdown ───────────────────────────────
$estados = $db->query('SELECT id, nombre_estado FROM estados_pedidos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

// ── Catálogo de proveedores (solo para Admin) ─────────────────────────────────
$proveedores = [];
if ($isAdmin) {
    $proveedores = $db->query(
        "SELECT DISTINCT u.id, u.nombre
         FROM usuarios u
         INNER JOIN usuarios_roles ur ON ur.id_usuario = u.id
         INNER JOIN roles r ON r.id = ur.id_rol
         WHERE r.nombre_rol = 'Cliente' AND u.activo = 1
         ORDER BY u.nombre ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

// ── Query 1: Datos principales de los pedidos ─────────────────────────────────
$where  = ['p.fecha_ingreso BETWEEN :desde AND :hasta'];
$params = [
    ':desde' => $fechaDesde,
    ':hasta' => $fechaHasta,
];

if ($isAdmin) {
    // Admin puede filtrar por cualquier proveedor (id_proveedor)
    if ($idProveedor > 0) {
        $where[]                 = 'p.id_proveedor = :id_proveedor';
        $params[':id_proveedor'] = $idProveedor;
    }
} elseif ($isProveedorExt) {
    // Usuario "Proveedor" (rol 5) → sus pedidos en id_proveedor
    $where[]                 = 'p.id_proveedor = :id_proveedor';
    $params[':id_proveedor'] = $currUserId;
} elseif ($isClienteExt) {
    // Usuario "Cliente" (rol 4) → sus pedidos en id_cliente
    $where[]             = 'p.id_cliente = :id_cliente';
    $params[':id_cliente'] = $currUserId;
} else {
    // Sin rol reconocido: no mostrar nada
    $where[] = '1 = 0';
}

if ($idEstado > 0) {
    $where[]              = 'p.id_estado = :id_estado';
    $params[':id_estado'] = $idEstado;
}

// Filtro por número de orden (búsqueda parcial)
if ($buscarOrden !== '') {
    $where[]               = 'p.numero_orden LIKE :numero_orden';
    $params[':numero_orden'] = '%' . $buscarOrden . '%';
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

// ── Count total para paginación ───────────────────────────────────────────────
$sqlCount  = "SELECT COUNT(*) FROM pedidos p {$whereStr}";
$stmtCount = $db->prepare($sqlCount);
foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
$stmtCount->execute();
$totalRegistros = (int)$stmtCount->fetchColumn();
$totalPaginas   = max(1, (int)ceil($totalRegistros / PER_PAGE));
// Corregir página fuera de rango
if ($pagina > $totalPaginas) $pagina = $totalPaginas;
$offset = ($pagina - 1) * PER_PAGE;

// ── Query principal paginada ──────────────────────────────────────────────────
$sqlPedidos = "
    SELECT
        p.id,
        p.numero_orden,
        p.fecha_ingreso,
        p.destinatario,
        p.telefono,
        p.direccion,
        p.zona,
        p.comentario,
        p.courier_service,
        ep.nombre_estado  AS estado_actual,
        p.precio_total_local,
        m.nombre          AS moneda,
        p.created_at      AS fecha_creado
    FROM pedidos p
    LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
    LEFT JOIN monedas m          ON m.id  = p.id_moneda
    {$whereStr}
    ORDER BY p.fecha_ingreso DESC
    LIMIT :limit OFFSET :offset
";

$stmtPed = $db->prepare($sqlPedidos);
foreach ($params as $k => $v) $stmtPed->bindValue($k, $v);
$stmtPed->bindValue(':limit',  PER_PAGE, PDO::PARAM_INT);
$stmtPed->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmtPed->execute();
$pedidos = $stmtPed->fetchAll(PDO::FETCH_ASSOC);

// ── Para el export Excel: traer TODOS los registros sin paginación ─────────────
// (solo se ejecuta cuando $export === true, ver bloque más abajo)
$pedidosExport = [];

// ── Query 2: Historial completo de esos pedidos ───────────────────────────────
$historialPorPedido = [];
$maxTransiciones    = 0;

if (!empty($pedidos)) {
    $pedidoIds    = array_column($pedidos, 'id');
    $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));

    $sqlHist = "
        SELECT
            h.id_pedido,
            ep_new.nombre_estado AS estado_nuevo,
            h.created_at         AS fecha_cambio,
            h.observaciones      AS motivo
        FROM pedidos_historial_estados h
        LEFT JOIN estados_pedidos ep_new ON ep_new.id = h.id_estado_nuevo
        WHERE h.id_pedido IN ({$placeholders})
        ORDER BY h.id_pedido ASC, h.created_at ASC
    ";
    $stmtH = $db->prepare($sqlHist);
    $stmtH->execute($pedidoIds);

    foreach ($stmtH->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $historialPorPedido[$h['id_pedido']][] = [
            'estado' => $h['estado_nuevo'],
            'fecha'  => $h['fecha_cambio'],
            'motivo' => $h['motivo'] ?? '',
        ];
    }

    foreach ($historialPorPedido as $hist) {
        $maxTransiciones = max($maxTransiciones, count($hist));
    }
}

// ── Mapa de colores por nombre de estado ──────────────────────────────────────
function colorEstado(string $estado): array {
    $e = mb_strtolower($estado);
    if (str_contains($e, 'entregado'))    return ['bg' => '#C8E6C9', 'text' => '#1B5E20'];
    if (str_contains($e, 'devuelto'))     return ['bg' => '#FFCDD2', 'text' => '#B71C1C'];
    if (str_contains($e, 'rechazado'))    return ['bg' => '#FFCDD2', 'text' => '#B71C1C'];
    if (str_contains($e, 'reprogramado')) return ['bg' => '#FFE0B2', 'text' => '#E65100'];
    if (str_contains($e, 'cancelado'))    return ['bg' => '#EEEEEE', 'text' => '#424242'];
    if (str_contains($e, 'ruta'))         return ['bg' => '#BBDEFB', 'text' => '#0D47A1'];
    if (str_contains($e, 'bodega'))       return ['bg' => '#E8EAF6', 'text' => '#283593'];
    return ['bg' => '#F5F5F5', 'text' => '#333333'];
}

// ── Excel export ──────────────────────────────────────────────────────────────
if ($export) {
    // Traer todos los registros para el Excel (sin LIMIT)
    $sqlExport = "
        SELECT
            p.id,
            p.numero_orden,
            p.fecha_ingreso,
            p.destinatario,
            p.telefono,
            p.direccion,
            p.zona,
            p.comentario,
            p.courier_service,
            ep.nombre_estado  AS estado_actual,
            p.precio_total_local,
            m.nombre          AS moneda,
            p.created_at      AS fecha_creado
        FROM pedidos p
        LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
        LEFT JOIN monedas m          ON m.id  = p.id_moneda
        {$whereStr}
        ORDER BY p.fecha_ingreso DESC
    ";
    $stmtExp = $db->prepare($sqlExport);
    foreach ($params as $k => $v) $stmtExp->bindValue($k, $v);
    $stmtExp->execute();
    $pedidosExport = $stmtExp->fetchAll(PDO::FETCH_ASSOC);
}

if ($export && !empty($pedidosExport)) {
    require_once __DIR__ . '/../../../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Reporte Pedidos');

    // Helper: convierte índice numérico (1-based) + fila a coordenada "A1"
    $coord = fn(int $col, int $row): string =>
        \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;

    // Cabeceras fijas
    $headersFixed = [
        'Núm. Orden', 'Fecha Ingreso', 'Destinatario', 'Teléfono',
        'Dirección', 'Zona', 'Comentario', 'Courier Service', 'Estado Actual',
        'Liquidación', 'Moneda', 'Fecha Creado',
    ];

    // Fila 1: Título (se define aquí, las celdas se configuran después de calcular el historial completo)
    $titulo = 'REPORTE DE PEDIDOS  ' . date('d/m/Y', strtotime($fechaDesde)) . ' → ' . date('d/m/Y', strtotime($fechaHasta));
    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 13],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '1A3A4A']],
        'alignment' => ['horizontal' => 'center'],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(22);

    // Para el export, recalcular historial usando pedidosExport
    $historialExport = [];
    $maxTransicionesExport = 0;
    if (!empty($pedidosExport)) {
        $expIds = array_column($pedidosExport, 'id');
        $expPlaceholders = implode(',', array_fill(0, count($expIds), '?'));
        $sqlHistExp = "
            SELECT h.id_pedido, ep_new.nombre_estado AS estado_nuevo, h.created_at AS fecha_cambio,
                   h.observaciones AS motivo
            FROM pedidos_historial_estados h
            LEFT JOIN estados_pedidos ep_new ON ep_new.id = h.id_estado_nuevo
            WHERE h.id_pedido IN ({$expPlaceholders})
            ORDER BY h.id_pedido ASC, h.created_at ASC
        ";
        $stmtHExp = $db->prepare($sqlHistExp);
        $stmtHExp->execute($expIds);
        foreach ($stmtHExp->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $historialExport[$h['id_pedido']][] = ['estado' => $h['estado_nuevo'], 'fecha' => $h['fecha_cambio'], 'motivo' => $h['motivo'] ?? ''];
        }
        foreach ($historialExport as $hist) {
            $maxTransicionesExport = max($maxTransicionesExport, count($hist));
        }
    }
    // Reconstruir encabezados con el máximo correcto del export
    $colIdx = 1;
    foreach ($headersFixed as $h) {
        $sheet->setCellValue($coord($colIdx++, 2), $h);
    }
    for ($n = 1; $n <= $maxTransicionesExport; $n++) {
        $sheet->setCellValue($coord($colIdx++, 2), "Estado {$n}");
        $sheet->setCellValue($coord($colIdx++, 2), "Fecha {$n}");
        $sheet->setCellValue($coord($colIdx++, 2), "Motivo {$n}");
    }
    $totalCols  = $colIdx - 1;
    $lastColLtr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
    $sheet->mergeCells("A1:{$lastColLtr}1");
    $sheet->setCellValue('A1', $titulo);

    // Estilo cabecera fila 2
    $sheet->getStyle("A2:{$lastColLtr}2")->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '2D6A4F']],
        'alignment' => ['horizontal' => 'center', 'wrapText' => true],
    ]);


    $excelRow = 3;
    foreach ($pedidosExport as $ped) {
        $hist = $historialExport[$ped['id']] ?? [];
        $c = 1;
        $sheet->setCellValue($coord($c++, $excelRow), $ped['numero_orden']);
        $sheet->setCellValue($coord($c++, $excelRow), $ped['fecha_ingreso']);
        $sheet->setCellValue($coord($c++, $excelRow), $ped['destinatario']);
        $sheet->setCellValue($coord($c++, $excelRow), $ped['telefono']);
        $sheet->setCellValue($coord($c++, $excelRow), $ped['direccion']);
        $sheet->setCellValue($coord($c++, $excelRow), $ped['zona']);
        $sheet->setCellValue($coord($c++, $excelRow), $ped['comentario']);
        $sheet->setCellValue($coord($c++, $excelRow), $ped['courier_service'] ?? '');

        // Estado actual con color
        $cellEstado = $coord($c, $excelRow);
        $sheet->setCellValue($cellEstado, $ped['estado_actual']);
        $clr = colorEstado($ped['estado_actual'] ?? '');
        $sheet->getStyle($cellEstado)->applyFromArray([
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => ltrim($clr['bg'], '#')]],
            'font' => ['color' => ['rgb' => ltrim($clr['text'], '#')], 'bold' => true],
        ]);
        $c++;

        $sheet->setCellValue($coord($c++, $excelRow), $ped['precio_total_local']);
        $sheet->setCellValue($coord($c++, $excelRow), $ped['moneda']);
        $sheet->setCellValue($coord($c++, $excelRow),
            $ped['fecha_creado'] ? date('d/m/Y H:i', strtotime($ped['fecha_creado'])) : '');

        // Pares dinámicos del historial
        for ($n = 0; $n < $maxTransicionesExport; $n++) {
            $entry  = $hist[$n] ?? null;
            $estado = $entry['estado'] ?? '';
            $fecha  = $entry ? date('d/m/Y H:i', strtotime($entry['fecha'])) : '';
            $motivo = $entry['motivo'] ?? '';

            $cellH = $coord($c, $excelRow);
            $sheet->setCellValue($cellH, $estado);
            if ($estado !== '') {
                $clrH = colorEstado($estado);
                $sheet->getStyle($cellH)->applyFromArray([
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => ltrim($clrH['bg'], '#')]],
                    'font' => ['color' => ['rgb' => ltrim($clrH['text'], '#')]],
                ]);
            }
            $c++;
            $sheet->setCellValue($coord($c++, $excelRow), $fecha);
            $sheet->setCellValue($coord($c++, $excelRow), $motivo);
        }
        $excelRow++;
    }

    // Auto-width en columnas fijas
    for ($ci = 1; $ci <= 11; $ci++) {
        $sheet->getColumnDimension(
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci)
        )->setAutoSize(true);
    }

    $filename = 'Reporte_Pedidos_' . date('Ymd', strtotime($fechaDesde)) . '_' . date('Ymd', strtotime($fechaHasta)) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
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
    <title>Reporte de Pedidos - RutaEx</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6f9; }

        .rpt-header {
            background: linear-gradient(135deg, #1a3a4a 0%, #2d6a4f 100%);
            color: #fff;
            padding: 1.5rem 2rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(29,106,79,.25);
        }
        .filter-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }

        /* Tabla */
        .tabla-reporte { font-size: .78rem; white-space: nowrap; border-collapse: separate; border-spacing: 0; }
        .tabla-reporte thead th {
            background: #1a3a4a;
            color: #fff;
            padding: .45rem .7rem;
            font-weight: 700;
            text-align: center;
            border: 1px solid rgba(255,255,255,.12);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .tabla-reporte thead th.th-hist {
            background: #2d6a4f;
        }
        .tabla-reporte tbody td {
            padding: .35rem .65rem;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }
        .tabla-reporte tbody tr:hover td { background-color: #f0f7ff !important; }
        .tabla-wrapper {
            overflow-x: auto;
            max-height: 72vh;
            overflow-y: auto;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }

        /* Badges de estado */
        .badge-estado {
            display: inline-block;
            padding: .28em .65em;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
            white-space: nowrap;
        }

        /* Separador visual entre pares */
        .td-estado-hist { border-left: 2px solid #c8d6df !important; }

        /* Sin resultados */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        .empty-state i { font-size: 3rem; opacity: .35; display: block; margin-bottom: .75rem; }
    </style>
</head>
<?php if (!$isPublicLink): ?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<body>
<?php else: ?>
<body class="bg-light">
<?php endif; ?>

<div class="container-fluid py-4">

    <!-- Cabecera -->
    <div class="rpt-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>Reporte de Pedidos
            </h4>
            <small class="opacity-75">
                Historial dinámico de estados por orden · <?= htmlspecialchars($fechaDesde) ?> → <?= htmlspecialchars($fechaHasta) ?>
            </small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (!$isPublicLink): ?>
            <!-- Enlace Público -->
            <?php if (!empty($tokenEnlacePublico)): ?>
            <div class="dropdown">
                <button class="btn btn-warning btn-sm fw-bold shadow-sm dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-share fs-6"></i> Opciones de Enlace
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
                    onclick="toggleEnlacePublico('habilitar')"
                    title="Habilita un enlace público permanente para compartir este reporte">
                <i class="bi bi-link-45deg fs-6"></i> Habilitar Enlace Público
            </button>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($pedidos)): ?>
            <a href="<?= RUTA_URL ?>pedidos/reportes?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>"
               class="btn btn-success btn-sm fw-bold shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </a>
            <?php endif; ?>
            <?php if (!$isPublicLink): ?>
            <a href="<?= RUTA_URL ?>pedidos/listar" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Pedidos
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card filter-card mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= RUTA_URL ?>pedidos/reportes" class="row g-2 align-items-end" id="form-filtros">

                <?php if ($isPublicLink): ?>
                <input type="hidden" name="u" value="<?= htmlspecialchars($pubU) ?>">
                <input type="hidden" name="t" value="<?= htmlspecialchars($pubT) ?>">
                <?php endif; ?>

                <!-- Búsqueda por número de orden -->
                <div class="col-md-2 col-12">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-search"></i> Núm. de Orden
                    </label>
                    <input type="text" name="numero_orden" class="form-control form-control-sm"
                           placeholder="Buscar…"
                           value="<?= htmlspecialchars($buscarOrden) ?>">
                </div>

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
                        <i class="bi bi-funnel"></i> Estado
                    </label>
                    <select name="id_estado" class="form-select form-select-sm">
                        <option value="0">Todos los estados</option>
                        <?php foreach ($estados as $est): ?>
                        <option value="<?= $est['id'] ?>" <?= $idEstado == $est['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($est['nombre_estado']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($isAdmin): ?>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-person-badge"></i> Proveedor
                    </label>
                    <select name="id_proveedor" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php foreach ($proveedores as $prov): ?>
                        <option value="<?= $prov['id'] ?>" <?= $idProveedor == $prov['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prov['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php elseif ($isProveedorExt || $isClienteExt): ?>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-person-badge"></i> Proveedor
                    </label>
                    <input type="text" class="form-control form-control-sm bg-light"
                           value="<?= htmlspecialchars($currUserNombre) ?>" readonly>
                    <input type="hidden" name="id_proveedor" value="<?= $currUserId ?>">
                </div>
                <?php endif; ?>

                <div class="col-md-1 col-6">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                        <i class="bi bi-search me-1"></i>Filtrar
                    </button>
                </div>

                <div class="col-auto ms-auto">
                    <small class="text-muted">
                        <?= number_format($totalRegistros) ?> pedido<?= $totalRegistros != 1 ? 's' : '' ?>
                        <?php if ($maxTransiciones > 0): ?>
                        · <?= $maxTransiciones ?> transiciones máx.
                        <?php endif; ?>
                        <?php if ($totalPaginas > 1): ?>
                        · pág. <?= $pagina ?>/<?= $totalPaginas ?>
                        <?php endif; ?>
                    </small>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <?php if (empty($pedidos)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body empty-state">
            <i class="bi bi-inbox"></i>
            <p class="fw-semibold mb-1">Sin resultados</p>
            <small class="text-muted">Ajusta el rango de fechas o los filtros y vuelve a intentarlo.</small>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="tabla-wrapper">
                <table class="tabla-reporte table table-bordered mb-0">
                    <thead>
                        <tr>
                            <!-- Columnas fijas -->
                            <th>Núm. Orden</th>
                            <th>Fecha Ingreso</th>
                            <th>Destinatario</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Zona</th>
                            <th>Comentario</th>
                            <th>Courier Service</th>
                            <th>Estado Actual</th>
                            <th>Liquidación</th>
                            <th>Moneda</th>
                            <th>Fecha Creado</th>
                            <!-- Columnas dinámicas del historial -->
                            <?php for ($n = 1; $n <= $maxTransiciones; $n++): ?>
                            <th class="th-hist" colspan="3">Cambio <?= $n ?></th>
                            <?php endfor; ?>
                        </tr>
                        <?php if ($maxTransiciones > 0): ?>
                        <tr>
                            <th colspan="12"></th>
                            <?php for ($n = 1; $n <= $maxTransiciones; $n++): ?>
                            <th class="th-hist" style="font-size:.7rem;font-weight:600;">Estado</th>
                            <th class="th-hist" style="font-size:.7rem;font-weight:600;">Fecha / Hora</th>
                            <th class="th-hist" style="font-size:.7rem;font-weight:600;">Motivo</th>
                            <?php endfor; ?>
                        </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                    <?php foreach ($pedidos as $ped):
                        $hist = $historialPorPedido[$ped['id']] ?? [];
                        $clrActual = colorEstado($ped['estado_actual'] ?? '');
                    ?>
                        <tr>
                            <td class="fw-semibold text-primary"><?= htmlspecialchars($ped['numero_orden']) ?></td>
                            <td><?= htmlspecialchars($ped['fecha_ingreso']) ?></td>
                            <td><?= htmlspecialchars($ped['destinatario'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($ped['telefono'] ?? '—') ?></td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;"
                                title="<?= htmlspecialchars($ped['direccion'] ?? '') ?>">
                                <?= htmlspecialchars($ped['direccion'] ?? '—') ?>
                            </td>
                            <td><?= htmlspecialchars($ped['zona'] ?? '—') ?></td>
                            <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;"
                                title="<?= htmlspecialchars($ped['comentario'] ?? '') ?>">
                                <?= htmlspecialchars($ped['comentario'] ?? '—') ?>
                            </td>
                            <td>
                                <?php if (!empty($ped['courier_service'])): ?>
                                    <span class="badge" style="background:#0dcaf0;color:#000;"><?= htmlspecialchars($ped['courier_service']) ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge-estado"
                                      style="background:<?= $clrActual['bg'] ?>;color:<?= $clrActual['text'] ?>;">
                                    <?= htmlspecialchars($ped['estado_actual'] ?? '—') ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?= $ped['precio_total_local'] !== null
                                    ? number_format((float)$ped['precio_total_local'], 2)
                                    : '—' ?>
                            </td>
                            <td><?= htmlspecialchars($ped['moneda'] ?? '—') ?></td>
                            <td><?= $ped['fecha_creado'] ? date('d/m/Y H:i', strtotime($ped['fecha_creado'])) : '—' ?></td>

                            <!-- Pares dinámicos del historial -->
                            <?php for ($n = 0; $n < $maxTransiciones; $n++):
                                $entry  = $hist[$n] ?? null;
                                $estado = $entry['estado'] ?? '';
                                $fecha  = $entry ? date('d/m/Y H:i', strtotime($entry['fecha'])) : '';
                                $motivo = $entry['motivo'] ?? '';
                                $clrH   = $estado ? colorEstado($estado) : ['bg' => 'transparent', 'text' => '#888'];
                            ?>
                            <td class="td-estado-hist text-center">
                                <?php if ($estado): ?>
                                <span class="badge-estado"
                                      style="background:<?= $clrH['bg'] ?>;color:<?= $clrH['text'] ?>;">
                                    <?= htmlspecialchars($estado) ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-muted" style="font-size:.74rem;">
                                <?= $fecha ?: '—' ?>
                            </td>
                            <td class="text-muted" style="font-size:.74rem; max-width:160px; overflow:hidden; text-overflow:ellipsis;"
                                title="<?= htmlspecialchars($motivo) ?>">
                                <?= $motivo ? htmlspecialchars($motivo) : '<span class="text-muted">—</span>' ?>
                            </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($totalPaginas > 1): ?>
    <!-- ── Paginación ── -->
    <?php
        // Construir base de parámetros sin la página para generar los enlaces
        $qBase = array_filter($_GET, fn($k) => $k !== 'pagina', ARRAY_FILTER_USE_KEY);
    ?>
    <nav class="mt-3 d-flex justify-content-center align-items-center gap-2" aria-label="Paginación">
        <!-- Anterior -->
        <?php if ($pagina > 1): ?>
        <a href="<?= RUTA_URL ?>pedidos/reportes?<?= http_build_query(array_merge($qBase, ['pagina' => $pagina - 1])) ?>"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-chevron-left"></i> Anterior
        </a>
        <?php else: ?>
        <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i> Anterior</button>
        <?php endif; ?>

        <!-- Páginas numeradas (ventana de ±3) -->
        <?php
            $ventanaInicio = max(1, $pagina - 3);
            $ventanaFin    = min($totalPaginas, $pagina + 3);
        ?>
        <?php if ($ventanaInicio > 1): ?>
        <a href="<?= RUTA_URL ?>pedidos/reportes?<?= http_build_query(array_merge($qBase, ['pagina' => 1])) ?>"
           class="btn btn-sm btn-outline-secondary">1</a>
        <?php if ($ventanaInicio > 2): ?><span class="px-1 text-muted">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $ventanaInicio; $p <= $ventanaFin; $p++): ?>
        <?php if ($p === $pagina): ?>
        <button class="btn btn-sm btn-primary" disabled><?= $p ?></button>
        <?php else: ?>
        <a href="<?= RUTA_URL ?>pedidos/reportes?<?= http_build_query(array_merge($qBase, ['pagina' => $p])) ?>"
           class="btn btn-sm btn-outline-secondary"><?= $p ?></a>
        <?php endif; ?>
        <?php endfor; ?>

        <?php if ($ventanaFin < $totalPaginas): ?>
        <?php if ($ventanaFin < $totalPaginas - 1): ?><span class="px-1 text-muted">…</span><?php endif; ?>
        <a href="<?= RUTA_URL ?>pedidos/reportes?<?= http_build_query(array_merge($qBase, ['pagina' => $totalPaginas])) ?>"
           class="btn btn-sm btn-outline-secondary"><?= $totalPaginas ?></a>
        <?php endif; ?>

        <!-- Siguiente -->
        <?php if ($pagina < $totalPaginas): ?>
        <a href="<?= RUTA_URL ?>pedidos/reportes?<?= http_build_query(array_merge($qBase, ['pagina' => $pagina + 1])) ?>"
           class="btn btn-sm btn-outline-secondary">
            Siguiente <i class="bi bi-chevron-right"></i>
        </a>
        <?php else: ?>
        <button class="btn btn-sm btn-outline-secondary" disabled>Siguiente <i class="bi bi-chevron-right"></i></button>
        <?php endif; ?>
    </nav>
    <p class="text-center text-muted small mt-1">
        Mostrando <?= number_format(($pagina - 1) * PER_PAGE + 1) ?>–<?= number_format(min($pagina * PER_PAGE, $totalRegistros)) ?>
        de <?= number_format($totalRegistros) ?> registros
    </p>
    <?php endif; ?>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
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
            fetch('<?= RUTA_URL ?>ajax/enlaces_publicos.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: '¡Operación exitosa!', text: data.message,
                            showConfirmButton: false, timer: 1500 })
                            .then(() => window.location.reload());
                    } else {
                        Swal.fire('Error', data.message || 'Error desconocido', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Ocurrió un error en la conexión.', 'error'));
        }
    });
}

function copiarEnlacePublico() {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('u', '<?= $currUserId ?>');
    urlParams.set('t', '<?= $tokenEnlacePublico ?>');
    urlParams.delete('export');
    const finalUrl = '<?= RUTA_URL ?>pedidos/reportes?' + urlParams.toString();

    navigator.clipboard.writeText(finalUrl).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Enlace copiado',
            text: 'Cualquier persona con este enlace podrá ver el reporte con los filtros actuales.',
            confirmButtonText: 'Entendido'
        });
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = finalUrl;
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        try {
            document.execCommand('copy');
            Swal.fire({ icon: 'success', title: 'Enlace copiado', timer: 2000, showConfirmButton: false });
        } catch(e) {
            Swal.fire({ icon: 'warning', title: 'Copia manual requerida',
                text: 'Este es tu enlace:', input: 'text', inputValue: finalUrl, confirmButtonText: 'Cerrar' });
        }
        document.body.removeChild(ta);
    });
}
</script>
</body>
</html>
