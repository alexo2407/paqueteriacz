<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modelo/usuario.php';

use Firebase\JWT\JWT;

class AuthController {
    private $secret_key = JWT_SECRET_KEY; // Clave secreta definida en config.php

    public function login($email, $password) {
        // Verificar credenciales del usuario en la base de datos
        $usuarioModel = new UsuarioModel();
        $user = $usuarioModel->verificarCredenciales($email, $password);

        if ($user) {
            // Crear el payload del token
            $payload = [
                'iss' => 'http://localhost', // Emisor
                'aud' => 'http://localhost', // Audiencia
                'iat' => time(),             // Fecha de emisión
                'exp' => time() + (60 * 60), // Expira en 1 hora
                'data' => [
                    'id' => $user['ID_Usuario'],
                    'nombre' => $user['Usuario'],
                    'rol' => $user['Rol']
                ]
            ];


            // Generar el token JWT
            $jwt = JWT::encode($payload, $this->secret_key, 'HS256');

            return [
                'success' => true,
                'token' => $jwt
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Credenciales inválidas'
            ];
        }
    }
}
