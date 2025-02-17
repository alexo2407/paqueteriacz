<?php
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/responder.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Encabezados para CORS
header('Content-Type: application/json');

// Verificar si el método es POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método no permitido', null, 405);
    exit;
}

// Leer los datos enviados en la solicitud
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
    responder(false, 'Faltan parámetros requeridos', null, 400);
    exit;
}

// Autenticar usuario y generar token
$auth = new AuthController();
$response = $auth->login($data['email'], $data['password']);


if ($response['success']) {
    responder(true, 'Login exitoso', ['token' => $response['token']]);
} else {
    responder(false, $response['message'], null, 401);
}
