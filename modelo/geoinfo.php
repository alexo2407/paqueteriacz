<?php
include_once __DIR__ . '/conexion.php';

/**
 * GeoinfoModel
 * 
 * Unified search across geographic entities (países, departamentos, municipios, barrios)
 */
class GeoinfoModel
{
    /**
     * Search across all geographic entities
     * 
     * @param string $query Search term (minimum 2 characters)
     * @param array $filters Optional filters: tipo, pais_id, departamento_id, municipio_id
     * @return array Search results with hierarchical context
     */
    public static function buscar($query, $filters = [])
    {
        $db = (new Conexion())->conectar();
        
        // Sanitize query
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }
        
        $searchPattern = '%' . $query . '%';
        $tipo = $filters['tipo'] ?? null;
        $paisId = isset($filters['pais_id']) ? (int)$filters['pais_id'] : null;
        $deptoId = isset($filters['departamento_id']) ? (int)$filters['departamento_id'] : null;
        $munId = isset($filters['municipio_id']) ? (int)$filters['municipio_id'] : null;
        
        $results = [];
        
        // Search in Países
        if (!$tipo || $tipo === 'pais') {
            $sql = "SELECT 
                        p.id,
                        'pais' AS tipo,
                        p.nombre,
                        p.codigo_iso,
                        NULL AS id_pais,
                        NULL AS pais,
                        NULL AS id_departamento,
                        NULL AS departamento,
                        NULL AS id_municipio,
                        NULL AS municipio,
                        CASE WHEN p.nombre = :query_exact THEN 1 ELSE 2 END AS relevancia
                    FROM paises p
                    WHERE p.nombre LIKE :query";
            
            if ($paisId) {
                $sql .= " AND p.id = :pais_id";
            }
            
            $sql .= " ORDER BY relevancia, p.nombre LIMIT 20";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':query', $searchPattern);
            $stmt->bindValue(':query_exact', $query);
            if ($paisId) {
                $stmt->bindValue(':pais_id', $paisId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        // Search in Departamentos
        if (!$tipo || $tipo === 'departamento') {
            $sql = "SELECT 
                        d.id,
                        'departamento' AS tipo,
                        d.nombre,
                        NULL AS codigo_iso,
                        d.id_pais,
                        p.nombre AS pais,
                        NULL AS id_departamento,
                        NULL AS departamento,
                        NULL AS id_municipio,
                        NULL AS municipio,
                        CASE WHEN d.nombre = :query_exact THEN 3 ELSE 4 END AS relevancia
                    FROM departamentos d
                    INNER JOIN paises p ON p.id = d.id_pais
                    WHERE d.nombre LIKE :query";
            
            if ($paisId) {
                $sql .= " AND d.id_pais = :pais_id";
            }
            
            $sql .= " ORDER BY relevancia, d.nombre LIMIT 20";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':query', $searchPattern);
            $stmt->bindValue(':query_exact', $query);
            if ($paisId) {
                $stmt->bindValue(':pais_id', $paisId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        // Search in Municipios
        if (!$tipo || $tipo === 'municipio') {
            $sql = "SELECT 
                        m.id,
                        'municipio' AS tipo,
                        m.nombre,
                        m.codigo_postal AS codigo_iso,
                        d.id_pais,
                        p.nombre AS pais,
                        m.id_departamento,
                        d.nombre AS departamento,
                        NULL AS id_municipio,
                        NULL AS municipio,
                        CASE WHEN m.nombre = :query_exact THEN 5 ELSE 6 END AS relevancia
                    FROM municipios m
                    INNER JOIN departamentos d ON d.id = m.id_departamento
                    INNER JOIN paises p ON p.id = d.id_pais
                    WHERE m.nombre LIKE :query";
            
            if ($paisId) {
                $sql .= " AND d.id_pais = :pais_id";
            }
            if ($deptoId) {
                $sql .= " AND m.id_departamento = :depto_id";
            }
            
            $sql .= " ORDER BY relevancia, m.nombre LIMIT 20";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':query', $searchPattern);
            $stmt->bindValue(':query_exact', $query);
            if ($paisId) {
                $stmt->bindValue(':pais_id', $paisId, PDO::PARAM_INT);
            }
            if ($deptoId) {
                $stmt->bindValue(':depto_id', $deptoId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        // Search in Barrios
        if (!$tipo || $tipo === 'barrio') {
            $sql = "SELECT 
                        b.id,
                        'barrio' AS tipo,
                        b.nombre,
                        COALESCE(b.codigo_postal, m.codigo_postal) AS codigo_iso,
                        d.id_pais,
                        p.nombre AS pais,
                        m.id_departamento,
                        d.nombre AS departamento,
                        b.id_municipio,
                        m.nombre AS municipio,
                        CASE WHEN b.nombre = :query_exact THEN 7 ELSE 8 END AS relevancia
                    FROM barrios b
                    INNER JOIN municipios m ON m.id = b.id_municipio
                    INNER JOIN departamentos d ON d.id = m.id_departamento
                    INNER JOIN paises p ON p.id = d.id_pais
                    WHERE b.nombre LIKE :query";
            
            if ($paisId) {
                $sql .= " AND d.id_pais = :pais_id";
            }
            if ($deptoId) {
                $sql .= " AND m.id_departamento = :depto_id";
            }
            if ($munId) {
                $sql .= " AND b.id_municipio = :mun_id";
            }
            
            $sql .= " ORDER BY relevancia, b.nombre LIMIT 20";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':query', $searchPattern);
            $stmt->bindValue(':query_exact', $query);
            if ($paisId) {
                $stmt->bindValue(':pais_id', $paisId, PDO::PARAM_INT);
            }
            if ($deptoId) {
                $stmt->bindValue(':depto_id', $deptoId, PDO::PARAM_INT);
            }
            if ($munId) {
                $stmt->bindValue(':mun_id', $munId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        // Sort by relevancia and limit to 20 total
        usort($results, function($a, $b) {
            if ($a['relevancia'] !== $b['relevancia']) {
                return $a['relevancia'] - $b['relevancia'];
            }
            return strcmp($a['nombre'], $b['nombre']);
        });
        
        // Remove relevancia from results and limit
        $results = array_slice($results, 0, 20);
        foreach ($results as &$result) {
            unset($result['relevancia']);
        }
        
        return $results;
    }
}
