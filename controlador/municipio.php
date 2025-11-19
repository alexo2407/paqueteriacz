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
    public function listar($depId = null)
    {
        return MunicipioModel::listarPorDepartamento($depId);
    }

    public function ver($id)
    {
        return MunicipioModel::obtenerPorId($id);
    }

    public function crear(array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $dep = isset($data['id_departamento']) ? (int)$data['id_departamento'] : null;
        if ($nombre === '' || !$dep) return ['success'=>false,'message'=>'Nombre y departamento obligatorios.','id'=>null];
        $id = MunicipioModel::crear($nombre, $dep);
        return $id ? ['success'=>true,'message'=>'Municipio creado.','id'=>$id] : ['success'=>false,'message'=>'No fue posible crear.','id'=>null];
    }

    public function actualizar($id, array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $dep = isset($data['id_departamento']) ? (int)$data['id_departamento'] : null;
        if ($nombre === '' || !$dep) return ['success'=>false,'message'=>'Nombre y departamento obligatorios.'];
        $ok = MunicipioModel::actualizar($id, $nombre, $dep);
        return $ok ? ['success'=>true,'message'=>'Municipio actualizado.'] : ['success'=>false,'message'=>'No fue posible actualizar.'];
    }

    public function eliminar($id)
    {
        if ($id <= 0) return ['success'=>false,'message'=>'ID inválido.'];
        $ok = MunicipioModel::eliminar($id);
        return $ok ? ['success'=>true,'message'=>'Municipio eliminado.'] : ['success'=>false,'message'=>'No fue posible eliminar.'];
    }
}
