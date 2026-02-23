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
                $sql .= " AND (ep.nombre_estado IS NULL OR LOWER(ep.nombre_estado) NOT IN (
                    'entregado',
                    'devuelto',
                    'rechazado',
                    'no puede pagar recaudo'
                ))";
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
                $sql .= " AND (ep.nombre_estado IS NULL OR LOWER(ep.nombre_estado) NOT IN (
                    'entregado',
                    'devuelto',
                    'rechazado',
                    'no puede pagar recaudo'
                ))";
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
        $estadosByNombre = [];
        $estadosById     = [];
        $stmtE = $db->query("SELECT id, nombre_estado FROM estados_pedidos");
        foreach ($stmtE->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $estadosByNombre[strtolower(trim($e['nombre_estado']))] = (int)$e['id'];
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
            $comentario  = $row['comentario']  ?? null;
            $estadoNombre = isset($row['estado']) ? strtolower(trim((string)$row['estado'])) : null;
            $idEstadoRaw  = isset($row['id_estado']) && $row['id_estado'] !== null ? (int)$row['id_estado'] : null;
            $motivo       = $row['motivo'] ?? null;

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
                '_line'        => $line,
                'id_pedido'    => (int)$pedido['id'],
                'numero_orden' => $pedido['numero_orden'],
                'comentario_anterior' => $pedido['comentario'],
                'estado_anterior_id'  => $pedido['id_estado'],
                'nuevo_comentario'    => $nuevoComentario,
                'nuevo_id_estado'     => $nuevoIdEstado,
                'motivo'              => $motivo,
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
                $idPedido       = (int)$row['id_pedido'];
                $nuevoComentario = $row['nuevo_comentario'];
                $nuevoIdEstado   = $row['nuevo_id_estado'];

                // Determinar si hay cambios reales
                $hayCambio = ($nuevoComentario !== null && $nuevoComentario !== (string)($row['comentario_anterior'] ?? ''))
                          || ($nuevoIdEstado  !== null && $nuevoIdEstado  !== (int)($row['estado_anterior_id'] ?? 0));

                if (!$hayCambio) {
                    $sinCambios++;
                    continue;
                }

                // Construir SET dinámico
                $sets   = [];
                $params = [];

                if ($nuevoComentario !== null) {
                    $sets[]             = 'comentario = :comentario';
                    $params[':comentario'] = $nuevoComentario;
                }
                if ($nuevoIdEstado !== null) {
                    $sets[]               = 'id_estado = :id_estado';
                    $params[':id_estado']  = $nuevoIdEstado;
                }

                $params[':id'] = $idPedido;
                $sql = 'UPDATE pedidos SET ' . implode(', ', $sets) . ' WHERE id = :id';

                $stmt = $db->prepare($sql);
                $ok   = $stmt->execute($params);

                if ($ok && $stmt->rowCount() > 0) {
                    $actualizados++;
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
}
