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

        // 1. Intentar con apache_request_headers o getallheaders
        $allHeaders = [];
        if (function_exists('apache_request_headers')) {
            $allHeaders = apache_request_headers();
        } elseif (function_exists('getallheaders')) {
            $allHeaders = getallheaders();
        }

        // Buscar 'Authorization' sin importar mayúsculas/minúsculas
        foreach ($allHeaders as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $header = $value;
                break;
            }
        }

        if (!$header) {
            // 2. Fallback a variables de servidor (común en configuraciones CGI/FastCGI)
            $serverKeys = [
                'HTTP_AUTHORIZATION',
                'REDIRECT_HTTP_AUTHORIZATION',
                'Authorization',
                'authorization'
            ];
            
            foreach ($serverKeys as $key) {
                if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                    $header = $_SERVER[$key];
                    break;
                }
            }
        }

        // 3. Si aún no lo tenemos, registrar para debug (opcional)
        if (!$header) {
            // error_log("AUTH_DEBUG: No se encontró header Authorization en: " . print_r($_SERVER, true));
        }

        if ($header) {
            if (preg_match('/Bearer\s+(.+)$/i', $header, $matches)) {
                return trim($matches[1]);
            }
            
            // Fallback: Si el usuario olvidó poner 'Bearer ', pero el header empieza por 'eyJ' (común en JWT)
            // o simplemente tiene contenido, lo intentamos usar tal cual.
            $trimmed = trim($header);
            if (!empty($trimmed)) {
                return $trimmed;
            }
        }

        return null;
    }
}
