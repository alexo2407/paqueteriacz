<?php
/**
 * LogisticsQueueService
 * 
 * Servicio para gestionar la cola de trabajos de logística.
 * Proporciona funciones para encolar, procesar y monitorear trabajos asíncronos.
 * 
 * Tipos de trabajos soportados:
 * - generar_guia: Generar documentos/etiquetas de envío
 * - actualizar_tracking: Sincronizar estados con APIs externas
 * - validar_direccion: Validar/normalizar direcciones con servicios geo
 * - notificar_estado: Enviar notificaciones de cambio de estado
 * 
 * @author Sistema Paquetería CZ
 * @version 1.0.0
 */

require_once __DIR__ . '/../modelo/conexion.php';

class LogisticsQueueService {
    
    /**
     * Tipos de trabajos válidos
     */
    const JOB_TYPES = [
        'generar_guia',
        'actualizar_tracking',
        'validar_direccion',
        'notificar_estado'
    ];
    
    /**
     * Tabla de backoff para reintentos (en segundos)
     * Intento 1: 1 minuto, 2: 5 min, 3: 15 min, 4: 1 hora, 5+: 6 horas
     */
    const BACKOFF_SECONDS = [60, 300, 900, 3600, 21600];
    
    /**
     * Encola un nuevo trabajo de logística.
     * 
     * @param string $jobType Tipo de trabajo (debe estar en JOB_TYPES)
     * @param int $pedidoId ID del pedido asociado
     * @param array $payload Datos adicionales para el trabajo (opcional)
     * @param int $maxIntentos Máximo de intentos (default: 5)
     * @return array ['success' => bool, 'message' => string, 'id' => int]
     */
    public static function queue($jobType, $pedidoId, $payload = [], $maxIntentos = 5) {
        try {
            // Validar tipo de trabajo
            if (!in_array($jobType, self::JOB_TYPES)) {
                return [
                    'success' => false,
                    'message' => "Tipo de trabajo inválido: {$jobType}"
                ];
            }
            
            // Validar pedido_id
            if (!is_numeric($pedidoId) || $pedidoId <= 0) {
                return [
                    'success' => false,
                    'message' => 'ID de pedido inválido'
                ];
            }
            
            $db = (new Conexion())->conectar();
            
            // Verificar que el pedido existe
            $stmt = $db->prepare("SELECT id FROM pedidos WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $pedidoId]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return [
                    'success' => false,
                    'message' => "Pedido {$pedidoId} no existe"
                ];
            }
            
            // Insertar trabajo en la cola
            $stmt = $db->prepare("
                INSERT INTO logistics_queue (
                    job_type,
                    pedido_id,
                    payload,
                    status,
                    attempts,
                    max_intentos,
                    next_retry_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :job_type,
                    :pedido_id,
                    :payload,
                    'pending',
                    0,
                    :max_intentos,
                    NOW(),
                    NOW(),
                    NOW()
                )
            ");
            
            $stmt->execute([
                ':job_type' => $jobType,
                ':pedido_id' => $pedidoId,
                ':payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':max_intentos' => $maxIntentos
            ]);
            
            $jobId = (int)$db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Trabajo encolado exitosamente',
                'id' => $jobId
            ];
            
        } catch (Exception $e) {
            error_log("Error encolando trabajo de logística: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al encolar trabajo: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene trabajos pendientes o fallidos listos para procesar.
     * Utiliza SKIP LOCKED para evitar race conditions en entornos con múltiples workers.
     * 
     * @param int $limit Límite de trabajos a obtener
     * @return array Lista de trabajos
     */
    public static function obtenerPendientes($limit = 10) {
        try {
            $db = (new Conexion())->conectar();
            
            // Intentar con SKIP LOCKED (MariaDB 10.6+)
            try {
                $stmt = $db->prepare("
                    SELECT * FROM logistics_queue
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
                // Fallback sin SKIP LOCKED para versiones antiguas
                $stmt = $db->prepare("
                    SELECT * FROM logistics_queue
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
            error_log("Error obteniendo trabajos pendientes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Marca un trabajo como en procesamiento.
     * 
     * @param int $id ID del trabajo
     * @return bool True si se actualizó
     */
    public static function marcarProcesando($id) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                UPDATE logistics_queue
                SET status = 'processing', updated_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([':id' => $id]);
            return true;
            
        } catch (Exception $e) {
            error_log("Error marcando trabajo como procesando: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marca un trabajo como completado exitosamente.
     * 
     * @param int $id ID del trabajo
     * @return bool True si se actualizó
     */
    public static function marcarCompletado($id) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                UPDATE logistics_queue
                SET 
                    status = 'completed',
                    processed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([':id' => $id]);
            return true;
            
        } catch (Exception $e) {
            error_log("Error marcando trabajo como completado: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Incrementa el contador de intentos y calcula el próximo reintento con backoff exponencial.
     * 
     * @param int $id ID del trabajo
     * @param string $error Mensaje de error
     * @return bool True si se actualizó
     */
    public static function incrementarIntento($id, $error) {
        try {
            $db = (new Conexion())->conectar();
            
            // Obtener trabajo actual
            $stmt = $db->prepare("SELECT attempts FROM logistics_queue WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$job) {
                return false;
            }
            
            $attempts = (int)$job['attempts'] + 1;
            
            // Calcular backoff exponencial
            $backoffIndex = min($attempts - 1, count(self::BACKOFF_SECONDS) - 1);
            $backoffSeconds = self::BACKOFF_SECONDS[$backoffIndex];
            $nextRetry = date('Y-m-d H:i:s', time() + $backoffSeconds);
            
            // Actualizar trabajo
            $stmt = $db->prepare("
                UPDATE logistics_queue
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
            error_log("Error incrementando intento de trabajo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene métricas de la cola de trabajos.
     * 
     * @return array Estadísticas de la cola
     */
    public static function obtenerMetricas() {
        try {
            $db = (new Conexion())->conectar();
            
            // Contar por estado
            $stmt = $db->query("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM logistics_queue
                GROUP BY status
            ");
            
            $byStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar por tipo de trabajo
            $stmt = $db->query("
                SELECT 
                    job_type,
                    COUNT(*) as count
                FROM logistics_queue
                GROUP BY job_type
            ");
            
            $byJobType = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Trabajos que alcanzaron máximo de intentos
            $stmt = $db->query("
                SELECT COUNT(*) as count
                FROM logistics_queue
                WHERE attempts >= max_intentos
            ");
            
            $permanentFailed = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'by_status' => $byStatus,
                'by_job_type' => $byJobType,
                'permanent_failed' => (int)$permanentFailed['count']
            ];
            
        } catch (Exception $e) {
            error_log("Error obteniendo métricas: " . $e->getMessage());
            return [
                'by_status' => [],
                'by_job_type' => [],
                'permanent_failed' => 0
            ];
        }
    }
    
    /**
     * Cuenta trabajos por estado.
     * 
     * @param string $status Estado a contar
     * @return int Cantidad de trabajos
     */
    public static function contarPorEstado($status) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT COUNT(*) FROM logistics_queue WHERE status = :status");
            $stmt->execute([':status' => $status]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error contando por estado: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtiene trabajos por pedido_id.
     * 
     * @param int $pedidoId ID del pedido
     * @return array Lista de trabajos
     */
    public static function obtenerPorPedido($pedidoId) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT * FROM logistics_queue
                WHERE pedido_id = :pedido_id
                ORDER BY created_at DESC
            ");
            $stmt->execute([':pedido_id' => $pedidoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo trabajos por pedido: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Resetea un trabajo fallido para reintentarlo inmediatamente.
     * 
     * @param int $id ID del trabajo
     * @return bool True si se actualizó
     */
    public static function resetear($id) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                UPDATE logistics_queue
                SET 
                    status = 'pending',
                    attempts = 0,
                    next_retry_at = NOW(),
                    last_error = NULL,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);
            return true;
        } catch (Exception $e) {
            error_log("Error reseteando trabajo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina un trabajo de la cola.
     * 
     * @param int $id ID del trabajo
     * @return bool True si se eliminó
     */
    public static function eliminar($id) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("DELETE FROM logistics_queue WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return true;
        } catch (Exception $e) {
            error_log("Error eliminando trabajo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpia trabajos completados antiguos.
     * 
     * @param int $dias Días de antigüedad (default: 30)
     * @return int Cantidad de trabajos eliminados
     */
    public static function limpiarCompletados($dias = 30) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                DELETE FROM logistics_queue
                WHERE status = 'completed'
                AND processed_at < DATE_SUB(NOW(), INTERVAL :dias DAY)
            ");
            $stmt->execute([':dias' => $dias]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Error limpiando trabajos completados: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene un resumen de trabajos agrupados por estado para un proveedor específico.
     * 
     * @param int $userId ID del usuario (proveedor)
     * @return array Resumen de conteos (total, pending, processing, completed, failed)
     */
    public static function obtenerResumenPorProveedor($userId) {
        try {
            $db = (new Conexion())->conectar();
            
            // Asumimos que el usuario es el proveedor del pedido
            $stmt = $db->prepare("
                SELECT 
                    lq.status, 
                    COUNT(*) as count 
                FROM logistics_queue lq
                INNER JOIN pedidos p ON p.id = lq.pedido_id
                WHERE p.id_proveedor = :userId
                GROUP BY lq.status
            ");
            
            $stmt->execute([':userId' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $summary = [
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0
            ];
            
            foreach ($rows as $row) {
                $status = $row['status'];
                $count = (int)$row['count'];
                if (isset($summary[$status])) {
                    $summary[$status] = $count;
                }
                $summary['total'] += $count;
            }
            
            return $summary;
            
        } catch (Exception $e) {
            error_log("Error obteniendo resumen por proveedor: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene los trabajos fallidos más recientes de un proveedor.
     * 
     * @param int $userId ID del usuario (proveedor)
     * @param int $limit Límite de resultados
     * @return array Lista de trabajos fallidos con detalles
     */
    public static function obtenerFallidosRecientesPorProveedor($userId, $limit = 10) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                SELECT 
                    p.numero_orden,
                    lq.job_type,
                    lq.last_error,
                    lq.updated_at,
                    lq.attempts
                FROM logistics_queue lq
                INNER JOIN pedidos p ON p.id = lq.pedido_id
                WHERE p.id_proveedor = :userId 
                  AND lq.status = 'failed'
                ORDER BY lq.updated_at DESC
                LIMIT :limit
            ");
            
            $stmt->execute([':userId' => $userId, ':limit' => $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error obteniendo fallidos por proveedor: " . $e->getMessage());
            return [];
        }
    }
}
