<?php
/**
 * ImportacionModel - Modelo para gestionar el registro de importaciones CSV
 * 
 * Permite registrar, listar y obtener detalles de importaciones realizadas
 * con propósitos de auditoría y trazabilidad.
 * 
 * @author Sistema Paquetería CZ
 * @version 1.0
 */

include_once __DIR__ . '/conexion.php';

class ImportacionModel
{
    /**
     * Registrar una nueva importación en la base de datos
     * 
     * @param array $data Datos de la importación
     * @return int ID de la importación registrada
     */
    public static function registrar($data)
    {
        try {
            $db = (new Conexion())->conectar();
            
            // Calcular estado automáticamente si no viene
            if (!isset($data['estado'])) {
                if ($data['filas_error'] == 0) {
                    $data['estado'] = 'completado';
                } elseif ($data['filas_exitosas'] > 0) {
                    $data['estado'] = 'parcial';
                } else {
                    $data['estado'] = 'fallido';
                }
            }
            
            $sql = "INSERT INTO importaciones_csv (
                id_usuario,
                archivo_nombre,
                archivo_size_bytes,
                tipo_plantilla,
                filas_totales,
                filas_exitosas,
                filas_error,
                filas_advertencias,
                tiempo_procesamiento_segundos,
                valores_defecto,
                productos_creados,
                errores_detallados,
                estado,
                archivo_errores
            ) VALUES (
                :id_usuario,
                :archivo_nombre,
                :archivo_size_bytes,
                :tipo_plantilla,
                :filas_totales,
                :filas_exitosas,
                :filas_error,
                :filas_advertencias,
                :tiempo_procesamiento_segundos,
                :valores_defecto,
                :productos_creados,
                :errores_detallados,
                :estado,
                :archivo_errores
            )";
            
            $stmt = $db->prepare($sql);
            
            $stmt->execute([
                ':id_usuario' => $data['id_usuario'],
                ':archivo_nombre' => $data['archivo_nombre'],
                ':archivo_size_bytes' => $data['archivo_size_bytes'] ?? null,
                ':tipo_plantilla' => $data['tipo_plantilla'] ?? 'custom',
                ':filas_totales' => $data['filas_totales'] ?? 0,
                ':filas_exitosas' => $data['filas_exitosas'] ?? 0,
                ':filas_error' => $data['filas_error'] ?? 0,
                ':filas_advertencias' => $data['filas_advertencias'] ?? 0,
                ':tiempo_procesamiento_segundos' => $data['tiempo_procesamiento_segundos'] ?? null,
                ':valores_defecto' => isset($data['valores_defecto']) ? json_encode($data['valores_defecto'], JSON_UNESCAPED_UNICODE) : null,
                ':productos_creados' => isset($data['productos_creados']) ? json_encode($data['productos_creados'], JSON_UNESCAPED_UNICODE) : '[]',
                ':errores_detallados' => isset($data['errores_detallados']) ? json_encode($data['errores_detallados'], JSON_UNESCAPED_UNICODE) : '[]',
                ':estado' => $data['estado'],
                ':archivo_errores' => $data['archivo_errores'] ?? null
            ]);
            
            return (int)$db->lastInsertId();
            
        } catch (Exception $e) {
            error_log('Error al registrar importación: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Listar importaciones con filtros y paginación
     * 
     * @param array $filtros Filtros opcionales: usuario_id, fecha_desde, fecha_hasta, estado
     * @param int $limite Límite de resultados
     * @param int $offset Offset para paginación
     * @return array Lista de importaciones
     */
    public static function listar($filtros = [], $limite = 50, $offset = 0)
    {
        try {
            $db = (new Conexion())->conectar();
            
            $where = [];
            $params = [];
            
            // Filtro por usuario
            if (!empty($filtros['usuario_id'])) {
                $where[] = 'i.id_usuario = :usuario_id';
                $params[':usuario_id'] = (int)$filtros['usuario_id'];
            }
            
            // Filtro por fecha desde
            if (!empty($filtros['fecha_desde'])) {
                $where[] = 'i.fecha_importacion >= :fecha_desde';
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }
            
            // Filtro por fecha hasta
            if (!empty($filtros['fecha_hasta'])) {
                $where[] = 'i.fecha_importacion <= :fecha_hasta';
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }
            
            // Filtro por estado
            if (!empty($filtros['estado'])) {
                $where[] = 'i.estado = :estado';
                $params[':estado'] = $filtros['estado'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT 
                        i.*,
                        u.nombre AS usuario_nombre,
                        u.email AS usuario_email
                    FROM importaciones_csv i
                    LEFT JOIN usuarios u ON u.id = i.id_usuario
                    {$whereClause}
                    ORDER BY i.fecha_importacion DESC
                    LIMIT :limite OFFSET :offset";
            
            $stmt = $db->prepare($sql);
            
            // Bind de parámetros
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decodificar JSON
            foreach ($resultados as &$r) {
                $r['valores_defecto'] = json_decode($r['valores_defecto'], true);
                $r['productos_creados'] = json_decode($r['productos_creados'], true);
                $r['errores_detallados'] = json_decode($r['errores_detallados'], true);
            }
            
            return $resultados;
            
        } catch (Exception $e) {
            error_log('Error al listar importaciones: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener detalles de una importación por ID
     * 
     * @param int $id ID de la importación
     * @return array|null Datos de la importación o null si no existe
     */
    public static function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            
            $sql = "SELECT 
                        i.*,
                        u.nombre AS usuario_nombre,
                        u.email AS usuario_email
                    FROM importaciones_csv i
                    LEFT JOIN usuarios u ON u.id = i.id_usuario
                    WHERE i.id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$resultado) {
                return null;
            }
            
            // Decodificar JSON
            $resultado['valores_defecto'] = json_decode($resultado['valores_defecto'], true);
            $resultado['productos_creados'] = json_decode($resultado['productos_creados'], true);
            $resultado['errores_detallados'] = json_decode($resultado['errores_detallados'], true);
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log('Error al obtener importación: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Contar total de importaciones (con filtros)
     * 
     * @param array $filtros Filtros opcionales
     * @return int Total de registros
     */
    public static function contar($filtros = [])
    {
        try {
            $db = (new Conexion())->conectar();
            
            $where = [];
            $params = [];
            
            if (!empty($filtros['usuario_id'])) {
                $where[] = 'id_usuario = :usuario_id';
                $params[':usuario_id'] = (int)$filtros['usuario_id'];
            }
            
            if (!empty($filtros['fecha_desde'])) {
                $where[] = 'fecha_importacion >= :fecha_desde';
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }
            
            if (!empty($filtros['fecha_hasta'])) {
                $where[] = 'fecha_importacion <= :fecha_hasta';
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }
            
            if (!empty($filtros['estado'])) {
                $where[] = 'estado = :estado';
                $params[':estado'] = $filtros['estado'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT COUNT(*) FROM importaciones_csv {$whereClause}";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return (int)$stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log('Error al contar importaciones: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtener estadísticas generales de importaciones
     * 
     * @return array Estadísticas agregadas
     */
    public static function obtenerEstadisticas()
    {
        try {
            $db = (new Conexion())->conectar();
            
            $sql = "SELECT 
                        COUNT(*) as total_importaciones,
                        SUM(filas_totales) as total_filas_procesadas,
                        SUM(filas_exitosas) as total_filas_exitosas,
                        SUM(filas_error) as total_filas_error,
                        AVG(tiempo_procesamiento_segundos) as tiempo_promedio,
                        SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completadas,
                        SUM(CASE WHEN estado = 'parcial' THEN 1 ELSE 0 END) as parciales,
                        SUM(CASE WHEN estado = 'fallido' THEN 1 ELSE 0 END) as fallidas
                    FROM importaciones_csv";
            
            $stmt = $db->query($sql);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error al obtener estadísticas: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Eliminar importaciones antiguas (limpieza)
     * 
     * @param int $diasAntiguedad Eliminar registros más antiguos que X días
     * @return int Número de registros eliminados
     */
    public static function limpiarAntiguas($diasAntiguedad = 90)
    {
        try {
            $db = (new Conexion())->conectar();
            
            $sql = "DELETE FROM importaciones_csv 
                    WHERE fecha_importacion < DATE_SUB(NOW(), INTERVAL :dias DAY)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':dias' => $diasAntiguedad]);
            
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log('Error al limpiar importaciones antiguas: ' . $e->getMessage());
            return 0;
        }
    }
}
