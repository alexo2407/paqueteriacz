<?php

require_once __DIR__ . '/../modelo/proveedor.php';

class ProveedorController
{
    public function listarProveedores()
    {
        return ProveedorModel::getAll();
    }

    public function verProveedor($id)
    {
        return ProveedorModel::getById($id);
    }

    public function crearProveedor($data)
    {
        return ProveedorModel::create($data);
    }

    public function actualizarProveedor($id, $data)
    {
        return ProveedorModel::update($id, $data);
    }

    public function eliminarProveedor($id)
    {
        return ProveedorModel::delete($id);
    }
}
