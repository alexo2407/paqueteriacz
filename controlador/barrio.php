<?php
require_once __DIR__ . '/../modelo/barrio.php';
require_once __DIR__ . '/../modelo/municipio.php';

/**
 * BarriosController
 *
 * CRUD y operaciones auxiliares sobre la entidad Barrio. Trabaja con
 * BarrioModel para las consultas y mantiene validaciones simples.
 */
class BarriosController
{
    /**
     * Listar barrios, opcionalmente filtrados por municipio.
     *
     * @param int|null $munId Identificador de municipio o null para no filtrar.
     * @return array Lista de barrios.
     */
    public function listar($munId = null)
    {
        return BarrioModel::listarPorMunicipio($munId);
    }

    /**
     * Obtener un barrio por su id.
     * @param int $id
     * @return array|null
     */
    public function ver($id)
    {
        return BarrioModel::obtenerPorId($id);
    }

    /**
     * Crear un nuevo barrio.
     *
     * @param array $data Claves esperadas: 'nombre', 'id_municipio'
     * @return array Envelope con success/message/id
     */
    public function crear(array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $mun = isset($data['id_municipio']) ? (int)$data['id_municipio'] : null;
        if ($nombre === '' || !$mun) return ['success'=>false,'message'=>'Nombre y municipio obligatorios.','id'=>null];
        $id = BarrioModel::crear($nombre, $mun);
        return $id ? ['success'=>true,'message'=>'Barrio creado.','id'=>$id] : ['success'=>false,'message'=>'No fue posible crear.','id'=>null];
    }

    /**
     * Actualizar un barrio existente.
     * @param int $id
     * @param array $data
     * @return array Envelope con success/message
     */
    public function actualizar($id, array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $mun = isset($data['id_municipio']) ? (int)$data['id_municipio'] : null;
        if ($nombre === '' || !$mun) return ['success'=>false,'message'=>'Nombre y municipio obligatorios.'];
        $ok = BarrioModel::actualizar($id, $nombre, $mun);
        return $ok ? ['success'=>true,'message'=>'Barrio actualizado.'] : ['success'=>false,'message'=>'No fue posible actualizar.'];
    }

    /**
     * Eliminar un barrio por id.
     * @param int $id
     * @return array Envelope con success/message
     */
    public function eliminar($id)
    {
        if ($id <= 0) return ['success'=>false,'message'=>'ID inválido.'];
        $ok = BarrioModel::eliminar($id);
        return $ok ? ['success'=>true,'message'=>'Barrio eliminado.'] : ['success'=>false,'message'=>'No fue posible eliminar.'];
    }
}
