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

// ── Filtros ───────────────────────────────────────────────────────────────────
$anioActual   = (int)date('Y');
$anio         = (int)($_GET['anio'] ?? $anioActual);
$numSemanas   = (int)($_GET['num_semanas'] ?? 3); // 3, 4, 6, 8 semanas
$semanaHasta  = (int)($_GET['semana_hasta'] ?? (int)date('W'));
if ($semanaHasta <= 0) $semanaHasta = 37;

// Generar array de semanas a evaluar (de menor a mayor)
$semanas = [];
for ($i = $numSemanas - 1; $i >= 0; $i--) {
    $s = $semanaHasta - $i;
    if ($s > 0) $semanas[] = $s;
}

$export = isset($_GET['export']) && $_GET['export'] === '1';

// ── Consultar configuración activa ───────────────────────────────────────────
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

// ── Obtener los datos agregados de pedidos ────────────────────────────────────
// Para optimizar, hacemos una consulta única por semanas y agrupamos
$placeholdersSem = implode(',', array_fill(0, count($semanas), '?'));
$paramsAgg = array_merge([$anio], $semanas);

$sqlAgg = "
    SELECT 
        WEEK(pe.fecha_ingreso, 3) AS semana,
        pe.id_cliente,
        pe.id_proveedor,
        pe.id_pais,
        COUNT(*) AS cantidad
    FROM pedidos pe
    WHERE YEAR(pe.fecha_ingreso) = ?
      AND WEEK(pe.fecha_ingreso, 3) IN ($placeholdersSem)
    GROUP BY semana, pe.id_cliente, pe.id_proveedor, pe.id_pais
";
$stmtAgg = $db->prepare($sqlAgg);
$stmtAgg->execute($paramsAgg);
$rawAgg = $stmtAgg->fetchAll(PDO::FETCH_ASSOC);

// Indexar por semana para búsquedas rápidas en memoria
$pedidosPorSemana = [];
foreach ($rawAgg as $row) {
    $sem = (int)$row['semana'];
    $pedidosPorSemana[$sem][] = $row;
}

