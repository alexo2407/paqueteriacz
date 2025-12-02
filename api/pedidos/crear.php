<?php
/**
 * POST /api/pedidos/crear
 *
 * Endpoint protegido para crear un nuevo pedido (order). Requiere
 * encabezado Authorization: Bearer <token> (JWT).
 *
 * Expected request headers:
 *  - Authorization: Bearer <JWT>
 *  - Content-Type: application/json
 *
 * Expected JSON body (example): see API docs. Important fields include:
 *  - numero_orden (int, unique)
 *  - destinatario, telefono
 *  - producto (string) or producto_id (int)
 *  - cantidad (int)
 *  - coordenadas ("lat,long")
 *  - pais, departamento, municipio (address fields required by validation)
 *  - id_moneda, id_vendedor, id_proveedor (FKs recommended)
 *
 * Responses are emitted using the standard envelope: { success, message, data }
 * Common errors:
 *  - 401: token missing/invalid
 *  - 400: invalid or empty JSON
 *  - application-specific messages: stock insuficiente, fk constraint failures, numero_orden duplicado
 */

// Encabezados para CORS y tipo de respuesta
// Encabezados para CORS y tipo de respuesta
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responder preflight para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Dependencias: middleware de autenticación y controlador de pedidos
try {
    require_once __DIR__ . '/../utils/autenticacion.php';
    require_once __DIR__ . '/../../controlador/pedido.php';
    require_once __DIR__ . '/../../modelo/pedido.php';

    // Verificar autenticación
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token requerido']);
        exit;
    }

    $token = str_replace('Bearer ', '', $headers['Authorization']);
    $auth = new AuthMiddleware();
    $validacion = $auth->validarToken($token);
    
    if (!$validacion['success']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $validacion['message']]);
        exit;
    }

    // Leer y validar el body JSON
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos o vacíos']);
        exit;
    }

    // Delegar la creación del pedido al controlador centralizado
    $pedidoController = new PedidosController();
    $response = $pedidoController->crearPedidoAPI($data);

    // El controlador ya devuelve el sobre { success, message, data }
    http_response_code(200);
    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

?>
