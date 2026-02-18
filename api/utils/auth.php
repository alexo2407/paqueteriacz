<?php
/**
 * AuthController
 *
 * Responsable de la lógica de autenticación: verificar credenciales
 * y emitir un JWT con la información mínima del usuario.
 *
 * Usage:
 *  $auth = new AuthController();
 *  $resp = $auth->login($email, $password);
 *  if ($resp['success']) { $token = $resp['token']; }
 *
 * Notes:
 *  - The secret key is read from JWT_SECRET_KEY (in config.php).
 *  - The token payload includes 'data' with id, nombre and rol.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modelo/usuario.php';

use Firebase\JWT\JWT;

class AuthController {
    private $secret_key = JWT_SECRET_KEY; // Clave secreta definida en config.php

    /**
     * Intentar iniciar sesión con email/password.
     * @param string $email
     * @param string $password
     * @return array { success: bool, token: string } on success or { success: false, message: string }
     */
    public function login($email, $password) {
        // Verificar credenciales del usuario en la base de datos
        $usuarioModel = new UsuarioModel();
        $user = $usuarioModel->verificarCredenciales($email, $password);

        if ($user) {
            // Crear el payload del token con claims mínimos
            $payload = [
                'iss' => 'http://localhost', // Emisor
                'aud' => 'http://localhost', // Audiencia
                'iat' => time(),             // Fecha de emisión
                'data' => [
                    'id' => $user['ID_Usuario'],
                    'nombre' => $user['Usuario'],
                    'rol' => $user['Rol']
                ]
            ];

            // Generar el token JWT usando HS256
            $jwt = JWT::encode($payload, $this->secret_key, 'HS256');

            return [
                'success' => true,
                'token' => $jwt,
                'user' => [
                    'id' => $user['ID_Usuario'],
                    'nombre' => $user['Usuario'],
                    'rol' => $user['Rol'],
                    'roles' => $user['RolesNombres'] ?? []
                ]
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Credenciales inválidas'
            ];
        }
    }
}
