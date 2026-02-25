<?php
/**
 * Controlador – Saldo por Producto
 * Ruta: /stock/saldo[?export=1]
 */

require_once 'modelo/conexion.php';
require_once 'modelo/inventario.php';
require_once 'utils/autenticacion.php';
require_once 'utils/permissions.php';

verificarAutenticacion();

$export = isset($_GET['export']) && $_GET['export'] === '1';

// ── Query ────────────────────────────────────────────────────────────────────
$db = (new Conexion())->conectar();

$sql = "
    SELECT
        pr.id,
        pr.nombre         AS producto,
        pr.sku,
        COALESCE(inv.cantidad_disponible, 0) AS disponible,
        COALESCE(inv.cantidad_reservada,  0) AS reservado,
        COALESCE(inv.cantidad_disponible, 0) - COALESCE(inv.cantidad_reservada, 0) AS neto_libre,
        inv.costo_promedio,
        inv.ubicacion,
        inv.ultima_entrada,
        inv.ultima_salida
    FROM productos pr
    LEFT JOIN inventario inv ON inv.id_producto = pr.id AND inv.ubicacion = 'Principal'
    WHERE pr.activo = 1
    ORDER BY pr.nombre ASC
";

$stmt = $db->query($sql);
$saldos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Export Excel ─────────────────────────────────────────────────────────────
if ($export) {
    require_once 'vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Saldo por Producto');

    $headers = ['ID', 'Producto', 'SKU', 'Disponible', 'Reservado', 'Neto Libre', 'Costo Promedio', 'Ubicación', 'Últ. Entrada', 'Últ. Salida'];
    foreach ($headers as $col => $h) {
        $sheet->setCellValueByColumnAndRow($col + 1, 1, $h);
    }

    foreach ($saldos as $row => $s) {
        $data = [
            $s['id'], $s['producto'], $s['sku'], $s['disponible'], $s['reservado'],
            $s['neto_libre'], $s['costo_promedio'], $s['ubicacion'],
            $s['ultima_entrada'], $s['ultima_salida'],
        ];
        foreach ($data as $col => $val) {
            $sheet->setCellValueByColumnAndRow($col + 1, $row + 2, $val);
        }
    }

    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
    foreach (range(1, count($headers)) as $i) {
        $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="saldo_productos_' . date('Ymd') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ── Renderizar vista ─────────────────────────────────────────────────────────
include 'vista/includes/header.php';
include 'vista/modulos/stock/saldo.php';
include 'vista/includes/footer.php';
