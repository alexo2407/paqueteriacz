<?php
include_once "conexion.php";
include_once __DIR__ . '/producto.php';
include_once __DIR__ . '/moneda.php';
include_once __DIR__ . '/usuario.php';

class PedidosModel
{
    /**
     * Inserta múltiples pedidos reutilizando una sola conexión y statement preparado.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array{inserted:int,errors:array<int,string>}
     */
    public static function insertarPedidosLote(array $rows)
    {
        $resultado = [
            'inserted' => 0,
            'errors' => []
        ];

        if (empty($rows)) {
            return $resultado;
        }

        try {
            $db = (new Conexion())->conectar();
            $db->beginTransaction();

            $sql = "
                INSERT INTO pedidos (
                    fecha_ingreso,
                    numero_orden,
                    destinatario,
                    telefono,
                    precio_local,
                    precio_usd,
                    pais,
                    departamento,
                    municipio,
                    barrio,
                    direccion,
                    zona,
                    comentario,
                    coordenadas,
                    id_estado,
                    id_moneda,
                    id_vendedor,
                    id_proveedor
                ) VALUES (
                    NOW(),
                    :numero_orden,
                    :destinatario,
                    :telefono,
                    :precio_local,
                    :precio_usd,
                    :pais,
                    :departamento,
                    :municipio,
                    :barrio,
                    :direccion,
                    :zona,
                    :comentario,
                    ST_GeomFromText(:coordenadas),
                    :id_estado,
                    :id_moneda,
                    :id_vendedor,
                    :id_proveedor
                )
            ";

            $stmt = $db->prepare($sql);
            $detalleStmt = $db->prepare('
                INSERT INTO pedidos_productos (id_pedido, id_producto, cantidad, cantidad_devuelta)
                VALUES (:id_pedido, :id_producto, :cantidad, 0)
            ');

            // Las operaciones de stock ahora se manejan mediante triggers en la base
            // de datos. No intentamos modificar la tabla `stock` desde PHP para evitar
            // inconsistencias entre esquemas en distintos despliegues.

        } catch (Exception $e) {
            $resultado['errors'][] = 'Error general al insertar pedidos: ' . $e->getMessage();
        }

        return $resultado;
    }

    /* ZONA API */

    /* VERFICAR SI EXISTE UN NUMERO DE ORDEN ANTES DE INSERTARLA */
    public static function existeNumeroOrden($numeroOrden) {
        try {
            $db = (new Conexion())->conectar();
            $query = "SELECT COUNT(*) FROM pedidos WHERE numero_orden = :numero_orden";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":numero_orden", $numeroOrden, PDO::PARAM_INT);
            $stmt->execute();
    
            // Retorna true si hay al menos un pedido con ese número de orden
            return $stmt->fetchColumn() > 0;
    
        } catch (Exception $e) {
            throw new Exception("Error al verificar el número de orden: " . $e->getMessage());
        }
    }
    

