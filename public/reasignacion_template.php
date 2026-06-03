<?php
/**
 * Generador de Plantilla XLSX para Reasignación Masiva de Proveedor
 *
 * Modo A (GET ?modo=A): Solo columna numero_orden
 *   → El proveedor se selecciona en el modal del sistema
 *
 * Modo B (GET ?modo=B): Columnas numero_orden + id_proveedor
 *   → Cada fila puede tener un proveedor distinto
 */

$modo = $_GET['modo'] ?? 'A';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    die('PhpSpreadsheet no instalado. Ejecuta: composer install');
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Reasignacion');

// ── Colores según modo ────────────────────────────────────────────────────────
$colorHeader = ($modo === 'A') ? 'C0392B' : '6C3483'; // rojo / morado

// ── Estilo de encabezado ──────────────────────────────────────────────────────
$headerStyle = [
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorHeader]],
    'font'      => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true, 'size' => 11, 'name' => 'Calibri'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
];

$dataStyle = [
    'font'      => ['size' => 10, 'name' => 'Calibri'],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D5D8DC']]],
];

$exampleStyle = [
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDFEFE']],
    'font'      => ['size' => 10, 'name' => 'Calibri', 'italic' => true, 'color' => ['rgb' => '7F8C8D']],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D5D8DC']]],
];

if ($modo === 'A') {
    // ── MODO A: Solo numero_orden ─────────────────────────────────────────────
    $sheet->setCellValue('A1', 'numero_orden');
    $sheet->getStyle('A1')->applyFromArray($headerStyle);
    $sheet->getColumnDimension('A')->setWidth(20);

    // Filas de ejemplo (comentadas con color gris)
    $ejemplos = ['28028424', '28028425', '28028426', '28028427', '28028428'];
    foreach ($ejemplos as $i => $val) {
        $row = $i + 2;
        $sheet->setCellValue("A{$row}", $val);
        $sheet->getStyle("A{$row}")->applyFromArray($exampleStyle);
    }

    // Filas vacías para rellenar
    $sheet->getStyle('A7:A106')->applyFromArray($dataStyle);

} else {
    // ── MODO B: numero_orden + id_proveedor ───────────────────────────────────
    $sheet->setCellValue('A1', 'numero_orden');
    $sheet->setCellValue('B1', 'id_proveedor');
    $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(18);

    // Filas de ejemplo
    $ejemplos = [
        ['28028424', '12'],
        ['28028425', '15'],
        ['28028426', '12'],
        ['28028427', '8'],
        ['28028428', '15'],
    ];
    foreach ($ejemplos as $i => [$orden, $prov]) {
        $row = $i + 2;
        $sheet->setCellValue("A{$row}", $orden);
        $sheet->setCellValue("B{$row}", $prov);
        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray($exampleStyle);
    }

    // Filas vacías para rellenar
    $sheet->getStyle('A7:B106')->applyFromArray($dataStyle);
}

// ── Altura de filas ───────────────────────────────────────────────────────────
$sheet->getRowDimension(1)->setRowHeight(28);
for ($r = 2; $r <= 106; $r++) {
    $sheet->getRowDimension($r)->setRowHeight(18);
}

// ── Congelar encabezado ───────────────────────────────────────────────────────
$sheet->freezePane('A2');

// ── Formato número para numero_orden ─────────────────────────────────────────
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
$sheet->getStyle('A2:A106')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);


// ── Hoja de instrucciones ─────────────────────────────────────────────────────
$instrSheet = $spreadsheet->createSheet();
$instrSheet->setTitle('Instrucciones');

