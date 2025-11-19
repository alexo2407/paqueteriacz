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
    public function listar($munId = null)
    {
        return BarrioModel::listarPorMunicipio($munId);
    }

    public function ver($id)
    {
        return BarrioModel::obtenerPorId($id);
    }

    public function crear(array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $mun = isset($data['id_municipio']) ? (int)$data['id_municipio'] : null;
        if ($nombre === '' || !$mun) return ['success'=>false,'message'=>'Nombre y municipio obligatorios.','id'=>null];
        $id = BarrioModel::crear($nombre, $mun);
        return $id ? ['success'=>true,'message'=>'Barrio creado.','id'=>$id] : ['success'=>false,'message'=>'No fue posible crear.','id'=>null];
    }

    public function actualizar($id, array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $mun = isset($data['id_municipio']) ? (int)$data['id_municipio'] : null;
        if ($nombre === '' || !$mun) return ['success'=>false,'message'=>'Nombre y municipio obligatorios.'];
        $ok = BarrioModel::actualizar($id, $nombre, $mun);
        return $ok ? ['success'=>true,'message'=>'Barrio actualizado.'] : ['success'=>false,'message'=>'No fue posible actualizar.'];
    }

    public function eliminar($id)
    {
        if ($id <= 0) return ['success'=>false,'message'=>'ID inválido.'];
        $ok = BarrioModel::eliminar($id);
        return $ok ? ['success'=>true,'message'=>'Barrio eliminado.'] : ['success'=>false,'message'=>'No fue posible eliminar.'];
    }
}
