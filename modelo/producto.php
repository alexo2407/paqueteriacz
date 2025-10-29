<?php

include_once __DIR__ . '/conexion.php';

class ProductoModel
{
    public static function listarConInventario()
    {
        try {
            $db = (new Conexion())->conectar();
            $sql = 'SELECT 
                        p.id,
                        p.nombre,
                        p.descripcion,
                        p.precio_usd,
                        COALESCE(SUM(s.cantidad), 0) AS stock_total
                    FROM productos p
                    LEFT JOIN stock s ON s.id_producto = p.id
                    GROUP BY p.id, p.nombre, p.descripcion, p.precio_usd
                    ORDER BY p.nombre ASC';
            $stmt = $db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar productos: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    public static function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, nombre, descripcion, precio_usd FROM productos WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener producto: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Obtener stock total disponible para un producto (suma de stock.cantidad)
     * Devuelve null si ocurre un error o no existe el producto
     */
    public static function obtenerStockTotal($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT COALESCE(SUM(cantidad), 0) as stock_total FROM stock WHERE id_producto = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) return null;
            return isset($row['stock_total']) ? (int)$row['stock_total'] : 0;
        } catch (PDOException $e) {
            error_log('Error al obtener stock del producto: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function buscarPorNombre($nombre)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, nombre, descripcion, precio_usd FROM productos WHERE nombre = :nombre');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al buscar producto: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function crearRapido($nombre, $descripcion = null, $precioUsd = null)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('INSERT INTO productos (nombre, descripcion, precio_usd) VALUES (:nombre, :descripcion, :precio_usd)');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            if ($descripcion === null || $descripcion === '') {
                $stmt->bindValue(':descripcion', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
            }
            if ($precioUsd === null || $precioUsd === '') {
                $stmt->bindValue(':precio_usd', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':precio_usd', $precioUsd);
            }
            $stmt->execute();
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error al crear producto rápido: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }
}
