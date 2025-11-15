<?php

require_once __DIR__ . '/../modelo/stock.php';
require_once __DIR__ . '/../utils/session.php';

/**
 * Class StockController
 *
 * Controlador para operaciones CRUD sobre la entidad "stock".
 * Valida entradas y delega la persistencia en modelo/stock.php (StockModel).
 *
 * Notas importantes:
 * - La gestión de inventario (movimientos/ajustes) se realiza preferentemente
 *   por triggers en la base de datos; evitar duplicar lógica en PHP que
 *   pueda entrar en conflicto con dichos triggers.
 */
class StockController
{
    /**
     * Obtener todos los registros de stock.
     *
     * @return array Lista de registros (cada registro es un array asociativo con
     *               keys: id, id_vendedor, producto, cantidad). Devuelve [] si hay error.
     */
    public function listar()
    {
        return StockModel::listar();
    }

    /**
     * Obtener un registro de stock por su identificador.
     *
     * @param int|string $id Identificador del registro.
     * @return array|null Registro asociado o null si no existe.
     */
    public function ver($id)
    {
        return StockModel::obtenerPorId($id);
    }

    /**
     * Crear un nuevo registro de stock tras validar los datos.
     *
    * @param array $data Datos del stock:
    *                    - int    id_usuario (obligatorio, >0)
    *                    - int    id_producto (obligatorio, >0)
    *                    - int    cantidad     (obligatorio, >=0)
     * @return array Respuesta estructurada:
     *               - Si validación falla: ['success'=>false,'message'=>...,'errors'=>[]]
     *               - Si inserción falla: ['success'=>false,'message'=>...]
     *               - Si éxito: ['success'=>true,'message'=>...,'id'=> <nuevoId>]
     */
    public function crear(array $data)
    {
        // Normalizar claves para StockModel (id_usuario, id_producto)
        if (isset($data['id_vendedor']) && !isset($data['id_usuario'])) {
            $data['id_usuario'] = (int)$data['id_vendedor'];
        }
        if (isset($data['producto']) && !isset($data['id_producto'])) {
            // intentar resolver producto si vino como nombre (no intentamos crear aquí)
            if (is_numeric($data['producto'])) {
                $data['id_producto'] = (int)$data['producto'];
            }
        }

        $validacion = $this->validarDatos($data);
        if (!$validacion['success']) {
            return $validacion;
        }

        $nuevoId = StockModel::crear($data);
        if ($nuevoId === false) {
            return [
                'success' => false,
                'message' => 'No fue posible registrar el stock.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Stock registrado correctamente.',
            'id' => $nuevoId
        ];
    }

    /**
     * Actualizar un registro de stock existente.
     *
     * @param int $id Identificador del registro a actualizar.
     * @param array $data Mismos campos que en crear().
     * @return array Respuesta estructurada indicando éxito o fallo.
     */
    public function actualizar($id, array $data)
    {
        // Normalizar claves para StockModel
        if (isset($data['id_vendedor']) && !isset($data['id_usuario'])) {
            $data['id_usuario'] = (int)$data['id_vendedor'];
        }
        if (isset($data['producto']) && !isset($data['id_producto'])) {
            if (is_numeric($data['producto'])) {
                $data['id_producto'] = (int)$data['producto'];
            }
        }

        $validacion = $this->validarDatos($data);
        if (!$validacion['success']) {
            return $validacion;
        }

        $ok = StockModel::actualizar($id, $data);
        if (!$ok) {
            return [
                'success' => false,
                'message' => 'No se pudo actualizar el registro.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Stock actualizado correctamente.'
        ];
    }

    /**
     * Eliminar un registro de stock por id.
     *
     * @param int $id Identificador del registro.
     * @return array Respuesta estructurada indicando éxito o fallo.
     */
    public function eliminar($id)
    {
        $ok = StockModel::eliminar($id);
        if (!$ok) {
            return [
                'success' => false,
                'message' => 'No se pudo eliminar el registro.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Registro eliminado.'
        ];
    }

    /**
     * Validar datos mínimos para crear/actualizar stock.
     *
     * @param array $data Datos recibidos.
     * @return array Si falla: ['success'=>false,'message'=>...,'errors'=>[]]; si OK: ['success'=>true]
     */
    private function validarDatos(array $data)
    {
        $errores = [];

    $idUsuario = isset($data['id_usuario']) ? (int) $data['id_usuario'] : 0;
    // Aceptamos id_producto o producto (nombre) — preferimos id_producto
    $idProducto = isset($data['id_producto']) ? (int) $data['id_producto'] : null;
    $productoNombre = isset($data['producto']) ? trim($data['producto']) : '';
    $cantidad = isset($data['cantidad']) ? (int) $data['cantidad'] : null;
        $cantidad = isset($data['cantidad']) ? (int) $data['cantidad'] : null;

        if ($idUsuario <= 0) {
            $errores[] = 'El usuario (id_usuario) es obligatorio.';
        }

        if ($idProducto === null || $idProducto <= 0) {
            // permitir que se envíe nombre de producto y resolver fuera del validador
            if ($productoNombre === '') {
                $errores[] = 'El producto es obligatorio (id_producto o nombre).';
            }
        }

        if ($cantidad === null || $cantidad < 0) {
            $errores[] = 'La cantidad debe ser un número igual o mayor a cero.';
        }

        if (!empty($errores)) {
            return [
                'success' => false,
                'message' => 'Revisa los datos ingresados.',
                'errors' => $errores
            ];
        }

        return [
            'success' => true
        ];
    }
}
