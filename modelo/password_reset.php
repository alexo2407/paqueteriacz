<?php

include_once __DIR__ . '/conexion.php';

/**
 * PasswordResetModel
 * 
 * Modelo para gestionar tokens de recuperación de contraseña
 */
class PasswordResetModel {
    
    /**
     * Crear un token de recuperación de contraseña
     * 
     * @param string $email Email del usuario
     * @return array ['success' => bool, 'token' => string|null, 'message' => string]
     */
    public function crearToken($email) {
        try {
            $db = (new Conexion())->conectar();
            
            // Generar token único
            $token = bin2hex(random_bytes(32));
            
            // Calcular expiración (1 hora desde ahora)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Eliminar tokens previos del mismo email (solo puede tener uno activo)
            $this->eliminarTokensPorEmail($email);
            
            // Insertar nuevo token
            $sql = "INSERT INTO password_resets (email, token, expires_at) 
                    VALUES (:email, :token, :expires_at)";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
            $stmt->bindValue(':expires_at', $expiresAt, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'token' => $token,
                    'message' => 'Token creado exitosamente'
                ];
            }
            
            return [
                'success' => false,
                'token' => null,
                'message' => 'No se pudo crear el token'
            ];
            
        } catch (PDOException $e) {
            error_log('Error al crear token: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [
                'success' => false,
                'token' => null,
                'message' => 'Error al crear token de recuperación'
            ];
        }
    }
    
    /**
     * Validar un token de recuperación
     * 
     * @param string $token Token a validar
     * @return array ['valid' => bool, 'email' => string|null, 'message' => string]
     */
    public function validarToken($token) {
        try {
            $db = (new Conexion())->conectar();
            
            $sql = "SELECT email, expires_at, used_at 
                    FROM password_resets 
                    WHERE token = :token";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return [
                    'valid' => false,
                    'email' => null,
                    'message' => 'Token inválido o no existe'
                ];
            }
            
            // Verificar si ya fue usado
            if ($result['used_at'] !== null) {
                return [
                    'valid' => false,
                    'email' => null,
                    'message' => 'Este token ya fue utilizado'
                ];
            }
            
            // Verificar si expiró
            $now = new DateTime();
            $expiresAt = new DateTime($result['expires_at']);
            
            if ($now > $expiresAt) {
                return [
                    'valid' => false,
                    'email' => null,
                    'message' => 'El token ha expirado. Por favor solicita uno nuevo.'
                ];
            }
            
            // Token válido
            return [
                'valid' => true,
                'email' => $result['email'],
                'message' => 'Token válido'
            ];
            
        } catch (PDOException $e) {
            error_log('Error al validar token: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [
                'valid' => false,
                'email' => null,
                'message' => 'Error al validar token'
            ];
        }
    }
    
    /**
     * Marcar un token como usado
     * 
     * @param string $token Token a marcar
     * @return bool
     */
    public function marcarComoUsado($token) {
        try {
            $db = (new Conexion())->conectar();
            
            $sql = "UPDATE password_resets 
                    SET used_at = NOW() 
                    WHERE token = :token";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log('Error al marcar token como usado: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }
    
    /**
     * Eliminar todos los tokens de un email específico
     * 
     * @param string $email Email del usuario
     * @return bool
     */
    public function eliminarTokensPorEmail($email) {
        try {
            $db = (new Conexion())->conectar();
            
            $sql = "DELETE FROM password_resets WHERE email = :email";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log('Error al eliminar tokens: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }
    
    /**
     * Limpiar tokens expirados (más de 24 horas)
     * 
     * @return int Número de tokens eliminados
     */
    public function limpiarTokensExpirados() {
        try {
            $db = (new Conexion())->conectar();
            
            $sql = "DELETE FROM password_resets 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log('Error al limpiar tokens expirados: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return 0;
        }
    }
    
    /**
     * Verificar si un email existe en la tabla de usuarios
     * 
     * @param string $email Email a verificar
     * @return bool
     */
    public function emailExiste($email) {
        try {
            $db = (new Conexion())->conectar();
            
            $sql = "SELECT id FROM usuarios WHERE email = :email";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetch() !== false;
            
        } catch (PDOException $e) {
            error_log('Error al verificar email: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }
}
