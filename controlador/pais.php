<?php
require_once __DIR__ . '/../modelo/pais.php';

class PaisesController
{
    public function listar()
    {
        return PaisModel::listar();
    }

    public function ver($id)
    {
        return PaisModel::obtenerPorId($id);
    }

    public function crear(array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $codigo = trim($data['codigo_iso'] ?? null);
        if ($nombre === '') return ['success' => false, 'message' => 'Nombre obligatorio.', 'id' => null];
        $id = PaisModel::crear($nombre, $codigo);
        return $id ? ['success' => true, 'message' => 'País creado.', 'id' => $id] : ['success' => false, 'message' => 'No fue posible crear.','id'=>null];
    }

    public function actualizar($id, array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $codigo = trim($data['codigo_iso'] ?? null);
        if ($nombre === '') return ['success' => false, 'message' => 'Nombre obligatorio.'];
        $ok = PaisModel::actualizar($id, $nombre, $codigo);
        return $ok ? ['success' => true, 'message' => 'País actualizado.'] : ['success' => false, 'message' => 'No fue posible actualizar.'];
    }

    public function eliminar($id)
    {
        if ($id <= 0) return ['success' => false, 'message' => 'ID inválido.'];
        $ok = PaisModel::eliminar($id);
        return $ok ? ['success' => true, 'message' => 'País eliminado.'] : ['success' => false, 'message' => 'No fue posible eliminar.'];
    }
}
