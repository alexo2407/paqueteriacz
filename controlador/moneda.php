<?php
require_once __DIR__ . '/../modelo/moneda.php';

class MonedasController
{
    public function listar()
    {
        return MonedaModel::listar();
    }

    public function ver($id)
    {
        return MonedaModel::obtenerPorId($id);
    }

    public function crear(array $data)
    {
        $codigo = trim($data['codigo'] ?? '');
        $nombre = trim($data['nombre'] ?? '');
        $tasa = $data['tasa_usd'] ?? null;
        if ($codigo === '' || $nombre === '') {
            return ['success' => false, 'message' => 'Código y nombre son obligatorios.', 'id' => null];
        }
        $id = MonedaModel::crear($codigo, $nombre, $tasa);
        if ($id === null) return ['success' => false, 'message' => 'No fue posible crear la moneda.', 'id' => null];
        return ['success' => true, 'message' => 'Moneda creada correctamente.', 'id' => $id];
    }

    public function actualizar($id, array $data)
    {
        $codigo = trim($data['codigo'] ?? '');
        $nombre = trim($data['nombre'] ?? '');
        $tasa = $data['tasa_usd'] ?? null;
        if ($codigo === '' || $nombre === '') return ['success' => false, 'message' => 'Código y nombre obligatorios.'];
        $ok = MonedaModel::actualizar($id, $codigo, $nombre, $tasa);
        return $ok ? ['success' => true, 'message' => 'Moneda actualizada.'] : ['success' => false, 'message' => 'No fue posible actualizar.'];
    }

    public function eliminar($id)
    {
        if ($id <= 0) return ['success' => false, 'message' => 'ID inválido.'];
        $ok = MonedaModel::eliminar($id);
        return $ok ? ['success' => true, 'message' => 'Moneda eliminada.'] : ['success' => false, 'message' => 'No fue posible eliminar.'];
    }
}
