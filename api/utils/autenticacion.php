<?php
/**
 * AuthMiddleware
 *
 * Utility to validate JWT tokens for protected API endpoints.
 * Returns an array with 'success' and either 'data' (decoded claims)
 * or 'message' (error description).
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware {
    private $secret_key = JWT_SECRET_KEY;

    /**
     * Decodifica y valida un token JWT.
     * @param string $token
     * @return array { success: bool, data?: array, message?: string }
     */
    public function validarToken($token) {
        try {
            // JWT::decode lanza una excepción si el token no es válido o expiró
            $decoded = JWT::decode($token, new Key($this->secret_key, 'HS256'));
            $userData = (array) $decoded->data;
            
            // Establecer el ID del usuario para auditoría
            if (isset($userData['id'])) {
                $GLOBALS['API_USER_ID'] = (int)$userData['id'];
            }
            
            return [
                'success' => true,
                'data' => $userData
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Invalid or expired token: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extrae el token Bearer de los encabezados de la petición.
     * Maneja variaciones de servidor (Apache, Nginx, CGI).
     * @return string|null
     */
    public static function obtenerTokenDeHeaders() {
        $header = null;

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }

        if (!$header) {
            // Fallback a variables de servidor (común en configuraciones CGI/FastCGI)
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $header = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['Authorization'])) {
                $header = $_SERVER['Authorization'];
            }
        }

        if ($header && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
