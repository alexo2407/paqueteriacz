<?php
require_once __DIR__ . '/../../controlador/moneda.php';
require_once __DIR__ . '/../utils/responder.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../utils/autenticacion.php';
$auth = new AuthMiddleware();
$token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
$check = $auth->validarToken($token);

if (!$check['success']) {
    responder(false, 'No autorizado', null, 401);
    exit;
}

require_once __DIR__ . '/../../utils/crm_roles.php';
$userId = $check['data']['id'] ?? 0;
// Allow Admin ONLY
if (!userHasAnyRole($userId, ['Administrador'])) {
    responder(false, 'Acceso denegado: solo administradores', null, 403);
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    responder(false, 'ID requerido', null, 400);
    exit;
}

try {
    $controller = new MonedasController();
    $resultado = $controller->eliminar($id);
    
    if ($resultado['success']) {
        responder(true, $resultado['message'], null, 200);
    } else {
        responder(false, $resultado['message'], null, 400);
    }
} catch (Exception $e) {
    responder(false, 'Error al eliminar moneda: ' . $e->getMessage(), null, 500);
}
