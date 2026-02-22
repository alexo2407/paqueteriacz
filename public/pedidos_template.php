<?php
/**
 * Generador de Plantillas CSV para Importación de Pedidos
 * 
 * REGLAS ESTRICTAS:
 * 1. Solo campos solicitados (14 campos).
 * 2. Ejemplos incluidos siempre.
 * 3. id_cliente siempre relleno.
 * 4. Sin campos adicionales innecesarios.
 */

// ── Sesión ──────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Forzar un ID si la sesión está vacía para que el ejemplo no venga vacío
$idClienteLogueado = $_SESSION['user_id'] ?? '7'; 

// ── Parámetros ──────────────────────────────────────────────────────────────
$delimiter = $_GET['delimiter'] ?? ',';
$useBom    = ($_GET['bom']      ?? '1') !== '0';
if (!in_array($delimiter, [',', ';'], true)) $delimiter = ',';

// ── Definición Única de Columnas (LOS 10 CAMPOS ESTRICTOS) ───────────────────
$columnas = [
    'numero_orden',        // ID externo
    'destinatario',        // Nombre
    'id_producto',         // ID Producto
    'id_cliente',          // ID Cliente (Dueño)
    'id_proveedor',        // ID Proveedor
    'telefono',            // Teléfono
    'direccion',           // Dirección
    'comentario',          // Notas
    'precio_total_local',  // Precio Local
    'es_combo',            // 1 o 0
    'codigo_postal',       // Código Postal
    'cantidad',            // Cantidad
    'id_estado',           // ID Estado
    'zona'                 // Zona
];

// ── Fila de Ejemplo ──────────────────────────────────────────────────────────
$filas = [];
$filas[] = [
    'numero_orden'       => '1001',
    'destinatario'       => 'Juan Pérez García',
    'id_producto'        => '1',
    'id_cliente'         => $idClienteLogueado,
    'id_proveedor'       => '1',
    'telefono'           => '88112233',
    'direccion'          => 'Reparto Las Colinas C-14, Managua',
    'comentario'         => 'Entregar en portón negro',
    'precio_total_local' => '150.00',
    'es_combo'           => '1',
    'codigo_postal'      => '10000',
    'cantidad'           => '1',
    'id_estado'          => '1',
    'zona'               => 'Norte'
];

// ── Salida CSV ───────────────────────────────────────────────────────────────
// Limpiar búfer para evitar errores o basura en el archivo
if (ob_get_length()) ob_clean();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="pedidos_plantilla.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

if ($useBom) {
    fwrite($output, "\xEF\xBB\xBF");
}

function esc($v, $d) {
    if ($v === null) return '';
    $v = (string)$v;
    if (strpos($v, $d) !== false || strpos($v, '"') !== false || strpos($v, "\n") !== false) {
        return '"' . str_replace('"', '""', $v) . '"';
    }
    return $v;
}

// 1. Escribir Encabezados
fwrite($output, implode($delimiter, array_map(fn($c) => esc($c, $delimiter), $columnas)) . "\r\n");

// 2. Escribir Filas de Ejemplo
foreach ($filas as $fila) {
    $data = [];
    foreach ($columnas as $col) {
        $data[] = esc($fila[$col] ?? '', $delimiter);
    }
    fwrite($output, implode($delimiter, $data) . "\r\n");
}

// 3. Escribir Filas Extras vacías (pero con id_cliente)
for ($i = 0; $i < 5; $i++) {
    $vacia = [];
    foreach ($columnas as $col) {
        if ($col === 'id_cliente') $vacia[] = esc($idClienteLogueado, $delimiter);
        else $vacia[] = '';
    }
    fwrite($output, implode($delimiter, $vacia) . "\r\n");
}

fclose($output);
exit;
