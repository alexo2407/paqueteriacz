<?php
include_once __DIR__ . '/conexion.php';
include_once __DIR__ . '/auditoria.php';

/**
 * PaisModel
 *
 * CRUD para tabla `paises`.
 */
class PaisModel
{
    /**
     * Listar países ordenados por nombre.
     * @return array Lista de países.
     */
    public static function listar()
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('SELECT id, nombre, codigo_iso FROM paises ORDER BY nombre ASC');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener país por id.
     * @param int $id
     * @return array|null Array asociativo o null si no existe.
     */
    public static function obtenerPorId($id)
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('SELECT id, nombre, codigo_iso FROM paises WHERE id = :id');
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Crear un nuevo país.
     * @param string $nombre
     * @param string|null $codigo_iso
     * @return int ID creado
     */
    public static function crear($nombre, $codigo_iso = null)
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('INSERT INTO paises (nombre, codigo_iso) VALUES (:nombre, :codigo_iso)');
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':codigo_iso', $codigo_iso ?: null, $codigo_iso ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();
        $nuevoId = (int)$db->lastInsertId();
        
        // Registrar auditoría
        AuditoriaModel::registrar(
            'paises',
            $nuevoId,
            'crear',
            AuditoriaModel::getIdUsuarioActual(),
            null,
            ['nombre' => $nombre, 'codigo_iso' => $codigo_iso]
        );
        
        return $nuevoId;
    }

    /**
     * Actualizar un país.
     * @param int $id
     * @param string $nombre
     * @param string|null $codigo_iso
     * @return bool True si se actualizó.
     */
    public static function actualizar($id, $nombre, $codigo_iso = null)
    {
        // Obtener datos anteriores para auditoría
        $datosAnteriores = self::obtenerPorId($id);
        
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('UPDATE paises SET nombre = :nombre, codigo_iso = :codigo_iso WHERE id = :id');
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':codigo_iso', $codigo_iso ?: null, $codigo_iso ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $resultado = $stmt->execute();
        
        if ($resultado) {
            // Registrar auditoría
            AuditoriaModel::registrar(
                'paises',
                $id,
                'actualizar',
                AuditoriaModel::getIdUsuarioActual(),
                $datosAnteriores,
                ['nombre' => $nombre, 'codigo_iso' => $codigo_iso]
            );
        }
        
        return $resultado;
    }

    /**
     * Eliminar país por id.
     * @param int $id
     * @return bool True si se eliminó correctamente.
     */
    public static function eliminar($id)
    {
        // Obtener datos antes de eliminar para auditoría
        $datosAnteriores = self::obtenerPorId($id);
        
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('DELETE FROM paises WHERE id = :id');
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $resultado = $stmt->execute();
        
        if ($resultado && $datosAnteriores) {
            // Registrar auditoría
            AuditoriaModel::registrar(
                'paises',
                $id,
                'eliminar',
                AuditoriaModel::getIdUsuarioActual(),
                $datosAnteriores,
                null
            );
        }
        
        return $resultado;
    }
}
