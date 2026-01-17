<?php
/**
 * CrmNotificationModel
 * 
 * Modelo para gestionar notificaciones internas del sistema CRM.
 * Reemplaza el sistema de webhooks externos por una bandeja de entrada.
 */

require_once __DIR__ . '/conexion.php';

class CrmNotificationModel {
    
    /**
     * Crea una nueva notificación interna.
     * 
     * @param string $eventType Tipo: 'SEND_TO_CLIENT' o 'SEND_TO_PROVIDER'
     * @param int $leadId ID del lead relacionado
     * @param int $destinationUserId Usuario que recibe la notificación
     * @param array $payload Datos del evento
     * @return array ['success' => bool, 'id' => int]
     */
    public static function agregar($eventType, $leadId, $destinationUserId, $payload) {
        try {
            $db = (new Conexion())->conectar();
            
            // Determinar el tipo basado en el payload
            // Si tiene estado_anterior/estado_nuevo, es una actualización
            // Si no, es un nuevo lead
            $hasStateChange = isset($payload['estado_anterior']) && isset($payload['estado_nuevo']);
            $type = $hasStateChange ? 'status_updated' : 'new_lead';
            
            $stmt = $db->prepare("
                INSERT INTO crm_notifications (
                    user_id, type, event_type, related_lead_id, 
                    payload, is_read, created_at
                ) VALUES (
                    :user_id, :type, :event_type, :lead_id,
                    :payload, 0, NOW()
                )
            ");
            
            $stmt->execute([
                ':user_id' => $destinationUserId,
                ':type' => $type,
                ':event_type' => $eventType,
                ':lead_id' => $leadId,
                ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);
            
            return [
                'success' => true,
                'id' => $db->lastInsertId()
            ];
            
        } catch (Exception $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al crear notificación'
            ];
        }
    }
    
    /**
     * Obtiene notificaciones de un usuario.
     * 
     * @param int $userId ID del usuario
     * @param bool $onlyUnread Solo no leídas
     * @param int $limit Límite de resultados
     * @param int $offset Desplazamiento
     * @param string $search Término de búsqueda
     * @param string $startDate Fecha de inicio para filtrar
     * @param string $endDate Fecha de fin para filtrar
     * @param string $leadStatus Estado del lead para filtrar
     * @return array Lista de notificaciones
     */
    public static function obtenerPorUsuario($userId, $onlyUnread = false, $limit = 500, $offset = 0, $search = '', $startDate = null, $endDate = null, $leadStatus = null) {
        try {
            $db = (new Conexion())->conectar();
            
            // Enable emulation to allow parameter reuse (:search múltiples veces)
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            
            $sql = "
                SELECT n.*,
                       l.estado_actual as lead_status_live,
                       l.telefono as lead_phone_live,
                       l.nombre as lead_name_live
                FROM crm_notifications n
                LEFT JOIN crm_leads l ON l.id = n.related_lead_id
                WHERE (n.user_id = :user_id OR l.proveedor_id = :user_id)
            ";
            
            $params = [':user_id' => $userId];
            
            if ($onlyUnread) {
                $sql .= " AND n.is_read = 0";
            }
            
            if (!empty($search)) {
                // Convertir búsqueda a minúsculas para case-insensitive
                $searchLower = strtolower($search);
                $searchUnicode = substr(json_encode($search), 1, -1);
                
                // Remover caracteres especiales para búsqueda numérica
                $searchNumeric = preg_replace('/[^0-9]/', '', $search);
                
                // Usar COLLATE utf8mb4_unicode_ci para búsqueda insensible a acentos y mayúsculas
                $sql .= " AND (
                    COALESCE(l.nombre, '') COLLATE utf8mb4_unicode_ci LIKE :search 
                    OR COALESCE(l.telefono, '') LIKE :search 
                    OR COALESCE(l.estado_actual, '') COLLATE utf8mb4_unicode_ci LIKE :search
                    OR n.payload COLLATE utf8mb4_unicode_ci LIKE :search 
                    OR n.payload LIKE :searchUnicode";
                
                // Agregar búsqueda numérica por ID si hay dígitos en la búsqueda
                if (!empty($searchNumeric)) {
                    $sql .= " OR n.related_lead_id = :searchNumeric 
                              OR CAST(n.related_lead_id AS CHAR) LIKE :searchNumericLike";
                    $params[':searchNumeric'] = (int)$searchNumeric;
                    $params[':searchNumericLike'] = "%$searchNumeric%";
                }
                
                $sql .= " )";
                
                $params[':search'] = "%$searchLower%";
                $params[':searchUnicode'] = "%$searchUnicode%";
            }

            if ($startDate && $endDate) {
                $sql .= " AND DATE(n.created_at) BETWEEN :start AND :end";
                $params[':start'] = $startDate;
                $params[':end'] = $endDate;
            }
            
            if (!empty($leadStatus)) {
                $sql .= " AND l.estado_actual = :leadStatus";
                $params[':leadStatus'] = $leadStatus;
            }
            
            // Inject LIMIT and OFFSET directly (safe because cast to int)
            $sql .= " ORDER BY n.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
            
            $stmt = $db->prepare($sql);
            $success = $stmt->execute($params);
            

            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error fetching notifications: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Cuenta el total de notificaciones para paginación.
     */
    public static function contarTotalPorUsuario($userId, $onlyUnread = false, $search = '', $startDate = null, $endDate = null, $leadStatus = null) {
        try {
            $db = (new Conexion())->conectar();
            
            // Enable emulation to allow parameter reuse
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            
            $sql = "
                SELECT COUNT(*) 
                FROM crm_notifications n
                LEFT JOIN crm_leads l ON l.id = n.related_lead_id
                WHERE (n.user_id = :user_id OR l.proveedor_id = :user_id)
            ";
            
            $params = [':user_id' => $userId];
            
            if ($onlyUnread) {
                $sql .= " AND n.is_read = 0";
            }
            
            if (!empty($search)) {
                // Convertir búsqueda a minúsculas para case-insensitive
                $searchLower = strtolower($search);
                $searchUnicode = substr(json_encode($search), 1, -1);
                
                // Remover caracteres especiales para búsqueda numérica
                $searchNumeric = preg_replace('/[^0-9]/', '', $search);
                
                // Usar COLLATE utf8mb4_unicode_ci para búsqueda insensible a acentos y mayúsculas
                $sql .= " AND (
                    COALESCE(l.nombre, '') COLLATE utf8mb4_unicode_ci LIKE :search 
                    OR COALESCE(l.telefono, '') LIKE :search 
                    OR COALESCE(l.estado_actual, '') COLLATE utf8mb4_unicode_ci LIKE :search
                    OR n.payload COLLATE utf8mb4_unicode_ci LIKE :search 
                    OR n.payload LIKE :searchUnicode";
                
                // Agregar búsqueda numérica por ID si hay dígitos en la búsqueda
                if (!empty($searchNumeric)) {
                    $sql .= " OR n.related_lead_id = :searchNumeric 
                              OR CAST(n.related_lead_id AS CHAR) LIKE :searchNumericLike";
                    $params[':searchNumeric'] = (int)$searchNumeric;
                    $params[':searchNumericLike'] = "%$searchNumeric%";
                }
                
                $sql .= " )";
                
                $params[':search'] = "%$searchLower%";
                $params[':searchUnicode'] = "%$searchUnicode%";
            }

            if ($startDate && $endDate) {
                $sql .= " AND DATE(n.created_at) BETWEEN :start AND :end";
                $params[':start'] = $startDate;
                $params[':end'] = $endDate;
            }
            
            if (!empty($leadStatus)) {
                $sql .= " AND l.estado_actual = :leadStatus";
                $params[':leadStatus'] = $leadStatus;
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return (int)$stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Error counting notifications: " . $e->getMessage());

            return 0;
        }
    }
    
    /**
     * Cuenta notificaciones no leídas de un usuario.
     * 
     * @param int $userId ID del usuario
     * @return int Cantidad de notificaciones no leídas
     */
    public static function contarNoLeidas($userId) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM crm_notifications 
                WHERE user_id = :user_id AND is_read = 0
            ");
            
            $stmt->execute([':user_id' => $userId]);
            return (int) $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Error counting unread notifications: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Marca una notificación como leída.
     * 
     * @param int $id ID de la notificación
     * @return bool
     */
    public static function marcarLeida($id) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                UPDATE crm_notifications 
                SET is_read = 1 
                WHERE id = :id
            ");
            
            $stmt->execute([':id' => $id]);
            return true;
            
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marca todas las notificaciones de un usuario como leídas.
     * 
     * @param int $userId ID del usuario
     * @return bool
     */
    public static function marcarTodasLeidas($userId) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                UPDATE crm_notifications 
                SET is_read = 1 
                WHERE user_id = :user_id AND is_read = 0
            ");
            
            $stmt->execute([':user_id' => $userId]);
            return true;
            
        } catch (Exception $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina una notificación.
     * 
     * @param int $id ID de la notificación
     * @param int $userId ID del usuario (verificación de ownership)
     * @return bool
     */
    public static function eliminar($id, $userId) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                DELETE FROM crm_notifications 
                WHERE id = :id AND user_id = :user_id
            ");
            
            $stmt->execute([
                ':id' => $id,
                ':user_id' => $userId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error deleting notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene notificaciones relacionadas a un lead.
     * 
     * @param int $leadId ID del lead
     * @return array
     */
    public static function obtenerPorLead($leadId) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                SELECT * FROM crm_notifications 
                WHERE related_lead_id = :lead_id 
                ORDER BY created_at DESC
            ");
            
            $stmt->execute([':lead_id' => $leadId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error fetching notifications by lead: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Limpia notificaciones antiguas ya leídas.
     * 
     * @param int $days Días de antigüedad
     * @return int Cantidad eliminada
     */
    public static function limpiarAntiguas($days = 30) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                DELETE FROM crm_notifications 
                WHERE is_read = 1 
                AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            
            $stmt->execute([':days' => $days]);
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("Error cleaning old notifications: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene específicamente las notificaciones de leads que siguen en estado 'nuevo'.
     * Esto es para la lista de tareas "Por Atender", independiente de la paginación del historial.
     */
    public static function obtenerLeadsPendientes($userId) {
        try {
            $db = (new Conexion())->conectar();
            
            // Buscar notificaciones tipo new_lead donde el lead asociado siga en 'EN_ESPERA' o 'nuevo'
            $sql = "
                SELECT n.*, 
                       l.estado_actual as lead_status_live,
                       l.telefono as lead_phone_live,
                       l.nombre as lead_name_live
                FROM crm_notifications n
                JOIN crm_leads l ON l.id = n.related_lead_id
                WHERE n.user_id = :user_id 
                AND n.type = 'new_lead'
                AND (l.estado_actual = 'EN_ESPERA' OR l.estado_actual = 'nuevo' OR l.estado_actual = 'NUEVO')
                ORDER BY n.created_at DESC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error fetching pending leads: " . $e->getMessage());
            return [];
        }
    }
}
