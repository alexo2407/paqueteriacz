<?php
include_once __DIR__ . '/conexion.php';

class BarrioModel
{
    public static function listarPorMunicipio($munId = null)
    {
        try {
            $db = (new Conexion())->conectar();
            if ($munId !== null) {
                $stmt = $db->prepare('SELECT id, nombre, id_municipio FROM barrios WHERE id_municipio = :mid ORDER BY nombre ASC');
                $stmt->bindValue(':mid', (int)$munId, PDO::PARAM_INT);
            } else {
                $stmt = $db->prepare('SELECT id, nombre, id_municipio FROM barrios ORDER BY nombre ASC');
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error listar barrios: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    public static function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, nombre, id_municipio FROM barrios WHERE id = :id');
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error obtener barrio: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function crear($nombre, $id_municipio)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('INSERT INTO barrios (nombre, id_municipio) VALUES (:nombre, :id_municipio)');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':id_municipio', (int)$id_municipio, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error crear barrio: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function actualizar($id, $nombre, $id_municipio)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE barrios SET nombre = :nombre, id_municipio = :id_municipio WHERE id = :id');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':id_municipio', (int)$id_municipio, PDO::PARAM_INT);
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error actualizar barrio: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    public static function eliminar($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('DELETE FROM barrios WHERE id = :id');
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error eliminar barrio: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }
}
