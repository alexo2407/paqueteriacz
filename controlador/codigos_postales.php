<?php
require_once __DIR__ . '/../modelo/codigos_postales.php';
require_once __DIR__ . '/../services/AddressService.php';

class CodigosPostalesController {
    
    /**
     * Listar CPs con filtros
     */
    public function listar($filtros = [], $pagina = 1, $limite = 20) {
        return CodigosPostalesModel::listar($filtros, $pagina, $limite);
    }

    /**
     * Ver un CP por ID
     */
    public function ver($id) {
        return CodigosPostalesModel::obtenerPorId($id);
    }

    /**
     * Crear nuevo CP
     */
    public function crear($data) {
        $id_pais = $data['id_pais'] ?? null;
        $cp = AddressService::normalizarCP($data['codigo_postal'] ?? '');

        if (!$id_pais || !$cp) {
            return ['success' => false, 'message' => 'País y Código Postal son obligatorios.'];
        }

        if (CodigosPostalesModel::existe($id_pais, $cp)) {
            return ['success' => false, 'message' => 'Este código postal ya está registrado para este país.'];
        }

        $id = CodigosPostalesModel::crear([
            'id_pais' => $id_pais,
            'codigo_postal' => $cp,
            'id_departamento' => $data['id_departamento'] ?? null,
            'id_municipio' => $data['id_municipio'] ?? null,
            'id_barrio' => $data['id_barrio'] ?? null,
            'nombre_localidad' => $data['nombre_localidad'] ?? null,
            'activo' => isset($data['activo']) ? (int)$data['activo'] : 1
        ]);

        return $id ? ['success' => true, 'message' => 'Código postal creado con éxito.', 'id' => $id] 
                   : ['success' => false, 'message' => 'Error al crear el código postal.'];
    }

    /**
     * Actualizar CP
     */
    public function actualizar($id, $data) {
        $id_pais = $data['id_pais'] ?? null;
        $cp = AddressService::normalizarCP($data['codigo_postal'] ?? '');

        if (!$id_pais || !$cp) {
            return ['success' => false, 'message' => 'País y Código Postal son obligatorios.'];
        }

        if (CodigosPostalesModel::existe($id_pais, $cp, $id)) {
            return ['success' => false, 'message' => 'Este código postal ya está registrado para este país.'];
        }

        $ok = CodigosPostalesModel::actualizar($id, [
            'id_pais' => $id_pais,
            'codigo_postal' => $cp,
            'id_departamento' => $data['id_departamento'] ?? null,
            'id_municipio' => $data['id_municipio'] ?? null,
            'id_barrio' => $data['id_barrio'] ?? null,
            'nombre_localidad' => $data['nombre_localidad'] ?? null,
            'activo' => isset($data['activo']) ? (int)$data['activo'] : 1
        ]);

        return $ok ? ['success' => true, 'message' => 'Código postal actualizado con éxito.'] 
                   : ['success' => false, 'message' => 'Error al actualizar el código postal.'];
    }

    /**
     * Toggle estado
     */
    /**
     * Toggle estado
     */
    public function toggle($id) {
        $ok = CodigosPostalesModel::toggle($id);
        return ['success' => $ok, 'message' => $ok ? 'Estado actualizado.' : 'Error al actualizar estado.'];
    }

    /**
     * Buscar CP por país y código (para AJAX)
     */
    public function buscarPorCodigo($id_pais, $codigo_postal) {
        $cp_norm = AddressService::normalizarCP($codigo_postal);
        $homologacion = AddressService::resolverHomologacion($id_pais, $cp_norm);

        if ($homologacion) {
            return ['success' => true, 'data' => $homologacion];
        }
        return ['success' => false, 'message' => 'Código postal no encontrado o sin mapear.'];
    }
}
