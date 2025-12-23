<?php
/**
 * POST /api/productos/actualizar
 *
 * Actualiza un producto existente. No requiere autenticación para simplificar.
 * Request POST: { id, nombre, sku?, descripcion?, precio_usd?, categoria_id?, marca?, unidad?, stock_minimo?, stock_maximo?, activo?, imagen_url? }
 * Response: { success, message }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../modelo/producto.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/session.php';

// Iniciar sesión para CSRF
start_secure_session();

// LOG: Registrar que se está ejecutando este archivo
error_log('API actualizar.php ejecutado - Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('POST data: ' . print_r($_POST, true));

// Leer datos POST (puede ser form-data o JSON)
$datos = [];

// Si es JSON
$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true);
if (is_array($json)) {
    $datos = $json;
} else {
    // Si es form-data
    $datos = $_POST;
}

// VALIDAR TOKEN CSRF
$csrfToken = $datos['csrf_token'] ?? null;
if (!verify_csrf_token($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    http_response_code(403);
    exit;
}

// Validar ID
$id = isset($datos['id']) ? (int)$datos['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
    exit;
}

// Validar que el producto existe
$productoExistente = ProductoModel::obtenerPorId($id);
if (!$productoExistente) {
    echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
    exit;
}

// Preparar datos para actualizar
$datosActualizar = [];

// Campos de texto
$camposTexto = ['nombre', 'sku', 'descripcion', 'marca', 'unidad', 'imagen_url'];
foreach ($camposTexto as $campo) {
    if (isset($datos[$campo])) {
        $datosActualizar[$campo] = trim($datos[$campo]);
    }
}

// Campos numéricos
if (isset($datos['precio_usd']) && $datos['precio_usd'] !== '') {
    $datosActualizar['precio_usd'] = (float)$datos['precio_usd'];
}

if (isset($datos['categoria_id'])) {
    $datosActualizar['categoria_id'] = $datos['categoria_id'] !== '' ? (int)$datos['categoria_id'] : null;
}

if (isset($datos['stock_minimo']) && $datos['stock_minimo'] !== '') {
    $datosActualizar['stock_minimo'] = (int)$datos['stock_minimo'];
}

if (isset($datos['stock_maximo']) && $datos['stock_maximo'] !== '') {
    $datosActualizar['stock_maximo'] = (int)$datos['stock_maximo'];
}

// Campo booleano activo
if (isset($datos['activo'])) {
    $datosActualizar['activo'] = ($datos['activo'] == '1' || $datos['activo'] === 1 || $datos['activo'] === true) ? 1 : 0;
}

// Validar que al menos hay un nombre
if (empty($datosActualizar['nombre']) && !isset($datosActualizar['nombre'])) {
    // Si no se envió nombre, usar el existente
    if (!isset($datos['nombre'])) {
        echo json_encode(['success' => false, 'message' => 'El nombre del producto es requerido']);
        exit;
    }
}

// Actualizar producto
$resultado = ProductoModel::actualizar($id, $datosActualizar);

if ($resultado) {
    echo json_encode([
        'success' => true,
        'message' => 'Producto actualizado correctamente',
        'id' => $id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo actualizar el producto. Verifica los datos e intenta nuevamente.'
    ]);
}
