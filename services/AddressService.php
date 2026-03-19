<?php
require_once __DIR__ . '/../modelo/codigos_postales.php';

class AddressService {

    /**
     * Obtiene el prefijo postal de un país desde la BD.
     * Retorna null si el país no tiene prefijo (ej. Guatemala).
     */
    private static function obtenerPrefijoPais($id_pais) {
        if (empty($id_pais)) return null;
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT prefijo_postal FROM paises WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$id_pais]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? ($row['prefijo_postal'] ?: null) : null;
        } catch (Exception $e) {
            error_log("AddressService::obtenerPrefijoPais error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Normaliza un código postal (trim, upper, quita espacios/puntos).
     * Si se pasa id_pais y el CP no tiene el prefijo del país, lo agrega automáticamente.
     *
     * Ejemplo: normalizarCP('10101', 2) → 'CR10101' (Costa Rica)
     *          normalizarCP('CR10101', 2) → 'CR10101' (ya tiene prefijo, no duplica)
     *          normalizarCP('04011', 6) → '04011'   (Guatemala sin prefijo, se deja igual)
     */
    public static function normalizarCP($cp, $id_pais = null) {
        if (empty($cp)) return null;

        $cp = strtoupper(trim((string)$cp));
        $cp = str_replace([' ', '.'], '', $cp);

        if ($id_pais) {
            $prefijo = self::obtenerPrefijoPais($id_pais);
            if ($prefijo && !str_starts_with($cp, $prefijo)) {
                $cp = $prefijo . $cp;
            }
        }

        return $cp;
    }

    /**
     * Busca o registra un código postal y retorna su información.
     * Normaliza el CP usando el prefijo_postal del país automáticamente.
     */
    public static function resolverHomologacion($id_pais, $codigo_postal, $data_adicional = []) {
        if (empty($id_pais) || empty($codigo_postal)) return null;

        $cp_norm = self::normalizarCP($codigo_postal, $id_pais);

        // 1. Buscar si ya existe
        $id_barrio_buscar = $data_adicional['id_barrio'] ?? null;
        $db = (new Conexion())->conectar();
        if ($id_barrio_buscar) {
            $stmt = $db->prepare("SELECT * FROM codigos_postales WHERE id_pais = :id_pais AND codigo_postal = :cp AND id_barrio = :id_barrio");
            $stmt->execute([':id_pais' => $id_pais, ':cp' => $cp_norm, ':id_barrio' => $id_barrio_buscar]);
        } else {
            $stmt = $db->prepare("SELECT * FROM codigos_postales WHERE id_pais = :id_pais AND codigo_postal = :cp AND id_barrio IS NULL");
            $stmt->execute([':id_pais' => $id_pais, ':cp' => $cp_norm]);
        }
        $homologacion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($homologacion) {
            return $homologacion;
        }

        // 2. Si no existe, crear un registro "parcial"
        try {
            $nuevo_id = CodigosPostalesModel::crear([
                'id_pais'         => $id_pais,
                'codigo_postal'   => $cp_norm,
                'id_departamento' => $data_adicional['id_departamento'] ?? null,
                'id_municipio'    => $data_adicional['id_municipio']    ?? null,
                'id_barrio'       => $data_adicional['id_barrio']       ?? null,
                'nombre_localidad'=> $data_adicional['nombre_localidad']?? null,
            ]);

            if ($nuevo_id) {
                return CodigosPostalesModel::obtenerPorId($nuevo_id);
            }
        } catch (Exception $e) {
            // Race condition: duplicado → re-consultar
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
