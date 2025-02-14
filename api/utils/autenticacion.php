<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware {
    private $secret_key = JWT_SECRET_KEY; // Clave definida en config.php

    public function validarToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->secret_key, 'HS256'));
            return [
                'success' => true,
                'data' => (array) $decoded->data
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Token inválido o expirado'
            ];
        }
    }
}