if ($modo === 'A') {
    $instrucciones = [
        ['REASIGNACIÓN DE PROVEEDOR — MODO A (Proveedor único)', '', ''],
        ['', '', ''],
        ['COLUMNA', 'REQ.', 'DESCRIPCIÓN'],
        ['A: numero_orden', 'SÍ', 'Número de orden del pedido que deseas reasignar (entero). Ej: 28028424'],
        ['', '', ''],
        ['INSTRUCCIONES', '', ''],
        ['', '', '1. Elimina las filas de ejemplo (las grises) antes de subir el archivo.'],
        ['', '', '2. Llena la columna A con los numero_orden de los pedidos a reasignar.'],
        ['', '', '3. En el sistema, selecciona el nuevo proveedor en el selector del modal.'],
        ['', '', '4. Sube este archivo. El sistema aplicará ese proveedor a TODOS los pedidos listados.'],
        ['', '', ''],
        ['', '', '⚠️ Si un numero_orden no existe en el sistema, esa fila se reportará como "no encontrada".'],
        ['', '', '⚠️ La reasignación queda registrada en el historial de auditoría de cada pedido.'],
    ];
} else {
    $instrucciones = [
        ['REASIGNACIÓN DE PROVEEDOR — MODO B (Proveedor por fila)', '', ''],
        ['', '', ''],
        ['COLUMNA', 'REQ.', 'DESCRIPCIÓN'],
        ['A: numero_orden', 'SÍ', 'Número de orden del pedido que deseas reasignar (entero). Ej: 28028424'],
        ['B: id_proveedor', 'SÍ', 'ID numérico del nuevo proveedor a asignar. Consúltalo en: Pedidos → Referencia → IDs disponibles.'],
        ['', '', ''],
        ['INSTRUCCIONES', '', ''],
        ['', '', '1. Elimina las filas de ejemplo (las grises) antes de subir el archivo.'],
        ['', '', '2. Llena la columna A con los numero_orden y la columna B con el id_proveedor correspondiente.'],
        ['', '', '3. Cada fila puede tener un proveedor diferente.'],
        ['', '', '4. Sube este archivo. El sistema procesará fila por fila.'],
        ['', '', ''],
        ['', '', '⚠️ Si un numero_orden no existe en el sistema, esa fila se reportará como "no encontrada".'],
        ['', '', '⚠️ Si un id_proveedor está vacío en alguna fila, esa fila se reportará como error.'],
        ['', '', '⚠️ La reasignación queda registrada en el historial de auditoría de cada pedido.'],
    ];
}

// Encabezado de instrucciones
$instrSheet->getStyle('A1:C1')->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorHeader]],
    'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true, 'size' => 12, 'name' => 'Calibri'],
]);
$instrSheet->mergeCells('A1:C1');

foreach ($instrucciones as $rowIdx => $row) {
    $instrSheet->setCellValue('A' . ($rowIdx + 1), $row[0]);
    $instrSheet->setCellValue('B' . ($rowIdx + 1), $row[1]);
    $instrSheet->setCellValue('C' . ($rowIdx + 1), $row[2]);
}

// Estilo cabecera de columnas
$instrSheet->getStyle('A3:C3')->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F3F4']],
    'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri'],
]);

// REQ en rojo
$totalInstr = count($instrucciones);
for ($r = 2; $r <= $totalInstr; $r++) {
    $val = $instrSheet->getCell('B' . $r)->getValue();
    if ($val === 'SÍ') {
        $instrSheet->getStyle('B' . $r)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'C0392B']],
        ]);
    }
}

$instrSheet->getColumnDimension('A')->setWidth(28);
$instrSheet->getColumnDimension('B')->setWidth(8);
$instrSheet->getColumnDimension('C')->setWidth(85);
$instrSheet->getStyle('C1:C' . $totalInstr)->getAlignment()->setWrapText(true);
$instrSheet->freezePane('A2');

// ── Propiedades del documento ─────────────────────────────────────────────────
$spreadsheet->getProperties()
    ->setCreator('PaqueteriaCZ')
    ->setTitle('Plantilla Reasignación de Proveedor ' . ($modo === 'A' ? '(Modo A)' : '(Modo B)'))
    ->setDescription('Plantilla para reasignación masiva de proveedor en pedidos existentes');

// ── Activar hoja principal ────────────────────────────────────────────────────
$spreadsheet->setActiveSheetIndex(0);

// ── Enviar como descarga ──────────────────────────────────────────────────────
if (ob_get_length()) ob_clean();

$suffix   = ($modo === 'A') ? 'proveedor_unico' : 'por_fila';
$filename = 'reasignacion_' . $suffix . '_' . date('Ymd') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
