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
                        p.fecha_entrega,
                        p.fecha_liquidacion,
                        p.destinatario,
                        p.telefono,
                        p.direccion,
                        p.zona,
                        p.codigo_postal,
                        p.precio_total_local,
                        p.comentario,
                        p.id_cliente,
                        p.id_proveedor,
                        p.id_pais,
                        p.id_moneda,
                        p.Location,
                        p.betweenStreets,
                        p.departmentName,
                        p.municipalitiesName,
                        p.postalCode,
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
                    -- Homologación via codigo_postal: resuelve depto/muni cuando las FKs del pedido son NULL
                    LEFT JOIN (
                        SELECT MIN(id) AS id,
                               codigo_postal,
                               MIN(id_departamento) AS id_departamento,
                               MIN(id_municipio)    AS id_municipio
                        FROM codigos_postales
                        WHERE id_departamento IS NOT NULL
                        GROUP BY codigo_postal
                    ) cp_hom ON cp_hom.codigo_postal = p.codigo_postal
                    LEFT JOIN departamentos d  ON d.id  = COALESCE(p.id_departamento, cp_hom.id_departamento)
                    LEFT JOIN municipios    mu ON mu.id = COALESCE(p.id_municipio,    cp_hom.id_municipio)
                    LEFT JOIN barrios       b  ON b.id  = p.id_barrio
                    LEFT JOIN usuarios uc ON p.id_cliente  = uc.id
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
                $sql .= " AND (CAST(p.numero_orden AS CHAR) LIKE :search1 OR p.destinatario LIKE :search2 OR p.telefono LIKE :search3)";
                $searchVal = '%' . $filtros['search'] . '%';
                $params[':search1'] = $searchVal;
                $params[':search2'] = $searchVal;
                $params[':search3'] = $searchVal;
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
                // Tab "En Proceso": solo mostrar pedidos activos reales
                // Incluir exclusivamente: En bodega (1) y En ruta o proceso (2)
                $sql .= " AND p.id_estado IN (1, 2)";
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
                $sql .= " AND (CAST(p.numero_orden AS CHAR) LIKE :search1 OR p.destinatario LIKE :search2 OR p.telefono LIKE :search3)";
                $searchVal = '%' . $filtros['search'] . '%';
                $params[':search1'] = $searchVal;
                $params[':search2'] = $searchVal;
                $params[':search3'] = $searchVal;
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
                // Tab "En Proceso": solo mostrar pedidos activos reales
                // Incluir exclusivamente: En bodega (1) y En ruta o proceso (2)
                $sql .= " AND p.id_estado IN (1, 2)";
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
     * Obtener pedidos liquidados (estado ID 14 = "Entregado – liquidado")
     * filtrados por rango de fecha_liquidacion y opcionalmente por id_cliente.
     *
     * @param int   $userId           ID del usuario actual
     * @param bool  $filterByProveedor Si true, filtra por id_proveedor; si false, por id_cliente
     * @param array $filtros           Claves soportadas: liq_desde, liq_hasta, search, id_cliente
     * @param int   $limit
     * @param int   $offset
     * @return array ['rows' => [...], 'total' => int, 'suma' => float]
     */
    public static function obtenerLiquidados(int $userId, bool $filterByProveedor, array $filtros = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $db = (new Conexion())->conectar();
            $filterField = $filterByProveedor ? 'p.id_proveedor' : 'p.id_cliente';

            $where  = ["{$filterField} = :user_id", "p.id_estado = 14"];
            $params = [':user_id' => $userId];

            if (!empty($filtros['liq_desde'])) {
                $where[]            = 'p.fecha_liquidacion >= :liq_desde';
                $params[':liq_desde'] = $filtros['liq_desde'];
            }
            if (!empty($filtros['liq_hasta'])) {
                $where[]            = 'p.fecha_liquidacion <= :liq_hasta';
                $params[':liq_hasta'] = $filtros['liq_hasta'];
            }
            if (!empty($filtros['search'])) {
                $where[] = '(CAST(p.numero_orden AS CHAR) LIKE :search OR p.destinatario LIKE :search2)';
                $params[':search']  = '%' . $filtros['search'] . '%';
                $params[':search2'] = '%' . $filtros['search'] . '%';
            }
            if (!empty($filtros['id_cliente']) && (int)$filtros['id_cliente'] > 0) {
                $where[]              = 'p.id_cliente = :id_cliente';
                $params[':id_cliente'] = (int)$filtros['id_cliente'];
            }

            $whereStr = 'WHERE ' . implode(' AND ', $where);

            // Total count + suma
            $stmtAgg = $db->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(p.precio_total_local),0) AS suma
                FROM pedidos p $whereStr");
            foreach ($params as $k => $v) $stmtAgg->bindValue($k, $v);
            $stmtAgg->execute();
            $agg = $stmtAgg->fetch(PDO::FETCH_ASSOC);

            // Rows
            $sql = "SELECT p.id, p.numero_orden, p.destinatario, p.telefono, p.fecha_ingreso,
                           p.fecha_liquidacion, p.precio_total_local,
                           ep.nombre_estado AS estado,
                           m.codigo AS moneda,
                           uc.nombre AS nombre_cliente,
                           up.nombre AS nombre_proveedor
                    FROM pedidos p
                    LEFT JOIN estados_pedidos ep ON p.id_estado = ep.id
                    LEFT JOIN monedas m ON p.id_moneda = m.id
                    LEFT JOIN usuarios uc ON p.id_cliente = uc.id
                    LEFT JOIN usuarios up ON p.id_proveedor = up.id
                    $whereStr
                    ORDER BY p.fecha_liquidacion DESC, p.id DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'rows'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total' => (int)($agg['total'] ?? 0),
                'suma'  => (float)($agg['suma']  ?? 0),
            ];

        } catch (Exception $e) {
            error_log('Error obtenerLiquidados: ' . $e->getMessage());
            return ['rows' => [], 'total' => 0, 'suma' => 0.0];
        }
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
     * Actualizar estado de un pedido con auditoría y movimientos de stock.
     */
    public static function actualizarEstado($pedidoId, $nuevoEstado, $observaciones, $usuarioId, $fechaEntrega = null, $fechaLiquidacion = null) {
        require_once __DIR__ . '/../services/PedidoService.php';
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

            // Bloquear fila para evitar condiciones de carrera
            $stmtLock = $db->prepare('SELECT id FROM pedidos WHERE id = :id FOR UPDATE');
            $stmtLock->execute([':id' => $pedidoId]);
            $stmtLock->fetchColumn();

            // Aplicar movimientos de stock según nuevo estado
            PedidoService::aplicarStockPorEstado($pedidoId, (int)$idEstadoNuevo, (int)$usuarioId, $db);
 
            // 1. Actualizar estado (+ fechas si aplican)
            $sql = "UPDATE pedidos SET id_estado = :id_estado";
            $params = [':id_estado' => $idEstadoNuevo, ':id' => $pedidoId];
            
            if (!empty($fechaEntrega)) {
                $sql .= ", fecha_entrega = :fecha_entrega";
                $params[':fecha_entrega'] = $fechaEntrega;
            }

            if (!empty($fechaLiquidacion)) {
                $sql .= ", fecha_liquidacion = :fecha_liquidacion";
                $params[':fecha_liquidacion'] = $fechaLiquidacion;
            }
            
            $sql .= " WHERE id = :id";
            // Comunicar usuario y observaciones al trigger after_pedido_update_estado
            $db->prepare("SET @current_user_id = :uid, @current_observaciones = :obs")
               ->execute([':uid' => (int)$usuarioId, ':obs' => $observaciones ?: null]);

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
 
            $datosAnteriores = [
                'id_estado'         => $pedidoActual['id_estado'],
                'estado'            => $pedidoActual['nombre_estado'],
                'fecha_entrega'     => $pedidoActual['fecha_entrega'],
                'fecha_liquidacion' => $pedidoActual['fecha_liquidacion'] ?? null,
            ];
            
            $datosNuevos = [
                'id_estado'     => $idEstadoNuevo,
                'estado'        => $nuevoEstado,
                'observaciones' => $observaciones
            ];
            
            if (!empty($fechaEntrega)) {
                $datosNuevos['fecha_entrega'] = $fechaEntrega;
            }

            if (!empty($fechaLiquidacion)) {
                $datosNuevos['fecha_liquidacion'] = $fechaLiquidacion;
            }
 
            AuditoriaModel::registrar(
                'pedidos',
                $pedidoId,
                'actualizar',
                $usuarioId,
                $datosAnteriores,
                $datosNuevos
            );

            $db->commit();

            // [Webhook] Notificar cambio de estado a API externo
            try {
                require_once __DIR__ . '/webhook.php';
                WebhookModel::dispararPorPedidoId((int)$pedidoId, (int)$idEstadoNuevo, $observaciones ?? null);
            } catch (Exception $webEx) {
                error_log("[Webhook] Error en actualizarEstado pedido $pedidoId: " . $webEx->getMessage());
            }

            // ── Crear notificación logística ──────────────────────────────────
            try {
                require_once __DIR__ . '/logistica_notification.php';

                $estadoAnteriorNombre = $pedidoActual['nombre_estado'] ?? ($datosAnteriores['estado'] ?? '');
                $titulo  = "Pedido #{$pedidoActual['numero_orden']} → {$nuevoEstado}";
                $mensaje = $observaciones ?: '';
                $payload = [
                    'estado_anterior' => $estadoAnteriorNombre,
                    'estado_nuevo'    => $nuevoEstado,
                    'numero_orden'    => $pedidoActual['numero_orden'] ?? '',
                ];

                // Recopilar destinatarios únicos (cliente + proveedor + admins)
                $destinatarios = [];

                $idClientePedido   = (int)($pedidoActual['id_cliente']   ?? 0);
                $idProveedorPedido = (int)($pedidoActual['id_proveedor'] ?? 0);

                if ($idClientePedido > 0) {
                    $destinatarios[$idClientePedido] = true;
                }
                if ($idProveedorPedido > 0) {
                    $destinatarios[$idProveedorPedido] = true;
                }

                // Obtener IDs de todos los usuarios con rol admin
                try {
                    $dbNotif = (new Conexion())->conectar();
                    // Busca admins por la tabla usuarios_roles o por el campo rol
                    $stmtAdmins = $dbNotif->prepare("
                        SELECT DISTINCT u.id
                        FROM usuarios u
                        INNER JOIN usuarios_roles ur ON ur.id_usuario = u.id
                        INNER JOIN roles r ON r.id = ur.id_rol
                        WHERE r.nombre = :rolAdmin
                    ");
                    $stmtAdmins->execute([':rolAdmin' => defined('ROL_NOMBRE_ADMIN') ? ROL_NOMBRE_ADMIN : 'Administrador']);
                    foreach ($stmtAdmins->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
                        $destinatarios[(int)$adminId] = true;
                    }
                } catch (Exception $eAdmins) {
                    error_log("[LogisticaNotification] No se pudieron obtener admins: " . $eAdmins->getMessage());
                }

                // Crear una notificación por cada destinatario único
                foreach (array_keys($destinatarios) as $uid) {
                    LogisticaNotificationModel::agregar(
                        $uid, 'estado_cambiado',
                        $titulo, $mensaje, $pedidoId, $payload
                    );
                }

            } catch (Exception $notifEx) {
                // No bloquear el flujo principal por errores de notificación
                error_log("[LogisticaNotification] Error al crear notif: " . $notifEx->getMessage());
            }


            return true;

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            error_log("Error actualizarEstado logistica: " . $e->getMessage());
            return false;
        }
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Bulk update — comentario y/o estado
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Valida un conjunto de filas sin modificar la BD.
     *
     * @param  array $rows          Filas parseadas por BulkParser::parseFile()
     * @param  int   $userId        ID del usuario actual
     * @param  bool  $isProveedor   true si el usuario es Cliente (Proveedor logístico)
     * @return array {rows_validadas, errores, advertencias, summary}
     */
    public static function bulkPreview(array $rows, int $userId, bool $isProveedor): array
    {
        $db = (new Conexion())->conectar();

        // ── 1. Recopilar identificadores de las filas ──────────────────────
        $idPedidos    = [];
        $numOrden     = [];

        foreach ($rows as $row) {
            $ip = isset($row['id_pedido'])    && $row['id_pedido']    !== null ? (int)$row['id_pedido']    : null;
            $no = isset($row['numero_orden']) && $row['numero_orden'] !== null ? (string)$row['numero_orden'] : null;
            if ($ip) $idPedidos[] = $ip;
            if ($no) $numOrden[]  = $no;
        }

        // ── 2. Consulta batch a pedidos ────────────────────────────────────
        $pedidosById    = [];
        $pedidosByOrden = [];

        if (!empty($idPedidos)) {
            $ph   = implode(',', array_fill(0, count($idPedidos), '?'));
            $stmt = $db->prepare("SELECT id, numero_orden, id_proveedor, id_cliente, id_estado, comentario FROM pedidos WHERE id IN ($ph)");
            $stmt->execute($idPedidos);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $pedidosById[(int)$p['id']] = $p;
            }
        }

        if (!empty($numOrden)) {
            $ph   = implode(',', array_fill(0, count($numOrden), '?'));
            $sql  = "SELECT id, numero_orden, id_proveedor, id_cliente, id_estado, comentario FROM pedidos WHERE CAST(numero_orden AS CHAR) IN ($ph)";
            if ($isProveedor) {
                $sql .= ' AND id_proveedor = ?';
                $params = array_merge($numOrden, [$userId]);
            } else {
                $params = $numOrden;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $key = (string)$p['numero_orden'];
                if (isset($pedidosByOrden[$key])) {
                    $pedidosByOrden[$key] = 'AMBIGUO'; // más de un pedido con ese número
                } else {
                    $pedidosByOrden[$key] = $p;
                }
            }
        }

        // ── 3. Cargar todos los estados en memoria ─────────────────────────
        // normalizeEstado: unifica guión largo Unicode (–, U+2013) con guión normal (-)
        // para que Excel no rompa el match al editar el CSV/XLSX.
        $normalizeEstado = fn(string $s): string =>
            strtolower(str_replace("\xE2\x80\x93", '-', trim($s)));  // E2 80 93 = –

        $estadosByNombre = [];
        $estadosById     = [];
        $stmtE = $db->query("SELECT id, nombre_estado FROM estados_pedidos");
        foreach ($stmtE->fetchAll(PDO::FETCH_ASSOC) as $e) {
            // Indexar con la clave normalizada (guión largo → guión normal)
            $estadosByNombre[$normalizeEstado($e['nombre_estado'])] = (int)$e['id'];
            $estadosById[(int)$e['id']] = trim($e['nombre_estado']);
        }

        // ── 4. Validar cada fila ───────────────────────────────────────────
        $rowsValidadas = [];
        $errores       = [];
        $advertencias  = [];

        foreach ($rows as $row) {
            $line = $row['_line'] ?? '?';
            $ip   = isset($row['id_pedido'])    && $row['id_pedido']    !== null ? (int)$row['id_pedido'] : null;
            $no   = isset($row['numero_orden']) && $row['numero_orden'] !== null ? (string)$row['numero_orden'] : null;
            $comentario    = $row['comentario']    ?? null;
            // Normalizar nombre de estado del Excel (guión largo → guión normal)
            $estadoNombre  = isset($row['estado']) ? $normalizeEstado((string)$row['estado']) : null;
            $idEstadoRaw   = isset($row['id_estado']) && $row['id_estado'] !== null ? (int)$row['id_estado'] : null;
            $motivo        = $row['motivo']          ?? null;
            $fechaEntrega     = isset($row['fecha_entrega'])     && $row['fecha_entrega']     !== null ? trim((string)$row['fecha_entrega'])     : null;
            $fechaLiquidacion = isset($row['fecha_liquidacion']) && $row['fecha_liquidacion'] !== null ? trim((string)$row['fecha_liquidacion']) : null;

            // Validar formato de fechas (acepta YYYY-MM-DD)
            if ($fechaEntrega !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEntrega)) {
                $errores[] = "Línea {$line}: fecha_entrega '{$fechaEntrega}' no es válida. Use YYYY-MM-DD (ej: 2026-03-15).";
                continue;
            }
            if ($fechaLiquidacion !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaLiquidacion)) {
                $errores[] = "Línea {$line}: fecha_liquidacion '{$fechaLiquidacion}' no es válida. Use YYYY-MM-DD (ej: 2026-03-15).";
                continue;
            }

            // Sin identificador
            if ($ip === null && $no === null) {
                $errores[] = "Línea {$line}: Falta id_pedido y numero_orden.";
                continue;
            }

            // Sin campo a actualizar
            if ($comentario === null && $estadoNombre === null && $idEstadoRaw === null) {
                $errores[] = "Línea {$line}: Debe incluir al menos comentario, estado o id_estado.";
                continue;
            }

            // Resolver pedido
            $pedido = null;
            if ($ip !== null) {
                $pedido = $pedidosById[$ip] ?? null;
                if ($pedido === null) {
                    $errores[] = "Línea {$line}: id_pedido {$ip} no existe.";
                    continue;
                }
            } else {
                $match = $pedidosByOrden[$no] ?? null;
                if ($match === null) {
                    $errores[] = "Línea {$line}: numero_orden {$no} no existe.";
                    continue;
                }
                if ($match === 'AMBIGUO') {
                    $errores[] = "Línea {$line}: numero_orden {$no} pertenece a más de un pedido — use id_pedido.";
                    continue;
                }
                $pedido = $match;
            }

            // Verificar propiedad si es proveedor
            if ($isProveedor && (int)$pedido['id_proveedor'] !== $userId) {
                $errores[] = "Línea {$line}: El pedido {$pedido['id']} no pertenece a su cuenta.";
                continue;
            }

            // Validar comentario
            $nuevoComentario = null;
            if ($comentario !== null) {
                if (strlen($comentario) > 500) {
                    $errores[] = "Línea {$line}: Comentario excede 500 caracteres (" . strlen($comentario) . ").";
                    continue;
                }
                $nuevoComentario = $comentario;
                if ($nuevoComentario === (string)($pedido['comentario'] ?? '')) {
                    $advertencias[] = "Línea {$line}: Comentario idéntico al actual (sin cambio).";
                }
            }

            // Validar estado
            $nuevoIdEstado = null;
            if ($idEstadoRaw !== null) {
                if (!isset($estadosById[$idEstadoRaw])) {
                    $validos = implode(', ', array_values($estadosById));
                    $errores[] = "Línea {$line}: id_estado {$idEstadoRaw} no existe. IDs válidos: {$validos}";
                    continue;
                }
                $nuevoIdEstado = $idEstadoRaw;
            } elseif ($estadoNombre !== null && $estadoNombre !== '') {
                if (!isset($estadosByNombre[$estadoNombre])) {
                    $validos = implode(', ', array_keys($estadosByNombre));
                    $errores[] = "Línea {$line}: Estado \"{$row['estado']}\" no reconocido. Válidos: {$validos}";
                    continue;
                }
                $nuevoIdEstado = $estadosByNombre[$estadoNombre];
            }

            if ($nuevoIdEstado !== null && $nuevoIdEstado === (int)$pedido['id_estado']) {
                $advertencias[] = "Línea {$line}: Estado idéntico al actual (sin cambio).";
            }

            $rowsValidadas[] = [
                '_line'             => $line,
                'id_pedido'         => (int)$pedido['id'],
                'numero_orden'      => $pedido['numero_orden'],
                'comentario_anterior' => $pedido['comentario'],
                'estado_anterior_id'  => $pedido['id_estado'],
                'nuevo_comentario'    => $nuevoComentario,
                'nuevo_id_estado'     => $nuevoIdEstado,
                'motivo'              => $motivo,
                'fecha_entrega'       => $fechaEntrega,
                'fecha_liquidacion'   => $fechaLiquidacion,
            ];
        }

        $totalFilas = count($rows);
        $totalErr   = count($errores);
        $totalWarn  = count($advertencias);
        $totalOk    = count($rowsValidadas);

        return [
            'rows_validadas' => $rowsValidadas,
            'errores'        => $errores,
            'advertencias'   => $advertencias,
            'summary'        => [
                'total'        => $totalFilas,
                'validas'      => $totalOk,
                'errores'      => $totalErr,
                'advertencias' => $totalWarn,
            ],
        ];
    }

    /**
     * Aplica las actualizaciones validadas en una transacción atómica.
     *
     * @param  array  $rowsValidadas  Salida de bulkPreview()['rows_validadas']
     * @param  int    $userId
     * @param  string $archivoNombre  Nombre original del archivo (para log)
     * @return array  {total, actualizados, sin_cambios, fallidos, failed_rows}
     */
    public static function bulkCommit(array $rowsValidadas, int $userId, string $archivoNombre = 'bulk'): array
    {
        require_once __DIR__ . '/auditoria.php';

        $db = (new Conexion())->conectar();
        $db->beginTransaction();

        $actualizados = 0;
        $sinCambios   = 0;
        $fallidos     = 0;
        $failedRows   = [];

        try {
            foreach ($rowsValidadas as $row) {
                $idPedido         = (int)$row['id_pedido'];
                $nuevoComentario  = $row['nuevo_comentario'];
                $nuevoIdEstado    = $row['nuevo_id_estado'];
                $fechaEntrega     = $row['fecha_entrega']     ?? null;
                $fechaLiquidacion = $row['fecha_liquidacion'] ?? null;

                // Determinar si hay cambios reales
                $hayCambio = ($nuevoComentario !== null && $nuevoComentario !== (string)($row['comentario_anterior'] ?? ''))
                          || ($nuevoIdEstado  !== null && $nuevoIdEstado  !== (int)($row['estado_anterior_id'] ?? 0))
                          || $fechaEntrega     !== null
                          || $fechaLiquidacion !== null;

                if (!$hayCambio) {
                    $sinCambios++;
                    continue;
                }

                // Construir SET dinámico
                $sets   = [];
                $params = [];

                if ($nuevoComentario !== null) {
                    $sets[]                = 'comentario = :comentario';
                    $params[':comentario'] = $nuevoComentario;
                }
                if ($nuevoIdEstado !== null) {
                    $sets[]              = 'id_estado = :id_estado';
                    $params[':id_estado'] = $nuevoIdEstado;
                }
                if ($fechaEntrega !== null) {
                    $sets[]                 = 'fecha_entrega = :fecha_entrega';
                    $params[':fecha_entrega'] = $fechaEntrega;
                }
                if ($fechaLiquidacion !== null) {
                    $sets[]                      = 'fecha_liquidacion = :fecha_liquidacion';
                    $params[':fecha_liquidacion']  = $fechaLiquidacion;
                }

                $params[':id'] = $idPedido;
                $sql = 'UPDATE pedidos SET ' . implode(', ', $sets) . ' WHERE id = :id';

                $stmt = $db->prepare($sql);
                // Comunicar usuario y observaciones al trigger after_pedido_update_estado
                if ($nuevoIdEstado !== null) {
                    $db->prepare("SET @current_user_id = :uid, @current_observaciones = :obs")
                       ->execute([':uid' => (int)$userId, ':obs' => $row['motivo'] ?? null]);
                }
                $ok   = $stmt->execute($params);

                if ($ok && $stmt->rowCount() > 0) {
                    $actualizados++;
                    
                    // [Webhook] Notificar cambio de estado a API externo
                    if ($nuevoIdEstado !== null) {
                        try {
                            require_once __DIR__ . '/webhook.php';
                            WebhookModel::dispararPorPedidoId((int)$idPedido, (int)$nuevoIdEstado, $row['motivo'] ?? null);
                        } catch (Exception $webEx) {
                            error_log("[Webhook] Error en bulkCommit pedido $idPedido: " . $webEx->getMessage());
                        }
                    }

                    // Auditoría
                    $antes  = [];
                    $nuevos = [];
                    if ($nuevoComentario !== null) {
                        $antes['comentario']  = $row['comentario_anterior'];
                        $nuevos['comentario'] = $nuevoComentario;
                    }
                    if ($nuevoIdEstado !== null) {
                        $antes['id_estado']  = $row['estado_anterior_id'];
                        $nuevos['id_estado'] = $nuevoIdEstado;
                    }
                    if ($fechaEntrega !== null) {
                        $nuevos['fecha_entrega'] = $fechaEntrega;
                    }
                    if ($fechaLiquidacion !== null) {
                        $nuevos['fecha_liquidacion'] = $fechaLiquidacion;
                    }
                    if (!empty($row['motivo'])) {
                        $nuevos['motivo'] = $row['motivo'];
                    }
                    AuditoriaModel::registrar('pedidos', $idPedido, 'actualizar', $userId, $antes, $nuevos);
                } else {
                    $fallidos++;
                    $failedRows[] = "Fila {$row['_line']}: pedido {$idPedido} — sin filas afectadas.";
                }
            }

            $db->commit();

            // Registrar en importaciones_csv
            try {
                $ins = $db->prepare("INSERT INTO importaciones_csv
                    (id_usuario, archivo_nombre, filas_totales, filas_exitosas, filas_error, estado)
                    VALUES (:uid, :arch, :total, :ok, :err, 'completado')");
                $total = count($rowsValidadas);
                $ins->execute([
                    ':uid'   => $userId,
                    ':arch'  => 'bulk_pedidos_' . $archivoNombre,
                    ':total' => $total,
                    ':ok'    => $actualizados,
                    ':err'   => $fallidos,
                ]);
            } catch (Exception $e) {
                error_log('BulkCommit log error: ' . $e->getMessage());
            }

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('BulkCommit error: ' . $e->getMessage());
            return [
                'total'        => count($rowsValidadas),
                'actualizados' => 0,
                'sin_cambios'  => 0,
                'fallidos'     => count($rowsValidadas),
                'failed_rows'  => ['Error interno: ' . $e->getMessage()],
            ];
        }

        return [
            'total'        => count($rowsValidadas),
            'actualizados' => $actualizados,
            'sin_cambios'  => $sinCambios,
            'fallidos'     => $fallidos,
            'failed_rows'  => $failedRows,
        ];
    }

    /**
     * Obtener productos de un conjunto de pedidos en una sola query (sin N+1).
     *
     * @param  int[] $pedidoIds  Array con los IDs de pedidos a consultar
     * @return array             Mapa [pedido_id => "Producto A (x2), Producto B (x1)"]
     */
    public static function obtenerProductosPorPedidos(array $pedidoIds): array
    {
        if (empty($pedidoIds)) return [];

        try {
            $db  = (new Conexion())->conectar();
            $ph  = implode(',', array_fill(0, count($pedidoIds), '?'));
            $sql = "SELECT pp.id_pedido, pr.nombre, pp.cantidad
                    FROM pedidos_productos pp
                    INNER JOIN productos pr ON pr.id = pp.id_producto
                    WHERE pp.id_pedido IN ($ph)
                    ORDER BY pp.id_pedido, pr.nombre";

            $stmt = $db->prepare($sql);
            $stmt->execute(array_values($pedidoIds));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar por pedido y concatenar "Nombre (xCant)"
            $mapa = [];
            foreach ($rows as $r) {
                $pid   = (int)$r['id_pedido'];
                $texto = $r['nombre'] . ' (x' . (int)$r['cantidad'] . ')';
                $mapa[$pid][] = $texto;
            }

            // Convertir arrays a string
            return array_map(fn($items) => implode(', ', $items), $mapa);

        } catch (Exception $e) {
            error_log('Error obtenerProductosPorPedidos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener productos de un conjunto de pedidos como array estructurado.
     * Útil para generar columnas dinámicas en Excel.
     *
     * @param  int[] $pedidoIds
     * @return array  Mapa [pedido_id => [['nombre' => string, 'cantidad' => int], ...]]
     */
    public static function obtenerProductosPorPedidosDetallado(array $pedidoIds): array
    {
        if (empty($pedidoIds)) return [];

        try {
            $db  = (new Conexion())->conectar();
            $ph  = implode(',', array_fill(0, count($pedidoIds), '?'));
            $sql = "SELECT pp.id_pedido, pr.nombre, pp.cantidad
                    FROM pedidos_productos pp
                    INNER JOIN productos pr ON pr.id = pp.id_producto
                    WHERE pp.id_pedido IN ($ph)
                    ORDER BY pp.id_pedido, pr.nombre";

            $stmt = $db->prepare($sql);
            $stmt->execute(array_values($pedidoIds));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $mapa = [];
            foreach ($rows as $r) {
                $pid = (int)$r['id_pedido'];
                $mapa[$pid][] = [
                    'nombre'   => $r['nombre'],
                    'cantidad' => (int)$r['cantidad'],
                ];
            }

            return $mapa;

        } catch (Exception $e) {
            error_log('Error obtenerProductosPorPedidosDetallado: ' . $e->getMessage());
            return [];
        }
    }
}
