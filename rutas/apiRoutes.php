<?php
require_once __DIR__ . '/../controlador/pedido.php';
require_once __DIR__ . '/../api/utils/autenticacion.php';
require_once __DIR__ . '/../api/utils/responder.php';

class ApiRouter {
    public static function route($endpoint, $method, $data = null) {
        $parts = explode('/', $endpoint);
        $resource = $parts[0];
        $action = $parts[1] ?? '';

        switch ($resource) {
            case 'pedidos':
                $controller = new PedidosController();
                if ($action === 'crear' && $method === 'POST') {
                    $headers = apache_request_headers();
                    if (!isset($headers['Authorization'])) {
                        responder(false, 'Authorization token is missing', null, 401);
                        exit;
                    }

                    $token = str_replace('Bearer ', '', $headers['Authorization']);
                    $auth = new AuthMiddleware();
                    $valid = $auth->validarToken($token);

                    if (!$valid['success']) {
                        responder(false, $valid['message'], null, 401);
                        exit;
                    }

                    $response = $controller->crearPedidoAPI($data);
                    responder(true, $response['message'], $response['data']);
                } elseif ($action === 'multiple' && $method === 'POST') {
                    // Bulk import endpoint for authenticated clients
                    $headers = apache_request_headers();
                    if (!isset($headers['Authorization'])) {
                        responder(false, 'Authorization token is missing', null, 401);
                        exit;
                    }

                    $token = str_replace('Bearer ', '', $headers['Authorization']);
                    $auth = new AuthMiddleware();
                    $valid = $auth->validarToken($token);

                    if (!$valid['success']) {
                        responder(false, $valid['message'], null, 401);
                        exit;
                    }

                    // The controller prints JSON and sets headers itself.
                    $controller->createMultiple();
                    exit;
                } elseif ($action === 'buscar' && $method === 'GET') {
                    $numeroOrden = $parts[2] ?? null;
                    if (!$numeroOrden) {
                        responder(false, 'Order number is missing', null, 400);
                        exit;
                    }

                    $response = $controller->buscarPedidoPorNumero($numeroOrden);
                    responder(true, 'Order found', $response['data']);
                } else {
                    responder(false, 'Invalid API action', null, 404);
                }
                break;

            default:
                responder(false, 'Invalid API resource', null, 404);
                break;
        }
    }
}
