<?php
require_once __DIR__ . '/../modelo/codigos_postales.php';
require_once __DIR__ . '/../services/AddressService.php';
require_once __DIR__ . '/../services/ImportCpService.php';

class CodigosPostalesController {

    /**
     * POST /codigos_postales/import/preview
     * Parsea el archivo, valida y devuelve vista previa JSON.
     */
    public function importPreview() {
        header('Content-Type: application/json; charset=utf-8');
        ob_start();

        require_once __DIR__ . '/../utils/authorization.php';
        $rolesNombres = $_SESSION['roles_nombres'] ?? [];
        $puedeImportar = in_array('Administrador', $rolesNombres, true) || in_array('Vendedor', $rolesNombres, true);
        if (!$puedeImportar) {
            ob_end_clean();
            echo json_encode(['ok' => false, 'message' => 'Sin permisos para importar.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE) {
            ob_end_clean();
            echo json_encode(['ok' => false, 'message' => 'No se recibió ningún archivo.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $opciones = [
            'modo'           => $_POST['modo']           ?? 'upsert',
            'crear_geo'      => ($_POST['crear_geo']      ?? '1') === '1',
            'default_activo' => (int)($_POST['default_activo'] ?? 1),
        ];

        $userId = $_SESSION['user_id'] ?? 0;

        ob_end_clean();
        try {
            $result = ImportCpService::previewImport($_FILES['archivo'], $opciones, $userId);
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /**
     * POST /codigos_postales/import/commit
     * Ejecuta la importación real desde el job temporal.
     */
    public function importCommit() {
        header('Content-Type: application/json; charset=utf-8');
        ob_start();

        require_once __DIR__ . '/../utils/authorization.php';
        $rolesNombres2 = $_SESSION['roles_nombres'] ?? [];
        $puedeImportar2 = in_array('Administrador', $rolesNombres2, true) || in_array('Vendedor', $rolesNombres2, true);
        if (!$puedeImportar2) {
            ob_end_clean();
            echo json_encode(['ok' => false, 'message' => 'Sin permisos para importar.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $jobId  = $_POST['job_id'] ?? '';
        $userId = $_SESSION['user_id'] ?? 0;

        if (empty($jobId)) {
            ob_end_clean();
            echo json_encode(['ok' => false, 'message' => 'job_id requerido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        ob_end_clean();
        try {
            $result = ImportCpService::commitImport($jobId, $userId);
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }


    
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
