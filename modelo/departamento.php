<?php
include_once __DIR__ . '/conexion.php';

class DepartamentoModel
{
    public static function listarPorPais($paisId = null)
    {
        try {
            $db = (new Conexion())->conectar();
            if ($paisId !== null) {
                $stmt = $db->prepare('SELECT id, nombre, id_pais FROM departamentos WHERE id_pais = :pid ORDER BY nombre ASC');
                $stmt->bindValue(':pid', (int)$paisId, PDO::PARAM_INT);
            } else {
                $stmt = $db->prepare('SELECT id, nombre, id_pais FROM departamentos ORDER BY nombre ASC');
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error listar departamentos: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    public static function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, nombre, id_pais FROM departamentos WHERE id = :id');
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error obtener departamento: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function crear($nombre, $id_pais)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('INSERT INTO departamentos (nombre, id_pais) VALUES (:nombre, :id_pais)');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':id_pais', (int)$id_pais, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error crear departamento: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function actualizar($id, $nombre, $id_pais)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE departamentos SET nombre = :nombre, id_pais = :id_pais WHERE id = :id');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':id_pais', (int)$id_pais, PDO::PARAM_INT);
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error actualizar departamento: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    public static function eliminar($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('DELETE FROM departamentos WHERE id = :id');
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error eliminar departamento: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }
}
