<?php
/**
 * CrmOutboxModel
 * 
 * Modelo para gestionar la cola de salida (outbox) con reintentos y backoff.
 */

require_once __DIR__ . '/conexion.php';

class CrmOutboxModel {
    
    /**
     * Agrega un mensaje al outbox.
     * 
     * @param string $eventType Tipo: 'SEND_TO_CLIENT' o 'SEND_TO_PROVIDER'
     * @param int|null $leadId ID del lead relacionado
     * @param int $destinationUserId ID del usuario destino
     * @param array $payload Payload a enviar
     * @return array ['success' => bool, 'message' => string, 'id' => int]
     */
    public static function agregar($eventType, $leadId, $destinationUserId, $payload) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                INSERT INTO crm_outbox (
                    event_type,
                    lead_id,
                    destination_user_id,
                    payload,
                    status,
                    attempts,
                    next_retry_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :event_type,
                    :lead_id,
                    :destination_user_id,
                    :payload,
                    'pending',
                    0,
                    NOW(),
                    NOW(),
                    NOW()
                )
            ");
            
            $stmt->execute([
                ':event_type' => $eventType,
                ':lead_id' => $leadId,
                ':destination_user_id' => $destinationUserId,
                ':payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ]);
            
            $id = (int)$db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Mensaje agregado a outbox',
                'id' => $id
            ];
            
        } catch (Exception $e) {
            error_log("Error adding to CRM outbox: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al agregar mensaje'];
        }
    }
    
    /**
     * Obtiene mensajes pendientes o fallidos listos para reintento.
     * 
     * @param int $limit Límite de mensajes
     * @return array Lista de mensajes
     */
    public static function obtenerPendientes($limit = 10) {
        try {
            $db = (new Conexion())->conectar();
            
            // Intentar con SKIP LOCKED
            try {
                $stmt = $db->prepare("
                    SELECT * FROM crm_outbox 
                    WHERE status IN ('pending', 'failed')
                    AND next_retry_at <= NOW()
                    AND attempts < max_intentos
                    ORDER BY created_at ASC
                    LIMIT :limit
                    FOR UPDATE SKIP LOCKED
                ");
                
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                // Fallback sin SKIP LOCKED
                $stmt = $db->prepare("
                    SELECT * FROM crm_outbox 
                    WHERE status IN ('pending', 'failed')
                    AND next_retry_at <= NOW()
                    AND attempts < max_intentos
                    ORDER BY created_at ASC
                    LIMIT :limit
                ");
                
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
        } catch (Exception $e) {
            error_log("Error fetching pending outbox messages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Marca un mensaje como enviando (lock).
     * 
     * @param int $id ID del mensaje
     * @return bool True si se actualizó
     */
    public static function marcarEnviando($id) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                UPDATE crm_outbox 
                SET status = 'sending', updated_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([':id' => $id]);
            return true;
            
        } catch (Exception $e) {
            error_log("Error marking outbox message as sending: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marca un mensaje como enviado exitosamente.
     * 
     * @param int $id ID del mensaje
     * @return bool True si se actualizó
     */
    public static function marcarEnviado($id) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                UPDATE crm_outbox 
                SET status = 'sent', updated_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([':id' => $id]);
            return true;
            
        } catch (Exception $e) {
            error_log("Error marking outbox message as sent: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Incrementa el contador de intentos y calcula backoff.
     * 
     * @param int $id ID del mensaje
     * @param string $error Mensaje de error
     * @return bool True si se actualizó
     */
    public static function incrementarIntento($id, $error) {
        try {
            $db = (new Conexion())->conectar();
            
            // Obtener mensaje actual
            $stmt = $db->prepare("SELECT attempts FROM crm_outbox WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $msg = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$msg) {
                return false;
            }
            
            $attempts = (int)$msg['attempts'] + 1;
            
            // Backoff exponencial: 1m, 5m, 15m, 1h, 6h
            $backoffs = [60, 300, 900, 3600, 21600];
            $backoffSeconds = $backoffs[min($attempts - 1, count($backoffs) - 1)];
            $nextRetry = date('Y-m-d H:i:s', time() + $backoffSeconds);
            
            $stmt = $db->prepare("
                UPDATE crm_outbox 
                SET 
                    status = 'failed',
                    attempts = :attempts,
                    next_retry_at = :next_retry,
                    last_error = :error,
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':id' => $id,
                ':attempts' => $attempts,
                ':next_retry' => $nextRetry,
                ':error' => substr($error, 0, 1000) // Limitar longitud
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error incrementing outbox attempt: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene métricas del outbox.
     * 
     * @return array Estadísticas de la cola
     */
    public static function obtenerMetricas() {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->query("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM crm_outbox
                GROUP BY status
            ");
            
            $metricas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar fallos permanentes (max attempts)
            $stmtFailed = $db->query("
                SELECT COUNT(*) as count
                FROM crm_outbox
                WHERE attempts >= max_intentos
            ");
            
            $permanentFailed = $stmtFailed->fetch(PDO::FETCH_ASSOC);
            
            return [
                'by_status' => $metricas,
                'permanent_failed' => (int)$permanentFailed['count']
            ];
            
        } catch (Exception $e) {
            error_log("Error getting outbox metrics: " . $e->getMessage());
            return ['by_status' => [], 'permanent_failed' => 0];
        }
    }
    
    /**
     * Contar mensajes por estado
     */
    public static function contarPorEstado($estado) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT COUNT(*) FROM crm_outbox WHERE status = :status");
            $stmt->execute([':status' => $estado]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error counting outbox by status: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtener mensajes por lead_id
     */
    public static function obtenerPorLeadId($leadId) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT * FROM crm_outbox WHERE lead_id = :lead_id ORDER BY created_at DESC");
            $stmt->execute([':lead_id' => $leadId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching outbox by lead_id: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener mensajes fallidos
     */
    public static function obtenerFallidos($limit = 10) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT * FROM crm_outbox 
                WHERE status = 'failed'
                ORDER BY updated_at DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching failed outbox messages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener tasa de éxito de webhooks
     */
    public static function obtenerTasaExito($desde, $hasta) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT 
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as enviados,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as fallidos
                FROM crm_outbox
                WHERE created_at BETWEEN :desde AND :hasta
            ");
            $stmt->execute([':desde' => $desde, ':hasta' => $hasta . ' 23:59:59']);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching webhook success rate: " . $e->getMessage());
            return ['enviados' => 0, 'fallidos' => 0];
        }
    }
    /**
     * Resetea un mensaje fallido para reintentarlo inmediatamente.
     */
    public static function resetear($id) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                UPDATE crm_outbox 
                SET status = 'pending', attempts = 0, next_retry_at = NOW(), last_error = NULL, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);
            return true;
        } catch (Exception $e) {
            error_log("Error reseting outbox message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina un mensaje del outbox.
     */
    public static function eliminar($id) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("DELETE FROM crm_outbox WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return true;
        } catch (Exception $e) {
            error_log("Error deleting outbox message: " . $e->getMessage());
            return false;
        }
    }
}

// Alias para compatibilidad
class_alias('CrmOutboxModel', 'CrmOutbox');

