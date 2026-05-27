<?php
/**
 * Generador de Plantilla XLSX para Importación de Pedidos
 * Compatible con PhpSpreadsheet ^2.x
 *
 * Columnas: Núm.Orden | Fecha Ing. | Destinatario | Teléfono | Dirección | Comentario |
 *   Zona | Código Postal | País | Depto. | Municipio | Barrio | Entre Calles |
 *   Estado | Fecha Ent. | Total | Moneda | Cliente | Proveedor | Es Combo |
 *   Producto 1 | Cantidad 1 | ... | Producto 5 | Cantidad 5
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$idClienteLogueado = $_SESSION['user_id'] ?? '7';

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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// ── Helper: convertir columna numérica + fila → coordenada A1 ────────────────
function coord(int $col, int $row): string {
    return Coordinate::stringFromColumnIndex($col) . $row;
}

// ── Número de productos por fila ────────────────────────────────────────────
$MAX_PRODUCTOS = 5;

// ── Encabezados de columnas fijas (en orden) ────────────────────────────────
$headersFijos = [
    'Núm. Orden',
    'Fecha Ing.',
    'Destinatario',
    'Teléfono',
    'Dirección',
    'Comentario',
    'Zona',
    'Código Postal',
    'País (texto libre)',
    'Depto. (texto libre)',
    'Municipio (texto libre)',
    'Barrio (texto libre)',
    'Entre Calles',
    'Estado',
    'Fecha Ent.',
    'Total',
    'Moneda',
    'Cliente (ID)',
    'Proveedor (ID)',
    'Es Combo (0/1)',
];
$numFijos = count($headersFijos);

// Construir array completo de encabezados (fijos + multi-producto)
$headers = $headersFijos;
for ($i = 1; $i <= $MAX_PRODUCTOS; $i++) {
    $headers[] = "Producto $i";
    $headers[] = "Cantidad $i";
}
$totalCols = count($headers);

// ── Fila de ejemplo ─────────────────────────────────────────────────────────
$ejemplo = [
    '28028424',                                                    // A: numero_orden
    '26/05/2025',                                                  // B: fecha_ingreso
    'Juan Pérez García',                                           // C: destinatario
    '50245173646',                                                 // D: telefono
    'Zona 5, calle principal, colonia oportunidad 4ta av lote 1',  // E: direccion
    '14.302022, -90.799585',                                       // F: comentario
    'Norte',                                                       // G: zona
    'GT3155',                                                      // H: codigo_postal
    'Guatemala',                                                   // I: pais
    'Guatemala',                                                   // J: departamento
    'Guatemala',                                                   // K: municipio
    '',                                                            // L: barrio
    '',                                                            // M: entre_calles
    'En ruta o proceso',                                           // N: estado
    '',                                                            // O: fecha_entrega
    '870',                                                         // P: precio_total_local
    'GTQ',                                                         // Q: moneda
    $idClienteLogueado,                                            // R: cliente (ID)
    '12',                                                          // S: id_proveedor
    '1',                                                           // T: es_combo
    // Productos
    'INMUSTEN',             '2',   // U-V: Producto 1 / Cantidad 1
    'FLEXOSAMINE CAPSULAS', '1',   // W-X: Producto 2 / Cantidad 2
    '',                     '',    // Y-Z
    '',                     '',    // AA-AB
    '',                     '',    // AC-AD
];

// ── Crear Spreadsheet ────────────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Pedidos');

// ── Escribir encabezados (fila 1) ────────────────────────────────────────────
foreach ($headers as $idx => $label) {
    $sheet->getCell(coord($idx + 1, 1))->setValue($label);
}

// ── Escribir fila de ejemplo (fila 2) ────────────────────────────────────────
foreach ($ejemplo as $idx => $valor) {
    $sheet->getCell(coord($idx + 1, 2))->setValue($valor);
}

// ── Escribir filas vacías 3–7 (con id_cliente pre-rellenado) ─────────────────
$clienteCol = 18; // columna R
for ($row = 3; $row <= 7; $row++) {
    $sheet->getCell(coord($clienteCol, $row))->setValue($idClienteLogueado);
}

// ── Estilos encabezados fijos (azul oscuro) ──────────────────────────────────
$lastFijoLetter   = Coordinate::stringFromColumnIndex($numFijos);
$lastTotalLetter  = Coordinate::stringFromColumnIndex($totalCols);
$firstProdLetter  = Coordinate::stringFromColumnIndex($numFijos + 1);

$sheet->getStyle("A1:{$lastFijoLetter}1")->applyFromArray([
    'fill' => [
        'fillType'   => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1F4E79'],
    ],
    'font' => [
        'color' => ['rgb' => 'FFFFFF'],
        'bold'  => true,
        'size'  => 10,
        'name'  => 'Calibri',
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true,
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']],
    ],
]);

// ── Estilos encabezados multi-producto (verde oscuro) ────────────────────────
if ($numFijos < $totalCols) {
    $sheet->getStyle("{$firstProdLetter}1:{$lastTotalLetter}1")->applyFromArray([
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1D6A3A'],
        ],
        'font' => [
            'color' => ['rgb' => 'FFFFFF'],
            'bold'  => true,
            'size'  => 10,
            'name'  => 'Calibri',
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']],
        ],
    ]);
}

// ── Estilo fila de ejemplo (fondo azul claro) ────────────────────────────────
$sheet->getStyle("A2:{$lastTotalLetter}2")->applyFromArray([
    'fill' => [
        'fillType'   => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'EBF5FB'],
    ],
    'font' => ['size' => 10, 'name' => 'Calibri'],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BDC3C7']],
    ],
]);

// ── Estilo filas vacías ──────────────────────────────────────────────────────
$sheet->getStyle("A3:{$lastTotalLetter}7")->applyFromArray([
    'font' => ['size' => 10, 'name' => 'Calibri'],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D5D8DC']],
    ],
]);

// ── Anchos de columnas ───────────────────────────────────────────────────────
$anchos = [
    1  => 14,  // Núm. Orden
    2  => 13,  // Fecha Ing.
    3  => 22,  // Destinatario
    4  => 16,  // Teléfono
    5  => 45,  // Dirección
    6  => 28,  // Comentario
    7  => 12,  // Zona
    8  => 14,  // Código Postal
    9  => 16,  // País
    10 => 18,  // Depto.
    11 => 18,  // Municipio
    12 => 16,  // Barrio
    13 => 14,  // Entre Calles
    14 => 20,  // Estado
    15 => 13,  // Fecha Ent.
    16 => 10,  // Total
    17 => 10,  // Moneda
    18 => 10,  // Cliente ID
    19 => 12,  // Proveedor ID
    20 => 13,  // Es Combo
];
foreach ($anchos as $colNum => $width) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colNum))->setWidth($width);
}
// Columnas de productos
for ($i = 1; $i <= $MAX_PRODUCTOS; $i++) {
    $prodCol = $numFijos + ($i - 1) * 2 + 1;
    $cantCol = $prodCol + 1;
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($prodCol))->setWidth(22);
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($cantCol))->setWidth(11);
}

// ── Alturas de filas ─────────────────────────────────────────────────────────
$sheet->getRowDimension(1)->setRowHeight(32);
for ($row = 2; $row <= 7; $row++) {
    $sheet->getRowDimension($row)->setRowHeight(18);
}

// ── Congelar primera fila ────────────────────────────────────────────────────
$sheet->freezePane('A2');

// ── Formato teléfono como texto ──────────────────────────────────────────────
$telLetter = Coordinate::stringFromColumnIndex(4);
$sheet->getStyle("{$telLetter}2:{$telLetter}7")
    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

// ── Formato fechas ───────────────────────────────────────────────────────────
foreach ([2, 15] as $fecCol) {
    $fecLetter = Coordinate::stringFromColumnIndex($fecCol);
    $sheet->getStyle("{$fecLetter}2:{$fecLetter}7")
        ->getNumberFormat()->setFormatCode('DD/MM/YYYY');
}

// ── Hoja de instrucciones ────────────────────────────────────────────────────
$instrSheet = $spreadsheet->createSheet();
$instrSheet->setTitle('Instrucciones');

$instrucciones = [
    ['CAMPO / COLUMNA',        'REQ.',  'DESCRIPCIÓN Y VALORES ACEPTADOS'],
    ['A: numero_orden',        'SÍ',    'ID externo único del pedido (número entero). Ej: 28028424'],
    ['B: fecha_ingreso',       'No',    'Fecha de ingreso en formato DD/MM/YYYY. Ej: 26/05/2025'],
    ['C: destinatario',        'SÍ',    'Nombre completo del destinatario'],
    ['D: telefono',            'SÍ',    'Teléfono con código de país. Ej: 50245173646'],
    ['E: direccion',           'SÍ',    'Dirección completa de entrega'],
    ['F: comentario',          'SÍ',    'Notas de entrega o coordenadas GPS. Ej: 14.302022, -90.799585'],
    ['G: zona',                'No',    'Zona de reparto. Ej: Norte, Sur, Centro'],
    ['H: codigo_postal',       'No',    'Código postal. Ej: GT3155'],
    ['I: pais',                'No',    'País en texto libre. Ej: Guatemala'],
    ['J: departamento',        'No',    'Departamento en texto libre. Ej: Guatemala'],
    ['K: municipio',           'No',    'Municipio en texto libre. Ej: Guatemala'],
    ['L: barrio',              'No',    'Barrio o colonia en texto libre'],
    ['M: entre_calles',        'No',    'Referencia de calles cruzadas'],
    ['N: estado',              'No',    'Nombre del estado del pedido. Ej: En ruta o proceso, Pendiente'],
    ['O: fecha_entrega',       'No',    'Fecha de entrega prometida en formato DD/MM/YYYY'],
    ['P: precio_total_local',  'SÍ',    'Precio total en moneda local (número mayor a 0). Ej: 870'],
    ['Q: moneda',              'No',    'Código de moneda. Ej: GTQ, USD'],
    ['R: cliente',             'SÍ',    'ID numérico del cliente dueño del pedido (pre-rellenado en la plantilla)'],
    ['S: id_proveedor',        'SÍ',    'ID numérico del proveedor de mensajería'],
    ['T: es_combo',            'SÍ',    '1 = combo / multi-producto  |  0 = estándar (un solo producto)'],
    ['U-V: Producto 1 / Cantidad 1', 'SÍ*', 'Nombre exacto del producto + cantidad. El producto DEBE existir en el sistema.'],
    ['W-X: Producto 2 / Cantidad 2', 'No',  'Segundo producto (opcional). Dejar vacío si no aplica.'],
    ['Y-Z: Producto 3 / Cantidad 3', 'No',  'Tercer producto (opcional).'],
    ['AA-AB: Producto 4 / Cantidad 4','No', 'Cuarto producto (opcional).'],
    ['AC-AD: Producto 5 / Cantidad 5','No', 'Quinto producto (opcional).'],
    ['', '', ''],
    ['⚠️ NOTAS IMPORTANTES', '', ''],
    ['', '', '• Los productos deben existir previamente en el sistema.'],
    ['', '', '• Si el nombre del producto no coincide exactamente → fila RECHAZADA.'],
    ['', '', '• Para 1 solo producto: es_combo=0, usar solo Producto 1 / Cantidad 1.'],
    ['', '', '• Para combos/multi: es_combo=1, llenar Producto 1, Producto 2, etc.'],
    ['', '', '• Usa "Vista Previa" antes de importar para detectar errores fila por fila.'],
];

// Encabezado instrucciones
$instrSheet->getStyle('A1:C1')->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
    'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true, 'size' => 11, 'name' => 'Calibri'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

foreach ($instrucciones as $rowIdx => $row) {
    $instrSheet->getCell('A' . ($rowIdx + 1))->setValue($row[0]);
    $instrSheet->getCell('B' . ($rowIdx + 1))->setValue($row[1]);
    $instrSheet->getCell('C' . ($rowIdx + 1))->setValue($row[2]);
}

// Color columna REQ.
for ($r = 2; $r <= count($instrucciones); $r++) {
    $val = $instrSheet->getCell('B' . $r)->getValue();
    if ($val === 'SÍ' || $val === 'SÍ*') {
        $instrSheet->getStyle('B' . $r)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'C0392B']],
        ]);
    } elseif ($val === 'No') {
        $instrSheet->getStyle('B' . $r)->applyFromArray([
            'font' => ['color' => ['rgb' => '2E86C1']],
        ]);
    }
}

// Fila de notas
$notaRow = 29; // fila de "NOTAS IMPORTANTES"
$instrSheet->getStyle("A{$notaRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => '873600'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDEBD0']],
]);

$instrSheet->getColumnDimension('A')->setWidth(30);
$instrSheet->getColumnDimension('B')->setWidth(8);
$instrSheet->getColumnDimension('C')->setWidth(80);
$instrSheet->getStyle('C1:C' . count($instrucciones))->getAlignment()->setWrapText(true);
$instrSheet->freezePane('A2');

// ── Activar hoja principal ────────────────────────────────────────────────────
$spreadsheet->setActiveSheetIndex(0);

// ── Propiedades del documento ─────────────────────────────────────────────────
$spreadsheet->getProperties()
    ->setCreator('PaqueteriaCZ')
    ->setTitle('Plantilla Importación Pedidos')
    ->setDescription('Plantilla multi-producto para importación masiva de pedidos')
    ->setKeywords('pedidos importacion xlsx plantilla');

// ── Enviar como descarga ──────────────────────────────────────────────────────
if (ob_get_length()) ob_clean();

$filename = 'plantilla_pedidos_' . date('Ymd') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
