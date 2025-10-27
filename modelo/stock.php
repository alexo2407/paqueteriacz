<?php

include_once __DIR__ . '/conexion.php';

class StockModel
{
    public static function listar()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, id_vendedor, producto, cantidad FROM stock');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    public static function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, id_vendedor, producto, cantidad FROM stock WHERE id = :id');
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener registro de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function crear(array $data)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('INSERT INTO stock (id_vendedor, producto, cantidad) VALUES (:id_vendedor, :producto, :cantidad)');
            $stmt->bindValue(':id_vendedor', $data['id_vendedor'], PDO::PARAM_INT);
            $stmt->bindValue(':producto', $data['producto'], PDO::PARAM_STR);
            $stmt->bindValue(':cantidad', $data['cantidad'], PDO::PARAM_INT);
            $ok = $stmt->execute();
            return $ok ? (int) $db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log('Error al crear registro de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    public static function actualizar($id, array $data)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE stock SET id_vendedor = :id_vendedor, producto = :producto, cantidad = :cantidad WHERE id = :id');
            $stmt->bindValue(':id_vendedor', $data['id_vendedor'], PDO::PARAM_INT);
            $stmt->bindValue(':producto', $data['producto'], PDO::PARAM_STR);
            $stmt->bindValue(':cantidad', $data['cantidad'], PDO::PARAM_INT);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al actualizar stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    public static function eliminar($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('DELETE FROM stock WHERE id = :id');
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al eliminar stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    public static function ajustarCantidad($id, $diferencia)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE stock SET cantidad = cantidad + :diferencia WHERE id = :id');
            $stmt->bindValue(':diferencia', $diferencia, PDO::PARAM_INT);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Error al ajustar cantidad de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }
}
