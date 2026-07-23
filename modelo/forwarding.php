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
     * Obtener un proveedor por slug.
     * @param string $slug
     * @return array|null
     */
    public static function obtenerProveedorPorSlug($slug)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT * FROM forwarding_providers WHERE slug = :slug");
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            error_log("ForwardingModel::obtenerProveedorPorSlug error: " . $e->getMessage());
            return null;
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
            return (int) $db->lastInsertId();
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
                $params[':config_override'] = $data['config_override'];
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

            $fields = ['response_payload', 'http_status', 'status', 'error_message', 'external_order_id', 'attempts'];
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
            $sql = "SELECT COUNT(*) FROM forwarding_log $whereClause";
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
            $stmt = $db->prepare("
                SELECT pp.id_producto, pp.cantidad, pp.cantidad_devuelta, pp.precio_unitario_usd,
                       pr.nombre AS producto_nombre, pr.sku
                FROM pedidos_productos pp
                INNER JOIN productos pr ON pr.id = pp.id_producto
                WHERE pp.id_pedido = :id_pedido
            ");
            $stmt->execute([':id_pedido' => $idPedido]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

            // Intento 1: query completa con JOIN a barrios y columnas extendidas
            try {
                $stmt = $db->prepare("
                    SELECT p.id, p.numero_orden, p.destinatario, p.telefono, p.direccion,
                           p.comentario, p.postalCode, p.codigo_postal, p.precio_total_local,
                           p.fecha_entrega, p.id_cliente,
                           p.municipalitiesName, p.departmentName, p.Location, p.betweenStreets,
                           p.zona, p.code_city,
                           p.id_municipio    AS _raw_id_municipio,
                           p.id_barrio       AS _raw_id_barrio,
                           p.id_departamento AS _raw_id_departamento,
                           IFNULL(dep.nombre, '') AS departamento,
                           IFNULL(bar.nombre, '') AS barrio_nombre
                    FROM pedidos p
                    LEFT JOIN departamentos dep ON dep.id = p.id_departamento
                    LEFT JOIN barrios bar ON bar.id = p.id_barrio
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
                           p.fecha_entrega, p.id_cliente,
                           p.zona, p.code_city,
                           NULL AS postalCode,
                           NULL AS municipalitiesName,
                           NULL AS departmentName,
                           NULL AS Location,
                           NULL AS betweenStreets,
                           NULL AS _raw_id_municipio,
                           NULL AS _raw_id_barrio,
                           NULL AS _raw_id_departamento,
                           ''   AS departamento,
                           ''   AS barrio_nombre
                    FROM pedidos p
                    WHERE p.id = :id
                ");
                $stmt->execute([':id' => $idPedido]);
                $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$pedido) return null;

            $pedido['productos'] = self::obtenerProductosPedido($idPedido);
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
            $stmt = $db->prepare("
                INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
                VALUES (:id_api_field, :internal_key, :transform_rule)
                ON DUPLICATE KEY UPDATE
                    internal_key   = VALUES(internal_key),
                    transform_rule = VALUES(transform_rule),
                    updated_at     = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                ':id_api_field'   => (int)$idApiField,
                ':internal_key'   => trim($internalKey),
                ':transform_rule' => $transformRule,
            ]);
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
}

