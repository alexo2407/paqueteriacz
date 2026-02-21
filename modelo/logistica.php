<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/pedido.php';
require_once __DIR__ . '/auditoria.php';

class LogisticaModel {

    /**
     * Obtener notificaciones (cambios recientes) de los pedidos del usuario.
     * Se basa en la tabla auditoria_cambios unida con pedidos.
     * 
     * @param int $userId ID del usuario
     * @param int $limit Límite de resultados
     * @param bool $filterByProveedor Si true, filtra por id_proveedor; si false, por id_cliente
     */
    public static function obtenerNotificacionesCliente($userId, $limit = 20, $filterByProveedor = false) {
        try {
            $db = (new Conexion())->conectar();
            
            $filterField = $filterByProveedor ? 'p.id_proveedor' : 'p.id_cliente';
            
            $sql = "SELECT 
                        a.id AS auditoria_id,
                        a.accion,
                        a.datos_anteriores,
                        a.datos_nuevos,
                        a.created_at,
                        p.id AS pedido_id,
                        p.numero_orden,
                        p.destinatario
                    FROM auditoria_cambios a
                    INNER JOIN pedidos p ON a.id_registro = p.id
                    WHERE a.tabla = 'pedidos' 
                    AND {$filterField} = :user_id
                    AND (a.accion = 'actualizar' OR a.accion = 'crear')
                    ORDER BY a.created_at DESC
                    LIMIT :limit";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($resultados as &$row) {
                if (!empty($row['datos_anteriores'])) {
                    $row['datos_anteriores'] = json_decode($row['datos_anteriores'], true);
                }
                if (!empty($row['datos_nuevos'])) {
                    $row['datos_nuevos'] = json_decode($row['datos_nuevos'], true);
                }
            }
            return $resultados;

        } catch (Exception $e) {
            error_log("Error al obtener notificaciones logistica: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener historial de pedidos con filtros
     * 
     * @param int $userId ID del usuario
     * @param array $filtros Filtros: fecha_desde, fecha_hasta, search, id_cliente, id_estado
     * @param bool $filterByProveedor Si true, filtra por id_proveedor; si false, por id_cliente
     * @param int|null $limit Límite (null = sin límite)
     * @param int $offset Offset para paginación
     * @param bool $excluirEstadosFinales Si true, excluye estados finales
     */
    public static function obtenerHistorialCliente($userId, $filtros = [], $filterByProveedor = false, $limit = null, $offset = 0, $excluirEstadosFinales = false) {
        try {
            $db = (new Conexion())->conectar();
            
            $filterField = $filterByProveedor ? 'p.id_proveedor' : 'p.id_cliente';
            
            $sql = "SELECT 
                        p.id,
                        p.numero_orden,
                        p.fecha_ingreso,
                        p.destinatario,
                        p.telefono,
                        p.direccion,
                        p.zona,
                        p.codigo_postal,
                        p.precio_total_local,
                        p.id_cliente,
                        p.id_proveedor,
                        ep.nombre_estado AS estado,
                        m.codigo AS moneda,
                        pa.nombre AS nombre_pais,
                        d.nombre AS nombre_departamento,
                        mu.nombre AS nombre_municipio,
                        b.nombre AS nombre_barrio,
                        uc.nombre AS nombre_cliente,
                        up.nombre AS nombre_proveedor
                    FROM pedidos p
                    LEFT JOIN estados_pedidos ep ON p.id_estado = ep.id
                    LEFT JOIN monedas m ON p.id_moneda = m.id
                    LEFT JOIN paises pa ON p.id_pais = pa.id
                    LEFT JOIN departamentos d ON p.id_departamento = d.id
                    LEFT JOIN municipios mu ON p.id_municipio = mu.id
                    LEFT JOIN barrios b ON p.id_barrio = b.id
                    LEFT JOIN usuarios uc ON p.id_cliente = uc.id
                    LEFT JOIN usuarios up ON p.id_proveedor = up.id
                    WHERE {$filterField} = :user_id";
            
            $params = [':user_id' => $userId];

            if (!empty($filtros['fecha_desde'])) {
                $sql .= " AND p.fecha_ingreso >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }
            if (!empty($filtros['fecha_hasta'])) {
                $sql .= " AND p.fecha_ingreso <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }
            if (!empty($filtros['search'])) {
                $sql .= " AND (p.numero_orden LIKE :search OR p.destinatario LIKE :search OR p.telefono LIKE :search)";
                $params[':search'] = '%' . $filtros['search'] . '%';
            }
            if (!empty($filtros['id_cliente']) && (int)$filtros['id_cliente'] > 0) {
                $sql .= " AND p.id_cliente = :id_cliente";
                $params[':id_cliente'] = (int)$filtros['id_cliente'];
            }
            if (!empty($filtros['id_estado']) && (int)$filtros['id_estado'] > 0) {
                $sql .= " AND p.id_estado = :id_estado";
                $params[':id_estado'] = (int)$filtros['id_estado'];
            }
            
            if ($excluirEstadosFinales) {
                $sql .= " AND (ep.nombre_estado IS NULL OR ep.nombre_estado NOT IN ('Entregado', 'Devuelto', 'Cancelado'))";
            }

            $sql .= " ORDER BY p.fecha_ingreso DESC";
            
            if ($limit !== null) {
                $sql .= " LIMIT :limit OFFSET :offset";
                $params[':limit'] = (int)$limit;
                $params[':offset'] = (int)$offset;
            }

            $stmt = $db->prepare($sql);

            // Bind enteros por separado para evitar problemas con LIMIT/OFFSET en PDO
            foreach ($params as $key => $val) {
                if ($key === ':limit' || $key === ':offset' || $key === ':id_cliente' || $key === ':id_estado' || $key === ':user_id') {
                    $stmt->bindValue($key, (int)$val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val);
                }
            }
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error al obtener historial logistica: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar total de pedidos para paginación
     */
    public static function contarPedidos($userId, $filtros = [], $filterByProveedor = false, $excluirEstadosFinales = false) {
        try {
            $db = (new Conexion())->conectar();
            
            $filterField = $filterByProveedor ? 'p.id_proveedor' : 'p.id_cliente';
            
            $sql = "SELECT COUNT(*) as total
                    FROM pedidos p
                    LEFT JOIN estados_pedidos ep ON p.id_estado = ep.id
                    WHERE {$filterField} = :user_id";
            
            $params = [':user_id' => $userId];

            if (!empty($filtros['fecha_desde'])) {
                $sql .= " AND p.fecha_ingreso >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }
            if (!empty($filtros['fecha_hasta'])) {
                $sql .= " AND p.fecha_ingreso <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }
            if (!empty($filtros['search'])) {
                $sql .= " AND (p.numero_orden LIKE :search OR p.destinatario LIKE :search OR p.telefono LIKE :search)";
                $params[':search'] = '%' . $filtros['search'] . '%';
            }
            if (!empty($filtros['id_cliente']) && (int)$filtros['id_cliente'] > 0) {
                $sql .= " AND p.id_cliente = :id_cliente";
                $params[':id_cliente'] = (int)$filtros['id_cliente'];
            }
            if (!empty($filtros['id_estado']) && (int)$filtros['id_estado'] > 0) {
                $sql .= " AND p.id_estado = :id_estado";
                $params[':id_estado'] = (int)$filtros['id_estado'];
            }
            
            if ($excluirEstadosFinales) {
                $sql .= " AND (ep.nombre_estado IS NULL OR ep.nombre_estado NOT IN ('Entregado', 'Devuelto', 'Cancelado'))";
            }

            $stmt = $db->prepare($sql);
            foreach ($params as $key => $val) {
                if ($key === ':id_cliente' || $key === ':id_estado' || $key === ':user_id') {
                    $stmt->bindValue($key, (int)$val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val);
                }
            }
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);

        } catch (Exception $e) {
            error_log("Error al contar pedidos logistica: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener clientes únicos que tienen pedidos con este proveedor / asignados al usuario
     */
    public static function obtenerClientesDelProveedor($userId, $filterByProveedor = false) {
        try {
            $db = (new Conexion())->conectar();
            $filterField = $filterByProveedor ? 'p.id_proveedor' : 'p.id_cliente';
            $sql = "SELECT DISTINCT uc.id, uc.nombre
                    FROM pedidos p
                    INNER JOIN usuarios uc ON p.id_cliente = uc.id
                    WHERE {$filterField} = :user_id
                    ORDER BY uc.nombre ASC";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error al obtener clientes logistica: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener historial de cambios de una orden específica con nombres de usuario
     */
    public static function obtenerHistorialCambiosPedido($pedidoId) {
        return AuditoriaModel::listarPorRegistro('pedidos', $pedidoId);
    }

    /**
     * Obtener todos los estados disponibles de la tabla estados_pedidos
     */
    public static function obtenerEstados() {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->query("SELECT id, nombre_estado FROM estados_pedidos ORDER BY nombre_estado ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error al obtener estados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener ID de estado por nombre
     */
    public static function obtenerIdEstadoPorNombre($nombre) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT id FROM estados_pedidos WHERE nombre_estado = :nombre LIMIT 1");
            $stmt->execute([':nombre' => $nombre]);
            $id = $stmt->fetchColumn();
            
            if ($id) return $id;
            
            $stmt = $db->prepare("SELECT id FROM estados_pedidos WHERE nombre_estado LIKE :nombre LIMIT 1");
            $stmt->execute([':nombre' => $nombre]);
            return $stmt->fetchColumn();
            
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Actualizar estado de un pedido con auditoría
     */
    public static function actualizarEstado($pedidoId, $nuevoEstado, $observaciones, $usuarioId) {
        try {
            $db = (new Conexion())->conectar();
            
            $idEstadoNuevo = self::obtenerIdEstadoPorNombre($nuevoEstado);
            if (!$idEstadoNuevo) {
                throw new Exception("Estado inválido: $nuevoEstado");
            }

            $pedidoActual = PedidosModel::obtenerPedidoPorId($pedidoId);
            if (!$pedidoActual) {
                throw new Exception("Pedido no encontrado");
            }

            $db->beginTransaction();

            $sql = "UPDATE pedidos SET id_estado = :id_estado WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id_estado' => $idEstadoNuevo, ':id' => $pedidoId]);

            $datosAnteriores = [
                'id_estado' => $pedidoActual['id_estado'],
                'estado' => $pedidoActual['nombre_estado']
            ];
            
            $datosNuevos = [
                'id_estado' => $idEstadoNuevo,
                'estado' => $nuevoEstado,
                'observaciones' => $observaciones
            ];

            AuditoriaModel::registrar(
                'pedidos',
                $pedidoId,
                'actualizar',
                $usuarioId,
                $datosAnteriores,
                $datosNuevos
            );

            $db->commit();
            return true;

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            error_log("Error actualizarEstado logistica: " . $e->getMessage());
            return false;
        }
    }
}
