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
    /**
     * Listar departamentos, opcionalmente filtrados por país.
     *
     * @param int|null $paisId Identificador de país o null para todos.
     * @return array
     */
    public function listar($paisId = null)
    {
        return DepartamentoModel::listarPorPais($paisId);
    }

    /**
     * Obtener departamento por id.
     * @param int $id
     * @return array|null
     */
    public function ver($id)
    {
        return DepartamentoModel::obtenerPorId($id);
    }

    /**
     * Crear un nuevo departamento.
     * @param array $data Espera 'nombre' y 'id_pais'
     * @return array Envelope con success/message/id
     */
    public function crear(array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $pais = isset($data['id_pais']) ? (int)$data['id_pais'] : null;
        if ($nombre === '' || !$pais) return ['success'=>false,'message'=>'Nombre y país obligatorios.','id'=>null];
        $id = DepartamentoModel::crear($nombre, $pais);
        return $id ? ['success'=>true,'message'=>'Departamento creado.','id'=>$id] : ['success'=>false,'message'=>'No fue posible crear.','id'=>null];
    }

    /**
     * Actualizar departamento.
     * @param int $id
     * @param array $data
     * @return array Envelope con success/message
     */
    public function actualizar($id, array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $pais = isset($data['id_pais']) ? (int)$data['id_pais'] : null;
        if ($nombre === '' || !$pais) return ['success'=>false,'message'=>'Nombre y país obligatorios.'];
        $ok = DepartamentoModel::actualizar($id, $nombre, $pais);
        return $ok ? ['success'=>true,'message'=>'Departamento actualizado.'] : ['success'=>false,'message'=>'No fue posible actualizar.'];
    }

    /**
     * Eliminar departamento por id.
     * @param int $id
     * @return array Envelope con success/message
     */
    public function eliminar($id)
    {
        if ($id <= 0) return ['success'=>false,'message'=>'ID inválido.'];
        $ok = DepartamentoModel::eliminar($id);
        return $ok ? ['success'=>true,'message'=>'Departamento eliminado.'] : ['success'=>false,'message'=>'No fue posible eliminar.'];
    }
}
