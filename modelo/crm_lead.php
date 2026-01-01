<?php
/**
 * CrmLeadModel
 * 
 * Modelo para gestionar leads CRM con validación de unicidad por proveedor_lead_id.
 */

require_once __DIR__ . '/conexion.php';

class CrmLeadModel {
    
    /**
     * Crea un nuevo lead CRM.
     * Valida unicidad por (proveedor_id, proveedor_lead_id).
     * 
     * @param array $data Datos del lead
     * @param int $proveedorId ID del proveedor (del JWT)
     * @return array ['success' => bool, 'message' => string, 'lead_id' => int]
     */
    public static function crearLead($data, $proveedorId) {
        try {
            $db = (new Conexion())->conectar();
            
            // Validar campos obligatorios
            if (empty($data['proveedor_lead_id'])) {
                return ['success' => false, 'message' => 'proveedor_lead_id es obligatorio'];
            }
            
            if (empty($data['fecha_hora'])) {
                return ['success' => false, 'message' => 'fecha_hora es obligatoria'];
            }
            
            // Verificar si ya existe (idempotencia)
            $existing = self::obtenerPorProveedorLeadId($proveedorId, $data['proveedor_lead_id']);
            if ($existing) {
                return [
                    'success' => false,
                    'message' => 'Lead duplicado: proveedor_lead_id ya existe',
                    'lead_id' => $existing['id'],
                    'duplicated' => true
                ];
            }
            
            // Insertar lead
            $stmt = $db->prepare("
                INSERT INTO crm_leads (
                    proveedor_id,
                    cliente_id,
                    proveedor_lead_id,
                    fecha_hora,
                    nombre,
                    telefono,
                    producto,
                    precio,
                    estado_actual,
                    duplicado,
                    created_at,
                    updated_at
                ) VALUES (
                    :proveedor_id,
                    :cliente_id,
                    :proveedor_lead_id,
                    :fecha_hora,
                    :nombre,
                    :telefono,
                    :producto,
                    :precio,
                    :estado_actual,
                    :duplicado,
                    NOW(),
                    NOW()
                )
            ");
            
            $stmt->execute([
                ':proveedor_id' => $proveedorId,
                ':cliente_id' => $data['cliente_id'] ?? null,
                ':proveedor_lead_id' => $data['proveedor_lead_id'],
                ':fecha_hora' => $data['fecha_hora'],
                ':nombre' => $data['nombre'] ?? null,
                ':telefono' => $data['telefono'] ?? null,
                ':producto' => $data['producto'] ?? null,
                ':precio' => $data['precio'] ?? null,
                ':estado_actual' => 'EN_ESPERA',
                ':duplicado' => 0
            ]);
            
            $leadId = (int)$db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Lead creado exitosamente',
                'lead_id' => $leadId
            ];
            
        } catch (PDOException $e) {
            // Capturar error de constraint único
            if ($e->getCode() == '23000') {
                return [
                    'success' => false,
                    'message' => 'Lead duplicado (constraint violation)',
                    'duplicated' => true
                ];
            }
            
            error_log("Error creating CRM lead: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al crear lead'];
        }
    }
    
    /**
     * Obtiene un lead por su ID.
     * 
     * @param int $leadId ID del lead
     * @return array|null Lead o null si no existe
     */
    public static function obtenerPorId($leadId) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                SELECT * FROM crm_leads WHERE id = :id
            ");
            
            $stmt->execute([':id' => $leadId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            
        } catch (Exception $e) {
            error_log("Error fetching CRM lead: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene un lead por proveedor_id y proveedor_lead_id.
     * 
     * @param int $proveedorId ID del proveedor
     * @param string $proveedorLeadId ID externo del proveedor
     * @return array|null Lead o null si no existe
     */
    public static function obtenerPorProveedorLeadId($proveedorId, $proveedorLeadId) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                SELECT * FROM crm_leads 
                WHERE proveedor_id = :proveedor_id 
                AND proveedor_lead_id = :proveedor_lead_id
            ");
            
            $stmt->execute([
                ':proveedor_id' => $proveedorId,
                ':proveedor_lead_id' => $proveedorLeadId
            ]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            
        } catch (Exception $e) {
            error_log("Error fetching CRM lead by proveedor_lead_id: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Actualiza el estado de un lead y registra en historial.
     * 
     * @param int $leadId ID del lead
     * @param string $estadoNuevo Nuevo estado
     * @param int $actorUserId ID del usuario que hace el cambio
     * @param string $observaciones Comentarios opcionales
     * @return array ['success' => bool, 'message' => string]
     */
    public static function actualizarEstado($leadId, $estadoNuevo, $actorUserId, $observaciones = null) {
        try {
            $db = (new Conexion())->conectar();
            $db->beginTransaction();
            
            // Obtener lead actual
            $lead = self::obtenerPorId($leadId);
            if (!$lead) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Lead no encontrado'];
            }
            
            $estadoAnterior = $lead['estado_actual'];
            
            // Actualizar estado en crm_leads
            $stmt = $db->prepare("
                UPDATE crm_leads 
                SET estado_actual = :estado_nuevo, updated_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':estado_nuevo' => $estadoNuevo,
                ':id' => $leadId
            ]);
            
            // Registrar en historial
            $stmtHistory = $db->prepare("
                INSERT INTO crm_lead_status_history (
                    lead_id,
                    estado_anterior,
                    estado_nuevo,
                    actor_user_id,
                    observaciones,
                    created_at
                ) VALUES (
                    :lead_id,
                    :estado_anterior,
                    :estado_nuevo,
                    :actor_user_id,
                    :observaciones,
                    NOW()
                )
            ");
            
            $stmtHistory->execute([
                ':lead_id' => $leadId,
                ':estado_anterior' => $estadoAnterior,
                ':estado_nuevo' => $estadoNuevo,
                ':actor_user_id' => $actorUserId,
                ':observaciones' => $observaciones
            ]);
            
            $db->commit();
            
            return [
                'success' => true,
                'message' => "Estado actualizado de {$estadoAnterior} a {$estadoNuevo}"
            ];
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error updating CRM lead status: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al actualizar estado'];
        }
    }
    
    /**
     * Lista leads con filtros y paginación.
     * 
     * @param array $filters Filtros opcionales (proveedor_id, cliente_id, estado, fecha_desde, fecha_hasta)
     * @param int $page Página actual (default: 1)
     * @param int $limit Límite por página (default: 50)
     * @return array ['total' => int, 'page' => int, 'leads' => array]
     */
    public static function listar($filters = [], $page = 1, $limit = 50) {
        try {
            $db = (new Conexion())->conectar();
            
            $where = [];
            $params = [];
            
            if (!empty($filters['proveedor_id'])) {
                $where[] = 'proveedor_id = :proveedor_id';
                $params[':proveedor_id'] = $filters['proveedor_id'];
            }
            
            if (!empty($filters['cliente_id'])) {
                $where[] = 'cliente_id = :cliente_id';
                $params[':cliente_id'] = $filters['cliente_id'];
            }
            
            if (!empty($filters['estado'])) {
                $where[] = 'estado_actual = :estado';
                $params[':estado'] = $filters['estado'];
            }
            
            if (!empty($filters['fecha_desde'])) {
                $where[] = 'fecha_hora >= :fecha_desde';
                $params[':fecha_desde'] = $filters['fecha_desde'];
            }
            
            if (!empty($filters['fecha_hasta'])) {
                $where[] = 'fecha_hora <= :fecha_hasta';
                $params[':fecha_hasta'] = $filters['fecha_hasta'] . ' 23:59:59';
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            // Contar total
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM crm_leads {$whereClause}");
            $stmtCount->execute($params);
            $total = (int)$stmtCount->fetchColumn();
            
            // Obtener página
            $offset = ($page - 1) * $limit;
            $stmt = $db->prepare("
                SELECT * FROM crm_leads 
                {$whereClause}
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'leads' => $leads
            ];
            
        } catch (Exception $e) {
            error_log("Error listing CRM leads: " . $e->getMessage());
            return ['total' => 0, 'page' => $page, 'limit' => $limit, 'leads' => []];
        }
    }
    
    /**
     * Obtiene el historial de estados de un lead.
     * 
     * @param int $leadId ID del lead
     * @return array Timeline de cambios de estado
     */
    public static function obtenerTimeline($leadId) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                SELECT 
                    h.*,
                    u.nombre as actor_nombre
                FROM crm_lead_status_history h
                LEFT JOIN usuarios u ON u.id = h.actor_user_id
                WHERE h.lead_id = :lead_id
                ORDER BY h.created_at DESC
            ");
            
            $stmt->execute([':lead_id' => $leadId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error fetching CRM lead timeline: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Contar leads por estado
     */
    public static function contarPorEstado() {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->query("
                SELECT estado_actual as estado, COUNT(*) as total 
                FROM crm_leads 
                GROUP BY estado_actual
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error counting leads by status: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener leads recientes
     */
    public static function obtenerRecientes($limit = 10) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT * FROM crm_leads ORDER BY created_at DESC LIMIT :limit");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching recent leads: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener tendencia de leads
     */
    public static function obtenerTendencia($dias = 30) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT DATE(created_at) as fecha, COUNT(*) as total
                FROM crm_leads
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :dias DAY)
                GROUP BY DATE(created_at)
                ORDER BY fecha ASC
            ");
            $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching leads trend: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Listar con filtros (wrapper del método listar existente)
     */
    public static function listarConFiltros($filtros, $page, $limit) {
        return self::listar($filtros, $page, $limit);
    }
    
    /**
     * Cambiar estado de lead (wrapper)
     */
    public static function cambiarEstado($id, $nuevoEstado, $observaciones, $userId) {
        return self::actualizarEstado($id, $nuevoEstado, $userId, $observaciones);
    }
    
    /**
     * Contar leads por proveedor
     */
    public static function contarPorProveedor($desde, $hasta) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT u.nombre, COUNT(*) as total
                FROM crm_leads l
                LEFT JOIN usuarios u ON u.id = l.proveedor_id
                WHERE l.created_at BETWEEN :desde AND :hasta
                GROUP BY l.proveedor_id, u.nombre
                ORDER BY total DESC
            ");
            $stmt->execute([':desde' => $desde, ':hasta' => $hasta . ' 23:59:59']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error counting leads by provider: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Contar leads por cliente
     */
    public static function contarPorCliente($desde, $hasta) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT u.nombre, COUNT(*) as total
                FROM crm_leads l
                LEFT JOIN usuarios u ON u.id = l.cliente_id
                WHERE l.created_at BETWEEN :desde AND :hasta
                AND l.cliente_id IS NOT NULL
                GROUP BY l.cliente_id, u.nombre
                ORDER BY total DESC
            ");
            $stmt->execute([':desde' => $desde, ':hasta' => $hasta . ' 23:59:59']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error counting leads by client: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener conversión por estado
     */
    public static function obtenerConversionPorEstado($desde, $hasta) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT estado_actual as estado, COUNT(*) as total
                FROM crm_leads
                WHERE created_at BETWEEN :desde AND :hasta
                GROUP BY estado_actual
                ORDER BY total DESC
            ");
            $stmt->execute([':desde' => $desde, ':hasta' => $hasta . ' 23:59:59']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching conversion by status: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Actualizar datos generales del lead (nombre, telefono, producto, precio, etc.)
     */
    public static function actualizarLead($id, $data) {
        try {
            $db = (new Conexion())->conectar();
            $fields = [];
            $params = [':id' => $id];

            // Campos actualizables
            $allowList = ['nombre', 'telefono', 'producto', 'precio', 'cliente_id', 'proveedor_id'];

            foreach ($allowList as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }

            if (empty($fields)) {
                return ['success' => true, 'message' => 'No hubo cambios'];
            }

            $fields[] = 'updated_at = NOW()';
            
            $sql = "UPDATE crm_leads SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Lead actualizado correctamente'];
        } catch (Exception $e) {
            error_log("Error updating CRM lead: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al actualizar lead'];
        }
    }
}

// Alias de clase para compatibilidad con el controlador
class_alias('CrmLeadModel', 'CrmLead');

