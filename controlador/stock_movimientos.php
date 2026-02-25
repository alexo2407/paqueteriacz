<?php
/**
 * Controlador – Movimientos de Stock
 * Ruta: /stock/movimientos[?fecha_desde=&fecha_hasta=&tipo=&id_cliente=&export=1]
 */

require_once 'modelo/conexion.php';
require_once 'modelo/stock.php';
require_once 'modelo/inventario.php';
require_once 'utils/autenticacion.php';
require_once 'utils/permissions.php';

verificarAutenticacion();

// ── Filtros ──────────────────────────────────────────────────────────────────
$fechaDesde  = $_GET['fecha_desde']  ?? date('Y-m-01');
$fechaHasta  = $_GET['fecha_hasta']  ?? date('Y-m-d');
$tipoFiltro  = $_GET['tipo']         ?? '';
$clienteId   = (int)($_GET['id_cliente'] ?? 0);
$export      = isset($_GET['export']) && $_GET['export'] === '1';

// ── Query ────────────────────────────────────────────────────────────────────
$db = (new Conexion())->conectar();

$where  = ['s.created_at BETWEEN :desde AND :hasta'];
$params = [
    ':desde' => $fechaDesde . ' 00:00:00',
    ':hasta' => $fechaHasta . ' 23:59:59',
];

if ($tipoFiltro !== '') {
    $where[]         = 's.tipo_movimiento = :tipo';
    $params[':tipo'] = $tipoFiltro;
}

if ($clienteId > 0) {
    $where[]           = 'p.id_cliente = :id_cliente';
    $params[':id_cliente'] = $clienteId;
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT
        s.id,
        s.created_at     AS fecha,
        pr.nombre        AS producto,
        s.cantidad,
        s.tipo_movimiento,
        s.referencia_tipo,
        s.referencia_id,
        s.motivo,
        s.ubicacion_origen,
        s.ubicacion_destino,
        u.nombre         AS usuario,
        p.numero_orden   AS orden_referencia,
        uc.nombre        AS cliente
    FROM stock s
    LEFT JOIN productos    pr ON pr.id = s.id_producto
    LEFT JOIN usuarios      u ON  u.id = s.id_usuario
    LEFT JOIN pedidos       p ON p.id  = s.referencia_id AND s.referencia_tipo = 'pedido'
    LEFT JOIN usuarios     uc ON uc.id = p.id_cliente
    $whereStr
    ORDER BY s.created_at DESC
    LIMIT 2000
";

$stmt = $db->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Clientes para filtro (admin) ─────────────────────────────────────────────
$clientes = [];
if (isAdmin() || isSuperAdmin()) {
    $stmtC = $db->query("SELECT id, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre ASC");
    $clientes = $stmtC->fetchAll(PDO::FETCH_ASSOC);
}

// ── Export Excel ─────────────────────────────────────────────────────────────
if ($export) {
    require_once 'vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Movimientos de Stock');

    $headers = ['ID', 'Fecha', 'Producto', 'Cantidad', 'Tipo', 'Referencia Tipo', 'Referencia ID', 'Motivo', 'Origen', 'Destino', 'Usuario', '# Orden', 'Cliente'];
    foreach ($headers as $col => $h) {
        $sheet->setCellValueByColumnAndRow($col + 1, 1, $h);
    }

    foreach ($movimientos as $row => $m) {
        $data = [
            $m['id'], $m['fecha'], $m['producto'], $m['cantidad'],
            $m['tipo_movimiento'], $m['referencia_tipo'], $m['referencia_id'],
            $m['motivo'], $m['ubicacion_origen'], $m['ubicacion_destino'],
            $m['usuario'], $m['orden_referencia'], $m['cliente'],
        ];
        foreach ($data as $col => $val) {
            $sheet->setCellValueByColumnAndRow($col + 1, $row + 2, $val);
        }
    }

    // Estilo encabezado
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
    foreach (range(1, count($headers)) as $i) {
        $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="movimientos_stock_' . date('Ymd') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ── Renderizar vista ─────────────────────────────────────────────────────────
include 'vista/includes/header.php';
include 'vista/modulos/stock/movimientos.php';
include 'vista/includes/footer.php';
