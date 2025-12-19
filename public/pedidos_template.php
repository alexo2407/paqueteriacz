<?php
/**
 * Generador de Plantillas CSV para Importación de Pedidos
 * 
 * Soporta 3 modos:
 * - básico: Columnas mínimas para usuarios simples
 * - avanzado: Todas las columnas disponibles
 * - ejemplo: Modo avanzado con datos de ejemplo
 * 
 * Parámetros GET:
 * - mode: basico|avanzado|ejemplo (default: ejemplo)
 * - delimiter: ,|; (default: coma)
 * - bom: 0|1 (default: 1 - incluir BOM UTF-8)
 */

// Parámetros configurables
$mode = isset($_GET['mode']) && in_array($_GET['mode'], ['basico', 'avanzado', 'ejemplo']) 
    ? $_GET['mode'] 
    : 'ejemplo';

$delimiter = isset($_GET['delimiter']) && $_GET['delimiter'] === ';' ? ';' : ',';
$bom = !isset($_GET['bom']) || $_GET['bom'] !== '0';

// Definir cabeceras según modo
$headers = [];
$rows = [];

if ($mode === 'basico') {
    // MODO BÁSICO: Solo campos esenciales
    $headers = [
        'numero_orden',
        'destinatario',
        'telefono',
        'producto_nombre',
        'cantidad',
        'direccion',
        'latitud',
        'longitud'
    ];
    
    // Filas de ejemplo
    $rows = [
        [
            'numero_orden' => '1001',
            'destinatario' => 'Juan Pérez',
            'telefono' => '55512345',
            'producto_nombre' => 'Producto Ejemplo',
            'cantidad' => 2,
            'direccion' => 'Calle 1 #123, Barrio Central',
            'latitud' => '12.13282000',
            'longitud' => '-86.25040000'
        ],
        // Fila en blanco para que el usuario complete
        array_fill_keys($headers, '')
    ];
    
} elseif ($mode === 'avanzado' || $mode === 'ejemplo') {
    // MODO AVANZADO: Todas las columnas
    $headers = [
        'numero_orden',
        'destinatario',
        'telefono',
        'id_producto',
        'producto_nombre',
        'cantidad',
        'direccion',
        'latitud',
        'longitud',
        'id_estado',
        'estado_nombre',          // NUEVO: alternativa a id_estado
        'id_moneda',
        'moneda_codigo',          // NUEVO: alternativa a id_moneda (USD, NIO, etc)
        'id_proveedor',
        'proveedor_nombre',       // NUEVO: alternativa a id_proveedor
        'id_vendedor',
        'vendedor_nombre',        // NUEVO: alternativa a id_vendedor
        'precio_local',
        'precio_usd',
        'id_pais',
        'id_departamento',
        'municipio',
        'barrio',
        'zona',
        'comentario'
    ];
    
    if ($mode === 'ejemplo') {
        // MODO EJEMPLO: Con datos de ejemplo reales mostrando USO DE NOMBRES
        $rows = [
            [
                'numero_orden' => '1001',
                'destinatario' => 'Juan Pérez',
                'telefono' => '55512345',
                'id_producto' => '',  // Dejar vacío para usar producto_nombre
                'producto_nombre' => 'Producto A',
                'cantidad' => 2,
                'direccion' => 'Calle 1 #123, Barrio Central',
                'latitud' => '12.13282000',
                'longitud' => '-86.25040000',
                'id_estado' => '',  // Usar nombre en su lugar
                'estado_nombre' => 'Pendiente',  // EJEMPLO: usando nombre en lugar de ID
                'id_moneda' => '',
                'moneda_codigo' => 'NIO',  // EJEMPLO: usando código en lugar de ID
                'id_proveedor' => '',  // Se usará el por defecto
                'proveedor_nombre' => '',
                'id_vendedor' => '',
                'vendedor_nombre' => '',
                'precio_local' => '150.00',
                'precio_usd' => '',
                'id_pais' => 3,  // Nicaragua
                'id_departamento' => 12,  // Managua
                'municipio' => 'Managua',
                'barrio' => 'Centro',
                'zona' => 'Zona 1',
                'comentario' => 'Pedido de demostración - usando NOMBRES en lugar de IDs'
            ],
            [
                'numero_orden' => '1002',
                'destinatario' => 'Empresa Comas S.A.',
                'telefono' => '55598765',
                'id_producto' => '',
                'producto_nombre' => 'Producto B - edición especial',
                'cantidad' => 1,
                'direccion' => 'Residencial Vista Lago, Casa 24',
                'latitud' => '12.85702192',
                'longitud' => '-85.81782867',
                'id_estado' => 1,  // EJEMPLO: También puedes usar IDs directamente
                'estado_nombre' => '',
                'id_moneda' => '',
                'moneda_codigo' => 'USD',  // Ejemplo con dólares
                'id_proveedor' => '',
                'proveedor_nombre' => '',
                'id_vendedor' => '',
                'vendedor_nombre' => '',
                'precio_local' => '230.50',
                'precio_usd' => '',
                'id_pais' => 3,
                'id_departamento' => 34,  // Masaya
                'municipio' => 'Masatepe',
                'barrio' => 'Barrio Los Pinos',
                'zona' => 'Zona 3',
                'comentario' => 'Entregar en horario laboral - mezclando IDs y nombres'
            ],
            // Agregar 2 filas en blanco
            array_fill_keys($headers, ''),
            array_fill_keys($headers, '')
        ];
    } else {
        // Modo avanzado sin ejemplos, solo headers
        $rows = [
            array_fill_keys($headers, '')
        ];
    }
}

// Generar nombre de archivo
$filename = "plantilla_pedidos_{$mode}.csv";

// Configurar headers HTTP para descarga
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Abrir output buffer
$output = fopen('php://output', 'w');

// Agregar BOM UTF-8 si está habilitado
if ($bom) {
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
}

// Escribir cabecera
fputcsv($output, $headers, $delimiter);

// Escribir filas
foreach ($rows as $row) {
    // Asegurar que la fila tenga todas las columnas en el orden correcto
    $orderedRow = [];
    foreach ($headers as $header) {
        $orderedRow[] = isset($row[$header]) ? $row[$header] : '';
    }
    fputcsv($output, $orderedRow, $delimiter);
}

fclose($output);
exit;

