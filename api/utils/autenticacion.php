<?php
/**
 * AuthMiddleware
 *
 * Utility to validate JWT tokens for protected API endpoints.
 * Returns an array with 'success' and either 'data' (decoded claims)
 * or 'message' (error description).
 *
 * Example:
 *  $m = new AuthMiddleware();
 *  $result = $m->validarToken($token);
 *  if ($result['success']) { $userData = $result['data']; }
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
            // Esto permite que AuditoriaModel capture quién hace cambios via API
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
}
