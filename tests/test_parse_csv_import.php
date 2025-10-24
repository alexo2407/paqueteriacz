<?php
// Script de prueba para parsear el CSV usando la misma lógica del importador
// No inserta en la base de datos; solo valida parsing, delimitador y filas.

error_reporting(E_ALL);
ini_set('display_errors', 1);

$csvPath = __DIR__ . '/../public/pedidos_template.csv';
if (!file_exists($csvPath)) {
    echo "Archivo no encontrado: $csvPath\n";
    exit(1);
}

$handle = fopen($csvPath, 'r');
if ($handle === false) {
    echo "No se pudo abrir el archivo CSV.\n";
    exit(1);
}

$firstLine = fgets($handle);
if ($firstLine === false) {
    echo "CSV vacío.\n";
    exit(1);
}

$firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
$countComma = substr_count($firstLine, ',');
$countSemi = substr_count($firstLine, ';');
$delimiter = ($countSemi > $countComma) ? ';' : ',';
echo "Delimiter detectado: $delimiter\n";

rewind($handle);
$header = fgetcsv($handle, 0, $delimiter);
if ($header === false) {
    echo "No se pudo leer la cabecera.\n";
    exit(1);
}

$cols = array_map(function($c){ return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $c))); }, $header);
echo "Columnas encontradas: " . implode(', ', $cols) . "\n";

$required = ['numero_orden','destinatario','telefono','producto','cantidad','direccion','latitud','longitud'];
$missing = [];
foreach ($required as $r) if (!in_array($r, $cols)) $missing[] = $r;
if (!empty($missing)) {
    echo "Faltan columnas requeridas: " . implode(', ', $missing) . "\n";
    fclose($handle);
    exit(1);
}

$line = 1;
$errors = [];
$rows = [];
while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $line++;
    $dataRow = [];
    foreach ($cols as $i => $colName) {
        $val = isset($row[$i]) ? $row[$i] : null;
        if (is_string($val)) $val = trim($val);
        $dataRow[$colName] = $val;
    }
    // omitir filas completamente vacías
    $allEmpty = true; foreach ($dataRow as $v) { if ($v !== null && $v !== '') { $allEmpty = false; break; } }
    if ($allEmpty) continue;

    // normalizar decimales
    if (isset($dataRow['latitud']) && is_string($dataRow['latitud'])) $dataRow['latitud'] = str_replace(',', '.', $dataRow['latitud']);
    if (isset($dataRow['longitud']) && is_string($dataRow['longitud'])) $dataRow['longitud'] = str_replace(',', '.', $dataRow['longitud']);

    if (!isset($dataRow['latitud']) || !isset($dataRow['longitud']) || !is_numeric($dataRow['latitud']) || !is_numeric($dataRow['longitud'])) {
        $errors[] = "Línea $line: coordenadas inválidas -> " . json_encode([$dataRow['latitud'] ?? null, $dataRow['longitud'] ?? null]);
        continue;
    }

    if (empty($dataRow['numero_orden'])) {
        $errors[] = "Línea $line: numero_orden vacío.";
        continue;
    }

    $rows[] = $dataRow;
}

fclose($handle);

echo "Filas parseadas correctamente: " . count($rows) . "\n";
if (!empty($errors)) {
    echo "Errores: \n" . implode("\n", $errors) . "\n";
} else {
    echo "Sin errores de parsing.\n";
}

// Mostrar algunas filas de ejemplo
for ($i=0; $i < min(5, count($rows)); $i++) {
    echo "Fila " . ($i+1) . ": " . json_encode($rows[$i]) . "\n";
}

echo "Test de parse completo.\n";

?>