    /* CREA EL PEDIDO DESDE EL API */
    public static function crearPedido($data)
    {
        try {
            $db = (new Conexion())->conectar();

            // Preparar los datos
            // Validar coordenadas (se espera "lat,long")
            $latitud = null;
            $longitud = null;
            if (!empty($data["coordenadas"]) && strpos($data["coordenadas"], ',') !== false) {
                $coordenadas = array_map('trim', explode(',', $data["coordenadas"]));
                if (count($coordenadas) === 2) {
                    $latitud = $coordenadas[0];
                    $longitud = $coordenadas[1];
                }
            }

            if ($latitud === null || $longitud === null) {
                throw new Exception('Coordenadas inválidas');
            }


            // var_dump($coordenadas);
            // Insertar el pedido en la base de datos
            $stmt = $db->prepare("
                INSERT INTO pedidos (
                    fecha_ingreso, numero_orden, destinatario, telefono, precio, producto, cantidad,
                    pais, departamento, municipio, barrio, direccion, zona, comentario, coordenadas, id_estado
                ) VALUES (
                    NOW(), :numero_orden, :destinatario, :telefono, :precio, :producto, :cantidad,
                    :pais, :departamento, :municipio, :barrio, :direccion, :zona, :comentario, ST_GeomFromText(:coordenadas), 1
                )
            ");
            $stmt->execute([
                ":numero_orden" => $data["numero_orden"],
                ":destinatario" => $data["destinatario"],
                ":telefono" => $data["telefono"],
                ":precio" => $data["precio"] ?? null,
                ":producto" => $data["producto"],
                ":cantidad" => $data["cantidad"],
                ":pais" => $data["pais"],
                ":departamento" => $data["departamento"],
                ":municipio" => $data["municipio"],
                ":barrio" => $data["barrio"] ?? null,
                ":direccion" => $data["direccion"],
                ":zona" => $data["zona"] ?? null,
                ":comentario" => $data["comentario"] ?? null,
                ":coordenadas" => "POINT($longitud $latitud)"
            ]);

            return [
                "numero_orden" => $data["numero_orden"],

                "pedido_id" => $db->lastInsertId()
            ];
        } catch (Exception $e) {
            throw new Exception("Error al insertar el pedido: " . $e->getMessage());
        }

        $stmt = null;
    }

    public function obtenerPedidoPorNumero($numeroOrden)
    {
        try {

            $db = (new Conexion())->conectar();
            // Consulta para obtener los datos del pedido y su estado
            $sql = "SELECT 
                        p.numero_orden, 
                        p.destinatario, 
                        p.telefono, 
                        p.precio, 
                        p.producto, 
                        p.cantidad, 
                        p.pais, 
                        p.coordenadas, 
                        e.nombre_estado 
                    FROM pedidos p
                    INNER JOIN estados e ON p.estado_id = e.id_estado
                    WHERE p.numero_orden = :numero_orden";

            // Preparar la consulta
            $stmt = $db->prepare($sql);

            // Asignar el parámetro
            $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);

            // Ejecutar la consulta
            $stmt->execute();

            // Verificar si se encontró un resultado
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC); // Devuelve los datos como un array asociativo
            } else {
                return null; // No se encontró el pedido
            }
        } catch (PDOException $e) {
            // Manejar errores de conexión o consulta
            throw new Exception("Error while fetching the order: " . $e->getMessage());
        }
    }

    /*  ZONA DEL FRONT END */

    /*  OBTENER PEDIDOS LISTA DE PEDIDOS COMPLETA  */

    public static function obtenerPedidosExtendidos()
    {
        try {
            $db = (new Conexion())->conectar();

            // Consulta SQL para obtener los datos necesarios
            $query = "
                SELECT 
                    p.id AS ID_Pedido,
                    p.numero_orden AS Numero_Orden,
                    p.destinatario AS Cliente,
                    p.comentario AS Comentario,
                    ST_Y(p.coordenadas) AS latitud, 
                    ST_X(p.coordenadas) AS longitud,
                    ep.nombre_estado AS Estado,
                    p.precio_local AS PrecioLocal,
                    p.precio_usd AS PrecioUSD,
                    m.codigo AS Moneda
                FROM pedidos p
                LEFT JOIN estados_pedidos ep ON p.id_estado = ep.id
                LEFT JOIN monedas m ON p.id_moneda = m.id
                ORDER BY p.fecha_ingreso DESC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al obtener los pedidos: " . $e->getMessage());
        }
    }

    /*  OBTENER PEDIDOS POR ID  */

    public static function obtenerPedidoPorId($id_pedido)
    {
        try {
            $db = (new Conexion())->conectar();
            // Consulta para obtener los datos del pedido incluyendo las coordenadas descompuestas
            $query = "
                SELECT 
                    p.*, 
                    ST_Y(p.coordenadas) AS latitud, 
                    ST_X(p.coordenadas) AS longitud,
                    ep.nombre_estado,
                    m.codigo AS moneda_codigo,
                    m.nombre AS moneda_nombre,
                    uV.nombre AS vendedor_nombre,
                    uP.nombre AS proveedor_nombre
                FROM pedidos p
                LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
                LEFT JOIN monedas m ON m.id = p.id_moneda
                LEFT JOIN usuarios uV ON uV.id = p.id_vendedor
                LEFT JOIN usuarios uP ON uP.id = p.id_proveedor
                WHERE p.id = :id_pedido
            ";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':id_pedido', $id_pedido, PDO::PARAM_INT);
            $stmt->execute();

            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pedido) {
                return null;
            }

            $detalle = $db->prepare('
                SELECT 
                    pp.id_producto,
                    pp.cantidad,
                    pp.cantidad_devuelta,
                    pr.nombre
                FROM pedidos_productos pp
                INNER JOIN productos pr ON pr.id = pp.id_producto
                WHERE pp.id_pedido = :id_pedido
            ');
            $detalle->bindParam(':id_pedido', $id_pedido, PDO::PARAM_INT);
            $detalle->execute();
            $pedido['productos'] = $detalle->fetchAll(PDO::FETCH_ASSOC);

            return $pedido;
        } catch (Exception $e) {
            throw new Exception("Error al obtener el pedido: " . $e->getMessage());
        }
    }

    /* ACTUALIZAR  */

    public static function actualizarPedido($data)
    {
        try {
            $db = (new Conexion())->conectar();
            // Crear el formato POINT para ST_GeomFromText
            $coordenadas = "POINT(" . $data['longitud'] . " " . $data['latitud'] . ")";
            // Aceptar valores faltantes usando null coalescing
            $pais = $data['pais'] ?? null;
            $departamento = $data['departamento'] ?? null;
            $municipio = $data['municipio'] ?? null;
            $barrio = $data['barrio'] ?? null;
            $zona = $data['zona'] ?? null;

            // Consulta SQL con ST_GeomFromText y campos de dirección adicionales
            $query = "
            UPDATE pedidos SET
                numero_orden = :numero_orden,
                destinatario = :destinatario,
                telefono = :telefono,
                pais = :pais,
                departamento = :departamento,
                municipio = :municipio,
                barrio = :barrio,
                zona = :zona,
                direccion = :direccion,
                comentario = :comentario,
                precio_local = :precio_local,
                precio_usd = :precio_usd,
                id_estado = :estado,
                id_vendedor = :vendedor,
                id_proveedor = :proveedor,
                id_moneda = :moneda,
                coordenadas = ST_GeomFromText(:coordenadas)
            WHERE id = :id_pedido
        ";

            // Preparar la consulta
            $stmt = $db->prepare($query);

            // Asociar los valores con bindValue para manejar nulls correctamente
            $stmt->bindValue(':numero_orden', $data['numero_orden'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':destinatario', $data['destinatario'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':telefono', $data['telefono'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':pais', $pais, PDO::PARAM_STR);
            $stmt->bindValue(':departamento', $departamento, PDO::PARAM_STR);
            $stmt->bindValue(':municipio', $municipio, PDO::PARAM_STR);
            $stmt->bindValue(':barrio', $barrio, PDO::PARAM_STR);
            $stmt->bindValue(':zona', $zona, PDO::PARAM_STR);
            $stmt->bindValue(':direccion', $data['direccion'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':comentario', $data['comentario'] ?? null, PDO::PARAM_STR);
            if (!isset($data['precio_local']) || $data['precio_local'] === '' || $data['precio_local'] === null) {
                $stmt->bindValue(':precio_local', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':precio_local', $data['precio_local']);
            }
            if (!isset($data['precio_usd']) || $data['precio_usd'] === '' || $data['precio_usd'] === null) {
                $stmt->bindValue(':precio_usd', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':precio_usd', $data['precio_usd']);
            }
            $stmt->bindValue(':estado', isset($data['estado']) ? (int)$data['estado'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':vendedor', isset($data['vendedor']) ? (int)$data['vendedor'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':proveedor', isset($data['proveedor']) ? (int)$data['proveedor'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':moneda', isset($data['moneda']) ? (int)$data['moneda'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':coordenadas', $coordenadas, PDO::PARAM_STR); // Pasamos el POINT como cadena
            $stmt->bindValue(':id_pedido', isset($data['id_pedido']) ? (int)$data['id_pedido'] : null, PDO::PARAM_INT);

            // Ejecutar la consulta
            $stmt->execute();

            // Actualizar productos (asumiendo un solo producto por pedido)
            if (isset($data['producto_id']) && isset($data['cantidad_producto'])) {
                // Primero, eliminar productos existentes
                $deleteStmt = $db->prepare('DELETE FROM pedidos_productos WHERE id_pedido = :id_pedido');
                $deleteStmt->bindValue(':id_pedido', (int)$data['id_pedido'], PDO::PARAM_INT);
                $deleteStmt->execute();

                // Insertar el nuevo producto
                $insertStmt = $db->prepare('
                    INSERT INTO pedidos_productos (id_pedido, id_producto, cantidad, cantidad_devuelta)
                    VALUES (:id_pedido, :id_producto, :cantidad, 0)
                ');
                $insertStmt->bindValue(':id_pedido', (int)$data['id_pedido'], PDO::PARAM_INT);
                $insertStmt->bindValue(':id_producto', (int)$data['producto_id'], PDO::PARAM_INT);
                $insertStmt->bindValue(':cantidad', (int)$data['cantidad_producto'], PDO::PARAM_INT);
                $insertStmt->execute();
            }

            // Retornar true si hubo cambios en la base de datos
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception("Error updating order: " . $e->getMessage());
        }
    }


    public static function crearPedidoConProductos(array $pedido, array $items)
    {
        // NOTA: La gestión de inventario (tabla `stock`) se realiza mediante
        // triggers en la base de datos. No se deben insertar/actualizar filas
        // en `stock` desde PHP en este flujo para evitar discrepancias entre
        // entornos con esquemas distintos. Ver migrations/README_STOCK_TRIGGER.md
        // para detalles y para desplegar los triggers en staging/producción.

        if (empty($items)) {
            throw new Exception('El pedido debe incluir al menos un producto.');
        }

        try {
            $db = (new Conexion())->conectar();
            $db->beginTransaction();

            $coordenadas = sprintf(
                'POINT(%s %s)',
                number_format((float)$pedido['longitud'], 8, '.', ''),
                number_format((float)$pedido['latitud'], 8, '.', '')
            );

            // Verificar disponibilidad de stock para cada producto antes de insertar.
            // Bloqueamos las filas relevantes con FOR UPDATE para evitar condiciones de carrera.
            $stockCheckStmt = $db->prepare('SELECT COALESCE(SUM(cantidad), 0) AS stock_total FROM stock WHERE id_producto = :id_producto FOR UPDATE');
            foreach ($items as $item) {
                $qty = (int)$item['cantidad'];
                if ($qty <= 0) continue;

                $stockCheckStmt->execute([':id_producto' => (int)$item['id_producto']]);
                $row = $stockCheckStmt->fetch(PDO::FETCH_ASSOC);
                $available = $row ? (int)$row['stock_total'] : 0;
                if ($available - $qty < 0) {
                    throw new Exception('Stock insuficiente para el producto ID ' . (int)$item['id_producto'] . '. Disponible: ' . $available . ', requerido: ' . $qty);
                }
            }

            $stmt = $db->prepare('
                INSERT INTO pedidos (
                    fecha_ingreso,
                    numero_orden,
                    destinatario,
                    telefono,
                    precio_local,
                    precio_usd,
                    pais,
                    departamento,
                    municipio,
                    barrio,
                    direccion,
                    zona,
                    comentario,
                    coordenadas,
                    id_estado,
                    id_moneda,
                    id_vendedor,
                    id_proveedor
                ) VALUES (
                    NOW(),
                    :numero_orden,
                    :destinatario,
                    :telefono,
                    :precio_local,
                    :precio_usd,
                    :pais,
                    :departamento,
                    :municipio,
                    :barrio,
                    :direccion,
                    :zona,
                    :comentario,
                    ST_GeomFromText(:coordenadas),
                    :estado,
                    :moneda,
                    :vendedor,
                    :proveedor
                )
            ');

            $stmt->bindValue(':numero_orden', $pedido['numero_orden'], PDO::PARAM_STR);
            $stmt->bindValue(':destinatario', $pedido['destinatario'], PDO::PARAM_STR);
            $stmt->bindValue(':telefono', $pedido['telefono'], PDO::PARAM_STR);

            if ($pedido['precio_local'] === null) {
                $stmt->bindValue(':precio_local', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':precio_local', $pedido['precio_local']);
            }

            if ($pedido['precio_usd'] === null) {
                $stmt->bindValue(':precio_usd', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':precio_usd', $pedido['precio_usd']);
            }

            $stmt->bindValue(':pais', $pedido['pais'] ?? null, empty($pedido['pais']) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':departamento', $pedido['departamento'] ?? null, empty($pedido['departamento']) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':municipio', $pedido['municipio'] ?? null, empty($pedido['municipio']) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':barrio', $pedido['barrio'] ?? null, empty($pedido['barrio']) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':direccion', $pedido['direccion'], PDO::PARAM_STR);
            $stmt->bindValue(':zona', $pedido['zona'] ?? null, empty($pedido['zona']) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':comentario', $pedido['comentario'] ?? null, empty($pedido['comentario']) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':coordenadas', $coordenadas, PDO::PARAM_STR);
            $stmt->bindValue(':estado', (int)$pedido['estado'], PDO::PARAM_INT);
            $stmt->bindValue(':moneda', (int)$pedido['moneda'], PDO::PARAM_INT);
            $stmt->bindValue(':vendedor', (int)$pedido['vendedor'], PDO::PARAM_INT);
            $stmt->bindValue(':proveedor', (int)$pedido['proveedor'], PDO::PARAM_INT);

            $stmt->execute();

            $pedidoId = (int)$db->lastInsertId();

            $detalleStmt = $db->prepare('
                INSERT INTO pedidos_productos (id_pedido, id_producto, cantidad, cantidad_devuelta)
                VALUES (:id_pedido, :id_producto, :cantidad, :cantidad_devuelta)
            ');

            foreach ($items as $item) {
                $detalleStmt->bindValue(':id_pedido', $pedidoId, PDO::PARAM_INT);
                $detalleStmt->bindValue(':id_producto', (int)$item['id_producto'], PDO::PARAM_INT);
                $detalleStmt->bindValue(':cantidad', (int)$item['cantidad'], PDO::PARAM_INT);
                $detalleStmt->bindValue(':cantidad_devuelta', isset($item['cantidad_devuelta']) ? (int)$item['cantidad_devuelta'] : 0, PDO::PARAM_INT);
                $detalleStmt->execute();
            }

            // Las operaciones de stock ahora se manejan por triggers en la base de datos.
            // No se realizan inserciones a la tabla `stock` desde PHP para evitar
            // inconsistencias entre despliegues con esquemas distintos.

            $db->commit();
            return $pedidoId;
        } catch (Exception $e) {
            if (isset($db) && $db instanceof PDO) {
                $db->rollBack();
            }
            throw new Exception('Error al crear pedido: ' . $e->getMessage());
        }
    }



    public static function obtenerEstados()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT id, nombre_estado FROM estados_pedidos ORDER BY nombre_estado ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al obtener los estados: " . $e->getMessage());
        }
    }


    public static function obtenerVendedores()
    {
        try {
            // Ahora usamos el rol Repartidor como "usuario asignado" para pedidos
            $usuarioModel = new UsuarioModel();
            return $usuarioModel->obtenerUsuariosPorRolNombre(ROL_NOMBRE_REPARTIDOR);
        } catch (Exception $e) {
            throw new Exception("Error al obtener los vendedores: " . $e->getMessage());
        }
    }

    /**
     * Alias explícito por claridad semántica: usuarios con rol Repartidor
     */
    public static function obtenerRepartidores()
    {
        try {
            $usuarioModel = new UsuarioModel();
            return $usuarioModel->obtenerUsuariosPorRolNombre(ROL_NOMBRE_REPARTIDOR);
        } catch (Exception $e) {
            throw new Exception("Error al obtener los repartidores: " . $e->getMessage());
        }
    }

    public static function obtenerProductos()
    {
        try {
            return ProductoModel::listarConInventario();
        } catch (Exception $e) {
            throw new Exception("Error al obtener los productos: " . $e->getMessage());
        }
    }

    public static function obtenerProveedores()
    {
        try {
            $usuarioModel = new UsuarioModel();
            return $usuarioModel->obtenerUsuariosPorRolNombre(ROL_NOMBRE_PROVEEDOR);
        } catch (Exception $e) {
            throw new Exception("Error al obtener los proveedores: " . $e->getMessage());
        }
    }

    public static function obtenerMonedas()
    {
        return MonedaModel::listar();
    }

    public static function obtenerMonedaPorId($id)
    {
        return MonedaModel::obtenerPorId($id);
    }

    /**
     * Listar pedidos asignados a un usuario (seguimiento repartidor)
     * Por ahora usa id_vendedor como campo de asignación.
     */
    public static function listarPorUsuarioAsignado(int $userId)
    {
        try {
            $db = (new Conexion())->conectar();
            $sql = "SELECT 
                        p.id,
                        p.numero_orden,
                        p.destinatario,
                        p.telefono,
                        p.direccion,
                        ST_Y(p.coordenadas) AS latitud,
                        ST_X(p.coordenadas) AS longitud,
                        p.fecha_ingreso,
                        ep.nombre_estado
                    FROM pedidos p
                    LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
                    WHERE p.id_vendedor = :uid
                    ORDER BY p.fecha_ingreso DESC";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar pedidos asignados: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }



    /* cambiar  estados en los datatable */

    public static function actualizarEstado($id_pedido, $estado) {
        try {
            $db = (new Conexion())->conectar();
    
            $query = "UPDATE pedidos SET id_estado = :estado WHERE id = :id_pedido";
            $stmt = $db->prepare($query);
    
            $stmt->bindParam(":estado", $estado, PDO::PARAM_INT);
            $stmt->bindParam(":id_pedido", $id_pedido, PDO::PARAM_INT);
            
            try {
                $stmt->execute();
                return $stmt->rowCount() > 0;
            } catch (PDOException $e) {
                return ["success" => false, "message" => "Error SQL: " . $e->getMessage()];
            }
            
            
        } catch (PDOException $e) {
            return ["success" => false, "message" => "Error SQL: " . $e->getMessage()];
        }
        
    }
    
}
