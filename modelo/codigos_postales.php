<?php
require_once "conexion.php";

class CodigosPostalesModel {
    
    /**
     * Buscar un código postal por país
     */
    public static function buscar($id_pais, $codigo_postal) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT cp.*, 
                                        d.nombre as nombre_departamento, 
                                        m.nombre as nombre_municipio, 
                                        b.nombre as nombre_barrio 
                                 FROM codigos_postales cp
                                 LEFT JOIN departamentos d ON cp.id_departamento = d.id
                                 LEFT JOIN municipios m ON cp.id_municipio = m.id
                                 LEFT JOIN barrios b ON cp.id_barrio = b.id
                                 WHERE cp.id_pais = :id_pais 
                                 AND cp.codigo_postal = :cp 
                                 AND cp.activo = 1");
            $stmt->execute([':id_pais' => $id_pais, ':cp' => $codigo_postal]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en CodigosPostalesModel::buscar: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear un registro de código postal (normalmente parcial desde la creación de pedido)
     */
    public static function crear($data) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("INSERT INTO codigos_postales 
                (id_pais, codigo_postal, id_departamento, id_municipio, id_barrio, nombre_localidad, activo) 
                VALUES (:id_pais, :cp, :id_dep, :id_mun, :id_bar, :localidad, 1)");
            
            $stmt->execute([
                ':id_pais' => $data['id_pais'],
                ':cp' => strtoupper(trim($data['codigo_postal'])),
                ':id_dep' => $data['id_departamento'] ?? null,
                ':id_mun' => $data['id_municipio'] ?? null,
                ':id_bar' => $data['id_barrio'] ?? null,
                ':localidad' => $data['nombre_localidad'] ?? null
            ]);
            
            return $db->lastInsertId();
        } catch (Exception $e) {
            error_log("Error en CodigosPostalesModel::crear: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Listar códigos postales con filtros y paginación
     */
    public static function listar($filtros = [], $pagina = 1, $limite = 20) {
        try {
            $db = (new Conexion())->conectar();
            $offset = ($pagina - 1) * $limite;
            
            $where = ["1=1"];
            $params = [];
            
            if (!empty($filtros['id_pais'])) {
                $where[] = "cp.id_pais = :id_pais";
                $params[':id_pais'] = $filtros['id_pais'];
            }
            if (!empty($filtros['codigo_postal'])) {
                $where[] = "cp.codigo_postal LIKE :cp";
                $params[':cp'] = '%' . strtoupper(trim($filtros['codigo_postal'])) . '%';
            }
            if (isset($filtros['activo']) && $filtros['activo'] !== '') {
                $where[] = "cp.activo = :activo";
                $params[':activo'] = $filtros['activo'];
            }
            if (!empty($filtros['parcial'])) {
                $where[] = "(cp.id_departamento IS NULL OR cp.id_municipio IS NULL)";
            }
            
            $sql = "SELECT cp.*, 
                           p.nombre as nombre_pais,
                           d.nombre as nombre_departamento, 
                           m.nombre as nombre_municipio, 
                           b.nombre as nombre_barrio 
                    FROM codigos_postales cp
                    INNER JOIN paises p ON cp.id_pais = p.id
                    LEFT JOIN departamentos d ON cp.id_departamento = d.id
                    LEFT JOIN municipios m ON cp.id_municipio = m.id
                    LEFT JOIN barrios b ON cp.id_barrio = b.id
                    WHERE " . implode(" AND ", $where) . "
                    ORDER BY cp.created_at DESC
                    LIMIT $limite OFFSET $offset";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar total para paginación
            $sqlCount = "SELECT COUNT(*) FROM codigos_postales cp WHERE " . implode(" AND ", $where);
            $stmtCount = $db->prepare($sqlCount);
            $stmtCount->execute($params);
            $total = (int)$stmtCount->fetchColumn();
            
            return [
                'items' => $items,
                'total' => $total,
                'paginas' => ceil($total / $limite)
            ];
        } catch (Exception $e) {
            error_log("Error en CodigosPostalesModel::listar: " . $e->getMessage());
            return ['items' => [], 'total' => 0, 'paginas' => 0];
        }
    }

    /**
     * Actualizar un registro
     */
    public static function actualizar($id, $data) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("UPDATE codigos_postales SET 
                id_pais = :id_pais,
                codigo_postal = :cp,
                id_departamento = :id_dep,
                id_municipio = :id_mun,
                id_barrio = :id_bar,
                nombre_localidad = :localidad,
                activo = :activo,
                updated_at = NOW()
                WHERE id = :id");
            
            return $stmt->execute([
                ':id' => $id,
                ':id_pais' => $data['id_pais'],
                ':cp' => strtoupper(trim($data['codigo_postal'])),
                ':id_dep' => $data['id_departamento'] ?? null,
                ':id_mun' => $data['id_municipio'] ?? null,
                ':id_bar' => $data['id_barrio'] ?? null,
                ':localidad' => $data['nombre_localidad'] ?? null,
                ':activo' => $data['activo'] ?? 1
            ]);
        } catch (Exception $e) {
            error_log("Error en CodigosPostalesModel::actualizar: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Alternar estado activo/inactivo
     */
    public static function toggle($id) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("UPDATE codigos_postales SET activo = 1 - activo WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verificar si ya existe un CP para ese país
     */
    public static function existe($id_pais, $codigo_postal, $excludeId = null) {
        try {
            $db = (new Conexion())->conectar();
            $sql = "SELECT COUNT(*) FROM codigos_postales WHERE id_pais = :id_pais AND codigo_postal = :cp";
            if ($excludeId) {
                $sql .= " AND id != :excludeId";
            }
            $stmt = $db->prepare($sql);
            $params = [
                ':id_pais' => $id_pais,
                ':cp' => strtoupper(trim($codigo_postal))
            ];
            if ($excludeId) {
                $params[':excludeId'] = $excludeId;
            }
            $stmt->execute($params);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtener por ID
     */
    public static function obtenerPorId($id) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT cp.*, 
                                         p.nombre as nombre_pais,
                                         d.nombre as nombre_departamento, 
                                         m.nombre as nombre_municipio, 
                                         b.nombre as nombre_barrio 
                                  FROM codigos_postales cp
                                  INNER JOIN paises p ON cp.id_pais = p.id
                                  LEFT JOIN departamentos d ON cp.id_departamento = d.id
                                  LEFT JOIN municipios m ON cp.id_municipio = m.id
                                  LEFT JOIN barrios b ON cp.id_barrio = b.id
                                  WHERE cp.id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    /**
     * Contar CPs activos de un país
     */
    public static function contarActivosPorPais($id_pais) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT COUNT(*) FROM codigos_postales WHERE id_pais = :id_pais AND activo = 1");
            $stmt->execute([':id_pais' => $id_pais]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Obtener mapa de CPs para inyección en vista (JSON)
     */
    public static function obtenerMapaPorPais($id_pais) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT codigo_postal, id_departamento, id_municipio, id_barrio, nombre_localidad, activo 
                                 FROM codigos_postales 
                                 WHERE id_pais = :id_pais");
            $stmt->execute([':id_pais' => $id_pais]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Contar CPs activos globalmente
     */
    public static function contarActivosGlobal() {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->query("SELECT COUNT(*) FROM codigos_postales WHERE activo = 1");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Obtener mapa global de CPs para autocompletado universal
     */
    public static function obtenerMapaGlobal() {
        try {
            $db = (new Conexion())->conectar();
            // Solo traemos los activos y las columnas necesarias para el autocompletado
            $stmt = $db->query("SELECT id_pais, codigo_postal, id_departamento, id_municipio, id_barrio, activo 
                               FROM codigos_postales 
                               WHERE activo = 1");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Búsqueda global por CP (para detectar colisiones) con nombres
     */
    public static function buscarGlobal($codigo_postal) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT cp.*, 
                                         p.nombre as nombre_pais,
                                         d.nombre as nombre_departamento, 
                                         m.nombre as nombre_municipio, 
                                         b.nombre as nombre_barrio 
                                  FROM codigos_postales cp
                                  INNER JOIN paises p ON cp.id_pais = p.id
                                  LEFT JOIN departamentos d ON cp.id_departamento = d.id
                                  LEFT JOIN municipios m ON cp.id_municipio = m.id
                                  LEFT JOIN barrios b ON cp.id_barrio = b.id
                                  WHERE cp.codigo_postal = :cp");
            $stmt->execute([':cp' => strtoupper(trim((string)$codigo_postal))]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en CodigosPostalesModel::buscarGlobal: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar CP por zona (barrio) - Búsqueda inversa
     */
    public static function buscarPorZona($id_pais, $id_barrio) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT cp.*, 
                                         p.nombre as nombre_pais,
                                         d.nombre as nombre_departamento, 
                                         m.nombre as nombre_municipio, 
                                         b.nombre as nombre_barrio 
                                  FROM codigos_postales cp
                                  INNER JOIN paises p ON cp.id_pais = p.id
                                  LEFT JOIN departamentos d ON cp.id_departamento = d.id
                                  LEFT JOIN municipios m ON cp.id_municipio = m.id
                                  LEFT JOIN barrios b ON cp.id_barrio = b.id
                                  WHERE cp.id_pais = :id_pais 
                                  AND cp.id_barrio = :id_barrio
                                  AND cp.activo = 1
                                  LIMIT 5");
            $stmt->execute([
                ':id_pais' => (int)$id_pais,
                ':id_barrio' => (int)$id_barrio
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en CodigosPostalesModel::buscarPorZona: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Eliminar CP por ID
     */
    public static function eliminar(int $id): bool {
        try {
            $db   = (new Conexion())->conectar();
            $stmt = $db->prepare("DELETE FROM codigos_postales WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error en CodigosPostalesModel::eliminar: " . $e->getMessage());
            return false;
        }
    }
}
