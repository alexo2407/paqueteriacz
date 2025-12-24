<?php

include_once __DIR__ . '/conexion.php';
include_once __DIR__ . '/auditoria.php';

/**
 * CategoriaModel
 *
 * Modelo para gestionar categorías de productos.
 * Soporta categorías jerárquicas (categorías y subcategorías).
 */
class CategoriaModel
{
    /**
     * Listar todas las categorías activas
     * 
     * @param bool $incluirInactivas Si incluir categorías inactivas
     * @return array Lista de categorías
     */
    public static function listar($incluirInactivas = false)
    {
        try {
            $db = (new Conexion())->conectar();
            $where = $incluirInactivas ? '' : 'WHERE activo = TRUE';
            
            $sql = "SELECT 
                        id,
                        nombre,
                        descripcion,
                        padre_id,
                        activo,
                        created_at,
                        updated_at
                    FROM categorias_productos
                    {$where}
                    ORDER BY nombre ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar categorías: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Listar categorías en formato jerárquico
     * 
     * @return array Categorías organizadas jerárquicamente
     */
    public static function listarJerarquico()
    {
        $categorias = self::listar();
        $jerarquia = [];
        
        // Primero agregar todas las categorías padre (sin padre_id)
        foreach ($categorias as $cat) {
            if (empty($cat['padre_id'])) {
                $cat['subcategorias'] = [];
                $jerarquia[$cat['id']] = $cat;
            }
        }
        
        // Luego agregar las subcategorías
        foreach ($categorias as $cat) {
            if (!empty($cat['padre_id']) && isset($jerarquia[$cat['padre_id']])) {
                $jerarquia[$cat['padre_id']]['subcategorias'][] = $cat;
            }
        }
        
        return array_values($jerarquia);
    }

    /**
     * Obtener una categoría por ID
     * 
     * @param int $id ID de la categoría
     * @return array|null Categoría o null si no existe
     */
    public static function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('
                SELECT 
                    id, nombre, descripcion, padre_id, activo, created_at, updated_at
                FROM categorias_productos 
                WHERE id = :id
            ');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener categoría: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Crear una nueva categoría
     * 
     * @param string $nombre Nombre de la categoría
     * @param string|null $descripcion Descripción
     * @param int|null $padreId ID de la categoría padre (para subcategorías)
     * @return int|null ID de la categoría creada o null en error
     */
    public static function crear($nombre, $descripcion = null, $padreId = null)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('
                INSERT INTO categorias_productos (nombre, descripcion, padre_id, activo)
                VALUES (:nombre, :descripcion, :padre_id, TRUE)
            ');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
            $stmt->bindValue(':padre_id', $padreId, PDO::PARAM_INT);
            $stmt->execute();
            $nuevoId = (int)$db->lastInsertId();
            
            // Registrar auditoría
            AuditoriaModel::registrar(
                'categorias_productos',
                $nuevoId,
                'crear',
                AuditoriaModel::getIdUsuarioActual(),
                null,
                ['nombre' => $nombre, 'descripcion' => $descripcion, 'padre_id' => $padreId]
            );
            
            return $nuevoId;
        } catch (PDOException $e) {
            error_log('Error al crear categoría: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Actualizar una categoría existente
     * 
     * @param int $id ID de la categoría
     * @param string $nombre Nombre
     * @param string|null $descripcion Descripción
     * @param int|null $padreId ID de categoría padre
     * @return bool True si se actualizó correctamente
     */
    public static function actualizar($id, $nombre, $descripcion = null, $padreId = null)
    {
        try {
            // Obtener datos anteriores
            $datosAnteriores = self::obtenerPorId($id);
            
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('
                UPDATE categorias_productos 
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    padre_id = :padre_id,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
            $stmt->bindValue(':padre_id', $padreId, PDO::PARAM_INT);
            $resultado = $stmt->execute();
            
            if ($resultado) {
                // Registrar auditoría
                AuditoriaModel::registrar(
                    'categorias_productos',
                    $id,
                    'actualizar',
                    AuditoriaModel::getIdUsuarioActual(),
                    $datosAnteriores,
                    ['nombre' => $nombre, 'descripcion' => $descripcion, 'padre_id' => $padreId]
                );
            }
            
            return $resultado;
        } catch (PDOException $e) {
            error_log('Error al actualizar categoría: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Activar o desactivar una categoría
     * 
     * @param int $id ID de la categoría
     * @param bool $activo Estado activo/inactivo
     * @return bool True si se actualizó correctamente
     */
    public static function cambiarEstado($id, $activo)
    {
        try {
            // Obtener datos anteriores
            $datosAnteriores = self::obtenerPorId($id);
            
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('
                UPDATE categorias_productos 
                SET activo = :activo,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':activo', $activo, PDO::PARAM_BOOL);
            $resultado = $stmt->execute();
            
            if ($resultado) {
                // Registrar auditoría
                AuditoriaModel::registrar(
                    'categorias_productos',
                    $id,
                    'actualizar',
                    AuditoriaModel::getIdUsuarioActual(),
                    $datosAnteriores,
                    ['activo' => $activo]
                );
            }
            
            return $resultado;
        } catch (PDOException $e) {
            error_log('Error al cambiar estado de categoría: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Eliminar una categoría
     * Solo si no tiene productos asociados ni subcategorías
     * 
     * @param int $id ID de la categoría
     * @return bool True si se eliminó correctamente
     */
    public static function eliminar($id)
    {
        try {
            $db = (new Conexion())->conectar();
            
            // Obtener datos antes de eliminar
            $datosAnteriores = self::obtenerPorId($id);
            
            // Verificar que no tenga productos
            $stmt = $db->prepare('SELECT COUNT(*) FROM productos WHERE categoria_id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                error_log('No se puede eliminar categoría con productos asociados', 3, __DIR__ . '/../logs/errors.log');
                return false;
            }
            
            // Verificar que no tenga subcategorías
            $stmt = $db->prepare('SELECT COUNT(*) FROM categorias_productos WHERE padre_id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                error_log('No se puede eliminar categoría con subcategorías', 3, __DIR__ . '/../logs/errors.log');
                return false;
            }
            
            // Eliminar
            $stmt = $db->prepare('DELETE FROM categorias_productos WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $resultado = $stmt->execute();
            
            if ($resultado) {
                // Registrar auditoría
                AuditoriaModel::registrar(
                    'categorias_productos',
                    $id,
                    'eliminar',
                    AuditoriaModel::getIdUsuarioActual(),
                    $datosAnteriores,
                    null
                );
            }
            
            return $resultado;
        } catch (PDOException $e) {
            error_log('Error al eliminar categoría: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Obtener subcategorías de una categoría padre
     * 
     * @param int $padreId ID de la categoría padre
     * @return array Lista de subcategorías
     */
    public static function obtenerSubcategorias($padreId)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('
                SELECT id, nombre, descripcion, activo
                FROM categorias_productos
                WHERE padre_id = :padre_id AND activo = TRUE
                ORDER BY nombre ASC
            ');
            $stmt->bindValue(':padre_id', $padreId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener subcategorías: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Contar productos por categoría
     * 
     * @param int|null $idUsuario ID del usuario creador (para filtrar por proveedor)
     * @return array Array asociativo [id_categoria => cantidad_productos]
     */
    public static function contarProductosPorCategoria($idUsuario = null)
    {
        try {
            $db = (new Conexion())->conectar();
            
            $whereUsuario = '';
            if ($idUsuario !== null) {
                $whereUsuario = 'AND p.id_usuario_creador = :id_usuario';
            }
            
            $sql = "SELECT 
                    c.id,
                    c.nombre,
                    COUNT(p.id) as total_productos
                FROM categorias_productos c
                LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = TRUE {$whereUsuario}
                GROUP BY c.id, c.nombre
                ORDER BY c.nombre ASC";
                
            $stmt = $db->prepare($sql);
            if ($idUsuario !== null) {
                $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al contar productos por categoría: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }
}
