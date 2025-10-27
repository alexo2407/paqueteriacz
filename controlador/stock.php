<?php

require_once __DIR__ . '/../modelo/stock.php';
require_once __DIR__ . '/../utils/session.php';

class StockController
{
    public function listar()
    {
        return StockModel::listar();
    }

    public function ver($id)
    {
        return StockModel::obtenerPorId($id);
    }

    public function crear(array $data)
    {
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

    public function actualizar($id, array $data)
    {
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

    private function validarDatos(array $data)
    {
        $errores = [];

        $idVendedor = isset($data['id_vendedor']) ? (int) $data['id_vendedor'] : 0;
        $producto = isset($data['producto']) ? trim($data['producto']) : '';
        $cantidad = isset($data['cantidad']) ? (int) $data['cantidad'] : null;

        if ($idVendedor <= 0) {
            $errores[] = 'El vendedor es obligatorio.';
        }

        if ($producto === '') {
            $errores[] = 'El producto es obligatorio.';
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
