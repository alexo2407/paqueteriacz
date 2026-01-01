<?php
/**
 * CrmIntegrationModel
 * 
 * Modelo para gestionar configuraciones de integraciones (webhooks).
 */

require_once __DIR__ . '/conexion.php';

class CrmIntegrationModel {
    
    /**
     * Obtiene una integración activa de un usuario.
     * 
     * @param int $userId ID del usuario
     * @param string $kind Tipo: 'cliente' o 'proveedor'
     * @return array|null Integración o null
     */
    public static function obtenerActiva($userId, $kind) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                SELECT * FROM crm_integrations 
                WHERE user_id = :user_id 
                AND kind = :kind
                AND is_active = 1
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':kind' => $kind
            ]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            
        } catch (Exception $e) {
            error_log("Error fetching CRM integration: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Crea o actualiza una integración.
     * 
     * @param int $userId ID del usuario
     * @param string $kind Tipo: 'cliente' o 'proveedor'
     * @param string $webhookUrl URL del webhook
     * @param string $secret Secret para firmar
     * @param bool $isActive Si está activa
     * @return array ['success' => bool, 'message' => string]
     */
    public static function guardar($userId, $kind, $webhookUrl, $secret, $isActive = true) {
        try {
            $db = (new Conexion())->conectar();
            
            // Verificar si ya existe
            $existing = $db->prepare("
                SELECT user_id FROM crm_integrations 
                WHERE user_id = :user_id AND kind = :kind
            ");
            $existing->execute([':user_id' => $userId, ':kind' => $kind]);
            
            if ($existing->fetch()) {
                // Actualizar
                $stmt = $db->prepare("
                    UPDATE crm_integrations 
                    SET webhook_url = :url, secret = :secret, is_active = :active
                    WHERE user_id = :user_id AND kind = :kind
                ");
            } else {
                // Insertar
                $stmt = $db->prepare("
                    INSERT INTO crm_integrations (user_id, kind, webhook_url, secret, is_active)
                    VALUES (:user_id, :kind, :url, :secret, :active)
                ");
            }
            
            $stmt->execute([
                ':user_id' => $userId,
                ':kind' => $kind,
                ':url' => $webhookUrl,
                ':secret' => $secret,
                ':active' => $isActive ? 1 : 0
            ]);
            
            return ['success' => true, 'message' => 'Integración guardada'];
            
        } catch (Exception $e) {
            error_log("Error saving CRM integration: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar integración'];
        }
    }
    
    /**
     * Desactiva una integración.
     * 
     * @param int $userId ID del usuario
     * @param string $kind Tipo de integración
     * @return bool True si se desactivó
     */
    public static function desactivar($userId, $kind) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                UPDATE crm_integrations 
                SET is_active = 0
                WHERE user_id = :user_id AND kind = :kind
            ");
            
            $stmt->execute([':user_id' => $userId, ':kind' => $kind]);
            return true;
            
        } catch (Exception $e) {
            error_log("Error deactivating CRM integration: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Listar todas las integraciones
     */
    public static function listarTodas() {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->query("
                SELECT i.*, u.nombre as user_name
                FROM crm_integrations i
                LEFT JOIN usuarios u ON u.id = i.user_id
                ORDER BY i.id DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error listing integrations: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Obtiene una integración por ID.
     */
    public static function obtenerPorId($id) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT i.*, u.nombre as user_name 
                FROM crm_integrations i
                LEFT JOIN usuarios u ON u.id = i.user_id
                WHERE i.id = :id
            ");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching integration by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Elimina una integración por ID.
     */
    public static function eliminar($id) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("DELETE FROM crm_integrations WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return ['success' => true, 'message' => 'Integración eliminada'];
        } catch (Exception $e) {
            error_log("Error deleting integration: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al eliminar integración'];
        }
    }
}

// Alias para compatibilidad
class_alias('CrmIntegrationModel', 'CrmIntegration');

