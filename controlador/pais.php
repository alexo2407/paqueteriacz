<?php
require_once __DIR__ . '/../modelo/pais.php';

/**
 * PaisesController
 *
 * Controlador para CRUD de países. Encapsula validaciones sencillas y
 * delega la persistencia a `PaisModel`.
 */
class PaisesController
{
    /**
     * Listar países disponibles.
     * @return array
     */
    public function listar()
    {
        return PaisModel::listar();
    }

    /**
     * Obtener país por id.
     * @param int $id
     * @return array|null
     */
    public function ver($id)
    {
        return PaisModel::obtenerPorId($id);
    }

    /**
     * Crear un nuevo país.
     * @param array $data Espera 'nombre' y opcionalmente 'codigo_iso'
     * @return array Envelope con success/message/id
     */
    public function crear(array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $codigo = trim($data['codigo_iso'] ?? null);
        if ($nombre === '') return ['success' => false, 'message' => 'Nombre obligatorio.', 'id' => null];
        $id = PaisModel::crear($nombre, $codigo);
        return $id ? ['success' => true, 'message' => 'País creado.', 'id' => $id] : ['success' => false, 'message' => 'No fue posible crear.','id'=>null];
    }

    /**
     * Actualizar país.
     * @param int $id
     * @param array $data
     * @return array Envelope con success/message
     */
    public function actualizar($id, array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        $codigo = trim($data['codigo_iso'] ?? null);
        if ($nombre === '') return ['success' => false, 'message' => 'Nombre obligatorio.'];
        $ok = PaisModel::actualizar($id, $nombre, $codigo);
        return $ok ? ['success' => true, 'message' => 'País actualizado.'] : ['success' => false, 'message' => 'No fue posible actualizar.'];
    }

    /**
     * Eliminar país por id.
     * @param int $id
     * @return array Envelope con success/message
     */
    public function eliminar($id)
    {
        if ($id <= 0) return ['success' => false, 'message' => 'ID inválido.'];
        $ok = PaisModel::eliminar($id);
        return $ok ? ['success' => true, 'message' => 'País eliminado.'] : ['success' => false, 'message' => 'No fue posible eliminar.'];
    }
}
