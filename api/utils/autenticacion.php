<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware {
    // $secret_key = JWT_SECRET_KEY; // Clave secreta definida en config.php

    public function validarToken($headers) {
        try {
            // Depuración: Muestra todos los encabezados recibidos
            error_log(json_encode($headers));
    
            // Verifica si el encabezado Authorization existe
            if (!isset($headers['Authorization'])) {
                return [
                    'success' => false,
                    'message' => 'Missing Authorization header'
                ];
            }
    
            // Extraer el token del encabezado Authorization
            $authHeader = $headers['Authorization'];
            if (strpos($authHeader, 'Bearer ') !== 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid Authorization header format'
                ];
            }
    
            // Obtener solo el token
            $jwt = trim(str_replace('Bearer ', '', $authHeader));
    
            // Decodificar el token JWT
            $decoded = JWT::decode($jwt, new Key(JWT_SECRET_KEY, 'HS256'));
    
            return [
                'success' => true,
                'data' => $decoded
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Invalid or expired token: ' . $e->getMessage()
            ];
        }
    }
}
