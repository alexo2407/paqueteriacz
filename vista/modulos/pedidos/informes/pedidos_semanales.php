<?php
ob_start();
/**
 * Informe: Cantidad de Pedidos Semanales por Coordinador y Cliente/País
 * Ruta: GET /pedidos/informes/pedidos_semanales
 */

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../utils/session.php';
require_once __DIR__ . '/../../../../utils/permissions.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';

start_secure_session();
require_login();
require_role(['Administrador']);

$db = (new Conexion())->conectar();

// ── Garantizar que la tabla de configuración exista ───────────────────────────
$db->exec("
    CREATE TABLE IF NOT EXISTS pedidos_coordinadores_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coordinador VARCHAR(100) NOT NULL,
        etiqueta VARCHAR(150) NOT NULL,
        id_cliente INT NULL,
        id_proveedor INT NULL,
        id_pais INT NULL,
        grupo INT DEFAULT 1,
        orden INT DEFAULT 0,
        activo TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_cliente (id_cliente),
        INDEX idx_pais (id_pais),
        INDEX idx_grupo (grupo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Pre-cargar si está vacía
$cntConfig = (int)$db->query("SELECT COUNT(*) FROM pedidos_coordinadores_config")->fetchColumn();
if ($cntConfig === 0) {
    $seed = [
        ['Aldo', 'LogisPro/ Verde Street CR', 16, 22, 2, 1, 1],
        ['Aldo', 'Verde Street CR', 16, NULL, 2, 1, 2],
        ['Aldo', 'Econ Global Argentina', NULL, NULL, NULL, 1, 3],
        ['David', 'Nutra Trade CR', 37, 38, 2, 1, 4],
        ['David', 'Econ Global Panamá', 21, 26, 8, 1, 5],
        ['Juan', 'Econ Global GT', 10, 39, 6, 1, 6],
        ['Juan', 'Nutra Trade GT', 9, 12, 6, 1, 7],
        ['Juan', 'LogisPro/ Verde Street GT', 15, 24, 6, 1, 8],
        ['Fernanda', 'LogisPro/ Verde Street Nic', 57, 58, 1, 1, 9],
        ['Eliel', 'Econ Global MX', 31, 32, 11, 1, 10],
        ['Eliel', 'LogisPro/ Verde Street Ecu', 59, 60, 13, 1, 11],
        ['Juan/ Dov', 'Econ Global CR', 4, NULL, 2, 2, 12],
        ['Roberto', 'Nutra Trade CR (Pulox)', 55, 56, 2, 2, 13],
        ['Roberto', 'Econ Global Uruguay', 52, 53, 9, 2, 14],
    ];
    $stSeed = $db->prepare("INSERT INTO pedidos_coordinadores_config (coordinador, etiqueta, id_cliente, id_proveedor, id_pais, grupo, orden) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($seed as $s) $stSeed->execute($s);
}

// ── Manejo de acciones AJAX / POST de configuración ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'guardar') {
        $id          = (int)($_POST['id'] ?? 0);
        $coordinador = trim($_POST['coordinador'] ?? '');
        $etiqueta    = trim($_POST['etiqueta'] ?? '');
        $idCliente   = !empty($_POST['id_cliente']) ? (int)$_POST['id_cliente'] : null;
        $idPais      = !empty($_POST['id_pais']) ? (int)$_POST['id_pais'] : null;
        $grupo       = max(1, (int)($_POST['grupo'] ?? 1));
        $orden       = (int)($_POST['orden'] ?? 0);

        if (!$coordinador || !$etiqueta) {
            echo json_encode(['ok' => false, 'msg' => 'Coordinador y Etiqueta son obligatorios']);
            exit;
        }

        if ($id > 0) {
            $stmt = $db->prepare("
                UPDATE pedidos_coordinadores_config 
                SET coordinador = :coord, etiqueta = :etiq, id_cliente = :cli, id_pais = :pais, grupo = :grp, orden = :ord 
                WHERE id = :id
            ");
            $stmt->execute([':coord' => $coordinador, ':etiq' => $etiqueta, ':cli' => $idCliente, ':pais' => $idPais, ':grp' => $grupo, ':ord' => $orden, ':id' => $id]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO pedidos_coordinadores_config (coordinador, etiqueta, id_cliente, id_pais, grupo, orden) 
                VALUES (:coord, :etiq, :cli, :pais, :grp, :ord)
            ");
            $stmt->execute([':coord' => $coordinador, ':etiq' => $etiqueta, ':cli' => $idCliente, ':pais' => $idPais, ':grp' => $grupo, ':ord' => $orden]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM pedidos_coordinadores_config WHERE id = :id")->execute([':id' => $id]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }
}

// ── Rango de Fechas y Cálculo Dinámico de Semanas ISO (Lunes a Domingo) ───────
$hoy = date('Y-m-d');
// Por defecto: últimas 4 semanas completas hasta hoy
$defaultHasta = $hoy;
$defaultDesde = date('Y-m-d', strtotime('-3 weeks monday this week', strtotime($defaultHasta)));

$fechaDesde = !empty($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : $defaultDesde;
$fechaHasta = !empty($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : $defaultHasta;

// Validar que las fechas sean coherentes
if ($fechaDesde > $fechaHasta) {
    $tmp = $fechaDesde;
    $fechaDesde = $fechaHasta;
    $fechaHasta = $tmp;
}

// Construir la lista de semanas ISO en el rango (Lunes a Domingo)
$dtInicio = new DateTime($fechaDesde);
$dtFin    = new DateTime($fechaHasta);

$dtCursor = clone $dtInicio;
$dtCursor->modify('monday this week');

$semanasInfo = [];
while ($dtCursor <= $dtFin) {
    $semNum  = (int)$dtCursor->format('W');
    $anioSem = (int)$dtCursor->format('o');
    $key     = $anioSem * 100 + $semNum; // Ej: 202636

    $lunes   = clone $dtCursor;
    $domingo = clone $dtCursor;
    $domingo->modify('+6 days');

    $semanasInfo[$key] = [
        'key'     => $key,
        'semana'  => $semNum,
        'anio'    => $anioSem,
        'label'   => "Semana $semNum",
        'rango'   => $lunes->format('d M') . ' – ' . $domingo->format('d M'),
        'lunes'   => $lunes->format('Y-m-d'),
        'domingo' => $domingo->format('Y-m-d'),
    ];
    $dtCursor->modify('+1 week');
}

// Rango de consulta SQL ampliado a los límites exactos de las semanas involucradas
$minFechaSQL = reset($semanasInfo)['lunes'] . ' 00:00:00';
$maxFechaSQL = end($semanasInfo)['domingo'] . ' 23:59:59';

$export = isset($_GET['export']) && $_GET['export'] === '1';

// ── Consultar configuración activa de coordinadores ───────────────────────────
$configs = $db->query("
    SELECT c.*, p.nombre as pais_nombre, u.nombre as cliente_nombre
    FROM pedidos_coordinadores_config c
    LEFT JOIN paises p ON p.id = c.id_pais
    LEFT JOIN usuarios u ON u.id = c.id_cliente
    WHERE c.activo = 1
    ORDER BY c.grupo ASC, c.orden ASC, c.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Catálogos para el modal de configuración ──────────────────────────────────
$clientesCat = $db->query("
    SELECT u.id, u.nombre 
    FROM usuarios u 
    INNER JOIN usuarios_roles ur ON ur.id_usuario = u.id 
    WHERE ur.id_rol IN (" . ROL_CLIENTE . ", " . ROL_PROVEEDOR . ") AND u.activo = 1 
    GROUP BY u.id 
    ORDER BY u.nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

$paisesCat = $db->query("SELECT id, nombre, codigo_iso FROM paises ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// ── Obtener datos agrupados de pedidos (con COALESCE para país) ───────────────
$sqlAgg = "
    SELECT 
        YEARWEEK(pe.fecha_ingreso, 3) AS anio_semana,
        WEEK(pe.fecha_ingreso, 3) AS semana,
        pe.id_cliente,
        pe.id_proveedor,
        COALESCE(pe.id_pais, u.id_pais, uprov.id_pais) AS id_pais,
        COUNT(*) AS cantidad
    FROM pedidos pe
    LEFT JOIN usuarios u ON u.id = pe.id_cliente
    LEFT JOIN usuarios uprov ON uprov.id = pe.id_proveedor
    WHERE pe.fecha_ingreso BETWEEN :desde AND :hasta
    GROUP BY anio_semana, semana, pe.id_cliente, pe.id_proveedor, COALESCE(pe.id_pais, u.id_pais, uprov.id_pais)
";
$stmtAgg = $db->prepare($sqlAgg);
$stmtAgg->execute([':desde' => $minFechaSQL, ':hasta' => $maxFechaSQL]);
$rawAgg = $stmtAgg->fetchAll(PDO::FETCH_ASSOC);

// Indexar por clave de semana (YYYYWW)
$pedidosPorSemana = [];
foreach ($rawAgg as $row) {
    $semKey = (int)$row['anio_semana'];
    $pedidosPorSemana[$semKey][] = $row;
}

// ── Función para computar cantidad de una configuración en una semana ────────
function calcularCantidad($cfg, $filasSemana) {
    if (empty($filasSemana)) return 0;
    // Si la fila no tiene cliente, proveedor ni país asignado, no sumar
    if (empty($cfg['id_cliente']) && empty($cfg['id_proveedor']) && empty($cfg['id_pais'])) {
        return 0;
    }

    $total = 0;
    foreach ($filasSemana as $r) {
        $matchCliente = false;
        $matchPais    = false;

        // Validación de Cliente / Proveedor
        if (!empty($cfg['id_cliente'])) {
            if ($r['id_cliente'] == $cfg['id_cliente'] || $r['id_proveedor'] == $cfg['id_cliente']) {
                $matchCliente = true;
            }
        } elseif (!empty($cfg['id_proveedor'])) {
            if ($r['id_proveedor'] == $cfg['id_proveedor'] || $r['id_cliente'] == $cfg['id_proveedor']) {
                $matchCliente = true;
            }
        } else {
            // Cuenta comodín general (ej. solo por país)
            $matchCliente = true;
        }

        // Validación de País
        if (!empty($cfg['id_pais'])) {
            if ($r['id_pais'] == $cfg['id_pais'] || ($r['id_pais'] === null && (!empty($cfg['id_cliente']) || !empty($cfg['id_proveedor'])))) {
                $matchPais = true;
            }
        } else {
            $matchPais = true;
        }

        if ($matchCliente && $matchPais) {
            $total += (int)$r['cantidad'];
        }
    }
    return $total;
}

// ── Procesar filas y calcular totales ─────────────────────────────────────────
$semanasKeys = array_keys($semanasInfo);
$totalesPorSemana = array_fill_keys($semanasKeys, 0);
$granTotalPeriodo = 0;
$volumenPorCoord  = [];

$filasCalculadas = [];
foreach ($configs as $cfg) {
    $filaValores = [];
    $totalFila   = 0;

    foreach ($semanasKeys as $semKey) {
        $filasSem = $pedidosPorSemana[$semKey] ?? [];
        $cant = calcularCantidad($cfg, $filasSem);
        $filaValores[$semKey] = $cant;
        $totalFila += $cant;
        $totalesPorSemana[$semKey] += $cant;
    }

    $granTotalPeriodo += $totalFila;
    $coordNom = $cfg['coordinador'];
    $volumenPorCoord[$coordNom] = ($volumenPorCoord[$coordNom] ?? 0) + $totalFila;

    $cfg['semanas_cant'] = $filaValores;
    $cfg['total_fila']   = $totalFila;
    $filasCalculadas[]   = $cfg;
}

// Métricas para tarjetas ejecutivas (KPIs)
$cantSemanas = max(1, count($semanasKeys));
$promedioSemanal = (int)round($granTotalPeriodo / $cantSemanas);
arsort($volumenPorCoord);
$topCoordinador = !empty($volumenPorCoord) ? key($volumenPorCoord) . ' (' . number_format(reset($volumenPorCoord)) . ')' : 'N/A';

// ── Exportación a Excel ───────────────────────────────────────────────────────
if ($export) {
    require_once __DIR__ . '/../../../../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Pedidos Semanales');

    // Encabezado principal
    $sheet->setCellValue('A1', 'CANTIDAD DE PEDIDOS SEMANALES POR COORDINADOR');
    $sheet->setCellValue('A2', 'Rango evaluado: ' . reset($semanasInfo)['lunes'] . ' al ' . end($semanasInfo)['domingo']);
    $totalCols = count($semanasKeys) + 3;
    $lastColLet = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);

    $sheet->mergeCells("A1:{$lastColLet}1");
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF1E3A8A');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $currentRow = 4;

    // Fila Cabecera 1: Nombres de semana
    $sheet->setCellValueByColumnAndRow(1, $currentRow, 'Coordinador');
    $sheet->setCellValueByColumnAndRow(2, $currentRow, 'Cliente y País');
    $cIdx = 3;
    foreach ($semanasInfo as $s) {
        $sheet->setCellValueByColumnAndRow($cIdx, $currentRow, $s['label']);
        $cIdx++;
    }
    $sheet->setCellValueByColumnAndRow($cIdx, $currentRow, 'TOTAL');

    // Fila Cabecera 2: Rango de fechas
    $sheet->setCellValueByColumnAndRow(1, $currentRow + 1, '');
    $sheet->setCellValueByColumnAndRow(2, $currentRow + 1, '');
    $cIdx = 3;
    foreach ($semanasInfo as $s) {
        $sheet->setCellValueByColumnAndRow($cIdx, $currentRow + 1, $s['rango']);
        $cIdx++;
    }
    $sheet->setCellValueByColumnAndRow($cIdx, $currentRow + 1, 'Período');

    // Estilos cabecera
    $headerRange = "A{$currentRow}:{$lastColLet}" . ($currentRow + 1);
    $sheet->getStyle($headerRange)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E40AF']],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER]
    ]);

    $currentRow += 2;

    // Datos
    $ultimoGrupo = null;
    foreach ($filasCalculadas as $f) {
        if ($ultimoGrupo !== null && $ultimoGrupo != $f['grupo']) {
            // Separador visual de grupo en Excel
            $sheet->setCellValueByColumnAndRow(1, $currentRow, "--- GRUPO {$f['grupo']} ---");
            $sheet->mergeCells("A{$currentRow}:{$lastColLet}{$currentRow}");
            $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->getColor()->setARGB('FF475569');
            $sheet->getStyle("A{$currentRow}:{$lastColLet}{$currentRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
            $currentRow++;
        }
        $ultimoGrupo = $f['grupo'];

        $sheet->setCellValueByColumnAndRow(1, $currentRow, $f['coordinador']);
        $sheet->setCellValueByColumnAndRow(2, $currentRow, $f['etiqueta']);
        $cIdx = 3;
        foreach ($semanasKeys as $semKey) {
            $sheet->setCellValueByColumnAndRow($cIdx, $currentRow, $f['semanas_cant'][$semKey] ?: 0);
            $cIdx++;
        }
        $sheet->setCellValueByColumnAndRow($cIdx, $currentRow, $f['total_fila']);

        $sheet->getStyle("A{$currentRow}:{$lastColLet}{$currentRow}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle("C{$currentRow}:{$lastColLet}{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("{$lastColLet}{$currentRow}")->getFont()->setBold(true);

        $currentRow++;
    }

    // Fila Total General
    $sheet->setCellValueByColumnAndRow(1, $currentRow, '');
    $sheet->setCellValueByColumnAndRow(2, $currentRow, 'TOTAL GENERAL');
    $cIdx = 3;
    foreach ($semanasKeys as $semKey) {
        $sheet->setCellValueByColumnAndRow($cIdx, $currentRow, $totalesPorSemana[$semKey]);
        $cIdx++;
    }
    $sheet->setCellValueByColumnAndRow($cIdx, $currentRow, $granTotalPeriodo);

    $totalRange = "A{$currentRow}:{$lastColLet}{$currentRow}";
    $sheet->getStyle($totalRange)->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

    foreach (range(1, $totalCols) as $c) {
        $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="pedidos_semanales_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

?>
<?php include __DIR__ . '/../../../../vista/includes/header.php'; ?>

<style>
:root {
    --sem-primary: #2563eb;
    --sem-primary-dark: #1d4ed8;
    --sem-bg: #f8fafc;
    --sem-card-bg: #ffffff;
    --sem-border: #e2e8f0;
}

.sem-wrapper {
    background: var(--sem-bg);
    min-height: 100vh;
    padding: 1.5rem 2rem;
    font-family: inherit;
}

/* Header & Título */
.sem-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.sem-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Tarjetas KPI */
.kpi-card {
    background: var(--sem-card-bg);
    border: 1px solid var(--sem-border);
    border-radius: 12px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 1rem;
    height: 100%;
    transition: transform .15s ease, box-shadow .15s ease;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.kpi-icon.blue   { background: #eff6ff; color: #2563eb; }
.kpi-icon.green  { background: #f0fdf4; color: #16a34a; }
.kpi-icon.purple { background: #faf5ff; color: #9333ea; }
.kpi-label {
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.04em;
    margin-bottom: 2px;
}
.kpi-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}

/* Card de Filtros */
.filter-card {
    background: var(--sem-card-bg);
    border: 1px solid var(--sem-border);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    margin-bottom: 1.5rem;
}
.quick-preset-btn {
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    border-radius: 6px;
    padding: 0.25rem 0.65rem;
    font-size: 0.78rem;
    font-weight: 500;
    cursor: pointer;
    transition: all .15s;
    text-decoration: none;
}
.quick-preset-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #94a3b8;
}

/* Tabla Unificada Moderna */
.table-card {
    background: var(--sem-card-bg);
    border: 1px solid var(--sem-border);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    overflow: hidden;
}
.table-responsive-custom {
    overflow-x: auto;
}
.modern-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
    margin-bottom: 0;
}
.modern-table thead th {
    background: #0f172a;
    color: #f8fafc;
    font-weight: 600;
    padding: 0.75rem 1rem;
    text-align: center;
    border-bottom: 2px solid #334155;
    vertical-align: middle;
}
.modern-table thead th.th-left {
    text-align: left;
}
.modern-table thead .sem-header-box {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.modern-table thead .sem-header-num {
    font-size: 0.95rem;
    font-weight: 700;
    color: #ffffff;
}
.modern-table thead .sem-header-sub {
    font-size: 0.74rem;
    color: #94a3b8;
    font-weight: 400;
    white-space: nowrap;
}

.modern-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background .12s;
}
.modern-table tbody tr:hover {
    background: #f8fafc;
}
.modern-table td {
    padding: 0.65rem 1rem;
    vertical-align: middle;
}

/* Coordinador badge */
.coord-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #1e293b;
}
.coord-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e0e7ff;
    color: #3730a3;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
}

/* Celdas numéricas */
.td-val {
    text-align: center;
    font-variant-numeric: tabular-nums;
    font-weight: 500;
}
.td-val.active-vol {
    color: #0f172a;
    font-weight: 600;
}
.td-val.zero-vol {
    color: #cbd5e1;
    font-weight: 400;
}

/* Columna Total */
.th-total, .td-total {
    background: #f8fafc;
    font-weight: 700;
    color: #1e3a8a;
    text-align: center;
    border-left: 2px solid #e2e8f0;
}

/* Separador de Grupo */
.group-separator-row td {
    background: #f1f5f9;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.45rem 1rem;
    border-top: 1px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
}

/* Fila Total General */
.tr-grand-total td {
    background: #eff6ff !important;
    border-top: 2px solid #93c5fd !important;
    font-weight: 800;
    font-size: 0.95rem;
    color: #1e3a8a;
}
</style>

<div class="sem-wrapper">

    <!-- ── Encabezado & Acciones ─────────────────────────────────────────── -->
    <div class="sem-header">
        <div>
            <h2><i class="bi bi-calendar3-range text-primary"></i> Cantidad de Pedidos Semanales</h2>
            <p class="text-muted small mb-0">Distribución de volumen semanal de órdenes asignadas por Coordinador y País</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= RUTA_URL ?>pedidos/informes/pedidos_semanales?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-sm btn-success px-3 d-inline-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-excel-fill"></i> Exportar a Excel
            </a>
            <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalConfig">
                <i class="bi bi-gear-fill"></i> Gestionar Coordinadores
            </button>
        </div>
    </div>

    <!-- ── Tarjetas KPI Ejecutivas ───────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-card">
                <div class="kpi-icon blue"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="kpi-label">Total Pedidos en Período</div>
                    <div class="kpi-value"><?= number_format($granTotalPeriodo) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card">
                <div class="kpi-icon green"><i class="bi bi-graph-up-arrow"></i></div>
                <div>
                    <div class="kpi-label">Promedio por Semana</div>
                    <div class="kpi-value"><?= number_format($promedioSemanal) ?> <span class="fs-6 fw-normal text-muted">/ sem</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card">
                <div class="kpi-icon purple"><i class="bi bi-trophy"></i></div>
                <div>
                    <div class="kpi-label">Coordinador con Mayor Carga</div>
                    <div class="kpi-value fs-5"><?= htmlspecialchars($topCoordinador) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Barra de Filtros Natural (Desde / Hasta) ─────────────────────── -->
    <div class="filter-card">
        <form method="GET" action="<?= RUTA_URL ?>pedidos/informes/pedidos_semanales" class="row g-2 align-items-end" id="formFiltro">
            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-semibold text-muted mb-1"><i class="bi bi-calendar-event me-1"></i>Desde</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaDesde) ?>" required>
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-semibold text-muted mb-1"><i class="bi bi-calendar-check me-1"></i>Hasta</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaHasta) ?>" required>
            </div>
            <div class="col-md-auto d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-sm btn-primary px-3">
                    <i class="bi bi-filter me-1"></i>Filtrar Período
                </button>
            </div>
            <div class="col-md-auto ms-auto d-flex align-items-center gap-1 flex-wrap pt-2 pt-md-0">
                <span class="small text-muted me-1">Accesos rápidos:</span>
                <button type="button" class="quick-preset-btn" onclick="aplicarPreset(3)">Últimas 3 semanas</button>
                <button type="button" class="quick-preset-btn" onclick="aplicarPreset(4)">Últimas 4 semanas</button>
                <button type="button" class="quick-preset-btn" onclick="aplicarPreset(6)">Últimas 6 semanas</button>
                <button type="button" class="quick-preset-btn" onclick="aplicarMesActual()">Este Mes</button>
            </div>
        </form>
    </div>

    <!-- ── TABLA UNIFICADA DE VOLUMEN SEMANAL ────────────────────────────── -->
    <div class="table-card">
        <div class="table-responsive-custom">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th class="th-left" style="width: 180px;">Coordinador</th>
                        <th class="th-left" style="min-width: 250px;">Cliente y País</th>
                        <?php foreach ($semanasInfo as $s): ?>
                        <th style="min-width: 110px;">
                            <div class="sem-header-box">
                                <span class="sem-header-num"><?= htmlspecialchars($s['label']) ?></span>
                                <span class="sem-header-sub"><?= htmlspecialchars($s['rango']) ?></span>
                            </div>
                        </th>
                        <?php endforeach; ?>
                        <th class="th-total" style="min-width: 120px;">
                            <div class="sem-header-box">
                                <span class="sem-header-num">TOTAL</span>
                                <span class="sem-header-sub text-primary">Período</span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $ultimoGrupo = null;
                    foreach ($filasCalculadas as $f): 
                        if ($ultimoGrupo !== null && $ultimoGrupo != $f['grupo']):
                    ?>
                    <tr class="group-separator-row">
                        <td colspan="<?= count($semanasKeys) + 3 ?>">
                            <i class="bi bi-collection me-1"></i> GRUPO <?= (int)$f['grupo'] ?> DE COORDINACIÓN
                        </td>
                    </tr>
                    <?php 
                        endif;
                        $ultimoGrupo = $f['grupo'];
                        $inicial = strtoupper(substr($f['coordinador'], 0, 1));
                    ?>
                    <tr>
                        <td>
                            <div class="coord-badge">
                                <div class="coord-avatar"><?= htmlspecialchars($inicial) ?></div>
                                <span><?= htmlspecialchars($f['coordinador']) ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="text-dark fw-medium"><?= htmlspecialchars($f['etiqueta']) ?></span>
                            <?php if (!empty($f['pais_nombre'])): ?>
                            <span class="badge bg-light text-secondary border ms-1 font-monospace" style="font-size: 0.72rem;"><?= htmlspecialchars($f['pais_nombre']) ?></span>
                            <?php endif; ?>
                        </td>
                        <?php foreach ($semanasKeys as $semKey): 
                            $val = $f['semanas_cant'][$semKey] ?? 0;
                        ?>
                        <td class="td-val <?= $val > 0 ? 'active-vol' : 'zero-vol' ?>">
                            <?= $val > 0 ? number_format($val) : '—' ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="td-total td-val">
                            <?= number_format($f['total_fila']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Fila Total General -->
                    <tr class="tr-grand-total">
                        <td></td>
                        <td class="text-end fw-bold">TOTAL GENERAL</td>
                        <?php foreach ($semanasKeys as $semKey): ?>
                        <td class="td-val"><?= number_format($totalesPorSemana[$semKey]) ?></td>
                        <?php endforeach; ?>
                        <td class="td-val text-primary" style="font-size: 1.05rem;"><?= number_format($granTotalPeriodo) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ── MODAL: Gestionar Coordinadores ───────────────────────────────────── -->
<div class="modal fade" id="modalConfig" tabindex="-1" aria-labelledby="modalConfigLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fs-6" id="modalConfigLabel">
                    <i class="bi bi-gear-fill me-2 text-primary"></i>Gestionar Coordinadores y Asignación de Cuentas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    Configura las filas de la matriz: asigna el <strong>Coordinador</strong>, la <strong>Etiqueta</strong> visible y vincula la cuenta del cliente/país en el sistema.
                </p>

                <!-- Formulario -->
                <form id="frmCoordinador" class="bg-light p-3 rounded-3 mb-4 border">
                    <input type="hidden" name="action" value="guardar">
                    <input type="hidden" name="id" id="cfg_id" value="0">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold mb-1">Coordinador *</label>
                            <input type="text" name="coordinador" id="cfg_coordinador" class="form-control form-control-sm" placeholder="ej. Aldo, Juan..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-1">Etiqueta (Cliente y País) *</label>
                            <input type="text" name="etiqueta" id="cfg_etiqueta" class="form-control form-control-sm" placeholder="ej. LogisPro/ Verde Street CR" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold mb-1">Cliente en Sistema</label>
                            <select name="id_cliente" id="cfg_id_cliente" class="form-select form-select-sm">
                                <option value="">-- Sin asignar --</option>
                                <?php foreach ($clientesCat as $cl): ?>
                                <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">País</label>
                            <select name="id_pais" id="cfg_id_pais" class="form-select form-select-sm">
                                <option value="">-- Todos --</option>
                                <?php foreach ($paisesCat as $pa): ?>
                                <option value="<?= $pa['id'] ?>"><?= htmlspecialchars($pa['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Grupo</label>
                            <select name="grupo" id="cfg_grupo" class="form-select form-select-sm">
                                <option value="1">Grupo 1</option>
                                <option value="2">Grupo 2</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Orden</label>
                            <input type="number" name="orden" id="cfg_orden" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col-md-8 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-sm btn-primary px-3">
                                <i class="bi bi-check-lg me-1"></i>Guardar Fila
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limpiarFormConfig()">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Tabla de lista -->
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Coord.</th>
                                <th>Etiqueta</th>
                                <th>Cliente BD</th>
                                <th>País BD</th>
                                <th>Grupo</th>
                                <th class="text-center" style="width: 80px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($configs as $c): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($c['coordinador']) ?></td>
                                <td><?= htmlspecialchars($c['etiqueta']) ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($c['cliente_nombre'] ?? 'Sin asignar') ?></small></td>
                                <td><small class="text-muted"><?= htmlspecialchars($c['pais_nombre'] ?? 'Todos') ?></small></td>
                                <td><span class="badge bg-secondary">Grupo <?= $c['grupo'] ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary py-0 px-1" onclick='editarConfig(<?= json_encode($c) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="eliminarConfig(<?= $c['id'] ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function editarConfig(data) {
    document.getElementById('cfg_id').value = data.id;
    document.getElementById('cfg_coordinador').value = data.coordinador;
    document.getElementById('cfg_etiqueta').value = data.etiqueta;
    document.getElementById('cfg_id_cliente').value = data.id_cliente || '';
    document.getElementById('cfg_id_pais').value = data.id_pais || '';
    document.getElementById('cfg_grupo').value = data.grupo || 1;
    document.getElementById('cfg_orden').value = data.orden || 0;
    document.getElementById('cfg_coordinador').focus();
}

function limpiarFormConfig() {
    document.getElementById('cfg_id').value = 0;
    document.getElementById('cfg_coordinador').value = '';
    document.getElementById('cfg_etiqueta').value = '';
    document.getElementById('cfg_id_cliente').value = '';
    document.getElementById('cfg_id_pais').value = '';
    document.getElementById('cfg_grupo').value = 1;
    document.getElementById('cfg_orden').value = 0;
}

document.getElementById('frmCoordinador').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('<?= RUTA_URL ?>pedidos/informes/pedidos_semanales', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            location.reload();
        } else {
            alert(data.msg || 'Error al guardar');
        }
    })
    .catch(err => {
        console.error(err);
        location.reload();
    });
});

function eliminarConfig(id) {
    if (!confirm('¿Seguro que deseas eliminar esta fila?')) return;
    const formData = new FormData();
    formData.append('action', 'eliminar');
    formData.append('id', id);

    fetch('<?= RUTA_URL ?>pedidos/informes/pedidos_semanales', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            location.reload();
        } else {
            alert('Error al eliminar');
        }
    })
    .catch(err => {
        console.error(err);
        location.reload();
    });
}

function aplicarPreset(numSemanas) {
    const hoy = new Date();
    // Calcular lunes de hace numSemanas - 1
    const day = hoy.getDay();
    const diff = hoy.getDate() - day + (day === 0 ? -6 : 1); // Lunes de esta semana
    const lunesEstaSemana = new Date(hoy.setDate(diff));

    const lunesInicio = new Date(lunesEstaSemana);
    lunesInicio.setDate(lunesInicio.getDate() - ((numSemanas - 1) * 7));

    const fmt = d => d.toISOString().split('T')[0];
    document.getElementById('fecha_desde').value = fmt(lunesInicio);
    document.getElementById('fecha_hasta').value = fmt(new Date());
    document.getElementById('formFiltro').submit();
}

function aplicarMesActual() {
    const hoy = new Date();
    const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    const fmt = d => d.toISOString().split('T')[0];
    document.getElementById('fecha_desde').value = fmt(primerDia);
    document.getElementById('fecha_hasta').value = fmt(hoy);
    document.getElementById('formFiltro').submit();
}
</script>

<?php include __DIR__ . '/../../../../vista/includes/footer.php'; ?>
