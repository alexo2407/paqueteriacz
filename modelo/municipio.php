<?php
include_once __DIR__ . '/conexion.php';
include_once __DIR__ . '/auditoria.php';

/**
 * MunicipioModel
 *
 * CRUD y búsquedas por departamento para la entidad municipios.
 */
class MunicipioModel
{
    /**
     * Listar municipios, opcionalmente filtrados por departamento.
     * @param int|null $depId
     * @return array
     */
    public static function listarPorDepartamento($depId = null)
    {
        $db = (new Conexion())->conectar();
        if ($depId !== null) {
            $stmt = $db->prepare('SELECT id, nombre, id_departamento, codigo_postal FROM municipios WHERE id_departamento = :did ORDER BY nombre ASC');
            $stmt->bindValue(':did', (int)$depId, PDO::PARAM_INT);
        } else {
            $stmt = $db->prepare('SELECT id, nombre, id_departamento, codigo_postal FROM municipios ORDER BY nombre ASC');
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener municipio por id.
     * @param int $id
     * @return array|null
     */
    public static function obtenerPorId($id)
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('SELECT id, nombre, id_departamento, codigo_postal FROM municipios WHERE id = :id');
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Crear un municipio asociado a un departamento.
     * @param string $nombre
     * @param int $id_departamento
     * @return int ID creado
     */
    public static function crear($nombre, $id_departamento, $codigo_postal = null)
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('INSERT INTO municipios (nombre, id_departamento, codigo_postal) VALUES (:nombre, :id_departamento, :codigo_postal)');
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':id_departamento', (int)$id_departamento, PDO::PARAM_INT);
        $stmt->bindValue(':codigo_postal', $codigo_postal, $codigo_postal ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();
        $nuevoId = (int)$db->lastInsertId();
        
        // Registrar auditoría
        AuditoriaModel::registrar(
            'municipios',
            $nuevoId,
            'crear',
            AuditoriaModel::getIdUsuarioActual(),
            null,
            ['nombre' => $nombre, 'id_departamento' => $id_departamento, 'codigo_postal' => $codigo_postal]
        );
        
        return $nuevoId;
    }

    /**
     * Actualizar un municipio.
     * @param int $id
     * @param string $nombre
     * @param int $id_departamento
     * @return bool True si se actualizó.
     */
    public static function actualizar($id, $nombre, $id_departamento, $codigo_postal = null)
    {
        // Obtener datos anteriores
        $datosAnteriores = self::obtenerPorId($id);
        
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('UPDATE municipios SET nombre = :nombre, id_departamento = :id_departamento, codigo_postal = :codigo_postal WHERE id = :id');
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':id_departamento', (int)$id_departamento, PDO::PARAM_INT);
        $stmt->bindValue(':codigo_postal', $codigo_postal, $codigo_postal ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $resultado = $stmt->execute();
        
        if ($resultado) {
            // Registrar auditoría
            AuditoriaModel::registrar(
                'municipios',
                $id,
                'actualizar',
                AuditoriaModel::getIdUsuarioActual(),
                $datosAnteriores,
                ['nombre' => $nombre, 'id_departamento' => $id_departamento, 'codigo_postal' => $codigo_postal]
            );
        }
        
        return $resultado;
    }

    /**
     * Eliminar un municipio por id.
     * @param int $id
     * @return bool True si eliminado.
     */
    public static function eliminar($id)
    {
        // Obtener datos antes de eliminar
        $datosAnteriores = self::obtenerPorId($id);
        
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('DELETE FROM municipios WHERE id = :id');
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $resultado = $stmt->execute();
        
        if ($resultado) {
            // Registrar auditoría
            AuditoriaModel::registrar(
                'municipios',
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
