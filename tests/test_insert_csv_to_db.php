<?php
// Script de prueba que parsea la plantilla CSV y trata de insertar la primera fila en la BD
// Úsalo localmente: ejecuta php tests/test_insert_csv_to_db.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modelo/pedido.php';

$csvPath = __DIR__ . '/../public/pedidos_template.csv';
if (!file_exists($csvPath)) {
    echo "Archivo no encontrado: $csvPath\n";
    exit(1);
}

$handle = fopen($csvPath, 'r');
$firstLine = fgets($handle);
$firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
$countComma = substr_count($firstLine, ',');
$countSemi = substr_count($firstLine, ';');
$delimiter = ($countSemi > $countComma) ? ';' : ',';
rewind($handle);
$header = fgetcsv($handle, 0, $delimiter);
$cols = array_map(function($c){ return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $c))); }, $header);

$line = 1;
$rows = [];
while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $line++;
    $dataRow = [];
    foreach ($cols as $i => $colName) {
        $val = isset($row[$i]) ? trim($row[$i]) : null;
        $dataRow[$colName] = $val;
    }
    $allEmpty = true; foreach ($dataRow as $v) { if ($v !== null && $v !== '') { $allEmpty = false; break; } }
    if ($allEmpty) continue;
    if (isset($dataRow['latitud'])) $dataRow['latitud'] = str_replace(',', '.', $dataRow['latitud']);
    if (isset($dataRow['longitud'])) $dataRow['longitud'] = str_replace(',', '.', $dataRow['longitud']);
    $rows[] = $dataRow;
}
fclose($handle);

if (count($rows) === 0) {
    echo "No hay filas para insertar.\n";
    exit(0);
}

$r = $rows[0];
// Sobrescribir numero_orden con valor único para evitar duplicados
$r['numero_orden'] = 'TST-' . time();
$r['cantidad'] = isset($r['cantidad']) && $r['cantidad'] !== '' ? (int)$r['cantidad'] : 1;
$r['coordenadas'] = ($r['latitud'] ?? '') . ',' . ($r['longitud'] ?? '');

echo "Intentando insertar pedido de prueba con numero_orden={$r['numero_orden']}...\n";
try {
    $res = PedidosModel::crearPedido($r);
    echo "Resultado: ";
    print_r($res);
} catch (Exception $e) {
    echo "Excepción al insertar: " . $e->getMessage() . "\n";
}

?>
