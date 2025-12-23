<?php

include_once __DIR__ . '/conexion.php';

/**
 * InventarioModel
 *
 * Modelo para gestionar el inventario consolidado.
 * Proporciona acceso rápido al stock disponible y reservado por producto y ubicación.
 */
class InventarioModel
{
    /**
     * Obtener inventario de todos los productos
     * 
     * @param string|null $ubicacion Filtrar por ubicación específica
     * @return array Lista de inventario con información de productos
     */
    public static function listar($ubicacion = null)
    {
        try {
            $db = (new Conexion())->conectar();
            
            $whereUbicacion = $ubicacion ? 'WHERE i.ubicacion = :ubicacion' : '';
            
            $sql = "SELECT 
                        i.id,
                        i.id_producto,
                        p.nombre AS producto_nombre,
                        p.sku,
                        p.stock_minimo,
                        p.stock_maximo,
                        i.ubicacion,
                        i.cantidad_disponible,
                        i.cantidad_reservada,
                        (i.cantidad_disponible + i.cantidad_reservada) as cantidad_total,
                        i.costo_promedio,
                        i.ultima_entrada,
                        i.ultima_salida,
                        i.updated_at,
                        CASE
                            WHEN i.cantidad_disponible <= 0 THEN 'agotado'
                            WHEN i.cantidad_disponible < p.stock_minimo THEN 'bajo'
                            WHEN i.cantidad_disponible > p.stock_maximo THEN 'exceso'
                            ELSE 'normal'
                        END as estado_stock
                    FROM inventario i
                    INNER JOIN productos p ON p.id = i.id_producto
                    {$whereUbicacion}
                    ORDER BY p.nombre ASC";
            
            $stmt = $db->prepare($sql);
            if ($ubicacion) {
                $stmt->bindValue(':ubicacion', $ubicacion, PDO::PARAM_STR);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar inventario: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener stock disponible de un producto en una ubicación
     * 
     * @param int $idProducto ID del producto
     * @param string $ubicacion Ubicación (default: 'Principal')
     * @return int Cantidad disponible
     */
    public static function obtenerDisponible($idProducto, $ubicacion = 'Principal')
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('
                SELECT cantidad_disponible
                FROM inventario
                WHERE id_producto = :id_producto AND ubicacion = :ubicacion
            ');
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->bindValue(':ubicacion', $ubicacion, PDO::PARAM_STR);
            $stmt->execute();
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['cantidad_disponible'] : 0;
        } catch (PDOException $e) {
            error_log('Error al obtener stock disponible: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return 0;
        }
    }

    /**
     * Reservar stock para un pedido
     * 
     * @param int $idProducto ID del producto
     * @param int $cantidad Cantidad a reservar
     * @param int $idPedido ID del pedido
     * @param string $ubicacion Ubicación del stock
     * @return bool True si se reservó correctamente
     */
    public static function reservarStock($idProducto, $cantidad, $idPedido, $ubicacion = 'Principal')
    {
        try {
            $db = (new Conexion())->conectar();
            
            // Verificar que haya stock disponible
            $disponible = self::obtenerDisponible($idProducto, $ubicacion);
            if ($disponible < $cantidad) {
                error_log("Stock insuficiente para reservar. Disponible: {$disponible}, Solicitado: {$cantidad}", 3, __DIR__ . '/../logs/errors.log');
                return false;
            }
            
            // Actualizar inventario: mover de disponible a reservado
            $stmt = $db->prepare('
                UPDATE inventario
                SET cantidad_disponible = cantidad_disponible - :cantidad,
                    cantidad_reservada = cantidad_reservada + :cantidad,
                    updated_at = NOW()
                WHERE id_producto = :id_producto AND ubicacion = :ubicacion
            ');
            $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->bindValue(':ubicacion', $ubicacion, PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al reservar stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Liberar reserva de stock (cuando se cancela un pedido)
     * 
     * @param int $idProducto ID del producto
     * @param int $cantidad Cantidad a liberar
     * @param int $idPedido ID del pedido
     * @param string $ubicacion Ubicación del stock
     * @return bool True si se liberó correctamente
     */
    public static function liberarReserva($idProducto, $cantidad, $idPedido, $ubicacion = 'Principal')
    {
        try {
            $db = (new Conexion())->conectar();
            
            // Actualizar inventario: mover de reservado a disponible
            $stmt = $db->prepare('
                UPDATE inventario
                SET cantidad_disponible = cantidad_disponible + :cantidad,
                    cantidad_reservada = cantidad_reservada - :cantidad,
                    updated_at = NOW()
                WHERE id_producto = :id_producto AND ubicacion = :ubicacion
            ');
            $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->bindValue(':ubicacion', $ubicacion, PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al liberar reserva: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Confirmar salida de stock reservado (cuando se entrega el pedido)
     * 
     * @param int $idProducto ID del producto
     * @param int $cantidad Cantidad a confirmar salida
     * @param string $ubicacion Ubicación del stock
     * @return bool True si se confirmó correctamente
     */
    public static function confirmarSalida($idProducto, $cantidad, $ubicacion = 'Principal')
    {
        try {
            $db = (new Conexion())->conectar();
            
            // Simplemente reducir la cantidad reservada (ya no está disponible ni reservado)
            $stmt = $db->prepare('
                UPDATE inventario
                SET cantidad_reservada = cantidad_reservada - :cantidad,
                    ultima_salida = NOW(),
                    updated_at = NOW()
                WHERE id_producto = :id_producto AND ubicacion = :ubicacion
            ');
            $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->bindValue(':ubicacion', $ubicacion, PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al confirmar salida: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Obtener productos con stock bajo (por debajo del mínimo)
     * 
     * @param int $limite Número máximo de resultados
     * @return array Lista de productos con stock bajo
     */
    public static function obtenerStockBajo($limite = 20)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('
                SELECT 
                    i.id_producto,
                    p.nombre,
                    p.sku,
                    i.ubicacion,
                    i.cantidad_disponible,
                    p.stock_minimo,
                    (p.stock_minimo - i.cantidad_disponible) as faltante
                FROM inventario i
                INNER JOIN productos p ON p.id = i.id_producto
                WHERE i.cantidad_disponible < p.stock_minimo
                    AND p.activo = TRUE
                ORDER BY faltante DESC
                LIMIT :limite
            ');
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener stock bajo: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Calcular el valor total del inventario
     * 
     * @param string|null $ubicacion Filtrar por ubicación
     * @return float Valor total del inventario
     */
    public static function obtenerValorTotal($ubicacion = null)
    {
        try {
            $db = (new Conexion())->conectar();
            
            $whereUbicacion = $ubicacion ? 'WHERE i.ubicacion = :ubicacion' : '';
            
            $sql = "SELECT 
                        SUM(i.cantidad_disponible * COALESCE(i.costo_promedio, 0)) as valor_total
                    FROM inventario i
                    {$whereUbicacion}";
            
            $stmt = $db->prepare($sql);
            if ($ubicacion) {
                $stmt->bindValue(':ubicacion', $ubicacion, PDO::PARAM_STR);
            }
            $stmt->execute();
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (float)($row['valor_total'] ?? 0) : 0.0;
        } catch (PDOException $e) {
            error_log('Error al obtener valor total del inventario: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return 0.0;
        }
    }

    /**
     * Obtener métricas del inventario
     * 
     * @return array Métricas generales del inventario
     */
    public static function obtenerMetricas()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->query('
                SELECT 
                    COUNT(DISTINCT i.id_producto) as total_productos,
                    SUM(i.cantidad_disponible) as total_unidades_disponibles,
                    SUM(i.cantidad_reservada) as total_unidades_reservadas,
                    SUM(i.cantidad_disponible * COALESCE(i.costo_promedio, 0)) as valor_total,
                    COUNT(CASE WHEN i.cantidad_disponible <= 0 THEN 1 END) as productos_agotados,
                    COUNT(CASE WHEN i.cantidad_disponible < p.stock_minimo THEN 1 END) as productos_stock_bajo
                FROM inventario i
                INNER JOIN productos p ON p.id = i.id_producto
                WHERE p.activo = TRUE
            ');
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('Error al obtener métricas: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Ajustar inventario manualmente
     * 
     * @param int $idProducto ID del producto
     * @param int $nuevaCantidad Nueva cantidad disponible
     * @param string $motivo Motivo del ajuste
     * @param int $idUsuario ID del usuario que realiza el ajuste
     * @param string $ubicacion Ubicación
     * @return bool True si se ajustó correctamente
     */
    public static function ajustar($idProducto, $nuevaCantidad, $motivo, $idUsuario, $ubicacion = 'Principal')
    {
        try {
            $db = (new Conexion())->conectar();
            $db->beginTransaction();
            
            // Obtener cantidad actual
            $actual = self::obtenerDisponible($idProducto, $ubicacion);
            $diferencia = $nuevaCantidad - $actual;
            
            // Actualizar inventario
            $stmt = $db->prepare('
                UPDATE inventario
                SET cantidad_disponible = :nueva_cantidad,
                    updated_at = NOW()
                WHERE id_producto = :id_producto AND ubicacion = :ubicacion
            ');
            $stmt->bindValue(':nueva_cantidad', $nuevaCantidad, PDO::PARAM_INT);
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->bindValue(':ubicacion', $ubicacion, PDO::PARAM_STR);
            $stmt->execute();
            
            // Registrar movimiento en tabla stock
            $stmt = $db->prepare('
                INSERT INTO stock (
                    id_producto, id_usuario, cantidad, 
                    tipo_movimiento, referencia_tipo, motivo,
                    ubicacion_destino
                ) VALUES (
                    :id_producto, :id_usuario, :cantidad,
                    \'ajuste\', \'ajuste_manual\', :motivo,
                    :ubicacion
                )
            ');
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $diferencia, PDO::PARAM_INT);
            $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
            $stmt->bindValue(':ubicacion', $ubicacion, PDO::PARAM_STR);
            $stmt->execute();
            
            $db->commit();
            return true;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error al ajustar inventario: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }
}
