<?php

include_once __DIR__ . '/conexion.php';

class MonedaModel
{
    public static function listar()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, codigo, nombre, tasa_usd FROM monedas ORDER BY nombre ASC');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar monedas: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    public static function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, codigo, nombre, tasa_usd FROM monedas WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener moneda: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function crear($codigo, $nombre, $tasa_usd = null)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('INSERT INTO monedas (codigo, nombre, tasa_usd) VALUES (:codigo, :nombre, :tasa_usd)');
            $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            if ($tasa_usd === null || $tasa_usd === '') {
                $stmt->bindValue(':tasa_usd', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':tasa_usd', $tasa_usd);
            }
            $stmt->execute();
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error crear moneda: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function actualizar($id, $codigo, $nombre, $tasa_usd = null)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE monedas SET codigo = :codigo, nombre = :nombre, tasa_usd = :tasa_usd WHERE id = :id');
            $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            if ($tasa_usd === null || $tasa_usd === '') {
                $stmt->bindValue(':tasa_usd', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':tasa_usd', $tasa_usd);
            }
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error actualizar moneda: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    public static function eliminar($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('DELETE FROM monedas WHERE id = :id');
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error eliminar moneda: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    public static function buscarPorCodigo($codigo)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, codigo, nombre, tasa_usd FROM monedas WHERE codigo = :codigo LIMIT 1');
            $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error buscar moneda: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }
}
