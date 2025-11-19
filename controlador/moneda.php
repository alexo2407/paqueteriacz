<?php
require_once __DIR__ . '/../modelo/moneda.php';

/**
 * MonedasController
 *
 * Controlador para CRUD de monedas. Valida entradas mínimas y delega la
 * persistencia a `MonedaModel`.
 */
class MonedasController
{
    /**
     * Listar monedas disponibles.
     * @return array
     */
    public function listar()
    {
        return MonedaModel::listar();
    }

    /**
     * Obtener moneda por id.
     * @param int $id
     * @return array|null
     */
    public function ver($id)
    {
        return MonedaModel::obtenerPorId($id);
    }

    /**
     * Crear una nueva moneda.
     * @param array $data ['codigo','nombre','tasa_usd']
     * @return array Envelope con success/message/id
     */
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

    /**
     * Actualizar moneda.
     * @param int $id
     * @param array $data
     * @return array Envelope con success/message
     */
    public function actualizar($id, array $data)
    {
        $codigo = trim($data['codigo'] ?? '');
        $nombre = trim($data['nombre'] ?? '');
        $tasa = $data['tasa_usd'] ?? null;
        if ($codigo === '' || $nombre === '') return ['success' => false, 'message' => 'Código y nombre obligatorios.'];
        $ok = MonedaModel::actualizar($id, $codigo, $nombre, $tasa);
        return $ok ? ['success' => true, 'message' => 'Moneda actualizada.'] : ['success' => false, 'message' => 'No fue posible actualizar.'];
    }

    /**
     * Eliminar moneda por id.
     * @param int $id
     * @return array Envelope con success/message
     */
    public function eliminar($id)
    {
        if ($id <= 0) return ['success' => false, 'message' => 'ID inválido.'];
        $ok = MonedaModel::eliminar($id);
        return $ok ? ['success' => true, 'message' => 'Moneda eliminada.'] : ['success' => false, 'message' => 'No fue posible eliminar.'];
    }
}
