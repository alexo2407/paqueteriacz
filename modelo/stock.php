<?php

include_once __DIR__ . '/conexion.php';
include_once __DIR__ . '/auditoria.php';

/**
 * Class StockModel
 *
 * Acceso a datos para la entidad `stock`.
 * Contiene métodos CRUD básicos. La lógica de movimientos/ajustes de stock
 * debería manejarse por triggers en la base de datos; algunos métodos de
 * manipulación fina están deshabilitados para evitar inconsistencias.
 */
class StockModel
{
    /**
     * NOTA: La gestión de stock está centralizada mediante triggers en la base
     * de datos. Evitar que PHP inserte/actualice movimientos directamente
     * desde los controladores/servicios para no duplicar la lógica.
     *
     * Si es necesario un método PHP para registros ad-hoc, crear uno nuevo
     * con nombre claro y documentarlo. El método registrarSalida está
     * deshabilitado por defecto en este repositorio para proteger la
     * integridad cuando los esquemas de `stock` varían entre despliegues.
     */
    /**
     * Listar todos los registros de stock.
     *
     * @return array Lista de registros (array asociativo). En caso de error devuelve [].
     */
    public static function listar()
    {
        try {
            $db = (new Conexion())->conectar();
            // Devolver información completa para la UI de movimientos
            $stmt = $db->prepare('
                SELECT 
                    s.id, 
                    s.id_usuario, 
                    s.id_producto, 
                    s.cantidad, 
                    s.tipo_movimiento,
                    s.motivo,
                    s.ubicacion_origen,
                    s.ubicacion_destino,
                    s.referencia_tipo,
                    s.referencia_id,
                    s.costo_unitario,
                    s.created_at,
                    s.updated_at,
                    p.nombre AS producto,
                    u.nombre AS usuario_nombre
                FROM stock s 
                LEFT JOIN productos p ON p.id = s.id_producto 
                LEFT JOIN usuarios u ON u.id = s.id_usuario
                ORDER BY s.id DESC
            ');
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
            $stmt = $db->prepare('SELECT s.id, s.id_usuario, s.id_producto, s.cantidad, p.nombre AS producto FROM stock s LEFT JOIN productos p ON p.id = s.id_producto WHERE s.id = :id');
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener registro de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Obtener un registro de stock por id.
     *
     * @param int $id
     * @return array|null Registro o null si no existe o en error.
     */

    /**
     * Crear un nuevo registro de stock.
     *
     * @param array $data Asociativo con claves: id_vendedor, producto, cantidad.
     * @return int|false Nuevo id insertado o false en caso de fallo.
     */
    public static function crear(array $data)
    {
        try {
            $db = (new Conexion())->conectar();
            // Ahora asumimos columnas: id_usuario, id_producto, cantidad
            $stmt = $db->prepare('INSERT INTO stock (id_usuario, id_producto, cantidad) VALUES (:id_usuario, :id_producto, :cantidad)');
            $stmt->bindValue(':id_usuario', $data['id_usuario'], PDO::PARAM_INT);
            $stmt->bindValue(':id_producto', $data['id_producto'], PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $data['cantidad'], PDO::PARAM_INT);
            $ok = $stmt->execute();
            return $ok ? (int) $db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log('Error al crear registro de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Actualizar un registro existente.
     *
     * @param int $id Identificador del registro a actualizar.
     * @param array $data Claves: id_vendedor, producto, cantidad.
     * @return bool True si la ejecución fue exitosa, false en caso contrario.
     */
    public static function actualizar($id, array $data)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE stock SET id_usuario = :id_usuario, id_producto = :id_producto, cantidad = :cantidad WHERE id = :id');
            $stmt->bindValue(':id_usuario', $data['id_usuario'], PDO::PARAM_INT);
            $stmt->bindValue(':id_producto', $data['id_producto'], PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $data['cantidad'], PDO::PARAM_INT);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al actualizar stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Eliminar un registro por id.
     *
     * @param int $id Identificador del registro.
     * @return bool True si eliminado, false si ocurrió un error.
     */
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

    /**
     * Ajustar la cantidad del registro por una diferencia (positiva o negativa).
     *
     * @param int $id Identificador del registro.
     * @param int $diferencia Valor a sumar (puede ser negativo).
     * @return bool True si se afectó alguna fila, false en caso contrario.
     */
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

    /**
     * Registrar un movimiento de salida (cantidad negativa) intentando varias
     * estrategias de INSERT para adaptarse a distintos esquemas de la tabla
     * `stock` en diferentes despliegues.
     *
     * @param int $idProducto
     * @param int $cantidad
     * @param int|null $idVendedor
     * @return bool
     * @throws Exception si ninguna estrategia funciona
     */
    public static function registrarSalida($idProducto, $cantidad, $idVendedor = null, $pdo = null, $referenciaTipo = null, $referenciaId = null)
    {
        try {
            $db = $pdo ? $pdo : (new Conexion())->conectar();
            
            // Validar que el usuario exista, si no, usar 1 (Admin/Sistema)
            $idUsuarioFinal = 1;
            if ($idVendedor && $idVendedor != 1) {
                $stmtCheck = $db->prepare("SELECT id FROM usuarios WHERE id = :id");
                $stmtCheck->execute([':id' => $idVendedor]);
                if ($stmtCheck->fetchColumn()) {
                    $idUsuarioFinal = $idVendedor;
                }
            }

            // Insertar movimiento negativo con referencia
            // Asumimos que la cantidad recibida es positiva (cantidad a descontar),
            // por lo que la guardamos como negativa en la tabla.
            $cantidadNegativa = -abs($cantidad);
            
            $stmt = $db->prepare('INSERT INTO stock (id_producto, id_usuario, cantidad, tipo_movimiento, referencia_tipo, referencia_id, updated_at) VALUES (:id_producto, :id_usuario, :cantidad, :tipo_movimiento, :referencia_tipo, :referencia_id, NOW())');
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->bindValue(':id_usuario', $idUsuarioFinal, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $cantidadNegativa, PDO::PARAM_INT);
            $stmt->bindValue(':tipo_movimiento', 'salida', PDO::PARAM_STR);
            $stmt->bindValue(':referencia_tipo', $referenciaTipo, PDO::PARAM_STR);
            $stmt->bindValue(':referencia_id', $referenciaId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al registrar salida de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            // Lanzar excepción para que la transacción superior pueda hacer rollback
            throw new Exception("No se pudo registrar la salida de stock: " . $e->getMessage());
        }
    }

    /**
     * Registrar un movimiento de entrada (cantidad positiva).
     * Útil para devoluciones o correcciones de inventario.
     *
     * @param int $idProducto
     * @param int $cantidad (debe ser positiva)
     * @param int|null $idVendedor
     * @param PDO|null $pdo
     * @return bool
     * @throws Exception
     */
    public static function registrarEntrada($idProducto, $cantidad, $idVendedor = null, $pdo = null)
    {
        try {
            $db = $pdo ? $pdo : (new Conexion())->conectar();
            
            // Validar que el usuario exista, si no, usar 1 (Admin/Sistema)
            $idUsuarioFinal = 1;
            if ($idVendedor && $idVendedor != 1) {
                $stmtCheck = $db->prepare("SELECT id FROM usuarios WHERE id = :id");
                $stmtCheck->execute([':id' => $idVendedor]);
                if ($stmtCheck->fetchColumn()) {
                    $idUsuarioFinal = $idVendedor;
                }
            }

            $cantidadPositiva = abs($cantidad);
            
            $stmt = $db->prepare('INSERT INTO stock (id_producto, id_usuario, cantidad, updated_at) VALUES (:id_producto, :id_usuario, :cantidad, NOW())');
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->bindValue(':id_usuario', $idUsuarioFinal, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $cantidadPositiva, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al registrar entrada de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            throw new Exception("No se pudo registrar la entrada de stock: " . $e->getMessage());
        }
    }

    /**
     * Registrar un movimiento completo con tipo y referencia
     * 
     * @param array $datos Array con: id_producto, id_usuario, cantidad, tipo_movimiento, referencia_tipo, referencia_id, motivo, ubicacion_destino, costo_unitario
     * @param PDO|null $pdo Conexión existente (para transacciones)
     * @return int|null ID del movimiento creado o null en error
     */
    public static function registrarMovimiento($datos, $pdo = null)
    {
        try {
            $db = $pdo ? $pdo : (new Conexion())->conectar();
            
            // Validar campos requeridos
            if (empty($datos['id_producto']) || empty($datos['cantidad']) || empty($datos['tipo_movimiento'])) {
                throw new Exception('Faltan campos requeridos: id_producto, cantidad, tipo_movimiento');
            }
            
            $sql = 'INSERT INTO stock (
                id_producto, id_usuario, cantidad, tipo_movimiento,
                referencia_tipo, referencia_id, motivo,
                ubicacion_origen, ubicacion_destino, costo_unitario
            ) VALUES (
                :id_producto, :id_usuario, :cantidad, :tipo_movimiento,
                :referencia_tipo, :referencia_id, :motivo,
                :ubicacion_origen, :ubicacion_destino, :costo_unitario
            )';
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_producto', $datos['id_producto'], PDO::PARAM_INT);
            $stmt->bindValue(':id_usuario', $datos['id_usuario'] ?? 1, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $datos['cantidad'], PDO::PARAM_INT);
            $stmt->bindValue(':tipo_movimiento', $datos['tipo_movimiento'], PDO::PARAM_STR);
            $stmt->bindValue(':referencia_tipo', $datos['referencia_tipo'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':referencia_id', $datos['referencia_id'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':motivo', $datos['motivo'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':ubicacion_origen', $datos['ubicacion_origen'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':ubicacion_destino', $datos['ubicacion_destino'] ?? 'Principal', PDO::PARAM_STR);
            $stmt->bindValue(':costo_unitario', $datos['costo_unitario'] ?? null);
            
            $stmt->execute();
            $nuevoId = (int)$db->lastInsertId();
            
            // Registrar auditoría
            AuditoriaModel::registrar(
                'stock',
                $nuevoId,
                'crear',
                AuditoriaModel::getIdUsuarioActual(),
                null,
                $datos
            );
            
            return $nuevoId;
        } catch (Exception $e) {
            error_log('Error al registrar movimiento de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            if (!$pdo) throw $e; // Re-lanzar si no estamos en transacción externa
            return null;
        }
    }

    /**
     * Obtener movimientos por rango de fechas
     * 
     * @param string $fechaInicio Fecha inicio (YYYY-MM-DD)
     * @param string $fechaFin Fecha fin (YYYY-MM-DD)
     * @param array $filtros Filtros adicionales: tipo_movimiento, id_producto, id_usuario
     * @return array Lista de movimientos
     */
    public static function obtenerMovimientosPorFecha($fechaInicio, $fechaFin, $filtros = [])
    {
        try {
            $db = (new Conexion())->conectar();
            $where = ['s.created_at >= :fecha_inicio', 's.created_at <= :fecha_fin'];
            $params = [
                ':fecha_inicio' => $fechaInicio . ' 00:00:00',
                ':fecha_fin' => $fechaFin . ' 23:59:59'
            ];
            
            if (!empty($filtros['tipo_movimiento'])) {
                $where[] = 's.tipo_movimiento = :tipo_movimiento';
                $params[':tipo_movimiento'] = $filtros['tipo_movimiento'];
            }
            
            if (!empty($filtros['id_producto'])) {
                $where[] = 's.id_producto = :id_producto';
                $params[':id_producto'] = $filtros['id_producto'];
            }
            
            if (!empty($filtros['id_usuario'])) {
                $where[] = 's.id_usuario = :id_usuario';
                $params[':id_usuario'] = $filtros['id_usuario'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $sql = "SELECT 
                        s.id,
                        s.id_producto,
                        p.nombre AS producto,
                        s.id_usuario,
                        u.nombre AS usuario,
                        s.cantidad,
                        s.tipo_movimiento,
                        s.referencia_tipo,
                        s.referencia_id,
                        s.motivo,
                        s.ubicacion_origen,
                        s.ubicacion_destino,
                        s.costo_unitario,
                        s.created_at
                    FROM stock s
                    LEFT JOIN productos p ON p.id = s.id_producto
                    LEFT JOIN usuarios u ON u.id = s.id_usuario
                    WHERE {$whereClause}
                    ORDER BY s.created_at DESC";
            
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener movimientos por fecha: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener resumen de movimientos por período
     * 
     * @param string $periodo 'dia', 'semana', 'mes', 'año'
     * @return array Resumen con entradas, salidas, ajustes
     */
    public static function obtenerResumenMovimientos($periodo = 'mes')
    {
        try {
            $db = (new Conexion())->conectar();
            
            $dateCondition = match($periodo) {
                'dia' => 'DATE(s.created_at) = CURDATE()',
                'semana' => 'YEARWEEK(s.created_at) = YEARWEEK(NOW())',
                'mes' => 'YEAR(s.created_at) = YEAR(NOW()) AND MONTH(s.created_at) = MONTH(NOW())',
                'año' => 'YEAR(s.created_at) = YEAR(NOW())',
                default => 'YEAR(s.created_at) = YEAR(NOW()) AND MONTH(s.created_at) = MONTH(NOW())'
            };
            
            $sql = "SELECT 
                        COUNT(*) as total_movimientos,
                        SUM(CASE WHEN tipo_movimiento = 'entrada' THEN cantidad ELSE 0 END) as total_entradas,
                        SUM(CASE WHEN tipo_movimiento = 'salida' THEN ABS(cantidad) ELSE 0 END) as total_salidas,
                        SUM(CASE WHEN tipo_movimiento = 'ajuste' THEN cantidad ELSE 0 END) as total_ajustes,
                        SUM(CASE WHEN tipo_movimiento = 'devolucion' THEN cantidad ELSE 0 END) as total_devoluciones,
                        SUM(CASE WHEN tipo_movimiento = 'transferencia' THEN cantidad ELSE 0 END) as total_transferencias
                    FROM stock s
                    WHERE {$dateCondition}";
            
            $stmt = $db->query($sql);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('Error al obtener resumen de movimientos: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Generar reporte Kardex para un producto
     * Muestra el historial de movimientos con saldo acumulado
     * 
     * @param int $idProducto ID del producto
     * @param string $fechaInicio Fecha inicio
     * @param string $fechaFin Fecha fin
     * @return array Kardex con movimientos y saldos
     */
    public static function generarReporteKardex($idProducto = null, $fechaInicio, $fechaFin, $numeroOrden = null)
    {
        try {
            $db = (new Conexion())->conectar();
            
            // Si hay número de orden, primero obtener IDs asociados para stats si es necesario, 
            // o simplemente adaptar la query principal.
            
            // 1. Saldo inicial (solo tiene sentido si filtramos por PRODUCTO único)
            $saldoInicial = 0;
            if ($idProducto) {
                $stmtInicial = $db->prepare('
                    SELECT COALESCE(SUM(cantidad), 0) as saldo_inicial
                    FROM stock
                    WHERE id_producto = :id_producto
                    AND created_at < :fecha_inicio
                ');
                $stmtInicial->execute([
                    ':id_producto' => $idProducto,
                    ':fecha_inicio' => $fechaInicio . ' 00:00:00'
                ]);
                $saldoInicial = (int)$stmtInicial->fetchColumn();
            }
            
            // 2. Query Principal
            // Construcción dinámica de condiciones
            $conditions = ['s.created_at >= :fecha_inicio', 's.created_at <= :fecha_fin'];
            $params = [
                ':fecha_inicio' => $fechaInicio . ' 00:00:00',
                ':fecha_fin' => $fechaFin . ' 23:59:59'
            ];

            if ($idProducto) {
                $conditions[] = 's.id_producto = :id_producto';
                $params[':id_producto'] = $idProducto;
            }

            if ($numeroOrden) {
                // Buscamos por número de orden en la tabla pedidos
                // OJO: La relación es stock -> pedido
                $conditions[] = 'p.numero_orden = :numero_orden';
                $params[':numero_orden'] = $numeroOrden;
            }

            $sql = '
                SELECT 
                    s.id,
                    s.created_at as fecha,
                    s.tipo_movimiento,
                    s.referencia_tipo,
                    s.referencia_id,
                    s.motivo,
                    s.cantidad,
                    s.costo_unitario,
                    u.nombre as usuario,
                    p.es_combo,
                    p.numero_orden,
                    p.id as pedido_id,
                    u_prod.nombre as producto_nombre,
                    u_prod.sku as producto_sku
                FROM stock s
                LEFT JOIN usuarios u ON u.id = s.id_usuario
                LEFT JOIN pedidos p ON s.referencia_tipo = \'pedido\' AND p.id = s.referencia_id
                LEFT JOIN productos u_prod ON s.id_producto = u_prod.id
                WHERE ' . implode(' AND ', $conditions) . '
                ORDER BY s.created_at ASC, s.id ASC
            ';

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Para each movimiento que es de un combo, obtener los productos del combo
            foreach ($movimientos as &$mov) {
                if ($mov['es_combo'] == 1 && $mov['pedido_id']) {
                    // Obtener todos los productos de este pedido combo
                    $stmtComboProds = $db->prepare('
                        SELECT 
                            pp.id_producto,
                            pp.cantidad,
                            prod.nombre as producto_nombre,
                            prod.sku
                        FROM pedidos_productos pp
                        LEFT JOIN productos prod ON prod.id = pp.id_producto
                        WHERE pp.id_pedido = :pedido_id
                    ');
                    $stmtComboProds->execute([':pedido_id' => $mov['pedido_id']]);
                    $mov['productos_combo'] = $stmtComboProds->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            
            // Calcular saldo acumulado
            $saldo = $saldoInicial;
            foreach ($movimientos as &$mov) {
                $saldo += (int)$mov['cantidad'];
                $mov['saldo'] = $saldo;
            }
            
            return [
                'saldo_inicial' => $saldoInicial,
                'movimientos' => $movimientos,
                'saldo_final' => $saldo
            ];
        } catch (PDOException $e) {
            error_log('Error al generar kardex: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return ['saldo_inicial' => 0, 'movimientos' => [], 'saldo_final' => 0];
        }
    }

    /**
     * Obtener movimientos por tipo
     * 
     * @param string $tipoMovimiento Tipo de movimiento (entrada, salida, ajuste, devolucion, transferencia)
     * @param int $limite Límite de resultados
     * @return array Lista de movimientos
     */
    public static function obtenerPorTipo($tipoMovimiento, $limite = 50)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('
                SELECT 
                    s.id,
                    s.id_producto,
                    p.nombre AS producto,
                    s.cantidad,
                    s.motivo,
                    s.ubicacion_destino,
                    s.created_at,
                    u.nombre AS usuario
                FROM stock s
                LEFT JOIN productos p ON p.id = s.id_producto
                LEFT JOIN usuarios u ON u.id = s.id_usuario
                WHERE s.tipo_movimiento = :tipo
                ORDER BY s.created_at DESC
                LIMIT :limite
            ');
            $stmt->bindValue(':tipo', $tipoMovimiento, PDO::PARAM_STR);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener movimientos por tipo: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }
}
