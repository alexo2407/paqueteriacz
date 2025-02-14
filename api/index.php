<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


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
        echo json_encode(['success' => false, 'message' => 'Endpoint no encontrado']);
        break;
}
