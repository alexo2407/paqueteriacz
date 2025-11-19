<?php
require_once __DIR__ . '/../modelo/departamento.php';
require_once __DIR__ . '/../modelo/pais.php';

/**
 * DepartamentosController
 *
 * Controller para gestión de departamentos/regiones. Valida datos mínimos
 * y delega operaciones CRUD a `DepartamentoModel`.
 */
class DepartamentosController
{
    public function listar($paisId = null)
    {
        return DepartamentoModel::listarPorPais($paisId);
    }

    public function ver($id)
    {
        return DepartamentoModel::obtenerPorId($id);
    }

    public function crear(array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $pais = isset($data['id_pais']) ? (int)$data['id_pais'] : null;
        if ($nombre === '' || !$pais) return ['success'=>false,'message'=>'Nombre y país obligatorios.','id'=>null];
        $id = DepartamentoModel::crear($nombre, $pais);
        return $id ? ['success'=>true,'message'=>'Departamento creado.','id'=>$id] : ['success'=>false,'message'=>'No fue posible crear.','id'=>null];
    }

    public function actualizar($id, array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $pais = isset($data['id_pais']) ? (int)$data['id_pais'] : null;
        if ($nombre === '' || !$pais) return ['success'=>false,'message'=>'Nombre y país obligatorios.'];
        $ok = DepartamentoModel::actualizar($id, $nombre, $pais);
        return $ok ? ['success'=>true,'message'=>'Departamento actualizado.'] : ['success'=>false,'message'=>'No fue posible actualizar.'];
    }

    public function eliminar($id)
    {
        if ($id <= 0) return ['success'=>false,'message'=>'ID inválido.'];
        $ok = DepartamentoModel::eliminar($id);
        return $ok ? ['success'=>true,'message'=>'Departamento eliminado.'] : ['success'=>false,'message'=>'No fue posible eliminar.'];
    }
}
