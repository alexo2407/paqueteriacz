<?php
require_once __DIR__ . '/../modelo/municipio.php';
require_once __DIR__ . '/../modelo/departamento.php';

/**
 * MunicipiosController
 *
 * Controlador para CRUD de municipios. Realiza validaciones simples y
 * delega la persistencia a `MunicipioModel`.
 */
class MunicipiosController
{
    /**
     * Listar municipios, opcionalmente filtrados por departamento.
     * @param int|null $depId
     * @return array
     */
    public function listar($depId = null)
    {
        return MunicipioModel::listarPorDepartamento($depId);
    }

    /**
     * Obtener un municipio por id.
     * @param int $id
     * @return array|null
     */
    public function ver($id)
    {
        return MunicipioModel::obtenerPorId($id);
    }

    /**
     * Crear un nuevo municipio.
     * @param array $data Espera 'nombre' y 'id_departamento'
     * @return array Envelope con success/message/id
     */
    public function crear(array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $dep = isset($data['id_departamento']) ? (int)$data['id_departamento'] : null;
        $cp = trim($data['codigo_postal'] ?? '');
        if ($cp === '') $cp = null;
        if ($nombre === '' || !$dep) return ['success'=>false,'message'=>'Nombre y departamento obligatorios.','id'=>null];
        $id = MunicipioModel::crear($nombre, $dep, $cp);
        return $id ? ['success'=>true,'message'=>'Municipio creado.','id'=>$id] : ['success'=>false,'message'=>'No fue posible crear.','id'=>null];
    }

    /**
     * Actualizar un municipio.
     * @param int $id
     * @param array $data
     * @return array Envelope con success/message
     */
    public function actualizar($id, array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $dep = isset($data['id_departamento']) ? (int)$data['id_departamento'] : null;
        $cp = trim($data['codigo_postal'] ?? '');
        if ($cp === '') $cp = null;
        if ($nombre === '' || !$dep) return ['success'=>false,'message'=>'Nombre y departamento obligatorios.'];
        $ok = MunicipioModel::actualizar($id, $nombre, $dep, $cp);
        return $ok ? ['success'=>true,'message'=>'Municipio actualizado.'] : ['success'=>false,'message'=>'No fue posible actualizar.'];
    }

    /**
     * Eliminar un municipio por id.
     * @param int $id
     * @return array Envelope con success/message
     */
    public function eliminar($id)
    {
        if ($id <= 0) return ['success'=>false,'message'=>'ID inválido.'];
        $ok = MunicipioModel::eliminar($id);
        return $ok ? ['success'=>true,'message'=>'Municipio eliminado.'] : ['success'=>false,'message'=>'No fue posible eliminar.'];
    }
}