// Función helper para calcular cantidad de una fila config en una semana
function calcularCantidad($cfg, $filasSemana) {
    if (empty($filasSemana)) return 0;
    $total = 0;
    foreach ($filasSemana as $r) {
        $matchCliente = false;
        $matchPais = false;

        // Validación de Cliente / Proveedor
        if ($cfg['id_cliente']) {
            if ($r['id_cliente'] == $cfg['id_cliente'] || $r['id_proveedor'] == $cfg['id_cliente']) {
                $matchCliente = true;
            }
        } elseif ($cfg['id_proveedor']) {
            if ($r['id_proveedor'] == $cfg['id_proveedor'] || $r['id_cliente'] == $cfg['id_proveedor']) {
                $matchCliente = true;
            }
        } else {
            // Sin cliente específico (cuenta comodín)
            $matchCliente = true;
        }

        // Validación de País
        if ($cfg['id_pais']) {
            if ($r['id_pais'] == $cfg['id_pais']) {
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

// ── Procesar datos por grupo ──────────────────────────────────────────────────
$grupos = [1 => [], 2 => []];
$totalesPorGrupo = [
    1 => array_fill_keys($semanas, 0),
    2 => array_fill_keys($semanas, 0)
];

foreach ($configs as $cfg) {
    $grp = (int)($cfg['grupo'] ?? 1);
    if (!isset($grupos[$grp])) {
        $grupos[$grp] = [];
        $totalesPorGrupo[$grp] = array_fill_keys($semanas, 0);
    }

    $filaValores = [];
    foreach ($semanas as $sem) {
        $filasSem = $pedidosPorSemana[$sem] ?? [];
        $cant = calcularCantidad($cfg, $filasSem);
        $filaValores[$sem] = $cant;
        $totalesPorGrupo[$grp][$sem] += $cant;
    }

    $cfg['semanas_cant'] = $filaValores;
    $grupos[$grp][] = $cfg;
}

// ── Exportación a Excel ───────────────────────────────────────────────────────
if ($export) {
    require_once __DIR__ . '/../../../../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Pedidos Semanales');

    // Título principal
    $sheet->setCellValue('A1', 'Cantidad de Pedidos semanales');
    $sheet->mergeCells('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($semanas) + 2) . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF0B5394');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $currentRow = 3;

    foreach ($grupos as $grpNum => $filasGrupo) {
        if (empty($filasGrupo)) continue;

        // Fila 1 Cabeceras
        $sheet->setCellValueByColumnAndRow(1, $currentRow, 'Coord.');
        $sheet->setCellValueByColumnAndRow(2, $currentRow, 'Cliente y Pais');
        $colIdx = 3;
        foreach ($semanas as $sem) {
            $sheet->setCellValueByColumnAndRow($colIdx, $currentRow, "S_{$sem}");
            $colIdx++;
        }

        // Fila 2 Sub-cabeceras
        $sheet->setCellValueByColumnAndRow(1, $currentRow + 1, '');
        $sheet->setCellValueByColumnAndRow(2, $currentRow + 1, '');
        $colIdx = 3;
        foreach ($semanas as $sem) {
            $sheet->setCellValueByColumnAndRow($colIdx, $currentRow + 1, 'Cantidad');
            $colIdx++;
        }

        $totalCols = count($semanas) + 2;
        $headerRange = "A{$currentRow}:" . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols) . ($currentRow + 1);
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9EAD3']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ]);
        $sheet->getStyle("A{$currentRow}:B" . ($currentRow + 1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $currentRow += 2;

        // Filas de datos
        foreach ($filasGrupo as $f) {
            $sheet->setCellValueByColumnAndRow(1, $currentRow, $f['coordinador']);
            $sheet->setCellValueByColumnAndRow(2, $currentRow, $f['etiqueta']);
            $colIdx = 3;
            foreach ($semanas as $sem) {
                $sheet->setCellValueByColumnAndRow($colIdx, $currentRow, $f['semanas_cant'][$sem]);
                $colIdx++;
            }
            $sheet->getStyle("A{$currentRow}:" . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols) . $currentRow)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
            ]);
            $sheet->getStyle("C{$currentRow}:" . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols) . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        }

        // Fila Total
        $sheet->setCellValueByColumnAndRow(1, $currentRow, '');
        $sheet->setCellValueByColumnAndRow(2, $currentRow, 'Total');
        $colIdx = 3;
        foreach ($semanas as $sem) {
            $sheet->setCellValueByColumnAndRow($colIdx, $currentRow, $totalesPorGrupo[$grpNum][$sem]);
            $colIdx++;
        }
        $totalRange = "A{$currentRow}:" . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols) . $currentRow;
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9EAD3']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ]);
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

        $currentRow += 3; // Espacio para el siguiente grupo
    }

    foreach (range(1, count($semanas) + 2) as $c) {
        $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="pedidos_semanales_' . date('Ymd') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

?>
<?php include __DIR__ . '/../../../../vista/includes/header.php'; ?>

<style>
.sem-page { background: #f8fafc; min-height: 100vh; padding: 1.5rem; }
.sem-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    margin-bottom: 1.5rem;
    border: 1px solid #e2e8f0;
}
.sem-title-box {
    text-align: center;
    margin-bottom: 1.5rem;
}
.sem-title {
    color: #1a56db;
    font-size: 1.6rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    margin: 0;
}

/* Tabla Estilo Excel */
.sem-table-wrap {
    margin-bottom: 2rem;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 6px rgba(0,0,0,0.04);
    overflow-x: auto;
}
.sem-table {
    width: 100%;
    border-collapse: collapse;
    font-family: inherit;
    font-size: 0.92rem;
}
.sem-table th, .sem-table td {
    border: 1px solid #c7d2de;
    padding: 0.45rem 0.85rem;
}
.sem-table thead tr:first-child th {
    background: #d9ead3;
    color: #1c3d18;
    font-weight: 700;
    text-align: center;
}
.sem-table thead tr:nth-child(2) th {
    background: #d9ead3;
    color: #1c3d18;
    font-weight: 700;
    text-align: center;
    font-size: 0.88rem;
}
.th-coord { width: 14%; text-align: left !important; }
.th-cliente { width: 44%; text-align: left !important; }
.th-sem { width: 14%; text-align: center; }

.td-coord { font-weight: 500; color: #1e293b; }
.td-cliente { color: #334155; }
.td-num { text-align: center; font-variant-numeric: tabular-nums; }

.tr-total td {
    background: #d9ead3;
    font-weight: 700;
    color: #0f2e0d;
}
.tr-total .td-total-label {
    text-align: right;
    padding-right: 1.5rem;
}

/* Botón Excel */
.btn-excel {
    background: #166534; color: #fff; border: none;
    border-radius: 8px; padding: .45rem 1rem; font-size: .85rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
    transition: background .15s;
}
.btn-excel:hover { background: #14532d; color: #fff; }
</style>

<div class="sem-page">

    <!-- ── Filtros y Barra de Acciones ───────────────────────────────────── -->
    <div class="sem-card">
        <form method="GET" action="<?= RUTA_URL ?>pedidos/informes/pedidos_semanales" class="row g-2 align-items-end">
            <div class="col-md-2 col-6">
                <label class="form-label small fw-semibold mb-1">Año</label>
                <select name="anio" class="form-select form-select-sm">
                    <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                    <option value="<?= $y ?>" <?= $anio == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-semibold mb-1">Hasta la Semana</label>
                <select name="semana_hasta" class="form-select form-select-sm">
                    <?php for ($s = 52; $s >= 1; $s--): ?>
                    <option value="<?= $s ?>" <?= $semanaHasta == $s ? 'selected' : '' ?>>Semana <?= $s ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-semibold mb-1">Mostrar</label>
                <select name="num_semanas" class="form-select form-select-sm">
                    <option value="3" <?= $numSemanas == 3 ? 'selected' : '' ?>>Últimas 3 semanas</option>
                    <option value="4" <?= $numSemanas == 4 ? 'selected' : '' ?>>Últimas 4 semanas</option>
                    <option value="6" <?= $numSemanas == 6 ? 'selected' : '' ?>>Últimas 6 semanas</option>
                </select>
            </div>
            <div class="col-md-auto ms-auto d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-sm btn-primary px-3">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="<?= RUTA_URL ?>pedidos/informes/pedidos_semanales?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn-excel">
                    <i class="bi bi-file-earmark-excel"></i>Excel
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalConfig">
                    <i class="bi bi-gear-fill me-1"></i>Gestionar Coordinadores
                </button>
            </div>
        </form>
    </div>

    <!-- ── Título Central ───────────────────────────────────────────────── -->
    <div class="sem-title-box">
        <h3 class="sem-title">Cantidad de Pedidos semanales</h3>
    </div>

    <!-- ── TABLAS POR GRUPO ─────────────────────────────────────────────── -->
    <?php foreach ($grupos as $grpNum => $filasGrupo): ?>
        <?php if (empty($filasGrupo)) continue; ?>
        <div class="sem-table-wrap">
            <table class="sem-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="th-coord">Coord.</th>
                        <th rowspan="2" class="th-cliente">Cliente y Pais</th>
                        <?php foreach ($semanas as $sem): ?>
                        <th class="th-sem">S_<?= $sem ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($semanas as $sem): ?>
                        <th>Cantidad</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filasGrupo as $f): ?>
                    <tr>
                        <td class="td-coord"><?= htmlspecialchars($f['coordinador']) ?></td>
                        <td class="td-cliente"><?= htmlspecialchars($f['etiqueta']) ?></td>
                        <?php foreach ($semanas as $sem): ?>
                        <td class="td-num"><?= number_format((int)$f['semanas_cant'][$sem]) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="tr-total">
                        <td></td>
                        <td class="td-total-label">Total</td>
                        <?php foreach ($semanas as $sem): ?>
                        <td class="td-num"><?= number_format((int)$totalesPorGrupo[$grpNum][$sem]) ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

</div>

<!-- ── MODAL: Configurar Coordinadores ──────────────────────────────────── -->
<div class="modal fade" id="modalConfig" tabindex="-1" aria-labelledby="modalConfigLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalConfigLabel"><i class="bi bi-gear-fill me-2 text-primary"></i>Gestionar Coordinadores y Clientes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Asigna a qué <strong>Coordinador</strong> corresponde cada cuenta, la <strong>Etiqueta</strong> con la que se muestra en la tabla, el <strong>Cliente</strong> asociado en el sistema y el <strong>Grupo</strong> (Tabla 1 o Tabla 2).
                </p>

                <!-- Formulario de agregar / editar -->
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
                                <option value="1">Tabla 1</option>
                                <option value="2">Tabla 2</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Orden</label>
                            <input type="number" name="orden" id="cfg_orden" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col-md-8 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-sm btn-success px-3">
                                <i class="bi bi-check-lg me-1"></i>Guardar Fila
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limpiarFormConfig()">
                                <i class="bi bi-x me-1"></i>Cancelar edición
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Listado actual -->
                <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Coord.</th>
                                <th>Etiqueta</th>
                                <th>Cliente BD</th>
                                <th>País BD</th>
                                <th>Grupo</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($configs as $c): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($c['coordinador']) ?></td>
                                <td><?= htmlspecialchars($c['etiqueta']) ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($c['cliente_nombre'] ?? 'Sin asignar') ?></small></td>
                                <td><small class="text-muted"><?= htmlspecialchars($c['pais_nombre'] ?? 'Todos') ?></small></td>
                                <td><span class="badge bg-secondary">Tabla <?= $c['grupo'] ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-xs btn-outline-primary py-0 px-2" onclick='editarConfig(<?= json_encode($c) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-xs btn-outline-danger py-0 px-2" onclick="eliminarConfig(<?= $c['id'] ?>)">
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
    if (!confirm('¿Seguro que deseas eliminar esta fila de la tabla?')) return;
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
</script>

<?php include __DIR__ . '/../../../../vista/includes/footer.php'; ?>
