<?php
/**
 * POST /api/auth/login
 *
 * Endpoint público que autentica a un usuario y devuelve un JWT dentro
 * del sobre de respuesta: { success, message, data: { token } }.
 *
 * Request JSON body:
 *  - email: string (required)
 *  - password: string (required)
 *
 * Success response (200):
 *  { success: true, message: 'Login exitoso', data: { token: '<JWT>' } }
 *
 * Error responses:
 *  - 400: missing parameters
 *  - 405: method not allowed
 *  - 401: invalid credentials
 */

require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/responder.php';

// Encabezados de respuesta
header('Content-Type: application/json');

// Este endpoint acepta únicamente POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // responder() fija el código HTTP y emite la respuesta JSON
    responder(false, 'Método no permitido', null, 405);
    exit;
}

// Leer payload JSON del cuerpo
$data = json_decode(file_get_contents('php://input'), true);

// Validación mínima de parámetros
if (!isset($data['email']) || !isset($data['password'])) {
    responder(false, 'Faltan parámetros requeridos', null, 400);
    exit;
}

// Autenticar usuario y generar token JWT
$auth = new AuthController();
$response = $auth->login($data['email'], $data['password']);

if ($response['success']) {
    // Devolvemos el token, datos de usuario y roles
    responder(true, 'Login exitoso', [
        'token' => $response['token'],
        'user' => $response['user'] ?? []
    ]);
} else {
    responder(false, $response['message'], null, 401);
}
