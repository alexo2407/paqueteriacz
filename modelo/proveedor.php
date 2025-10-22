<?php

include_once __DIR__ . '/conexion.php';

class ProveedorModel
{
    public static function getAll()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT id, nombre, email, telefono, creado_en FROM proveedores");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener proveedores: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    public static function getById($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT id, nombre, email, telefono, creado_en FROM proveedores WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener proveedor: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function create($data)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("INSERT INTO proveedores (nombre, email, telefono, creado_en) VALUES (:nombre, :email, :telefono, NOW())");
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':telefono', $data['telefono']);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al crear proveedor: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    public static function update($id, $data)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("UPDATE proveedores SET nombre = :nombre, email = :email, telefono = :telefono WHERE id = :id");
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':telefono', $data['telefono']);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al actualizar proveedor: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    public static function delete($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("DELETE FROM proveedores WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al eliminar proveedor: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }
}
