<?php
/**
 * CrmInboxModel
 * 
 * Modelo para gestionar la cola de entrada (inbox) con idempotencia.
 */

require_once __DIR__ . '/conexion.php';

class CrmInboxModel {
    
    /**
     * Agrega un mensaje al inbox.
     * 
     * @param string $source Origen: 'proveedor' o 'cliente'
     * @param string $idempotencyKey Clave única para idempotencia
     * @param array $payload Payload del mensaje
     * @return array ['success' => bool, 'message' => string, 'id' => int]
     */
    public static function agregar($source, $idempotencyKey, $payload) {
        try {
            $db = (new Conexion())->conectar();
            
            // Verificar si ya existe (idempotencia)
            $existing = self::obtenerPorIdempotencyKey($source, $idempotencyKey);
            if ($existing) {
                return [
                    'success' => true,
                    'message' => 'Mensaje ya existe en inbox',
                    'id' => $existing['id'],
                    'status' => $existing['status'],
                    'duplicated' => true
                ];
            }
            
            $stmt = $db->prepare("
                INSERT INTO crm_inbox (
                    source,
                    idempotency_key,
                    payload,
                    status,
                    received_at
                ) VALUES (
                    :source,
                    :idempotency_key,
                    :payload,
                    'pending',
                    NOW()
                )
            ");
            
            $stmt->execute([
                ':source' => $source,
                ':idempotency_key' => $idempotencyKey,
                ':payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ]);
            
            $id = (int)$db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Mensaje agregado a inbox',
                'id' => $id
            ];
            
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                return [
                    'success' => false,
                    'message' => 'Mensaje duplicado (constraint violation)',
                    'duplicated' => true
                ];
            }
            
            error_log("Error adding to CRM inbox: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al agregar mensaje'];
        }
    }
    
    /**
     * Obtiene un mensaje por idempotency key.
     * 
     * @param string $source Origen del mensaje
     * @param string $idempotencyKey Clave de idempotencia
     * @return array|null Mensaje o null
     */
    public static function obtenerPorIdempotencyKey($source, $idempotencyKey) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                SELECT * FROM crm_inbox 
                WHERE source = :source AND idempotency_key = :key
            ");
            
            $stmt->execute([
                ':source' => $source,
                ':key' => $idempotencyKey
            ]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            
        } catch (Exception $e) {
            error_log("Error fetching inbox message: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene mensajes pendientes para procesar.
     * Usa FOR UPDATE SKIP LOCKED si está disponible (MariaDB 10.6+).
     * 
     * @param int $limit Límite de mensajes
     * @return array Lista de mensajes pendientes
     */
    public static function obtenerPendientes($limit = 10) {
        try {
            $db = (new Conexion())->conectar();
            
            // Intentar con SKIP LOCKED (requiere MariaDB 10.6+)
            try {
                $stmt = $db->prepare("
                    SELECT * FROM crm_inbox 
                    WHERE status = 'pending'
                    ORDER BY received_at ASC
                    LIMIT :limit
                    FOR UPDATE SKIP LOCKED
                ");
                
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                // Fallback sin SKIP LOCKED para versiones antiguas
                $stmt = $db->prepare("
                    SELECT * FROM crm_inbox 
                    WHERE status = 'pending'
                    ORDER BY received_at ASC
                    LIMIT :limit
                ");
                
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
        } catch (Exception $e) {
            error_log("Error fetching pending inbox messages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Marca un mensaje como procesado.
     * 
     * @param int $id ID del mensaje
     * @return bool True si se actualizó
     */
    public static function marcarProcesado($id) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                UPDATE crm_inbox 
                SET status = 'processed', processed_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([':id' => $id]);
            return true;
            
        } catch (Exception $e) {
            error_log("Error marking inbox message as processed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marca un mensaje como fallido.
     * 
     * @param int $id ID del mensaje
     * @param string $error Mensaje de error
     * @return bool True si se actualizó
     */
    public static function marcarFallido($id, $error) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                UPDATE crm_inbox 
                SET status = 'failed', last_error = :error, processed_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':id' => $id,
                ':error' => $error
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error marking inbox message as failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Contar mensajes por estado
     */
    public static function contarPorEstado($estado) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT COUNT(*) FROM crm_inbox WHERE status = :status");
            $stmt->execute([':status' => $estado]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error counting inbox by status: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtener fallidos
     */
    public static function obtenerFallidos($limit = 10) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT * FROM crm_inbox 
                WHERE status = 'failed'
                ORDER BY processed_at DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching failed inbox messages: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Resetea un mensaje fallido para reintentarlo.
     */
    public static function resetear($id) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                UPDATE crm_inbox 
                SET status = 'pending', last_error = NULL, processed_at = NULL
                WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);
            return true;
        } catch (Exception $e) {
            error_log("Error reseting inbox message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina un mensaje del inbox.
     */
    public static function eliminar($id) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("DELETE FROM crm_inbox WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return true;
        } catch (Exception $e) {
            error_log("Error deleting inbox message: " . $e->getMessage());
            return false;
        }
    }
}

// Alias para compatibilidad
class_alias('CrmInboxModel', 'CrmInbox');

