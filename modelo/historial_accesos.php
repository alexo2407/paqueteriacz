<?php

include_once __DIR__ . '/conexion.php';
include_once __DIR__ . '/auditoria.php'; // reutiliza getClientIp() y resolverPais()

/**
 * HistorialAccesosModel
 *
 * Registra y consulta el historial de accesos (logins) al sistema.
 * Cada entrada almacena: usuario, IP, país de origen, user-agent,
 * tipo de acceso (gui | api) y marca de tiempo.
 */
class HistorialAccesosModel
{
    /**
     * Registrar un acceso exitoso al sistema.
     *
     * @param int    $idUsuario ID del usuario que inició sesión
     * @param string $tipo      'gui' (formulario web) o 'api' (JWT)
     * @return int|null ID del registro creado, o null en error
     */
    public static function registrar(int $idUsuario, string $tipo = 'gui'): ?int
    {
        try {
            $db = (new Conexion())->conectar();

            $ip        = AuditoriaModel::getClientIp();
            $pais      = self::resolverPais($ip);
            $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
                ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500)
                : null;

            $sql = 'INSERT INTO historial_accesos
                        (id_usuario, ip_address, pais_origen, user_agent, tipo)
                    VALUES
                        (:id_usuario, :ip_address, :pais_origen, :user_agent, :tipo)';

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_usuario',  $idUsuario,                PDO::PARAM_INT);
            $stmt->bindValue(':ip_address',  $ip,                       $ip  === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':pais_origen', $pais,                     $pais === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':user_agent',  $userAgent,                $userAgent === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':tipo',        in_array($tipo, ['gui','api'], true) ? $tipo : 'gui', PDO::PARAM_STR);
            $stmt->execute();

            return (int)$db->lastInsertId();

        } catch (PDOException $e) {
            error_log('HistorialAccesos::registrar error: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Listar accesos con filtros opcionales.
     *
     * @param array $filtros  Claves: id_usuario, tipo, pais_origen, fecha_inicio, fecha_fin
     * @param int   $limite   Máximo de registros a retornar
     * @return array
     */
    public static function listar(array $filtros = [], int $limite = 200): array
    {
        try {
            $db     = (new Conexion())->conectar();
            $where  = [];
            $params = [];

            if (!empty($filtros['id_usuario'])) {
                $where[]                = 'h.id_usuario = :id_usuario';
                $params[':id_usuario']  = (int)$filtros['id_usuario'];
            }
            if (!empty($filtros['tipo'])) {
                $where[]        = 'h.tipo = :tipo';
                $params[':tipo'] = $filtros['tipo'];
            }
            if (!empty($filtros['pais_origen'])) {
                $where[]                 = 'h.pais_origen = :pais_origen';
                $params[':pais_origen']  = $filtros['pais_origen'];
            }
            if (!empty($filtros['fecha_inicio'])) {
                $where[]                  = 'DATE(h.created_at) >= :fecha_inicio';
                $params[':fecha_inicio']  = $filtros['fecha_inicio'];
            }
            if (!empty($filtros['fecha_fin'])) {
                $where[]               = 'DATE(h.created_at) <= :fecha_fin';
                $params[':fecha_fin']  = $filtros['fecha_fin'];
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT
                        h.id,
                        h.id_usuario,
                        u.nombre AS usuario_nombre,
                        h.ip_address,
                        h.pais_origen,
                        h.user_agent,
                        h.tipo,
                        h.created_at
                    FROM historial_accesos h
                    LEFT JOIN usuarios u ON u.id = h.id_usuario
                    {$whereClause}
                    ORDER BY h.created_at DESC
                    LIMIT :limite";

            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log('HistorialAccesos::listar error: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Listar los países únicos que han iniciado sesión (para el filtro).
     *
     * @return string[]
     */
    public static function obtenerPaisesUnicos(): array
    {
        try {
            $db   = (new Conexion())->conectar();
            $stmt = $db->query("SELECT DISTINCT pais_origen FROM historial_accesos WHERE pais_origen IS NOT NULL ORDER BY pais_origen ASC");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Listar usuarios únicos que tienen accesos registrados (para el filtro).
     *
     * @return array  [{id, nombre}]
     */
    public static function obtenerUsuariosConAcceso(): array
    {
        try {
            $db   = (new Conexion())->conectar();
            $sql  = 'SELECT DISTINCT u.id, u.nombre
                     FROM usuarios u
                     INNER JOIN historial_accesos h ON h.id_usuario = u.id
                     ORDER BY u.nombre ASC';
            $stmt = $db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // -----------------------------------------------------------------------
    // Helpers privados (misma lógica que AuditoriaModel para evitar dep. circular)
    // -----------------------------------------------------------------------

    /**
     * Resolver IP → nombre de país (en inglés) usando ip-api.com.
     * IPs privadas/locales → 'Local'. Fallo/timeout → null.
     */
    private static function resolverPais(?string $ip): ?string
    {
        if ($ip === null) return null;

        // Rangos privados / loopback → sin llamada externa
        if (
            $ip === '127.0.0.1' || $ip === '::1' ||
            strpos($ip, '192.168.') === 0 ||
            strpos($ip, '10.')      === 0 ||
            strpos($ip, '172.16.')  === 0 ||
            strpos($ip, '172.17.')  === 0 ||
            strpos($ip, '172.18.')  === 0 ||
            strpos($ip, '172.19.')  === 0 ||
            strpos($ip, '172.2')    === 0 ||
            strpos($ip, '172.30.')  === 0 ||
            strpos($ip, '172.31.')  === 0 ||
            strpos($ip, 'localhost') !== false
        ) {
            return 'Local';
        }

        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout'       => 1,
                    'ignore_errors' => true,
                    'user_agent'    => 'PaqueteriaCZ-Audit/1.0',
                ],
            ]);
            $raw = @file_get_contents('http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country', false, $ctx);
            if ($raw === false) return null;

            $data = json_decode($raw, true);
            if (isset($data['status']) && $data['status'] === 'success' && !empty($data['country'])) {
                return (string)$data['country'];
            }
        } catch (\Throwable $e) {
            // Silencioso
        }

        return null;
    }
}
