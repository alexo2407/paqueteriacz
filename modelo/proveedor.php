<?php

include_once "conexion.php";

class ProveedorModel
{
    public static function getAll()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT id, nombre, email, telefono, pais FROM proveedores");
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
            $stmt = $db->prepare("SELECT id, nombre, email, telefono, pais FROM proveedores WHERE id = :id");
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
            // Insert with pais and contrasena (hashed if provided)
            $stmt = $db->prepare("INSERT INTO proveedores (nombre, telefono, email, pais, contrasena) VALUES (:nombre, :telefono, :email, :pais, :contrasena)");
            $stmt->bindValue(':nombre', $data['nombre'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':telefono', $data['telefono'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':email', $data['email'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':pais', $data['pais'] ?? null, PDO::PARAM_STR);

            // Hash password if provided, otherwise set NULL
            $passwordValue = null;
            if (!empty($data['contrasena'])) {
                $passwordValue = password_hash($data['contrasena'], PASSWORD_DEFAULT);
            }
            $stmt->bindValue(':contrasena', $passwordValue, PDO::PARAM_STR);

            $ok = $stmt->execute();
            if ($ok) {
                return $db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log('Error al crear proveedor: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    public static function update($id, $data)
    {
        try {
            $db = (new Conexion())->conectar();
            // Update fields; update password only if provided
            $fields = [];
            $params = [];
            $fields[] = 'nombre = :nombre';
            $params[':nombre'] = [$data['nombre'] ?? null, PDO::PARAM_STR];
            $fields[] = 'telefono = :telefono';
            $params[':telefono'] = [$data['telefono'] ?? null, PDO::PARAM_STR];
            $fields[] = 'email = :email';
            $params[':email'] = [$data['email'] ?? null, PDO::PARAM_STR];
            $fields[] = 'pais = :pais';
            $params[':pais'] = [$data['pais'] ?? null, PDO::PARAM_STR];

            if (!empty($data['contrasena'])) {
                $fields[] = 'contrasena = :contrasena';
                $params[':contrasena'] = [password_hash($data['contrasena'], PASSWORD_DEFAULT), PDO::PARAM_STR];
            }

            $sql = "UPDATE proveedores SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            foreach ($params as $p => $val) {
                $stmt->bindValue($p, $val[0], $val[1]);
            }
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
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
