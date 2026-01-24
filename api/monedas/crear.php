<?php
require_once __DIR__ . '/../../controlador/moneda.php';
require_once __DIR__ . '/../utils/responder.php';
require_once __DIR__ . '/../utils/autenticacion.php';

header('Content-Type: application/json');

$auth = new AuthMiddleware();
$token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
$check = $auth->validarToken($token);

if (!$check['success']) {
    responder(false, 'No autorizado', null, 401);
    exit;
}

require_once __DIR__ . '/../../utils/crm_roles.php';
$userId = $check['data']['id'] ?? 0;
// Allow Admin or Proveedor
if (!userHasAnyRole($userId, ['Administrador', 'Proveedor'])) {
    responder(false, 'Acceso denegado', null, 403);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    responder(false, 'Datos inválidos', null, 400);
    exit;
}

try {
    $controller = new MonedasController();
    $resultado = $controller->crear($input);
    
    if ($resultado['success']) {
        responder(true, $resultado['message'], ['id' => $resultado['id']], 201);
    } else {
        responder(false, $resultado['message'], null, 400);
    }
} catch (Exception $e) {
    responder(false, 'Error al crear moneda: ' . $e->getMessage(), null, 500);
}
