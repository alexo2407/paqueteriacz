<?php

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */


require_once __DIR__ . '/../config/config.php'; // Configuraciones globales
require_once __DIR__ . '/../vendor/autoload.php'; // Autoload para dependencias

header('Content-Type: application/json');

// Obtener la ruta solicitada
$path = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Enrutar según la solicitud
switch (true) {
    case preg_match('/^\/api\/auth\/login$/', $path) && $method === 'POST':
        require_once __DIR__ . '/api/auth/login.php';
        break;

    case preg_match('/^\/api\/pedidos\/listar$/', $path) && $method === 'GET':
        require_once __DIR__ . '/api/pedidos/listar.php';
        break;

    case preg_match('/^\/api\/pedidos\/crear$/', $path) && $method === 'POST':
        require_once __DIR__ . '/api/pedidos/crear.php';
        break;

    // Agrega más rutas según sea necesario
    default:
    http_response_code(404);

     // Redirigir a una página de error 404 si no es una solicitud API
     header("Location: 404/");
     exit;

    // Detectar si la solicitud es una API
    /*$isApiRequest = strpos($path, '/api/') === 0;

    if ($isApiRequest) {
        // Respuesta JSON mejorada
        $response = [
            'success' => false,
            'error' => [
                'code' => 404,
                'message' => 'El endpoint solicitado no existe.',
                'path' => $path,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];

        // Configurar encabezado de respuesta como JSON
        header('Content-Type: application/json');
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        // Redirigir a una página de error 404 si no es una solicitud API
        header("Location: 404/");
        exit;
    }*/

    
}
