<?php
// Endpoint que genera la plantilla CSV dinámicamente usando el helper
require_once __DIR__ . '/../utils/csv_generator.php';

// Delimitador configurable via query param ?delimiter=, or ;
$delimiter = isset($_GET['delimiter']) && $_GET['delimiter'] === ';' ? ';' : ',';
$bom = !isset($_GET['bom']) || $_GET['bom'] !== '0';

// Ordered headers (stable)
$headers = [
    'numero_orden','destinatario','telefono','producto','cantidad','direccion','latitud','longitud','id_pais','id_departamento','municipio','barrio','zona','comentario'
];

// Example rows as generator
function templateRows() {
    yield [
        'numero_orden' => '1001',
        'destinatario' => 'Juan Pérez',
        'telefono' => '55512345',
        'producto' => 'Producto A',
        'cantidad' => 2,
        'direccion' => 'Calle 1 #123, Barrio Central',
        'latitud' => '12.13282000',
        'longitud' => '-86.25040000',
    // Use numeric IDs for country/department (replace with real ids in your DB)
    'id_pais' => 3,
    'id_departamento' => 12,
        'municipio' => 'Managua',
        'barrio' => 'Centro',
        'zona' => 'Zona 1',
        'comentario' => 'Pedido de demostración'
    ];

    // Row with commas, quotes and formula to test
    yield [
        'numero_orden' => '1002',
        'destinatario' => 'Empresa Comas S.A.',
        'telefono' => '55598765',
        'producto' => 'Producto B - edición especial',
        'cantidad' => 1,
        'direccion' => 'Residencial Vista Lago, Casa 24',
        'latitud' => '12.85702192',
        'longitud' => '-85.81782867',
    'id_pais' => 3,
    'id_departamento' => 34,
        'municipio' => 'Masatepe',
        'barrio' => 'Barrio Los Pinos',
        'zona' => 'Zona 3',
        'comentario' => 'Entregar en horario laboral'
    ];

    // Blank template row for user to fill
    yield array_fill_keys($GLOBALS['headers'], '');
}

try {
    // Use generator for rows
    $rows = templateRows();
    // Stream CSV
    generate_csv_stream($rows, $headers, null, $delimiter, $bom);
} catch (Exception $e) {
    // On error, send 500 and message
    if (!headers_sent()) header('Content-Type: text/plain', true, 500);
    echo 'Error generating CSV: ' . $e->getMessage();
    // Also log
    @file_put_contents(__DIR__ . '/../logs/import_errors.log', date('c') . " - csv_gen_error: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
}

exit;
