<?php


header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Authorization');

// Cargar helpers y modelos con rutas relativas correctas desde /api/pedidos
require_once __DIR__ . '/../utils/autenticacion.php';
require_once __DIR__ . '/../utils/responder.php';
// Asegurar que el modelo de Pedidos esté disponible antes de incluir el controlador
require_once __DIR__ . '/../../modelo/pedido.php';
require_once __DIR__ . '/../../controlador/pedido.php';

// Verificar autenticación
$token = AuthMiddleware::obtenerTokenDeHeaders();

if (!$token) {
    responder(false, 'Token requerido', null, 401);
    exit;
}
$auth = new AuthMiddleware();
$validacion = $auth->validarToken($token);

if (!$validacion['success']) {
    responder(false, $validacion['message'], null, 403);
    exit;
}

// Obtener el número de orden desde la URL
$numeroOrden = $_GET['numero_orden'] ?? null;

if (!$numeroOrden) {
    responder(false, 'El parámetro numero_orden es requerido. Ejemplo: ?numero_orden=9548346', null, 400);
    exit;
}

// Scoping por usuario para clientes/proveedores (evita colisiones cuando 2 clientes tienen el mismo numero_orden)
$userData     = $validacion['data'] ?? [];
$authUserId   = (int)($userData['id'] ?? 0);
$authUserRole = (int)($userData['rol'] ?? 0);
$isAdmin      = ($authUserRole === (defined('ROL_ADMIN') ? ROL_ADMIN : 1));

// Si es admin, puede buscar globalmente o filtrar por ?id_cliente si se envía
$idClienteFilter = null;
if (!$isAdmin) {
    $idClienteFilter = $authUserId;
} elseif (!empty($_GET['id_cliente']) && is_numeric($_GET['id_cliente'])) {
    $idClienteFilter = (int)$_GET['id_cliente'];
}

$pedidoController = new PedidosController();
$response = $pedidoController->buscarPedidoPorNumero($numeroOrden, $idClienteFilter);

// Usar el helper responder() para mantener el sobre de respuesta consistente
if (isset($response['success']) && $response['success']) {
    responder(true, $response['message'] ?? 'Pedido encontrado', $response['data'] ?? null, 200);
} else {
    responder(false, $response['message'] ?? 'Pedido no encontrado', null, 404);
}
?>
