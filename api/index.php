<?php

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */


require_once __DIR__ . '/../config/config.php'; // Configuraciones globales
require_once __DIR__ . '/../vendor/autoload.php'; // Autoload para dependencias

header('Content-Type: application/json');

// Normalizar la ruta solicitada (sin query string) y quitar slash final
$rawPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($rawPath, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Rutas explícitas mínimas (usar includes relativos correctos)
if (preg_match('/\/api\/auth\/login$/', $path) && $method === 'POST') {
    require_once __DIR__ . '/auth/login.php';
    exit;
}

if (preg_match('/\/api\/pedidos\/listar$/', $path) && $method === 'GET') {
    require_once __DIR__ . '/pedidos/listar.php';
    exit;
}

if (preg_match('/\/api\/pedidos\/crear$/', $path) && $method === 'POST') {
    require_once __DIR__ . '/pedidos/crear.php';
    exit;
}

// Si la ruta está bajo /api/ devolvemos JSON 404 para APIs
if (strpos($path, '/api/') === 0) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 404,
            'message' => 'El endpoint solicitado no existe.',
            'path' => $path,
            'timestamp' => date('c')
        ]
    ]);
    exit;
}

// No es una ruta API: redirigir a la página 404 del sitio
http_response_code(404);
header("Location: 404/");
exit;
