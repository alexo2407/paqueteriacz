<?php
/**
 * POST /api/productos/crear
 *
 * Crea un producto. Requiere Authorization: Bearer <JWT>.
 * Request JSON: { nombre: string (required), descripcion?: string, precio_usd?: number }
 * Response: { success, message, id }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../utils/autenticacion.php';
require_once __DIR__ . '/../utils/responder.php';
require_once __DIR__ . '/../../modelo/producto.php';

// Obtener headers (apache_request_headers disponible en Apache; fallback a $_SERVER)
$headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
if (empty($headers)) {
    // intentar detectar Authorization en $_SERVER
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
}

if (empty($headers['Authorization'])) {
    responder(false, 'Token requerido', null, 401);
    exit;
}

$token = str_replace('Bearer ', '', $headers['Authorization']);
$auth = new AuthMiddleware();
$valid = $auth->validarToken($token);
if (!$valid['success']) {
    responder(false, $valid['message'], null, 403);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    responder(false, 'Payload JSON inválido', null, 400);
    exit;
}

$nombre = trim($body['nombre'] ?? '');
$descripcion = isset($body['descripcion']) ? trim($body['descripcion']) : null;
$precioUsd = isset($body['precio_usd']) && $body['precio_usd'] !== '' ? $body['precio_usd'] : null;
// Optional initial stock provided in payload
$initialStock = isset($body['stock']) && $body['stock'] !== '' ? $body['stock'] : null;

if ($nombre === '') {
    responder(false, 'El campo "nombre" es obligatorio.', null, 400);
    exit;
}

// Crear producto
$newId = ProductoModel::crear($nombre, $descripcion, $precioUsd);
if ($newId === null) {
    responder(false, 'Error al crear el producto.', null, 500);
} else {
    $responseData = ['id' => $newId];

    // Si se solicitó stock inicial y es numérico, insertar movimiento en stock
    if ($initialStock !== null && is_numeric($initialStock)) {
        // obtener id de usuario desde el token. AuthMiddleware devuelve data con user info
        $userId = $valid['data']['id'] ?? null;
        // Si no hay userId, usar fallback si está configurado
        if (empty($userId) && defined('FALLBACK_USER_FOR_STOCK') && FALLBACK_USER_FOR_STOCK !== null) {
            $userId = FALLBACK_USER_FOR_STOCK;
        }

        if (!empty($userId)) {
            $cantidad = (int)$initialStock;
            $stockInsertId = ProductoModel::agregarMovimientoStock($newId, (int)$userId, $cantidad);
            if ($stockInsertId !== null) {
                $responseData['stock_inserted'] = $stockInsertId;
            } else {
                $responseData['stock_insert_error'] = 'No fue posible insertar movimiento de stock.';
            }
        } else {
            $responseData['stock_insert_error'] = 'No se proporcionó id de usuario para registrar stock.';
        }
    }

    responder(true, 'Producto creado correctamente.', $responseData, 201);
}

?>
