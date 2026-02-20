<?php
require_once __DIR__ . '/../modelo/codigos_postales.php';

class AddressService {
    
    /**
     * Normaliza un código postal (trim, upper, quita guiones/espacios)
     */
    public static function normalizarCP($cp) {
        if (empty($cp)) return null;
        // Upper + Trim + Quitar espacios y guiones
        $cp = strtoupper(trim((string)$cp));
        return str_replace([' ', '-'], '', $cp);
    }

    /**
     * Busca o registra un código postal y retorna su información
     */
    public static function resolverHomologacion($id_pais, $codigo_postal, $data_adicional = []) {
        if (empty($id_pais) || empty($codigo_postal)) return null;

        $cp_norm = self::normalizarCP($codigo_postal);
        
        // 1. Buscar si ya existe (incluye inactivos para saber su estado real)
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare("SELECT * FROM codigos_postales WHERE id_pais = :id_pais AND codigo_postal = :cp");
        $stmt->execute([':id_pais' => $id_pais, ':cp' => $cp_norm]);
        $homologacion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($homologacion) {
            return $homologacion;
        }

        // 2. Si no existe, intentar crear un registro "parcial"
        try {
            $nuevo_id = CodigosPostalesModel::crear([
                'id_pais' => $id_pais,
                'codigo_postal' => $cp_norm,
                'id_departamento' => $data_adicional['id_departamento'] ?? null,
                'id_municipio' => $data_adicional['id_municipio'] ?? null,
                'id_barrio' => $data_adicional['id_barrio'] ?? null,
                'nombre_localidad' => $data_adicional['nombre_localidad'] ?? null
            ]);

            if ($nuevo_id) {
                return CodigosPostalesModel::obtenerPorId($nuevo_id);
            }
        } catch (Exception $e) {
            // Manejar race condition: si falló por registro duplicado, re-consultar
            if (strpos($e->getMessage(), '23000') !== false) {
                $stmt->execute([':id_pais' => $id_pais, ':cp' => $cp_norm]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            error_log("Error in resolverHomologacion: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Determina si la información de un código postal está incompleta
     */
    public static function isPartial($row) {
        if (!$row) return true;
        return empty($row['id_departamento']) || empty($row['id_municipio']);
    }
}
