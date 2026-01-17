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
     * Contar leads por estado con filtros opcionales
     */
    public static function contarPorEstado($filters = []) {
        try {
            $db = (new Conexion())->conectar();
            
            $where = "WHERE 1=1";
            $params = [];
            
            if (!empty($filters['proveedor_id'])) {
                $where .= " AND proveedor_id = :proveedor_id";
                $params[':proveedor_id'] = $filters['proveedor_id'];
            }
            
            if (!empty($filters['fecha_desde'])) {
                $where .= " AND fecha_hora >= :fecha_desde";
                $params[':fecha_desde'] = $filters['fecha_desde'];
            }
            
            if (!empty($filters['fecha_hasta'])) {
                $where .= " AND fecha_hora <= :fecha_hasta";
                $params[':fecha_hasta'] = $filters['fecha_hasta'] . ' 23:59:59';
            }

            $stmt = $db->prepare("
                SELECT estado_actual as estado, COUNT(*) as total 
                FROM crm_leads 
                $where
                GROUP BY estado_actual
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error counting leads by status: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener leads recientes con filtros opcionales
     */
    public static function obtenerRecientes($limit = 10, $filters = []) {
        try {
            $db = (new Conexion())->conectar();
            
            $where = "WHERE 1=1";
            $params = [];
            
            if (!empty($filters['proveedor_id'])) {
                $where .= " AND proveedor_id = :proveedor_id";
                $params[':proveedor_id'] = $filters['proveedor_id'];
            }
            
            // Nota: Para "Recientes", usualmente queremos los últimos creados, 
            // pero si hay filtro de fechas, lo aplicamos a fecha_hora o created_at.
            // Usaremos fecha_hora para consistencia con los reportes.
            if (!empty($filters['fecha_desde'])) {
                $where .= " AND fecha_hora >= :fecha_desde";
                $params[':fecha_desde'] = $filters['fecha_desde'];
            }
            
            if (!empty($filters['fecha_hasta'])) {
                $where .= " AND fecha_hora <= :fecha_hasta";
                $params[':fecha_hasta'] = $filters['fecha_hasta'] . ' 23:59:59';
            }

            $stmt = $db->prepare("SELECT * FROM crm_leads $where ORDER BY created_at DESC LIMIT :limit");
            
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching recent leads: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener tendencia de leads con filtros opcionales
     */
    public static function obtenerTendencia($dias = 30, $filters = []) {
        try {
            $db = (new Conexion())->conectar();
            
            $where = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL :dias DAY)";
            $params = [':dias' => $dias];
            
            if (!empty($filters['proveedor_id'])) {
                $where .= " AND proveedor_id = :proveedor_id";
                $params[':proveedor_id'] = $filters['proveedor_id'];
            }
            
            // Si el filtro de fechas es más específico que "últimos X días", 
            // podríamos sobrescribir el WHERE, pero por ahora mantenemos la lógica de 
            // "Tendencia de los últimos X días" filtrada por el proveedor seleccionado.
            // Si se quisiera tendencia en rango de fechas arbitrario, se ajustaría aquí.
            if (!empty($filters['fecha_desde']) && !empty($filters['fecha_hasta'])) {
                // Si hay rango explícito, ignoramos $dias y usamos el rango
                $where = "WHERE fecha_hora BETWEEN :fecha_desde AND :fecha_hasta";
                unset($params[':dias']);
                $params[':fecha_desde'] = $filters['fecha_desde'];
                $params[':fecha_hasta'] = $filters['fecha_hasta'] . ' 23:59:59';
                
                if (!empty($filters['proveedor_id'])) {
                    $where .= " AND proveedor_id = :proveedor_id";
                    $params[':proveedor_id'] = $filters['proveedor_id'];
                }
            }

            $stmt = $db->prepare("
                SELECT DATE(created_at) as fecha, COUNT(*) as total
                FROM crm_leads
                $where
                GROUP BY DATE(created_at)
                ORDER BY fecha ASC
            ");
            
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v); // Bind all params
            }
            
            // Special handling for :dias if it exists and wasn't unset (it's INT for DATE_SUB)
            // But PDO bindValue infers type usually. Strictly, INTERVAL parameterization consists of quirks. 
            // In standard MySQL via PDO, INTERVAL :param DAY works if param is int.
            // However, safe bet is to verify binding.
            
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
    
    /**
     * Obtiene métricas de leads para un proveedor con filtros opcionales.
     * 
     * @param int $proveedorId ID del proveedor
     * @param array $filters Filtros opcionales: start_date, end_date, client_id
     * @return array Métricas: total, procesados, en_espera, por_estado
     */
    public static function obtenerMetricasProveedor($proveedorId, $filters = []) {
        try {
            $db = (new Conexion())->conectar();
            
            $where = "WHERE proveedor_id = :proveedor_id";
            $params = [':proveedor_id' => $proveedorId];
            
            // Filtro de Fechas
            if (!empty($filters['start_date'])) {
                $where .= " AND created_at >= :start_date";
                $params[':start_date'] = $filters['start_date'] . " 00:00:00";
            }
            if (!empty($filters['end_date'])) {
                $where .= " AND created_at <= :end_date";
                $params[':end_date'] = $filters['end_date'] . " 23:59:59";
            }
            
            // Filtro de Cliente
            if (!empty($filters['client_id'])) {
                $where .= " AND cliente_id = :client_id";
                $params[':client_id'] = $filters['client_id'];
            }
            
            // Total de leads
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM crm_leads $where");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();
            
            // Procesados
            $stmt = $db->prepare("
                SELECT COUNT(*) as procesados
                FROM crm_leads
                $where AND estado_actual NOT IN ('EN_ESPERA', 'nuevo', 'NUEVO')
            ");
            $stmt->execute($params);
            $procesados = (int)$stmt->fetchColumn();
            
            // En espera
            $enEspera = $total - $procesados;
            
            // Distribución por estado
            $stmt = $db->prepare("
                SELECT estado_actual, COUNT(*) as cantidad
                FROM crm_leads
                $where
                GROUP BY estado_actual
                ORDER BY cantidad DESC
            ");
            $stmt->execute($params);
            $porEstado = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $porEstado[$row['estado_actual']] = (int)$row['cantidad'];
            }
            
            return [
                'total' => $total,
                'procesados' => $procesados,
                'en_espera' => $enEspera,
                'por_estado' => $porEstado,
                'filters' => $filters 
            ];
            
        } catch (Exception $e) {
            error_log("Error obteniendo métricas proveedor: " . $e->getMessage());
            return [
                'total' => 0,
                'procesados' => 0,
                'en_espera' => 0,
                'por_estado' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene la lista de clientes a los que un proveedor ha enviado leads.
     * Útil para filtros.
     * 
     * @param int $proveedorId
     * @return array Lista de clientes [id, nombre]
     */
    public static function obtenerClientesAsociados($proveedorId) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT DISTINCT u.id, u.nombre 
                FROM crm_leads l
                JOIN usuarios u ON l.cliente_id = u.id
                WHERE l.proveedor_id = :proveedor_id
                ORDER BY u.nombre ASC
            ");
            $stmt->execute([':proveedor_id' => $proveedorId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching associated clients: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta los leads en estado EN_ESPERA para un proveedor específico.
     * 
     * @param int $proveedorId
     * @return int
     */
    public static function contarPendientesProveedor($proveedorId) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM crm_leads 
                WHERE proveedor_id = :proveedor_id 
                AND estado_actual = 'EN_ESPERA'
            ");
            $stmt->execute([':proveedor_id' => $proveedorId]);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error counting pending leads: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Cuenta el total de leads en el sistema (todos los proveedores y estados).
     * Usado para verificar si mostrar el menú CRM.
     * 
     * @return int
     */
    public static function contarTotalLeads() {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->query("SELECT COUNT(*) FROM crm_leads");
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error counting total leads: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Asigna un cliente a un lead.
     * 
     * @param int $leadId ID del lead
     * @param int $clienteId ID del cliente a asignar
     * @return array ['success' => bool, 'message' => string]
     */
    public static function asignarCliente($leadId, $clienteId) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                UPDATE crm_leads 
                SET cliente_id = :cliente_id,
                    updated_at = NOW()
                WHERE id = :lead_id
            ");
            
            $stmt->execute([
                ':cliente_id' => $clienteId,
                ':lead_id' => $leadId
            ]);
            
            return ['success' => true, 'message' => 'Lead asignado correctamente'];
        } catch (Exception $e) {
            error_log("Error asignando cliente: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al asignar cliente'];
        }
    }
    
    /**
     * Obtiene leads sin cliente asignado de un proveedor.
     * 
     * @param int $proveedorId ID del proveedor
     * @return array Lista de leads sin asignar
     */
    public static function obtenerSinAsignarPorProveedor($proveedorId) {
        try {
            $db = (new Conexion())->conectar();
            
            $stmt = $db->prepare("
                SELECT * FROM crm_leads 
                WHERE proveedor_id = :proveedor_id 
                AND cliente_id IS NULL
                ORDER BY created_at DESC
            ");
            
            $stmt->execute([':proveedor_id' => $proveedorId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo leads sin asignar: " . $e->getMessage());
            return [];
        }
    }
}

// Alias de clase para compatibilidad con el controlador
class_alias('CrmLeadModel', 'CrmLead');

