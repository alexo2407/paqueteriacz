<?php
/**
 * Importador oficial de catálogo C807 Express desde Excel
 * Uso: php cli/import_c807_excel.php [ruta_al_excel]
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../modelo/conexion.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$defaultPath = getenv('HOME') . '/Downloads/Base de Hub y departamentos (5).xlsx';
$excelFile = $argv[1] ?? $defaultPath;

if (!file_exists($excelFile)) {
    echo "ERROR: El archivo no existe: {$excelFile}\n";
    exit(1);
}

echo "Cargando archivo Excel: {$excelFile}...\n";
$spreadsheet = IOFactory::load($excelFile);
$sheet = $spreadsheet->getSheetByName('Listado');
if (!$sheet) {
    $sheet = $spreadsheet->getActiveSheet();
}

$rows = $sheet->toArray(null, true, true, false);
array_shift($rows); // Quitar encabezados

$db = (new Conexion())->conectar();

// Mapa de departamentos por código ISO
$deptoMap = [
    'AHU' => 2,
    'CAB' => 3,
    'CHA' => 4,
    'CUS' => 5,
    'LIB' => 6,
    'PAZ' => 7,
    'UNI' => 8,
    'MOR' => 9,
    'MIG' => 10,
    'SLV' => 11,
    'VIC' => 12,
    'ANA' => 13,
    'SON' => 14,
    'USU' => 15,
];

function normalizar($str) {
    $str = mb_strtolower(trim($str), 'UTF-8');
    $acentos = ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'n', 'ü'=>'u'];
    $str = strtr($str, $acentos);
    $str = preg_replace('/\b(de|la|del|el|los|las)\b/', '', $str);
    return preg_replace('/[^a-z0-9]/', '', $str);
}

// Cargar municipios actuales de la BD
$currentMunis = $db->query("SELECT id, c807_departamento_id, nombre FROM c807_municipios")->fetchAll(PDO::FETCH_ASSOC);

$indexDb = [];
foreach ($currentMunis as $cm) {
    $depId = (int)$cm['c807_departamento_id'];
    $norm = normalizar($cm['nombre']);
    $indexDb[$depId][$norm] = (int)$cm['id'];
}

$actualizados = 0;
$insertados = 0;

$stmtUpd = $db->prepare("
    UPDATE c807_municipios 
    SET nombre = :nombre,
        codigo_estacion = :codigo_estacion,
        estacion = :estacion,
        codigo_distrito = :codigo_distrito,
        updated_at = NOW()
    WHERE id = :id
");

$stmtIns = $db->prepare("
    INSERT INTO c807_municipios (c807_departamento_id, nombre, codigo_estacion, estacion, codigo_distrito, created_at, updated_at)
    VALUES (:c807_dep_id, :nombre, :codigo_estacion, :estacion, :codigo_distrito, NOW(), NOW())
");

foreach ($rows as $r) {
    if (empty($r[3]) || empty($r[5])) continue;

    $hub          = trim((string)$r[1]);
    $hubCod       = trim((string)$r[2]);
    $depto        = trim((string)$r[3]);
    $deptoCod     = strtoupper(trim((string)$r[4]));
    $distrito     = trim((string)$r[5]);
    $distritoCod  = trim((string)$r[6]);

    $depId = $deptoMap[$deptoCod] ?? null;
    if (!$depId) {
        echo "Advertencia: Código de departamento desconocido: {$deptoCod} ({$depto})\n";
        continue;
    }

    $normDistrito = normalizar($distrito);
    $existingId = $indexDb[$depId][$normDistrito] ?? null;

    // Búsqueda flexible si no hubo match exacto
    if (!$existingId && isset($indexDb[$depId])) {
        foreach ($indexDb[$depId] as $keyNorm => $candId) {
            if ($keyNorm === $normDistrito || strpos($keyNorm, $normDistrito) !== false || strpos($normDistrito, $keyNorm) !== false) {
                $existingId = $candId;
                break;
            }
        }
    }

    if ($existingId) {
        $stmtUpd->execute([
            ':nombre'          => $distrito,
            ':codigo_estacion' => $hubCod,
            ':estacion'        => $hub,
            ':codigo_distrito' => $distritoCod,
            ':id'              => $existingId,
        ]);
        $actualizados++;
    } else {
        $stmtIns->execute([
            ':c807_dep_id'     => $depId,
            ':nombre'          => $distrito,
            ':codigo_estacion' => $hubCod,
            ':estacion'        => $hub,
            ':codigo_distrito' => $distritoCod,
        ]);
        $insertados++;
        echo "Insertado nuevo distrito: {$distrito} en Depto ID {$depId}\n";
    }
}

// Actualizar registro histórico alias Puerto La Libertad (ID 901)
$stmtUpd->execute([
    ':nombre'          => 'Puerto La Libertad',
    ':codigo_estacion' => 'HSBL',
    ':estacion'        => 'HUB SAN BLAS',
    ':codigo_distrito' => 'La Libertad',
    ':id'              => 901,
]);

echo "\n--- RESUMEN DE IMPORTACIÓN C807 ---\n";
echo "Total actualizados: {$actualizados}\n";
echo "Total nuevos insertados: {$insertados}\n";
echo "Proceso finalizado exitosamente.\n";
