<?php
/**
 * ForwardingModel
 *
 * Modelo para operaciones CRUD sobre las tablas de forwarding:
 * - forwarding_providers: catálogo de proveedores externos
 * - forwarding_rules: reglas cliente → proveedor
 * - forwarding_log: historial de envíos
 */

include_once __DIR__ . '/conexion.php';

class ForwardingModel
{
    // =========================================================================
    // PROVIDERS
    // =========================================================================

    /**
     * Obtener todos los proveedores.
     * @param bool $soloActivos Si true, filtra solo activos
     * @return array
     */
    public static function obtenerProveedores($soloActivos = false)
    {
        try {
            $db = (new Conexion())->conectar();
            $sql = "SELECT * FROM forwarding_providers";
            if ($soloActivos) $sql .= " WHERE activo = 1";
            $sql .= " ORDER BY nombre ASC";
            $stmt = $db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerProveedores error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener un proveedor por ID.
     * @param int $id
     * @return array|null
     */
    public static function obtenerProveedorPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT * FROM forwarding_providers WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerProveedorPorId error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener un proveedor por slug (retorna el primero activo o encontrado).
     * @param string $slug
     * @return array|null
     */
    public static function obtenerProveedorPorSlug($slug)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT * FROM forwarding_providers WHERE slug = :slug ORDER BY activo DESC, id ASC LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerProveedorPorSlug error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener todos los proveedores asociados a un slug determinado.
     * @param string $slug
     * @return array
     */
    public static function obtenerProveedoresPorSlug($slug)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT * FROM forwarding_providers WHERE slug = :slug ORDER BY id ASC");
            $stmt->execute([':slug' => $slug]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerProveedoresPorSlug error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Crear un proveedor.
     * @param array $data
     * @return int|false ID del proveedor creado o false
     */
    public static function crearProveedor($data)
    {
        try {
            $db = (new Conexion())->conectar();
            try {
                $stmt = $db->prepare("
                    INSERT INTO forwarding_providers 
                        (nombre, slug, base_url, auth_endpoint, order_endpoint, auth_method, credentials, default_config, activo)
                    VALUES 
                        (:nombre, :slug, :base_url, :auth_endpoint, :order_endpoint, :auth_method, :credentials, :default_config, :activo)
                ");
                $stmt->execute([
                    ':nombre'         => $data['nombre'],
                    ':slug'           => $data['slug'],
                    ':base_url'       => rtrim($data['base_url'], '/'),
                    ':auth_endpoint'  => $data['auth_endpoint'] ?? '/api/AccountApi',
                    ':order_endpoint' => $data['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail',
                    ':auth_method'    => $data['auth_method'] ?? 'bearer_jwt',
                    ':credentials'    => is_string($data['credentials']) ? $data['credentials'] : json_encode($data['credentials']),
                    ':default_config' => isset($data['default_config']) ? (is_string($data['default_config']) ? $data['default_config'] : json_encode($data['default_config'])) : null,
                    ':activo'         => $data['activo'] ?? 1,
                ]);
            } catch (PDOException $pdoEx) {
                $stmt = $db->prepare("
                    INSERT INTO forwarding_providers 
                        (nombre, slug, base_url, auth_method, credentials, activo)
                    VALUES 
                        (:nombre, :slug, :base_url, :auth_method, :credentials, :activo)
                ");
                $stmt->execute([
                    ':nombre'      => $data['nombre'],
                    ':slug'        => $data['slug'],
                    ':base_url'    => rtrim($data['base_url'], '/'),
                    ':auth_method' => $data['auth_method'] ?? 'bearer_jwt',
                    ':credentials' => is_string($data['credentials']) ? $data['credentials'] : json_encode($data['credentials']),
                    ':activo'      => $data['activo'] ?? 1,
                ]);
            }
            $nuevoId = (int) $db->lastInsertId();
            if ($nuevoId > 0 && ($data['slug'] ?? '') === 'shipedge') {
                self::sembrarPlantillaShipEdge($nuevoId, false);
                self::sembrarEstadosShipEdge();
            }
            return $nuevoId;
        } catch (Exception $e) {
            error_log("ForwardingModel::crearProveedor error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar un proveedor.
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function actualizarProveedor($id, $data)
    {
        try {
            $db = (new Conexion())->conectar();
            $sets = [];
            $params = [':id' => $id];

            $fields = ['nombre', 'slug', 'base_url', 'auth_endpoint', 'order_endpoint', 'auth_method', 'activo'];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $sets[] = "$f = :$f";
                    $params[":$f"] = $data[$f];
                }
            }
            // Campos JSON
            if (array_key_exists('credentials', $data)) {
                $sets[] = "credentials = :credentials";
                $params[':credentials'] = is_string($data['credentials']) ? $data['credentials'] : json_encode($data['credentials']);
            }
            if (array_key_exists('default_config', $data)) {
                $sets[] = "default_config = :default_config";
                $params[':default_config'] = is_string($data['default_config']) ? $data['default_config'] : json_encode($data['default_config']);
            }

            if (empty($sets)) return false;

            $sql = "UPDATE forwarding_providers SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() >= 0;
        } catch (Exception $e) {
            error_log("ForwardingModel::actualizarProveedor error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Toggle activo de un proveedor.
     * @param int $id
     * @param int $activo 0 o 1
     * @return bool
     */
    public static function toggleProveedor($id, $activo)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("UPDATE forwarding_providers SET activo = :activo WHERE id = :id");
            $stmt->execute([':id' => $id, ':activo' => (int)$activo]);
            return true;
        } catch (Exception $e) {
            error_log("ForwardingModel::toggleProveedor error: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // RULES
    // =========================================================================

    /**
     * Obtener reglas activas para un cliente.
     * @param int $idCliente
     * @return array
     */
    public static function obtenerReglasActivasPorCliente($idCliente)
    {
        try {
            $db = (new Conexion())->conectar();

            // Intento 1: query completa con default_config
            // (puede fallar si la columna no existe aún en el schema del servidor)
            try {
                $stmt = $db->prepare("
                    SELECT r.*, p.nombre AS provider_nombre, p.slug, p.base_url,
                           p.auth_endpoint, p.order_endpoint, p.auth_method,
                           p.credentials, p.default_config AS provider_config
                    FROM forwarding_rules r
                    INNER JOIN forwarding_providers p ON p.id = r.id_provider
                    WHERE r.id_cliente = :id_cliente
                      AND r.activo = 1
                      AND p.activo = 1
                ");
                $stmt->execute([':id_cliente' => $idCliente]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $pdoEx) {
                // Fallback: la columna default_config puede no existir en todos los entornos.
                // Se loguea el error real y se reintenta sin esa columna.
                error_log("ForwardingModel::obtenerReglasActivasPorCliente: query principal falló ("
                    . $pdoEx->getMessage() . "). Usando fallback sin default_config para id_cliente={$idCliente}.");
                $stmt = $db->prepare("
                    SELECT r.*, p.nombre AS provider_nombre, p.slug, p.base_url,
                           p.auth_endpoint, p.order_endpoint, p.auth_method,
                           p.credentials, NULL AS provider_config
                    FROM forwarding_rules r
                    INNER JOIN forwarding_providers p ON p.id = r.id_provider
                    WHERE r.id_cliente = :id_cliente
                      AND r.activo = 1
                      AND p.activo = 1
                ");
                $stmt->execute([':id_cliente' => $idCliente]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerReglasActivasPorCliente error fatal para id_cliente={$idCliente}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todas las reglas con nombres de cliente y proveedor.
     * @return array
     */
    public static function obtenerTodasLasReglas()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->query("
                SELECT r.*, 
                       u.nombre AS cliente_nombre,
                       p.nombre AS provider_nombre, p.slug AS provider_slug
                FROM forwarding_rules r
                LEFT JOIN usuarios u ON u.id = r.id_cliente
                LEFT JOIN forwarding_providers p ON p.id = r.id_provider
                ORDER BY r.created_at DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerTodasLasReglas error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Crear una regla.
     * @param int $idCliente
     * @param int $idProvider
     * @param string|null $configOverride JSON
     * @return int|false
     */
    public static function crearRegla($idCliente, $idProvider, $configOverride = null)
    {
        try {
            $db = (new Conexion())->conectar();
            if (is_array($configOverride)) {
                $configOverride = json_encode($configOverride, JSON_UNESCAPED_UNICODE);
            }
            $stmt = $db->prepare("
                INSERT INTO forwarding_rules (id_cliente, id_provider, activo, config_override)
                VALUES (:id_cliente, :id_provider, 1, :config_override)
            ");
            $stmt->execute([
                ':id_cliente'      => $idCliente,
                ':id_provider'     => $idProvider,
                ':config_override' => $configOverride,
            ]);
            return (int) $db->lastInsertId();
        } catch (Exception $e) {
            error_log("ForwardingModel::crearRegla error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Toggle activo de una regla.
     * @param int $id
     * @param int $activo
     * @return bool
     */
    public static function toggleRegla($id, $activo)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("UPDATE forwarding_rules SET activo = :activo WHERE id = :id");
            $stmt->execute([':id' => $id, ':activo' => (int)$activo]);
            return true;
        } catch (Exception $e) {
            error_log("ForwardingModel::toggleRegla error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar una regla.
     * @param int $id
     * @return bool
     */
    public static function eliminarRegla($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("DELETE FROM forwarding_rules WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return true;
        } catch (Exception $e) {
            error_log("ForwardingModel::eliminarRegla error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar una regla (cliente y/o proveedor).
     * @param int $id
     * @param array $data  Claves permitidas: id_cliente, id_provider, config_override
     * @return bool
     */
    public static function actualizarRegla($id, array $data)
    {
        try {
            $db = (new Conexion())->conectar();
            $sets   = [];
            $params = [':id' => $id];

            if (isset($data['id_cliente'])) {
                $sets[]             = 'id_cliente = :id_cliente';
                $params[':id_cliente'] = (int)$data['id_cliente'];
            }
            if (isset($data['id_provider'])) {
                $sets[]               = 'id_provider = :id_provider';
                $params[':id_provider'] = (int)$data['id_provider'];
            }
            if (array_key_exists('config_override', $data)) {
                $sets[]                  = 'config_override = :config_override';
                $valOverride             = $data['config_override'];
                $params[':config_override'] = is_array($valOverride) ? json_encode($valOverride, JSON_UNESCAPED_UNICODE) : $valOverride;
            }

            if (empty($sets)) return false;

            $sql = "UPDATE forwarding_rules SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return true;
        } catch (Exception $e) {
            error_log("ForwardingModel::actualizarRegla error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si un pedido ya fue enviado exitosamente a un proveedor para una regla específica.
     * Usado para evitar reenvíos duplicados al disparar el forwarding diferido.
     *
     * @param int $idPedido
     * @param int $idRule
     * @return bool True si ya existe un registro status='success' para este pedido+regla
     */
    public static function yaFueEnviadoExitosamente(int $idPedido, int $idRule): bool
    {
        try {
            $db   = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT (
                    (SELECT COUNT(*) FROM forwarding_log
                     WHERE id_pedido = :id_pedido
                       AND id_rule   = :id_rule
                       AND status    = 'success')
                    +
                    (SELECT COUNT(*) FROM forwarding_guias fg
                     INNER JOIN forwarding_rules fr ON fr.id_provider = fg.id_provider
                     WHERE fg.id_pedido = :id_pedido2
                       AND fr.id        = :id_rule2)
                ) AS total
            ");
            $stmt->execute([
                ':id_pedido'  => $idPedido,
                ':id_rule'    => $idRule,
                ':id_pedido2' => $idPedido,
                ':id_rule2'   => $idRule,
            ]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("ForwardingModel::yaFueEnviadoExitosamente error: " . $e->getMessage());
            return false; // En caso de error, no bloquear el reenvío
        }
    }

    /**
     * Obtener el registro exitoso de un pedido y regla si ya existe.
     * @param int $idPedido
     * @param int $idRule
     * @return array|null
     */
    public static function obtenerLogExitoso(int $idPedido, int $idRule)
    {
        try {
            $db   = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT id, external_order_id, created_at
                FROM forwarding_log
                WHERE id_pedido = :id_pedido
                  AND id_rule   = :id_rule
                  AND status    = 'success'
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([
                ':id_pedido' => $idPedido,
                ':id_rule'   => $idRule,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;

            // Fallback: verificar si ya existe en forwarding_guias
            $stmt2 = $db->prepare("
                SELECT fg.id, fg.numero_guia AS external_order_id, fg.created_at
                FROM forwarding_guias fg
                INNER JOIN forwarding_rules fr ON fr.id_provider = fg.id_provider
                WHERE fg.id_pedido = :id_pedido
                  AND fr.id        = :id_rule
                ORDER BY fg.id DESC
                LIMIT 1
            ");
            $stmt2->execute([
                ':id_pedido' => $idPedido,
                ':id_rule'   => $idRule,
            ]);
            $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
            return $row2 ?: null;
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerLogExitoso error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Marcar otros logs previos en estado failed/pending como resueltos/cancelados
     * cuando un reintento tiene éxito, evitando reintentos redundantes.
     * @param int $idPedido
     * @param int $idRule
     * @param int $logExitosoId
     * @return bool
     */
    public static function marcarLogsPreviosResueltos(int $idPedido, int $idRule, int $logExitosoId): bool
    {
        try {
            $db   = (new Conexion())->conectar();
            $stmt = $db->prepare("
                UPDATE forwarding_log
                SET status = 'cancelled',
                    error_message = CONCAT('Superado por reintento exitoso (Log #', :exitoso, ')')
                WHERE id_pedido = :id_pedido
                  AND id_rule   = :id_rule
                  AND status IN ('failed', 'pending')
                  AND id != :exitoso_id
            ");
            $stmt->execute([
                ':exitoso'    => $logExitosoId,
                ':id_pedido'  => $idPedido,
                ':id_rule'    => $idRule,
                ':exitoso_id' => $logExitosoId,
            ]);
            return true;
        } catch (Exception $e) {
            error_log("ForwardingModel::marcarLogsPreviosResueltos error: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // LOGS
    // =========================================================================


    /**
     * Registrar un intento de forwarding.
     * @param array $data
     * @return int|false
     */
    public static function registrarLog($data)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                INSERT INTO forwarding_log 
                    (id_pedido, id_provider, id_rule, request_payload, response_payload, 
                     http_status, status, error_message, external_order_id, attempts)
                VALUES 
                    (:id_pedido, :id_provider, :id_rule, :request_payload, :response_payload,
                     :http_status, :status, :error_message, :external_order_id, :attempts)
            ");
            $stmt->execute([
                ':id_pedido'         => $data['id_pedido'],
                ':id_provider'       => $data['id_provider'],
                ':id_rule'           => $data['id_rule'],
                ':request_payload'   => $data['request_payload'] ?? null,
                ':response_payload'  => $data['response_payload'] ?? null,
                ':http_status'       => $data['http_status'] ?? null,
                ':status'            => $data['status'] ?? 'pending',
                ':error_message'     => $data['error_message'] ?? null,
                ':external_order_id' => $data['external_order_id'] ?? null,
                ':attempts'          => $data['attempts'] ?? 1,
            ]);
            return (int) $db->lastInsertId();
        } catch (Exception $e) {
            error_log("ForwardingModel::registrarLog error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar un log existente.
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function actualizarLog($id, $data)
    {
        try {
            $db = (new Conexion())->conectar();
            $sets = [];
            $params = [':id' => $id];

            $fields = ['request_payload', 'response_payload', 'http_status', 'status', 'error_message', 'external_order_id', 'attempts'];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $sets[] = "$f = :$f";
                    $params[":$f"] = $data[$f];
                }
            }
            if (empty($sets)) return false;

            $sql = "UPDATE forwarding_log SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return true;
        } catch (Exception $e) {
            error_log("ForwardingModel::actualizarLog error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener logs con filtros y paginación.
     * @param array $filtros  Claves opcionales: id_provider, status, fecha_desde, fecha_hasta
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function obtenerLogs($filtros = [], $limit = 50, $offset = 0)
    {
        try {
            $db = (new Conexion())->conectar();
            $where = [];
            $params = [];

            if (!empty($filtros['numero_orden'])) {
                $where[] = "pe.numero_orden LIKE :numero_orden";
                $params[':numero_orden'] = '%' . $filtros['numero_orden'] . '%';
            }
            if (!empty($filtros['id_provider'])) {
                $where[] = "fl.id_provider = :id_provider";
                $params[':id_provider'] = (int)$filtros['id_provider'];
            }
            if (!empty($filtros['status'])) {
                $where[] = "fl.status = :status";
                $params[':status'] = $filtros['status'];
            }
            if (!empty($filtros['fecha_desde'])) {
                $where[] = "fl.created_at >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }
            if (!empty($filtros['fecha_hasta'])) {
                $where[] = "fl.created_at <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT fl.*, 
                       p.nombre AS provider_nombre,
                       pe.numero_orden
                FROM forwarding_log fl
                LEFT JOIN forwarding_providers p ON p.id = fl.id_provider
                LEFT JOIN pedidos pe ON pe.id = fl.id_pedido
                $whereClause
                ORDER BY fl.created_at DESC
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerLogs error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar logs con filtros (para paginación).
     * @param array $filtros
     * @return int
     */
    public static function contarLogs($filtros = [])
    {
        try {
            $db = (new Conexion())->conectar();
            $where = [];
            $params = [];

            if (!empty($filtros['numero_orden'])) {
                $where[] = "pe.numero_orden LIKE :numero_orden";
                $params[':numero_orden'] = '%' . $filtros['numero_orden'] . '%';
            }
            if (!empty($filtros['id_provider'])) {
                $where[] = "id_provider = :id_provider";
                $params[':id_provider'] = (int)$filtros['id_provider'];
            }
            if (!empty($filtros['status'])) {
                $where[] = "status = :status";
                $params[':status'] = $filtros['status'];
            }
            if (!empty($filtros['fecha_desde'])) {
                $where[] = "created_at >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }
            if (!empty($filtros['fecha_hasta'])) {
                $where[] = "created_at <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            $sql = "SELECT COUNT(*) FROM forwarding_log fl LEFT JOIN pedidos pe ON pe.id = fl.id_pedido $whereClause";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("ForwardingModel::contarLogs error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener estadísticas para el dashboard.
     * @return array
     */
    public static function obtenerEstadisticas()
    {
        try {
            $db = (new Conexion())->conectar();

            // Proveedores activos
            $stmt = $db->query("SELECT COUNT(*) FROM forwarding_providers WHERE activo = 1");
            $proveedoresActivos = (int)$stmt->fetchColumn();

            // Reglas activas
            $stmt = $db->query("SELECT COUNT(*) FROM forwarding_rules WHERE activo = 1");
            $reglasActivas = (int)$stmt->fetchColumn();

            // Envíos de hoy
            $stmt = $db->query("SELECT COUNT(*) FROM forwarding_log WHERE DATE(created_at) = CURDATE()");
            $enviosHoy = (int)$stmt->fetchColumn();

            // Fallos de hoy
            $stmt = $db->query("SELECT COUNT(*) FROM forwarding_log WHERE DATE(created_at) = CURDATE() AND status = 'failed'");
            $fallosHoy = (int)$stmt->fetchColumn();

            // Éxitos de hoy
            $stmt = $db->query("SELECT COUNT(*) FROM forwarding_log WHERE DATE(created_at) = CURDATE() AND status = 'success'");
            $exitosHoy = (int)$stmt->fetchColumn();

            return [
                'proveedores_activos' => $proveedoresActivos,
                'reglas_activas'      => $reglasActivas,
                'envios_hoy'          => $enviosHoy,
                'fallos_hoy'          => $fallosHoy,
                'exitos_hoy'          => $exitosHoy,
            ];
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerEstadisticas error: " . $e->getMessage());
            return [
                'proveedores_activos' => 0,
                'reglas_activas'      => 0,
                'envios_hoy'          => 0,
                'fallos_hoy'          => 0,
                'exitos_hoy'          => 0,
            ];
        }
    }

    /**
     * Obtener los productos de un pedido con sus nombres.
     * @param int $idPedido
     * @return array
     */
    public static function obtenerProductosPedido($idPedido)
    {
        try {
            $db = (new Conexion())->conectar();
            try {
                $stmt = $db->prepare("
                    SELECT pp.id_producto, pp.cantidad, pp.cantidad_devuelta, pp.precio_unitario_usd,
                           pp.precio_unitario_usd AS precio_unitario,
                           pr.nombre AS producto_nombre, pr.sku
                    FROM pedidos_productos pp
                    INNER JOIN productos pr ON pr.id = pp.id_producto
                    WHERE pp.id_pedido = :id_pedido
                ");
                $stmt->execute([':id_pedido' => $idPedido]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $pdoEx) {
                $stmt = $db->prepare("
                    SELECT pp.id_producto, pp.cantidad, pp.cantidad_devuelta,
                           NULL AS precio_unitario_usd,
                           NULL AS precio_unitario,
                           pr.nombre AS producto_nombre, pr.sku
                    FROM pedidos_productos pp
                    INNER JOIN productos pr ON pr.id = pp.id_producto
                    WHERE pp.id_pedido = :id_pedido
                ");
                $stmt->execute([':id_pedido' => $idPedido]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerProductosPedido error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener datos completos de un pedido para forwarding.
     * @param int $idPedido
     * @return array|null
     */
    public static function obtenerPedidoParaForwarding($idPedido)
    {
        try {
            $db = (new Conexion())->conectar();

            // Intento 1: query completa con JOIN a municipios, barrios, departamentos, países, monedas y columnas extendidas
            try {
                $stmt = $db->prepare("
                    SELECT p.id, p.numero_orden, p.destinatario, p.telefono, p.direccion,
                           p.comentario, p.correo, p.peso, p.unidad_peso, p.bultos,
                           p.postalCode, p.codigo_postal, p.precio_total_local,
                           p.fecha_entrega, p.id_cliente, p.id_pais, p.id_moneda,
                           p.municipalitiesName, p.departmentName, p.Location, p.betweenStreets,
                           p.zona, p.code_city,
                           p.id_municipio    AS _raw_id_municipio,
                           p.id_barrio       AS _raw_id_barrio,
                           p.id_departamento AS _raw_id_departamento,
                           IFNULL(dep.nombre, '')    AS departamento,
                           IFNULL(mun.nombre, '')    AS municipio_nombre,
                           IFNULL(bar.nombre, '')    AS barrio_nombre,
                           IFNULL(pa.nombre, '')     AS pais_nombre,
                           IFNULL(pa.codigo_iso, '') AS pais_codigo_iso,
                           IFNULL(mo.codigo, '')     AS moneda_codigo
                    FROM pedidos p
                    LEFT JOIN departamentos dep ON dep.id = p.id_departamento
                    LEFT JOIN municipios mun ON mun.id = p.id_municipio
                    LEFT JOIN barrios bar ON bar.id = p.id_barrio
                    LEFT JOIN paises pa ON pa.id = p.id_pais
                    LEFT JOIN monedas mo ON mo.id = p.id_moneda
                    WHERE p.id = :id
                ");
                $stmt->execute([':id' => $idPedido]);
                $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $pdoEx) {
                // Fallback: alguna columna o tabla puede no existir en el schema actual.
                error_log("ForwardingModel::obtenerPedidoParaForwarding: query completa falló ("
                    . $pdoEx->getMessage() . "). Usando fallback simplificado para id={$idPedido}.");
                $stmt = $db->prepare("
                    SELECT p.id, p.numero_orden, p.destinatario, p.telefono, p.direccion,
                           p.comentario, p.codigo_postal, p.precio_total_local,
                           p.fecha_entrega, p.id_cliente, p.id_pais, p.id_moneda,
                           p.zona, p.code_city,
                           NULL AS correo,
                           NULL AS peso,
                           NULL AS unidad_peso,
                           1    AS bultos,
                           NULL AS postalCode,
                           NULL AS municipalitiesName,
                           NULL AS departmentName,
                           NULL AS Location,
                           NULL AS betweenStreets,
                           NULL AS _raw_id_municipio,
                           NULL AS _raw_id_barrio,
                           NULL AS _raw_id_departamento,
                           ''   AS departamento,
                           ''   AS municipio_nombre,
                           ''   AS barrio_nombre,
                           IFNULL(pa.nombre, '')     AS pais_nombre,
                           IFNULL(pa.codigo_iso, '') AS pais_codigo_iso,
                           IFNULL(mo.codigo, '')     AS moneda_codigo
                    FROM pedidos p
                    LEFT JOIN paises pa ON pa.id = p.id_pais
                    LEFT JOIN monedas mo ON mo.id = p.id_moneda
                    WHERE p.id = :id
                ");
                $stmt->execute([':id' => $idPedido]);
                $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$pedido) return null;

            $pedido['productos'] = self::obtenerProductosPedido($idPedido);
            require_once __DIR__ . '/../services/PedidoRecoleccionService.php';
            $pedido['recoleccion'] = PedidoRecoleccionService::obtenerPorPedidoId($idPedido, $db);
            return $pedido;
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerPedidoParaForwarding error fatal para id={$idPedido}: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // API FIELDS (forwarding_api_fields)
    // =========================================================================

    /**
     * Obtener todos los campos definidos para un proveedor.
     * @param int $idProvider
     * @return array
     */
    public static function obtenerApiFields($idProvider)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT f.*, m.internal_key, m.transform_rule, m.id AS mapping_id
                FROM forwarding_api_fields f
                LEFT JOIN forwarding_api_mappings m ON m.id_api_field = f.id
                WHERE f.id_provider = :id_provider
                ORDER BY f.sort_order ASC, f.id ASC
            ");
            $stmt->execute([':id_provider' => (int)$idProvider]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerApiFields error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Crear un campo de API.
     * @param array $data
     * @return int|false
     */
    public static function crearApiField($data)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                INSERT INTO forwarding_api_fields
                    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
                VALUES
                    (:id_provider, :field_path, :label, :field_type, :is_required, :default_value, :sort_order)
            ");
            $stmt->execute([
                ':id_provider'   => (int)$data['id_provider'],
                ':field_path'    => trim($data['field_path']),
                ':label'         => trim($data['label']),
                ':field_type'    => $data['field_type'] ?? 'string',
                ':is_required'   => (int)($data['is_required'] ?? 0),
                ':default_value' => $data['default_value'] ?? null,
                ':sort_order'    => (int)($data['sort_order'] ?? 0),
            ]);
            return (int)$db->lastInsertId();
        } catch (Exception $e) {
            error_log("ForwardingModel::crearApiField error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar un campo de API (en cascada también borra su mapeo).
     * @param int $id
     * @return bool
     */
    public static function eliminarApiField($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("DELETE FROM forwarding_api_fields WHERE id = :id");
            $stmt->execute([':id' => (int)$id]);
            return true;
        } catch (Exception $e) {
            error_log("ForwardingModel::eliminarApiField error: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // API MAPPINGS (forwarding_api_mappings)
    // =========================================================================

    /**
     * Guardar (crear o actualizar) el mapeo de un campo de API.
     * @param int $idApiField
     * @param string $internalKey
     * @param string|null $transformRule
     * @return bool
     */
    public static function guardarApiMapping($idApiField, $internalKey, $transformRule = null)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmtCheck = $db->prepare("SELECT id FROM forwarding_api_mappings WHERE id_api_field = :id_api_field LIMIT 1");
            $stmtCheck->execute([':id_api_field' => (int)$idApiField]);
            $existingId = $stmtCheck->fetchColumn();

            if ($existingId) {
                $stmt = $db->prepare("
                    UPDATE forwarding_api_mappings
                    SET internal_key   = :internal_key,
                        transform_rule = :transform_rule,
                        updated_at     = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':internal_key'   => trim($internalKey),
                    ':transform_rule' => $transformRule,
                    ':id'             => (int)$existingId,
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
                    VALUES (:id_api_field, :internal_key, :transform_rule)
                ");
                $stmt->execute([
                    ':id_api_field'   => (int)$idApiField,
                    ':internal_key'   => trim($internalKey),
                    ':transform_rule' => $transformRule,
                ]);
            }
            return true;
        } catch (Exception $e) {
            error_log("ForwardingModel::guardarApiMapping error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todos los mapeos activos de un proveedor (para el PayloadBuilder).
     * @param int $idProvider
     * @return array
     */
    public static function obtenerMapeosDeProveedor($idProvider)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT f.field_path, f.field_type, f.is_required, f.default_value,
                       m.internal_key, m.transform_rule
                FROM forwarding_api_fields f
                INNER JOIN forwarding_api_mappings m ON m.id_api_field = f.id
                WHERE f.id_provider = :id_provider
                ORDER BY f.sort_order ASC, f.id ASC
            ");
            $stmt->execute([':id_provider' => (int)$idProvider]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerMapeosDeProveedor error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener el último log de forwarding exitoso para un pedido y un proveedor específico.
     * @param int $idPedido
     * @param string $slugProvider
     * @return array|null
     */
    public static function obtenerLogForwardingExitoso($idPedido, $slugProvider)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT fl.*, p.slug, p.nombre AS provider_nombre
                FROM forwarding_log fl
                INNER JOIN forwarding_providers p ON p.id = fl.id_provider
                WHERE fl.id_pedido = :id_pedido 
                  AND fl.status = 'success' 
                  AND p.slug = :slug
                ORDER BY fl.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([
                ':id_pedido' => $idPedido,
                ':slug'      => $slugProvider
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerLogForwardingExitoso error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualizar metadatos de un campo de API (obligatoriedad, tipo, valor por defecto, etiqueta).
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function actualizarApiField($id, $data)
    {
        try {
            $db = (new Conexion())->conectar();
            $sets = [];
            $params = [':id' => (int)$id];
            $allowed = ['field_path', 'label', 'field_type', 'is_required', 'default_value', 'sort_order'];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = $data[$col];
                }
            }
            if (empty($sets)) {
                return false;
            }
            $sql = "UPDATE forwarding_api_fields SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("ForwardingModel::actualizarApiField error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retorna la definición de la plantilla inicial de campos y mapeos para ShipEdge OMS v41.
     * @return array
     */
    public static function getPlantillaInicialShipEdge()
    {
        return [
            [
                'field_path'     => 'order_number',
                'label'          => 'Número único de orden (order_number)',
                'field_type'     => 'string',
                'is_required'    => 1,
                'default_value'  => null,
                'sort_order'     => 10,
                'internal_key'   => 'numero_orden',
                'transform_rule' => 'preserve_string',
            ],
            [
                'field_path'     => 'shipping_method',
                'label'          => 'Método de envío configurado (shipping_method)',
                'field_type'     => 'string',
                'is_required'    => 1,
                'default_value'  => null,
                'sort_order'     => 20,
                'internal_key'   => '_config_shipping_method',
                'transform_rule' => 'Desde configuración de cuenta o regla',
            ],
            [
                'field_path'     => 'customer.firstname',
                'label'          => 'Nombre completo del destinatario (customer.firstname)',
                'field_type'     => 'string',
                'is_required'    => 1,
                'default_value'  => null,
                'sort_order'     => 30,
                'internal_key'   => 'destinatario',
                'transform_rule' => null,
            ],
            [
                'field_path'     => 'customer.address1',
                'label'          => 'Dirección principal (customer.address1)',
                'field_type'     => 'string',
                'is_required'    => 1,
                'default_value'  => null,
                'sort_order'     => 40,
                'internal_key'   => 'direccion',
                'transform_rule' => null,
            ],
            [
                'field_path'     => 'customer.address2',
                'label'          => 'Dirección secundaria / Entre calles (customer.address2)',
                'field_type'     => 'string',
                'is_required'    => 0,
                'default_value'  => null,
                'sort_order'     => 50,
                'internal_key'   => 'betweenStreets',
                'transform_rule' => 'Opcional si existe',
            ],
            [
                'field_path'     => 'customer.city',
                'label'          => 'Municipio / Ciudad (customer.city)',
                'field_type'     => 'string',
                'is_required'    => 1,
                'default_value'  => null,
                'sort_order'     => 60,
                'internal_key'   => 'municipio_nombre|municipalitiesName',
                'transform_rule' => 'Prioridad: municipio_nombre -> municipalitiesName',
            ],
            [
                'field_path'     => 'customer.state',
                'label'          => 'Departamento / Provincia / Estado (customer.state)',
                'field_type'     => 'string',
                'is_required'    => 1,
                'default_value'  => null,
                'sort_order'     => 70,
                'internal_key'   => 'departamento|departmentName',
                'transform_rule' => 'Prioridad: departamento -> departmentName',
            ],
            [
                'field_path'     => 'customer.zip',
                'label'          => 'Código Postal como texto (customer.zip)',
                'field_type'     => 'string',
                'is_required'    => 1,
                'default_value'  => null,
                'sort_order'     => 80,
                'internal_key'   => 'codigo_postal|postalCode',
                'transform_rule' => 'preserve_leading_zeros',
            ],
            [
                'field_path'     => 'customer.country',
                'label'          => 'País de destino (customer.country)',
                'field_type'     => 'string',
                'is_required'    => 1,
                'default_value'  => null,
                'sort_order'     => 90,
                'internal_key'   => 'pais_codigo_iso|pais_nombre|_config_default_country',
                'transform_rule' => 'Prioridad: ISO país -> Nombre país -> País config',
            ],
            [
                'field_path'     => 'customer.email',
                'label'          => 'Correo electrónico (customer.email)',
                'field_type'     => 'string',
                'is_required'    => 0,
                'default_value'  => null,
                'sort_order'     => 100,
                'internal_key'   => 'correo',
                'transform_rule' => 'Opcional si existe',
            ],
            [
                'field_path'     => 'customer.phone',
                'label'          => 'Teléfono del destinatario (customer.phone)',
                'field_type'     => 'string',
                'is_required'    => 0,
                'default_value'  => null,
                'sort_order'     => 110,
                'internal_key'   => 'telefono',
                'transform_rule' => 'Opcional si existe',
            ],
            [
                'field_path'     => 'items[].sku',
                'label'          => 'SKU del Producto (items[].sku)',
                'field_type'     => 'string',
                'is_required'    => 1,
                'default_value'  => null,
                'sort_order'     => 120,
                'internal_key'   => 'productos.sku',
                'transform_rule' => null,
            ],
            [
                'field_path'     => 'items[].quantity',
                'label'          => 'Cantidad del Producto (items[].quantity)',
                'field_type'     => 'int',
                'is_required'    => 1,
                'default_value'  => null,
                'sort_order'     => 130,
                'internal_key'   => 'productos.cantidad',
                'transform_rule' => null,
            ],
            [
                'field_path'     => 'items[].sold_price',
                'label'          => 'Precio unitario validado (items[].sold_price)',
                'field_type'     => 'float',
                'is_required'    => 0,
                'default_value'  => null,
                'sort_order'     => 140,
                'internal_key'   => 'productos.precio_unitario',
                'transform_rule' => 'validate_currency',
            ],
        ];
    }

    /**
     * Sembrar la plantilla inicial de campos y mapeos de ShipEdge v41 para un proveedor.
     * @param int $idProvider
     * @param bool $sobrescribir Si es true, actualiza también mapeos existentes.
     * @return bool
     */
    public static function sembrarPlantillaShipEdge($idProvider, $sobrescribir = false)
    {
        try {
            $db = (new Conexion())->conectar();
            $plantilla = self::getPlantillaInicialShipEdge();

            foreach ($plantilla as $item) {
                $stmtCheck = $db->prepare("
                    SELECT id FROM forwarding_api_fields
                    WHERE id_provider = :id_provider AND field_path = :field_path
                    LIMIT 1
                ");
                $stmtCheck->execute([
                    ':id_provider' => (int)$idProvider,
                    ':field_path'  => $item['field_path'],
                ]);
                $existingFieldId = $stmtCheck->fetchColumn();

                if (!$existingFieldId) {
                    $fieldId = self::crearApiField([
                        'id_provider'   => (int)$idProvider,
                        'field_path'    => $item['field_path'],
                        'label'         => $item['label'],
                        'field_type'    => $item['field_type'],
                        'is_required'   => $item['is_required'],
                        'default_value' => $item['default_value'],
                        'sort_order'    => $item['sort_order'],
                    ]);
                    if ($fieldId) {
                        self::guardarApiMapping($fieldId, $item['internal_key'], $item['transform_rule']);
                    }
                } elseif ($sobrescribir) {
                    self::actualizarApiField((int)$existingFieldId, [
                        'label'         => $item['label'],
                        'field_type'    => $item['field_type'],
                        'is_required'   => $item['is_required'],
                        'default_value' => $item['default_value'],
                        'sort_order'    => $item['sort_order'],
                    ]);
                    self::guardarApiMapping((int)$existingFieldId, $item['internal_key'], $item['transform_rule']);
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("ForwardingModel::sembrarPlantillaShipEdge error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sembrar los estados iniciales de ShipEdge en forwarding_status_mapping.
     * IMPORTANTE: 'shipped' -> 2 (En ruta o proceso), NUNCA 3 (Entregado).
     * @return bool
     */
    public static function sembrarEstadosShipEdge()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmtDev = $db->query("SELECT id FROM estados_pedidos WHERE nombre_estado = 'Devolución' LIMIT 1");
            $idDevolucion = (int)($stmtDev ? $stmtDev->fetchColumn() : 0) ?: 7;

            $estados = [
                ['pending',       null, 'Orden recibida / Pendiente en OMS',                    1,  0, 1],
                ['processing',    null, 'En proceso de preparación / Picking en almacén',       1,  0, 1],
                ['in_process',    null, 'En proceso en almacén (ShipEdge OMS)',                 1,  0, 1],
                ['ready_to_ship', null, 'Empacado / Listo para despacho',                      12,  0, 1],
                ['packed',        null, 'Empacado en almacén',                                 12,  0, 1],
                ['shipped',       null, 'Despachado de almacén (En ruta — NO es Entregado)',    2,  1, 1],
                ['in_transit',    null, 'En tránsito con transportista',                        2,  0, 1],
                ['delivered',     null, 'Entregado confirmado por transportista (por validar)', 3,  0, 1],
                ['on_hold',       null, 'Orden retenida / En espera (On Hold)',                 4,  0, 1],
                ['backorder',     null, 'Sin stock / Backorder en almacén',                     4,  0, 1],
                ['cancelled',     null, 'Orden cancelada en ShipEdge',                          6,  0, 1],
                ['canceled',      null, 'Orden cancelada en ShipEdge (US spelling)',            6,  0, 1],
                ['returned',      null, 'Devolución reportada en ShipEdge',          $idDevolucion, 0, 1],
            ];

            $stmtCheck = $db->prepare("
                SELECT id FROM forwarding_status_mapping
                WHERE provider_slug = 'shipedge' AND codigo_externo = :codigo_externo
                LIMIT 1
            ");
            $stmtInsert = $db->prepare("
                INSERT INTO forwarding_status_mapping
                    (provider_slug, codigo_externo, razon_codigo, descripcion_externa, id_estado_interno, es_confirmado, activo)
                VALUES
                    ('shipedge', :codigo_externo, :razon_codigo, :descripcion_externa, :id_estado_interno, :es_confirmado, :activo)
            ");
            $stmtUpdate = $db->prepare("
                UPDATE forwarding_status_mapping
                SET descripcion_externa = :descripcion_externa
                WHERE id = :id
            ");

            foreach ($estados as $est) {
                $stmtCheck->execute([':codigo_externo' => $est[0]]);
                $existId = $stmtCheck->fetchColumn();
                if ($existId) {
                    $stmtUpdate->execute([
                        ':descripcion_externa' => $est[2],
                        ':id'                  => (int)$existId,
                    ]);
                } else {
                    $stmtInsert->execute([
                        ':codigo_externo'      => $est[0],
                        ':razon_codigo'        => $est[1],
                        ':descripcion_externa' => $est[2],
                        ':id_estado_interno'   => $est[3],
                        ':es_confirmado'       => $est[4],
                        ':activo'              => $est[5],
                    ]);
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("ForwardingModel::sembrarEstadosShipEdge error: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // HOMOLOGACIÓN DE ESTADOS EXTERNOS (forwarding_status_mapping)
    // =========================================================================

    /**
     * Listar mapeos de estados externos a internos.
     * @param string|null $providerSlug
     * @return array
     */
    public static function obtenerMapeosEstados($providerSlug = null)
    {
        try {
            $db = (new Conexion())->conectar();
            $where = '';
            $params = [];
            if (!empty($providerSlug)) {
                $where = 'WHERE fsm.provider_slug = :slug';
                $params[':slug'] = $providerSlug;
            }
            $stmt = $db->prepare("
                SELECT fsm.*, ep.nombre_estado AS estado_interno_nombre
                FROM forwarding_status_mapping fsm
                LEFT JOIN estados_pedidos ep ON ep.id = fsm.id_estado_interno
                $where
                ORDER BY fsm.provider_slug ASC, fsm.id ASC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerMapeosEstados error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Guardar (crear o actualizar) un mapeo de estado externo -> interno.
     * Protege la regla de negocio: en 'shipedge', 'shipped' nunca puede mapearse a Entregado (ID 3).
     * @param array $data
     * @return int|bool
     * @throws InvalidArgumentException
     */
    public static function guardarMapeoEstado($data)
    {
        $slug = strtolower(trim($data['provider_slug'] ?? ''));
        $codigoExterno = trim($data['codigo_externo'] ?? '');
        $idEstadoInterno = (int)($data['id_estado_interno'] ?? 0);

        if ($slug === 'shipedge' && strtolower($codigoExterno) === 'shipped' && $idEstadoInterno === 3) {
            throw new InvalidArgumentException(
                "Regla de negocio ShipEdge: el estado externo 'shipped' indica despacho de almacén (En ruta), no puede homologarse como 'Entregado' (ID 3)."
            );
        }

        try {
            $db = (new Conexion())->conectar();
            $id = !empty($data['id']) ? (int)$data['id'] : 0;

            if ($id <= 0) {
                $stmtFind = $db->prepare("
                    SELECT id FROM forwarding_status_mapping
                    WHERE provider_slug = :provider_slug AND codigo_externo = :codigo_externo
                    LIMIT 1
                ");
                $stmtFind->execute([
                    ':provider_slug'  => $slug,
                    ':codigo_externo' => $codigoExterno,
                ]);
                $id = (int)($stmtFind->fetchColumn() ?: 0);
            }

            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE forwarding_status_mapping
                    SET provider_slug       = :provider_slug,
                        codigo_externo      = :codigo_externo,
                        razon_codigo        = :razon_codigo,
                        descripcion_externa = :descripcion_externa,
                        id_estado_interno   = :id_estado_interno,
                        es_confirmado       = :es_confirmado,
                        activo              = :activo
                    WHERE id = :id
                ");
                return $stmt->execute([
                    ':provider_slug'       => $slug,
                    ':codigo_externo'      => $codigoExterno,
                    ':razon_codigo'        => !empty($data['razon_codigo']) ? trim($data['razon_codigo']) : null,
                    ':descripcion_externa' => !empty($data['descripcion_externa']) ? trim($data['descripcion_externa']) : null,
                    ':id_estado_interno'   => $idEstadoInterno,
                    ':es_confirmado'       => isset($data['es_confirmado']) ? (int)$data['es_confirmado'] : 0,
                    ':activo'              => isset($data['activo']) ? (int)$data['activo'] : 1,
                    ':id'                  => $id,
                ]);
            }

            $stmt = $db->prepare("
                INSERT INTO forwarding_status_mapping
                    (provider_slug, codigo_externo, razon_codigo, descripcion_externa, id_estado_interno, es_confirmado, activo)
                VALUES
                    (:provider_slug, :codigo_externo, :razon_codigo, :descripcion_externa, :id_estado_interno, :es_confirmado, :activo)
            ");
            $stmt->execute([
                ':provider_slug'       => $slug,
                ':codigo_externo'      => $codigoExterno,
                ':razon_codigo'        => !empty($data['razon_codigo']) ? trim($data['razon_codigo']) : null,
                ':descripcion_externa' => !empty($data['descripcion_externa']) ? trim($data['descripcion_externa']) : null,
                ':id_estado_interno'   => $idEstadoInterno,
                ':es_confirmado'       => isset($data['es_confirmado']) ? (int)$data['es_confirmado'] : 0,
                ':activo'              => isset($data['activo']) ? (int)$data['activo'] : 1,
            ]);
            return (int)$db->lastInsertId() ?: true;
        } catch (Exception $e) {
            error_log("ForwardingModel::guardarMapeoEstado error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar un mapeo de estado externo.
     * @param int $id
     * @return bool
     */
    public static function eliminarMapeoEstado($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("DELETE FROM forwarding_status_mapping WHERE id = :id");
            return $stmt->execute([':id' => (int)$id]);
        } catch (Exception $e) {
            error_log("ForwardingModel::eliminarMapeoEstado error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Listar catálogo de estados internos de pedidos.
     * @return array
     */
    public static function obtenerEstadosInternos()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->query("SELECT id, nombre_estado, descripcion FROM estados_pedidos ORDER BY id ASC");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerEstadosInternos error: " . $e->getMessage());
            return [];
        }
    }
}

