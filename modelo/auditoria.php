<?php

include_once __DIR__ . '/conexion.php';

/**
 * AuditoriaModel
 *
 * Modelo centralizado para registrar y consultar cambios en tablas maestras.
 * Permite trazabilidad completa de quién creó/modificó/eliminó registros.
 */
class AuditoriaModel
{
    /**
     * Registrar un cambio en la auditoría.
     *
     * @param string $tabla Nombre de la tabla afectada (ej: 'productos', 'monedas')
     * @param int $idRegistro ID del registro afectado
     * @param string $accion Tipo de acción: 'crear', 'actualizar', 'eliminar'
     * @param int|null $idUsuario ID del usuario que realiza la acción (null para sistema)
     * @param array|null $datosAnteriores Estado anterior del registro (para updates/deletes)
     * @param array|null $datosNuevos Estado nuevo del registro (para creates/updates)
     * @return int|null ID del registro de auditoría creado, o null en error
     */
    public static function registrar(
        string $tabla,
        int $idRegistro,
        string $accion,
        ?int $idUsuario = null,
        ?array $datosAnteriores = null,
        ?array $datosNuevos = null
    ): ?int {
        try {
            $db = (new Conexion())->conectar();
            
            $sql = 'INSERT INTO auditoria_cambios 
                    (tabla, id_registro, accion, id_usuario, datos_anteriores, datos_nuevos, ip_address, user_agent) 
                    VALUES 
                    (:tabla, :id_registro, :accion, :id_usuario, :datos_anteriores, :datos_nuevos, :ip_address, :user_agent)';
            
            $stmt = $db->prepare($sql);
            
            $stmt->bindValue(':tabla', $tabla, PDO::PARAM_STR);
            $stmt->bindValue(':id_registro', $idRegistro, PDO::PARAM_INT);
            $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
            
            if ($idUsuario === null) {
                $stmt->bindValue(':id_usuario', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            }
            
            // Convertir arrays a JSON
            $jsonAnteriores = $datosAnteriores !== null ? json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE) : null;
            $jsonNuevos = $datosNuevos !== null ? json_encode($datosNuevos, JSON_UNESCAPED_UNICODE) : null;
            
            if ($jsonAnteriores === null) {
                $stmt->bindValue(':datos_anteriores', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':datos_anteriores', $jsonAnteriores, PDO::PARAM_STR);
            }
            
            if ($jsonNuevos === null) {
                $stmt->bindValue(':datos_nuevos', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':datos_nuevos', $jsonNuevos, PDO::PARAM_STR);
            }
            
            // Obtener IP y User Agent
            $ipAddress = self::getClientIp();
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null;
            
            if ($ipAddress === null) {
                $stmt->bindValue(':ip_address', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
            }
            
            if ($userAgent === null) {
                $stmt->bindValue(':user_agent', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':user_agent', $userAgent, PDO::PARAM_STR);
            }
            
            $stmt->execute();
            return (int)$db->lastInsertId();
            
        } catch (PDOException $e) {
            error_log('Error al registrar auditoría: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Listar cambios de una tabla específica.
     *
     * @param string $tabla Nombre de la tabla
     * @param int $limite Número máximo de registros
     * @return array Lista de cambios
     */
    public static function listarPorTabla(string $tabla, int $limite = 50): array
    {
        try {
            $db = (new Conexion())->conectar();
            $sql = 'SELECT 
                        a.id,
                        a.tabla,
                        a.id_registro,
                        a.accion,
                        a.id_usuario,
                        u.nombre AS usuario_nombre,
                        a.datos_anteriores,
                        a.datos_nuevos,
                        a.ip_address,
                        a.created_at
                    FROM auditoria_cambios a
                    LEFT JOIN usuarios u ON u.id = a.id_usuario
                    WHERE a.tabla = :tabla
                    ORDER BY a.created_at DESC
                    LIMIT :limite';
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':tabla', $tabla, PDO::PARAM_STR);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return self::procesarResultados($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            error_log('Error al listar auditoría por tabla: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Listar cambios realizados por un usuario específico.
     *
     * @param int $idUsuario ID del usuario
     * @param int $limite Número máximo de registros
     * @return array Lista de cambios
     */
    public static function listarPorUsuario(int $idUsuario, int $limite = 50): array
    {
        try {
            $db = (new Conexion())->conectar();
            $sql = 'SELECT 
                        a.id,
                        a.tabla,
                        a.id_registro,
                        a.accion,
                        a.id_usuario,
                        a.datos_anteriores,
                        a.datos_nuevos,
                        a.ip_address,
                        a.created_at
                    FROM auditoria_cambios a
                    WHERE a.id_usuario = :id_usuario
                    ORDER BY a.created_at DESC
                    LIMIT :limite';
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return self::procesarResultados($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            error_log('Error al listar auditoría por usuario: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener historial completo de un registro específico.
     *
     * @param string $tabla Nombre de la tabla
     * @param int $idRegistro ID del registro
     * @return array Historial de cambios del registro
     */
    public static function listarPorRegistro(string $tabla, int $idRegistro): array
    {
        try {
            $db = (new Conexion())->conectar();
            $sql = 'SELECT 
                        a.id,
                        a.tabla,
                        a.id_registro,
                        a.accion,
                        a.id_usuario,
                        u.nombre AS usuario_nombre,
                        a.datos_anteriores,
                        a.datos_nuevos,
                        a.ip_address,
                        a.created_at
                    FROM auditoria_cambios a
                    LEFT JOIN usuarios u ON u.id = a.id_usuario
                    WHERE a.tabla = :tabla AND a.id_registro = :id_registro
                    ORDER BY a.created_at ASC';
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':tabla', $tabla, PDO::PARAM_STR);
            $stmt->bindValue(':id_registro', $idRegistro, PDO::PARAM_INT);
            $stmt->execute();
            
            return self::procesarResultados($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            error_log('Error al listar auditoría por registro: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener los últimos cambios del sistema (para dashboard).
     *
     * @param int $limite Número máximo de registros
     * @return array Lista de cambios recientes
     */
    public static function obtenerUltimosCambios(int $limite = 20): array
    {
        try {
            $db = (new Conexion())->conectar();
            $sql = 'SELECT 
                        a.id,
                        a.tabla,
                        a.id_registro,
                        a.accion,
                        a.id_usuario,
                        u.nombre AS usuario_nombre,
                        a.ip_address,
                        a.created_at
                    FROM auditoria_cambios a
                    LEFT JOIN usuarios u ON u.id = a.id_usuario
                    ORDER BY a.created_at DESC
                    LIMIT :limite';
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener últimos cambios: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Contar cambios por tabla en un período.
     *
     * @param string|null $fechaInicio Fecha inicio (Y-m-d)
     * @param string|null $fechaFin Fecha fin (Y-m-d)
     * @return array Conteo por tabla
     */
    public static function contarPorTabla(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        try {
            $db = (new Conexion())->conectar();
            
            $where = '';
            $params = [];
            
            if ($fechaInicio !== null) {
                $where .= ' WHERE created_at >= :fecha_inicio';
                $params[':fecha_inicio'] = $fechaInicio . ' 00:00:00';
            }
            
            if ($fechaFin !== null) {
                $where .= ($where === '' ? ' WHERE' : ' AND') . ' created_at <= :fecha_fin';
                $params[':fecha_fin'] = $fechaFin . ' 23:59:59';
            }
            
            $sql = "SELECT 
                        tabla,
                        COUNT(*) as total,
                        SUM(CASE WHEN accion = 'crear' THEN 1 ELSE 0 END) as creados,
                        SUM(CASE WHEN accion = 'actualizar' THEN 1 ELSE 0 END) as actualizados,
                        SUM(CASE WHEN accion = 'eliminar' THEN 1 ELSE 0 END) as eliminados
                    FROM auditoria_cambios
                    {$where}
                    GROUP BY tabla
                    ORDER BY total DESC";
            
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al contar auditoría por tabla: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Procesar resultados decodificando JSON.
     *
     * @param array $resultados Resultados de la consulta
     * @return array Resultados con JSON decodificado
     */
    private static function procesarResultados(array $resultados): array
    {
        foreach ($resultados as &$row) {
            if (isset($row['datos_anteriores']) && $row['datos_anteriores'] !== null) {
                $row['datos_anteriores'] = json_decode($row['datos_anteriores'], true);
            }
            if (isset($row['datos_nuevos']) && $row['datos_nuevos'] !== null) {
                $row['datos_nuevos'] = json_decode($row['datos_nuevos'], true);
            }
        }
        return $resultados;
    }

    /**
     * Obtener la IP del cliente de forma segura.
     *
     * @return string|null IP del cliente o null si no está disponible
     */
    public static function getClientIp(): ?string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxies
            'HTTP_X_REAL_IP',            // Nginx
            'HTTP_CLIENT_IP',            // Proxy alternativo
            'REMOTE_ADDR'                // Conexión directa
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Si hay múltiples IPs (X-Forwarded-For), tomar la primera
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validar que sea una IP válida
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return null;
    }

    /**
     * Obtener el ID del usuario actual.
     * 
     * Busca el ID en el siguiente orden de prioridad:
     * 1. Variable global API_USER_ID (establecida por la API después de validar JWT)
     * 2. Sesión PHP - user_id (para usuarios web logueados)
     * 3. Sesión PHP - ID_Usuario (compatibilidad)
     *
     * @return int|null ID del usuario o null si no hay usuario autenticado
     */
    public static function getIdUsuarioActual(): ?int
    {
        // 1. Primero verificar si la API estableció el ID del usuario (desde JWT)
        if (isset($GLOBALS['API_USER_ID']) && is_numeric($GLOBALS['API_USER_ID'])) {
            return (int)$GLOBALS['API_USER_ID'];
        }
        
        // 2. Verificar sesión web - user_id (variable principal del sistema)
        if (session_status() !== PHP_SESSION_NONE && isset($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }
        
        // 3. Compatibilidad - ID_Usuario
        if (session_status() !== PHP_SESSION_NONE && isset($_SESSION['ID_Usuario'])) {
            return (int)$_SESSION['ID_Usuario'];
        }
        
        return null;
    }

    /**
     * Establecer el ID del usuario actual para la API.
     * Debe llamarse después de validar el JWT en endpoints protegidos.
     *
     * @param int $idUsuario ID del usuario autenticado via JWT
     */
    public static function setApiUserId(int $idUsuario): void
    {
        $GLOBALS['API_USER_ID'] = $idUsuario;
    }

    /**
     * Listar registros de auditoría con filtros opcionales.
     *
     * @param array $filtros Filtros opcionales (tabla, accion, id_usuario, fecha_inicio, fecha_fin)
     * @param int $limite Número máximo de registros
     * @return array Lista de cambios
     */
    public static function listar(array $filtros = [], int $limite = 100): array
    {
        try {
            $db = (new Conexion())->conectar();
            
            $where = [];
            $params = [];
            
            if (!empty($filtros['tabla'])) {
                $where[] = 'a.tabla = :tabla';
                $params[':tabla'] = $filtros['tabla'];
            }
            
            if (!empty($filtros['accion'])) {
                $where[] = 'a.accion = :accion';
                $params[':accion'] = $filtros['accion'];
            }
            
            if (!empty($filtros['id_usuario'])) {
                $where[] = 'a.id_usuario = :id_usuario';
                $params[':id_usuario'] = (int)$filtros['id_usuario'];
            }
            
            if (!empty($filtros['fecha_inicio'])) {
                $where[] = 'DATE(a.created_at) >= :fecha_inicio';
                $params[':fecha_inicio'] = $filtros['fecha_inicio'];
            }
            
            if (!empty($filtros['fecha_fin'])) {
                $where[] = 'DATE(a.created_at) <= :fecha_fin';
                $params[':fecha_fin'] = $filtros['fecha_fin'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT 
                        a.id,
                        a.tabla,
                        a.id_registro,
                        a.accion,
                        a.id_usuario,
                        u.nombre AS usuario_nombre,
                        a.datos_anteriores,
                        a.datos_nuevos,
                        a.ip_address,
                        a.user_agent,
                        a.created_at
                    FROM auditoria_cambios a
                    LEFT JOIN usuarios u ON u.id = a.id_usuario
                    {$whereClause}
                    ORDER BY a.created_at DESC
                    LIMIT :limite";
            
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar auditoría: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener lista de tablas únicas que tienen registros de auditoría.
     *
     * @return array Lista de nombres de tablas
     */
    public static function obtenerTablasUnicas(): array
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->query('SELECT DISTINCT tabla FROM auditoria_cambios ORDER BY tabla ASC');
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log('Error al obtener tablas únicas: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener lista de usuarios que tienen registros de auditoría.
     *
     * @return array Lista de usuarios con id y nombre
     */
    public static function obtenerUsuariosConAuditoria(): array
    {
        try {
            $db = (new Conexion())->conectar();
            $sql = 'SELECT DISTINCT u.id, u.nombre 
                    FROM usuarios u
                    INNER JOIN auditoria_cambios a ON a.id_usuario = u.id
                    ORDER BY u.nombre ASC';
            $stmt = $db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener usuarios con auditoría: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }
}
