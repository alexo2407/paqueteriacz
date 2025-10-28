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
}
