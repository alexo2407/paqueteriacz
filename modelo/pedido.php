<?php
include_once "conexion.php";
include_once __DIR__ . '/producto.php';
include_once __DIR__ . '/moneda.php';
include_once __DIR__ . '/usuario.php';
include_once __DIR__ . '/pais.php';
include_once __DIR__ . '/departamento.php';

class PedidosModel
{
    /**
     * Comprueba si una tabla contiene una columna dada en la base de datos actual.
     *
     * @param PDO $db
     * @param string $table
     * @param string $column
     * @return bool
     */
    private static function tableHasColumn($db, $table, $column)
    {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
            $stmt->execute([':table' => $table, ':column' => $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            // Si hay algún error al consultar information_schema devolvemos true por compatibilidad
            return true;
        }
    }

    /**
     * Resolver un valor de país (id o nombre/codigo) a su id numérico.
     * Devuelve null si no se puede resolver.
     * @param mixed $value
     * @return int|null
     */
    private static function resolvePaisId($value)
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (int)$value;
        // Intentar resolver por nombre o codigo_iso
        $paises = PaisModel::listar();
        $needle = mb_strtolower(trim((string)$value));
        foreach ($paises as $p) {
            if (mb_strtolower($p['nombre']) === $needle) return (int)$p['id'];
            if (isset($p['codigo_iso']) && mb_strtolower($p['codigo_iso']) === $needle) return (int)$p['id'];
        }
        return null;
    }

    /**
     * Resolver departamento a id. Si se proporciona $paisHint puede filtrar la búsqueda.
     * @param mixed $value
     * @param mixed $paisHint (id or name)
     * @return int|null
     */
    private static function resolveDepartamentoId($value, $paisHint = null)
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (int)$value;
        // intentar resolver id_pais desde hint
        $paisId = null;
        if ($paisHint !== null) {
            if (is_numeric($paisHint)) $paisId = (int)$paisHint;
            else $paisId = self::resolvePaisId($paisHint);
        }
        $departamentos = DepartamentoModel::listarPorPais($paisId);
        $needle = mb_strtolower(trim((string)$value));
        foreach ($departamentos as $d) {
            if (mb_strtolower($d['nombre']) === $needle) return (int)$d['id'];
        }
        // si no encontró y no filtramos por país, intentar buscar en todos
        if ($paisId !== null) {
            $departamentosAll = DepartamentoModel::listarPorPais(null);
            foreach ($departamentosAll as $d) {
                if (mb_strtolower($d['nombre']) === $needle) return (int)$d['id'];
            }
        }
        return null;
    }
    
    /**
     * Obtener todos los números de orden existentes (para validación de duplicados)
     * @return array Lista de números de orden
     */
    public static function obtenerNumerosOrdenExistentes()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->query('SELECT numero_orden FROM pedidos');
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log('Error al obtener números de orden: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Inserta múltiples pedidos reutilizando una sola conexión y statement preparado.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param bool $autoCreateProducts Si crear productos automáticamente si no existen
     * @param array $defaultValues Valores por defecto (estado, proveedor, moneda, vendedor)
     * @return array{inserted:int,errors:array<int,string>,productos_creados:array<string>}
     */
    public static function insertarPedidosLote(array $rows, $autoCreateProducts = true, $defaultValues = [])
    {
        $resultado = [
            'inserted' => 0,
            'errors' => [],
            'productos_creados' => []
        ];

        if (empty($rows)) {
            return $resultado;
        }

        try {
            $db = (new Conexion())->conectar();

            // Construir INSERT dinámico según columnas disponibles (compatibilidad con esquemas)
            $columns = ['fecha_ingreso', 'numero_orden', 'destinatario', 'telefono'];
            // Lista de columnas candidatas que algunas bases pueden no tener
            // Note: DB schema uses foreign keys id_pais and id_departamento now
            $candidates = ['precio_local','precio_usd','id_pais','id_departamento','municipio','barrio','direccion','zona','comentario','coordenadas','id_estado','id_moneda','id_vendedor','id_proveedor'];
            foreach ($candidates as $c) {
                if (self::tableHasColumn($db, 'pedidos', $c)) {
                    $columns[] = $c;
                }
            }

            $placeholders = [];
            foreach ($columns as $col) {
                if ($col === 'fecha_ingreso') {
                    $placeholders[] = 'NOW()';
                    continue;
                }
                if ($col === 'coordenadas') {
                    $placeholders[] = 'ST_GeomFromText(:coordenadas)';
                    continue;
                }
                $placeholders[] = ':' . $col;
            }

            $sql = 'INSERT INTO pedidos (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $db->prepare($sql);
            $detalleStmt = $db->prepare('
                INSERT INTO pedidos_productos (id_pedido, id_producto, cantidad, cantidad_devuelta)
                VALUES (:id_pedido, :id_producto, :cantidad, 0)
            ');

            // Procesar fila por fila: cada pedido se inserta en su propia transacción
            // para minimizar el impacto de errores en filas individuales.
            foreach ($rows as $idx => $row) {
                try {
                    $db->beginTransaction();

                    // Normalizar campos básicos
                    $numeroOrden = $row['numero_orden'] ?? null;
                    $destinatario = $row['destinatario'] ?? null;
                    $telefono = $row['telefono'] ?? null;
                    $direccion = $row['direccion'] ?? null;
                    $comentario = $row['comentario'] ?? null;
                    $pais = $row['pais'] ?? null;
                    $departamento = $row['departamento'] ?? null;
                    $municipio = $row['municipio'] ?? null;
                    $barrio = $row['barrio'] ?? null;
                    $zona = $row['zona'] ?? null;

                    // Coordenadas: aceptar lat/long o campo 'coordenadas' (lat,long)
                    $lat = $row['latitud'] ?? null;
                    $lng = $row['longitud'] ?? null;
                    if (($lat === null || $lng === null) && !empty($row['coordenadas'])) {
                        $parts = array_map('trim', explode(',', $row['coordenadas']));
                        if (count($parts) === 2) {
                            $lat = $parts[0];
                            $lng = $parts[1];
                        }
                    }
                    if ($lat === null || $lng === null) {
                        throw new Exception('Coordenadas inválidas para fila index ' . $idx);
                    }

                    $precio_local = $row['precio'] ?? $row['precio_local'] ?? null;
                    if ($precio_local === '') $precio_local = null;

                    $precio_usd = $row['precio_usd'] ?? null;
                    if ($precio_usd === '') $precio_usd = null;

                    // Ejecutar inserción del pedido: preparar array de parámetros acorde a las columnas construidas
                    $params = [];
                    foreach ($columns as $col) {
                        switch ($col) {
                            case 'coordenadas':
                                $params[':coordenadas'] = 'POINT(' . (float)$lng . ' ' . (float)$lat . ')';
                                break;
                            case 'numero_orden':
                                $params[':numero_orden'] = $numeroOrden;
                                break;
                            case 'destinatario':
                                $params[':destinatario'] = $destinatario;
                                break;
                            case 'telefono':
                                $params[':telefono'] = $telefono;
                                break;
                            case 'precio_local':
                                $params[':precio_local'] = $precio_local;
                                break;
                            case 'precio_usd':
                                $params[':precio_usd'] = $precio_usd;
                                break;
                            case 'id_pais':
                                // accept numeric id or try to resolve by name/code
                                $params[':id_pais'] = self::resolvePaisId($pais);
                                break;
                            case 'id_departamento':
                                $params[':id_departamento'] = self::resolveDepartamentoId($departamento, $pais);
                                break;
                            case 'municipio':
                                $params[':municipio'] = $municipio;
                                break;
                            case 'barrio':
                                $params[':barrio'] = $barrio;
                                break;
                            case 'direccion':
                                $params[':direccion'] = $direccion;
                                break;
                            case 'zona':
                                $params[':zona'] = $zona;
                                break;
                            case 'comentario':
                                $params[':comentario'] = $comentario;
                                break;
                            case 'id_estado':
                                $val = $row['id_estado'] ?? ($defaultValues['estado'] ?? 1);
                                $params[':id_estado'] = ($val === '') ? 1 : $val;
                                break;
                            case 'id_moneda':
                                $val = $row['id_moneda'] ?? ($defaultValues['moneda'] ?? null);
                                $params[':id_moneda'] = ($val === '') ? null : $val;
                                break;
                            case 'id_vendedor':
                                $val = $row['id_vendedor'] ?? ($defaultValues['vendedor'] ?? null);
                                $params[':id_vendedor'] = ($val === '') ? null : $val;
                                break;
                            case 'id_proveedor':
                                $val = $row['id_proveedor'] ?? ($defaultValues['proveedor'] ?? null);
                                $params[':id_proveedor'] = ($val === '') ? null : $val;
                                break;
                        }
                    }

                    $stmt->execute($params);

                    $pedidoId = (int)$db->lastInsertId();

                    // Resolver producto a id
                    $productoId = null;
                    $productoNombre = $row['producto_nombre'] ?? $row['producto'] ?? null;
                    
                    if (isset($row['id_producto']) && is_numeric($row['id_producto']) && (int)$row['id_producto'] > 0) {
                        $productoId = (int)$row['id_producto'];
                    } elseif (isset($row['producto_id']) && is_numeric($row['producto_id']) && (int)$row['producto_id'] > 0) {
                        $productoId = (int)$row['producto_id'];
                    } elseif (!empty($productoNombre)) {
                        $p = ProductoModel::buscarPorNombre($productoNombre);
                        if ($p && isset($p['id'])) {
                            $productoId = (int)$p['id'];
                        } elseif ($autoCreateProducts) {
                            // Crear producto rápido solo si autoCreateProducts está activado
                            $productoId = ProductoModel::crearRapido($productoNombre);
                            // Registrar producto creado
                            if (!in_array($productoNombre, $resultado['productos_creados'])) {
                                $resultado['productos_creados'][] = $productoNombre;
                            }
                        } else {
                            throw new Exception("Producto '{$productoNombre}' no existe y autoCreateProducts está desactivado");
                        }
                    }

                    $cantidad = isset($row['cantidad']) ? (int)$row['cantidad'] : 1;
                    if ($productoId !== null && $cantidad > 0) {
                        $detalleStmt->execute([
                            ':id_pedido' => $pedidoId,
                            ':id_producto' => $productoId,
                            ':cantidad' => $cantidad
                        ]);
                    }

                    $db->commit();
                    $resultado['inserted']++;
                } catch (Exception $eRow) {
                    // Rollback de esta fila y registrar error
                    if ($db->inTransaction()) $db->rollBack();
                    $resultado['errors'][] = 'Fila ' . ($idx + 1) . ' error: ' . $eRow->getMessage();
                    // continuar con la siguiente fila
                }
            }

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
    /**
     * Comprobar existencia de un número de orden en la tabla `pedidos`.
     *
     * @param int|string $numeroOrden Número de orden a comprobar.
     * @return bool True si existe al menos un pedido con ese número, false en caso contrario.
     * @throws Exception En caso de error de consulta.
     */
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
    

    /**
     * Crear un pedido simple (API legacy).
     *
     * Inserta una fila en `pedidos` y, opcionalmente, una fila en
     * `pedidos_productos` si se proporcionan `producto_id`/`cantidad`.
     * Este método construye el INSERT dinámicamente según columnas presentes
     * en la base de datos (compatibilidad entre despliegues).
     *
     * @param array $data Datos del pedido (numero_orden, destinatario, telefono, coordenadas, precio, producto, cantidad, etc.)
     * @return array Retorna ['numero_orden' => ..., 'pedido_id' => int]
     * @throws Exception Si falla la validación o la inserción.
     */
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
            // Insertar el pedido en la base de datos (sin columnas producto/cantidad)
            // Construir INSERT dinámico según columnas disponibles para compatibilidad
            $columns = ['fecha_ingreso', 'numero_orden', 'destinatario', 'telefono'];
            // Use id_pais / id_departamento (FKs) in schema-aware inserts
            $candidates = ['precio_local','precio_usd','id_pais','id_departamento','municipio','barrio','direccion','zona','comentario','coordenadas','id_estado'];
            foreach ($candidates as $c) {
                if (self::tableHasColumn($db, 'pedidos', $c)) {
                    $columns[] = $c;
                }
            }

            $placeholders = [];
            foreach ($columns as $col) {
                if ($col === 'fecha_ingreso') {
                    $placeholders[] = 'NOW()';
                    continue;
                }
                if ($col === 'coordenadas') {
                    $placeholders[] = 'ST_GeomFromText(:coordenadas)';
                    continue;
                }
                $placeholders[] = ':' . $col;
            }

            $sql = 'INSERT INTO pedidos (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $db->prepare($sql);

            $precio_local = $data['precio'] ?? $data['precio_local'] ?? null;
            $precio_usd = $data['precio_usd'] ?? null;

            // Preparar parámetros según columnas
            $params = [];
            foreach ($columns as $col) {
                switch ($col) {
                    case 'coordenadas':
                        $params[':coordenadas'] = "POINT($longitud $latitud)";
                        break;
                    case 'numero_orden':
                        $params[':numero_orden'] = $data['numero_orden'];
                        break;
                    case 'destinatario':
                        $params[':destinatario'] = $data['destinatario'];
                        break;
                    case 'telefono':
                        $params[':telefono'] = $data['telefono'];
                        break;
                    case 'precio_local':
                        $params[':precio_local'] = $precio_local;
                        break;
                    case 'precio_usd':
                        $params[':precio_usd'] = $precio_usd;
                        break;
                    case 'id_pais':
                        // accept numeric id or try to resolve by name/code
                        $params[':id_pais'] = self::resolvePaisId($data['pais'] ?? null);
                        break;
                    case 'id_departamento':
                        // try to resolve departamento, optionally using provided pais hint
                        $params[':id_departamento'] = self::resolveDepartamentoId($data['departamento'] ?? null, $data['pais'] ?? null);
                        break;
                        break;
                    case 'municipio':
                        $params[':municipio'] = $data['municipio'] ?? null;
                        break;
                    case 'barrio':
                        $params[':barrio'] = $data['barrio'] ?? null;
                        break;
                    case 'direccion':
                        $params[':direccion'] = $data['direccion'] ?? null;
                        break;
                    case 'zona':
                        $params[':zona'] = $data['zona'] ?? null;
                        break;
                    case 'comentario':
                        $params[':comentario'] = $data['comentario'] ?? null;
                        break;
                    case 'id_estado':
                        $params[':id_estado'] = 1;
                        break;
                }
            }

            $stmt->execute($params);

            $pedidoId = (int)$db->lastInsertId();

            // Insertar producto(s) en la tabla pivot cuando se provean.
            // Aceptamos dos formatos en $data: (1) 'producto_id' + 'cantidad'
            // o (2) 'producto' (nombre) + 'cantidad' — en este caso intentamos resolver el id.
            $productoId = null;
            $cantidad = isset($data['cantidad']) ? (int)$data['cantidad'] : null;

            if (isset($data['producto_id'])) {
                $productoId = (int)$data['producto_id'];
            } elseif (!empty($data['producto'])) {
                // Intentar resolver por nombre (retorna null si no existe)
                $p = ProductoModel::buscarPorNombre($data['producto']);
                $productoId = $p['id'] ?? null;
            }

            if ($productoId !== null && $cantidad !== null) {
                $detalle = $db->prepare('INSERT INTO pedidos_productos (id_pedido, id_producto, cantidad, cantidad_devuelta) VALUES (:id_pedido, :id_producto, :cantidad, 0)');
                $detalle->execute([
                    ':id_pedido' => $pedidoId,
                    ':id_producto' => $productoId,
                    ':cantidad' => $cantidad
                ]);
            }

            return [
                "numero_orden" => $data["numero_orden"],
                "pedido_id" => $pedidoId
            ];
        } catch (Exception $e) {
            throw new Exception("Error al insertar el pedido: " . $e->getMessage());
        }

        $stmt = null;
    }

    /**
     * Obtener un pedido por su número de orden.
     *
     * @param int|string $numeroOrden
     * @return array|null Array asociativo con los campos seleccionados o null si no existe.
     * @throws Exception En caso de error en la consulta.
     */
    public function obtenerPedidoPorNumero($numeroOrden)
    {
        try {

            $db = (new Conexion())->conectar();
            // Consulta para obtener los datos del pedido y su estado (adaptada al esquema actual)
            $sql = "SELECT 
                        p.numero_orden,
                        p.destinatario,
                        p.telefono,
                        p.precio_local,
                        p.precio_usd,
                        p.id_pais,
                        p.id_departamento,
                        ST_Y(p.coordenadas) AS latitud,
                        ST_X(p.coordenadas) AS longitud,
                        ep.nombre_estado AS nombre_estado
                    FROM pedidos p
                    LEFT JOIN estados_pedidos ep ON p.id_estado = ep.id
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
                    m.codigo AS Moneda,
                    p.id_proveedor
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
    
    /**
     * Listar pedidos para exportación CSV con todos los campos
     * 
     * @param array $filtros Filtros opcionales
     * @param int $limite Límite de resultados
     * @return array Pedidos con productos incluidos
     */
    public static function listarParaExportar($filtros = [], $limite = 1000)
    {
        try {
            $db = (new Conexion())->conectar();
            
            $where = [];
            $params = [];
            
            // Construir WHERE clause según filtros
            if (!empty($filtros['id_estado'])) {
                $where[] = 'p.id_estado = :id_estado';
                $params[':id_estado'] = (int)$filtros['id_estado'];
            }
            
            if (!empty($filtros['id_proveedor'])) {
                $where[] = 'p.id_proveedor = :id_proveedor';
                $params[':id_proveedor'] = (int)$filtros['id_proveedor'];
            }
            
            if (!empty($filtros['fecha_desde'])) {
                $where[] = 'p.fecha_ingreso >= :fecha_desde';
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }
            
            if (!empty($filtros['fecha_hasta'])) {
                $where[] = 'p.fecha_ingreso <= :fecha_hasta';
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $query = "
                SELECT 
                    p.id,
                    p.numero_orden,
                    p.destinatario,
                    p.telefono,
                    p.direccion,
                    ST_Y(p.coordenadas) AS latitud,
                    ST_X(p.coordenadas) AS longitud,
                    p.id_estado,
                    p.id_moneda,
                    p.id_proveedor,
                    p.id_vendedor,
                    p.precio_local,
                    p.precio_usd,
                    p.id_pais,
                    p.id_departamento,
                    p.municipio,
                    p.barrio,
                    p.zona,
                    p.comentario
                FROM pedidos p
                {$whereClause}
                ORDER BY p.fecha_ingreso DESC
                LIMIT :limite
            ";
            
            $stmt = $db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Para cada pedido, obtener sus productos
            $stmtProductos = $db->prepare('
                SELECT 
                    pp.id_producto,
                    pp.cantidad,
                    pp.cantidad_devuelta,
                    pr.nombre
                FROM pedidos_productos pp
                INNER JOIN productos pr ON pr.id = pp.id_producto
                WHERE pp.id_pedido = :id_pedido
            ');
            
            foreach ($pedidos as &$pedido) {
                $stmtProductos->execute([':id_pedido' => $pedido['id']]);
                $pedido['productos'] = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $pedidos;
            
        } catch (Exception $e) {
            throw new Exception("Error al listar pedidos para exportar: " . $e->getMessage());
        }
    }

    /*  OBTENER PEDIDOS POR ID  */

    /**
     * Obtener un pedido completo por su identificador.
     *
     * Incluye la lista de productos asociados en la clave 'productos'.
     * Retorna null si no existe.
     *
     * @param int $id_pedido
     * @return array|null
     * @throws Exception En caso de fallo en la consulta.
     */
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
                    uP.nombre AS proveedor_nombre,
                    (SELECT fecha_asignacion FROM entregas WHERE id_pedido = p.id ORDER BY id DESC LIMIT 1) as fecha_asignacion,
                    (SELECT fecha_entrega FROM entregas WHERE id_pedido = p.id ORDER BY id DESC LIMIT 1) as fecha_entrega,
                    (SELECT observaciones FROM entregas WHERE id_pedido = p.id ORDER BY id DESC LIMIT 1) as entrega_observaciones
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

    /**
     * Actualizar los datos de un pedido existente.
     *
     * Acepta coordenadas (latitud/longitud) y campos opcionales. También
     * puede actualizar la relación pedidos_productos cuando se proveen
     * `producto_id` y `cantidad_producto`.
     *
     * @param array $data Debe contener 'id_pedido' y los campos a modificar.
     * @return bool True si se aplicaron cambios, False si no o en caso de error.
     * @throws Exception En caso de error de base de datos.
     */
    public static function actualizarPedido($data)
    {
        try {
            $db = (new Conexion())->conectar();
            $db->beginTransaction();

            // Build UPDATE query dynamically based on provided fields
            $fields = [];
            $params = [':id' => (int)$data['id_pedido']];

            // Basic fields
            if (isset($data['numero_orden'])) {
                $fields[] = 'numero_orden = :numero_orden';
                $params[':numero_orden'] = (int)$data['numero_orden'];
            }
            if (isset($data['destinatario'])) {
                $fields[] = 'destinatario = :destinatario';
                $params[':destinatario'] = $data['destinatario'];
            }
            if (isset($data['telefono'])) {
                $fields[] = 'telefono = :telefono';
                $params[':telefono'] = $data['telefono'];
            }
            if (isset($data['direccion'])) {
                $fields[] = 'direccion = :direccion';
                $params[':direccion'] = $data['direccion'];
            }
            if (isset($data['comentario'])) {
                $fields[] = 'comentario = :comentario';
                $params[':comentario'] = $data['comentario'] !== '' ? $data['comentario'] : null;
            }

            // Coordinates
            if (isset($data['latitud']) && isset($data['longitud'])) {
                $fields[] = 'coordenadas = ST_GeomFromText(:coordenadas)';
                $params[':coordenadas'] = sprintf('POINT(%s %s)', 
                    number_format((float)$data['longitud'], 8, '.', ''),
                    number_format((float)$data['latitud'], 8, '.', '')
                );
            }

            // Prices
            if (isset($data['precio_local'])) {
                $fields[] = 'precio_local = :precio_local';
                $params[':precio_local'] = $data['precio_local'] !== '' ? (float)$data['precio_local'] : null;
            }
            if (isset($data['precio_usd'])) {
                $fields[] = 'precio_usd = :precio_usd';
                $params[':precio_usd'] = $data['precio_usd'] !== '' ? (float)$data['precio_usd'] : null;
            }

            // Foreign keys - only update if value is provided and not empty
            if (isset($data['estado']) && $data['estado'] !== '' && $data['estado'] !== '0') {
                $fields[] = 'id_estado = :id_estado';
                $params[':id_estado'] = (int)$data['estado'];
            }
            if (isset($data['moneda']) && $data['moneda'] !== '' && $data['moneda'] !== '0') {
                $fields[] = 'id_moneda = :id_moneda';
                $params[':id_moneda'] = (int)$data['moneda'];
            }
            if (isset($data['vendedor']) && $data['vendedor'] !== '' && $data['vendedor'] !== '0') {
                $fields[] = 'id_vendedor = :id_vendedor';
                $params[':id_vendedor'] = (int)$data['vendedor'];
            }
            if (isset($data['proveedor']) && $data['proveedor'] !== '' && $data['proveedor'] !== '0') {
                $fields[] = 'id_proveedor = :id_proveedor';
                $params[':id_proveedor'] = (int)$data['proveedor'];
            }
            if (isset($data['id_pais'])) {
                $fields[] = 'id_pais = :id_pais';
                $params[':id_pais'] = $data['id_pais'] !== '' ? (int)$data['id_pais'] : null;
            }
            if (isset($data['id_departamento'])) {
                $fields[] = 'id_departamento = :id_departamento';
                $params[':id_departamento'] = $data['id_departamento'] !== '' ? (int)$data['id_departamento'] : null;
            }
            if (isset($data['id_municipio'])) {
                $fields[] = 'id_municipio = :id_municipio';
                $params[':id_municipio'] = $data['id_municipio'] !== '' ? (int)$data['id_municipio'] : null;
            }
            if (isset($data['id_barrio'])) {
                $fields[] = 'id_barrio = :id_barrio';
                $params[':id_barrio'] = $data['id_barrio'] !== '' ? (int)$data['id_barrio'] : null;
            }

            // Execute UPDATE if there are fields to update
            if (!empty($fields)) {
                $sql = 'UPDATE pedidos SET ' . implode(', ', $fields) . ' WHERE id = :id';
                $stmt = $db->prepare($sql);
                $stmt->execute($params);

                // Si es repartidor actualizando estado, marcar timestamp para bloquear futuras actualizaciones
                if (isset($data['estado']) && !empty($data['is_repartidor'])) {
                    $stmtTimestamp = $db->prepare('UPDATE pedidos SET repartidor_updated_at = NOW() WHERE id = :id');
                    $stmtTimestamp->execute([':id' => (int)$data['id_pedido']]);
                }
            }

            // Update products if provided
            if (isset($data['productos']) && is_array($data['productos'])) {
                // Delete existing products
                $stmtDel = $db->prepare('DELETE FROM pedidos_productos WHERE id_pedido = :id_pedido');
                $stmtDel->execute([':id_pedido' => (int)$data['id_pedido']]);

                // Insert new products
                $stmtIns = $db->prepare('INSERT INTO pedidos_productos (id_pedido, id_producto, cantidad, cantidad_devuelta) VALUES (:id_pedido, :id_producto, :cantidad, :cantidad_devuelta)');
                foreach ($data['productos'] as $prod) {
                    if (isset($prod['producto_id']) && isset($prod['cantidad'])) {
                        $stmtIns->execute([
                            ':id_pedido' => (int)$data['id_pedido'],
                            ':id_producto' => (int)$prod['producto_id'],
                            ':cantidad' => (int)$prod['cantidad'],
                            ':cantidad_devuelta' => isset($prod['cantidad_devuelta']) ? (int)$prod['cantidad_devuelta'] : 0
                        ]);
                    }
                }
            } elseif (isset($data['producto_id']) && isset($data['cantidad_producto'])) {
                // Legacy single product update
                $stmtDel = $db->prepare('DELETE FROM pedidos_productos WHERE id_pedido = :id_pedido');
                $stmtDel->execute([':id_pedido' => (int)$data['id_pedido']]);

                $stmtIns = $db->prepare('INSERT INTO pedidos_productos (id_pedido, id_producto, cantidad, cantidad_devuelta) VALUES (:id_pedido, :id_producto, :cantidad, 0)');
                $stmtIns->execute([
                    ':id_pedido' => (int)$data['id_pedido'],
                    ':id_producto' => (int)$data['producto_id'],
                    ':cantidad' => (int)$data['cantidad_producto']
                ]);
            }

            $db->commit();
            return true;

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Eliminar un pedido y restaurar el stock de sus productos.
     *
     * @param int $idPedido
     * @return bool
     * @throws Exception
     */
    public static function eliminarPedido($idPedido)
    {
        try {
            $db = (new Conexion())->conectar();
            $db->beginTransaction();

            require_once __DIR__ . '/stock.php';

            // 1. Obtener items del pedido para restaurar stock
            $stmtItems = $db->prepare("SELECT id_producto, cantidad FROM pedidos_productos WHERE id_pedido = :id_pedido");
            $stmtItems->execute([':id_pedido' => $idPedido]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            // 2. Obtener vendedor del pedido (para el registro de stock)
            $stmtPedido = $db->prepare("SELECT id_vendedor FROM pedidos WHERE id = :id");
            $stmtPedido->execute([':id' => $idPedido]);
            $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);
            $vendedorId = $pedido ? ($pedido['id_vendedor'] ?: null) : null;

            // 3. Restaurar stock
            foreach ($items as $item) {
                StockModel::registrarEntrada($item['id_producto'], $item['cantidad'], $vendedorId, $db);
            }

            // 4. Eliminar items
            $stmtDelItems = $db->prepare("DELETE FROM pedidos_productos WHERE id_pedido = :id_pedido");
            $stmtDelItems->execute([':id_pedido' => $idPedido]);

            // 5. Eliminar entregas asociadas (si existen)
            $stmtDelEntregas = $db->prepare("DELETE FROM entregas WHERE id_pedido = :id_pedido");
            $stmtDelEntregas->execute([':id_pedido' => $idPedido]);

            // 6. Eliminar pedido
            $stmtDelPedido = $db->prepare("DELETE FROM pedidos WHERE id = :id");
            $stmtDelPedido->execute([':id' => $idPedido]);

            $db->commit();
            return true;

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Error al eliminar el pedido: " . $e->getMessage());
        }
    }


    /**
     * Crear un pedido y sus productos en una transacción.
     *
     * - Valida stock disponible (SELECT ... FOR UPDATE) antes de insertar.
     * - Inserta en `pedidos` y en `pedidos_productos`.
     * - Devuelve el id del pedido creado.
     *
     * @param array $pedido Datos del pedido (numero_orden, destinatario, telefono, latitud, longitud, etc.)
     * @param array<int,array{ id_producto:int, cantidad:int, cantidad_devuelta?:int }> $items Lista de items
     * @return int ID del pedido creado
     * @throws Exception Si no hay items, stock insuficiente o error en la transacción.
     */
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

            // Determine a safe user id to satisfy stock-related DB triggers
            // which may attempt to insert rows into `stock` with a non-null
            // `id_usuario`. Priority:
            // 1) If the caller provided vendedor/proveedor keep them.
            // 2) If FALLBACK_USER_FOR_STOCK is configured, use it.
            // 3) Try to pick the current session user (if session available).
            // 4) Query the DB for any active user id and use that.
            // 5) If none found, throw an informative exception to prompt config.
            $resolvedFallbackUser = null;
            if (!empty($pedido['vendedor'])) {
                $resolvedFallbackUser = (int)$pedido['vendedor'];
            } elseif (!empty($pedido['proveedor'])) {
                $resolvedFallbackUser = (int)$pedido['proveedor'];
            }

            if ($resolvedFallbackUser === null) {
                if (defined('FALLBACK_USER_FOR_STOCK') && FALLBACK_USER_FOR_STOCK !== null) {
                    $resolvedFallbackUser = (int)FALLBACK_USER_FOR_STOCK;
                }
            }

            // Try to obtain a session user id if available
            if ($resolvedFallbackUser === null) {
                try {
                    if (session_status() === PHP_SESSION_NONE) {
                        @session_start();
                    }
                    if (!empty($_SESSION['user_id'])) {
                        $resolvedFallbackUser = (int)$_SESSION['user_id'];
                    }
                } catch (Exception $e) {
                    // ignore session problems
                }
            }

            // As a last resort, query the usuarios table for any user id
            if ($resolvedFallbackUser === null) {
                try {
                    $q = $db->query('SELECT id FROM usuarios WHERE activo = 1 LIMIT 1');
                    $row = $q->fetch(PDO::FETCH_ASSOC);
                    if ($row && isset($row['id'])) {
                        $resolvedFallbackUser = (int)$row['id'];
                    }
                } catch (Exception $e) {
                    // ignore DB read issues here; we'll throw below if still null
                }
            }

            if ($resolvedFallbackUser === null) {
                throw new Exception('No se pudo determinar un id de usuario válido para operaciones de stock. Define FALLBACK_USER_FOR_STOCK en la configuración o asegúrate de que la tabla usuarios contenga al menos un usuario activo.');
            }

            // Apply resolved fallback where missing so DB triggers get a valid id
            if (empty($pedido['vendedor']) || $pedido['vendedor'] === null) {
                $pedido['vendedor'] = $resolvedFallbackUser;
                $pedido['id_vendedor'] = $resolvedFallbackUser;
            }
            if (empty($pedido['proveedor']) || $pedido['proveedor'] === null) {
                $pedido['proveedor'] = $resolvedFallbackUser;
                $pedido['id_proveedor'] = $resolvedFallbackUser;
            }

            // Ensure id_vendedor/id_proveedor are set if they were passed as vendedor/proveedor
            if (isset($pedido['vendedor']) && !isset($pedido['id_vendedor'])) {
                $pedido['id_vendedor'] = $pedido['vendedor'];
            }
            if (isset($pedido['proveedor']) && !isset($pedido['id_proveedor'])) {
                $pedido['id_proveedor'] = $pedido['proveedor'];
            }

            $coordenadas = sprintf(
                'POINT(%s %s)',
                number_format((float)$pedido['longitud'], 8, '.', ''),
                number_format((float)$pedido['latitud'], 8, '.', '')
            );

            // Verificar disponibilidad de stock para cada producto antes de insertar.
            // Bloqueamos las filas relevantes con FOR UPDATE para evitar condiciones de carrera.
            $stockCheckStmt = $db->prepare('SELECT COALESCE(SUM(cantidad), 0) AS stock_total FROM stock WHERE id_producto = :id_producto FOR UPDATE');
            // 1. Validar stock para todos los items antes de insertar nada
            // Esto previene insertar un pedido si falta stock de algún producto.
            foreach ($items as $item) {
                $prodId = (int)$item['id_producto'];
                $cantidadSolicitada = (int)$item['cantidad'];
                
                // Obtener stock actual
                // Idealmente deberíamos hacer SELECT ... FOR UPDATE para bloquear la fila,
                // pero ProductoModel::obtenerStockTotal usa SUM(stock) lo cual es complejo de bloquear.
                // Por ahora confiamos en la validación inmediata.
                $stockActual = ProductoModel::obtenerStockTotal($prodId);
                
                if ($stockActual === null) {
                    throw new Exception("Producto ID $prodId no encontrado o error de stock.");
                }
                
                if ($stockActual < $cantidadSolicitada) {
                    throw new Exception("Stock insuficiente para el producto ID $prodId. Disponible: $stockActual, Solicitado: $cantidadSolicitada.");
                }
            }

            // 2. Insertar el pedido
            // Construir INSERT dinámico según columnas disponibles
            $columns = ['fecha_ingreso', 'numero_orden', 'destinatario', 'telefono'];
            $candidates = ['precio_local','precio_usd','id_pais','id_departamento','municipio','barrio','direccion','zona','comentario','coordenadas','id_estado','id_moneda','id_vendedor','id_proveedor'];
            
            foreach ($candidates as $c) {
                if (self::tableHasColumn($db, 'pedidos', $c)) {
                    $columns[] = $c;
                }
            }

            $placeholders = [];
            foreach ($columns as $col) {
                if ($col === 'fecha_ingreso') {
                    $placeholders[] = 'NOW()';
                    continue;
                }
                if ($col === 'coordenadas') {
                    $placeholders[] = 'ST_GeomFromText(:coordenadas)';
                    continue;
                }
                $placeholders[] = ':' . $col;
            }

            $sql = 'INSERT INTO pedidos (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $db->prepare($sql);
            // Bind params
            if (in_array('numero_orden', $columns)) $stmt->bindValue(':numero_orden', $pedido['numero_orden']);
            if (in_array('destinatario', $columns)) $stmt->bindValue(':destinatario', $pedido['destinatario']);
            if (in_array('telefono', $columns)) $stmt->bindValue(':telefono', $pedido['telefono']);
            
            if (in_array('coordenadas', $columns)) {
                $lat = $pedido['latitud'];
                $lng = $pedido['longitud'];
                $stmt->bindValue(':coordenadas', "POINT($lng $lat)");
            }

            // Bind optional params
            $map = [
                'precio_local' => 'precio_local',
                'precio_usd' => 'precio_usd',
                'id_pais' => 'id_pais',
                'id_departamento' => 'id_departamento',
                'municipio' => 'municipio',
                'barrio' => 'barrio',
                'direccion' => 'direccion',
                'zona' => 'zona',
                'comentario' => 'comentario',
                'id_estado' => 'estado',
                'id_moneda' => 'moneda',
                'id_vendedor' => 'vendedor',
                'id_proveedor' => 'proveedor'
            ];

            foreach ($map as $col => $key) {
                if (in_array($col, $columns)) {
                    $val = isset($pedido[$key]) ? $pedido[$key] : null;
                    // Ensure integers for IDs, but treat 0 or empty as NULL for FKs
                    if (strpos($col, 'id_') === 0) {
                        if ($val !== null && $val !== '' && $val != 0) {
                            $val = (int)$val;
                        } else {
                            $val = null;
                        }
                    }
                    $stmt->bindValue(':' . $col, $val);
                }
            }

            $stmt->execute();
            $pedidoId = (int)$db->lastInsertId();

            // 3. Insertar items y descontar stock
            $detalleStmt = $db->prepare('INSERT INTO pedidos_productos (id_pedido, id_producto, cantidad, cantidad_devuelta) VALUES (:id_pedido, :id_producto, :cantidad, 0)');
            
            require_once __DIR__ . '/stock.php'; // Asegurar que StockModel está disponible

            foreach ($items as $item) {
                $prodId = (int)$item['id_producto'];
                $cant = (int)$item['cantidad'];

                // Insertar detalle
                $detalleStmt->bindValue(':id_pedido', $pedidoId, PDO::PARAM_INT);
                $detalleStmt->bindValue(':id_producto', $prodId, PDO::PARAM_INT);
                $detalleStmt->bindValue(':cantidad', $cant, PDO::PARAM_INT);
                $detalleStmt->execute();

                // Descontar stock (PHP logic)
                // Usamos el id_vendedor del pedido como responsable del movimiento, si existe
                $vendedorId = isset($pedido['vendedor']) ? (int)$pedido['vendedor'] : null;
                StockModel::registrarSalida($prodId, $cant, $vendedorId, $db);
            }

            $db->commit();
            return $pedidoId;

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
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

    /**
     * Obtener ventas diarias del mes actual.
     *
     * @return array Array de objetos con fecha y total.
     */
    public static function obtenerVentasMesActual()
    {
        try {
            $db = (new Conexion())->conectar();
            // Verificar si existe la columna precio_local, si no usar 0
            if (!self::tableHasColumn($db, 'pedidos', 'precio_local')) {
                return [];
            }
            
            $query = "
                SELECT 
                    DATE(fecha_ingreso) as fecha, 
                    SUM(precio_local) as total 
                FROM pedidos 
                WHERE MONTH(fecha_ingreso) = MONTH(CURRENT_DATE()) 
                  AND YEAR(fecha_ingreso) = YEAR(CURRENT_DATE()) 
                GROUP BY DATE(fecha_ingreso) 
                ORDER BY fecha ASC
            ";
            $stmt = $db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtener ID de estado por nombre.
     * @param string $nombre
     * @return int|null
     */
    public static function obtenerIdEstadoPorNombre($nombre) {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT id FROM estados_pedidos WHERE nombre_estado = :nombre");
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->execute();
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ? (int)$res['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Obtener ventas comparativas (Mes Actual vs Mes Anterior).
     * Filtra por estado 'Entregado' si existe, sino cuenta todo.
     * @param int|null $proveedorId Si se proporciona, filtra por proveedor
     */
    public static function obtenerVentasComparativa($proveedorId = null) {
        try {
            $db = (new Conexion())->conectar();
            if (!self::tableHasColumn($db, 'pedidos', 'precio_local')) return [];

            $idEntregado = self::obtenerIdEstadoPorNombre('Entregado');
            $condicionEstado = "";
            if ($idEntregado) {
                $condicionEstado = " AND id_estado = $idEntregado ";
            }

            // Filtro por proveedor si se proporciona
            $condicionProveedor = "";
            if ($proveedorId !== null) {
                $condicionProveedor = " AND id_proveedor = " . (int)$proveedorId;
            }

            // Mes Actual
            $queryActual = "
                SELECT DATE(fecha_ingreso) as fecha, SUM(precio_local) as total 
                FROM pedidos 
                WHERE MONTH(fecha_ingreso) = MONTH(CURRENT_DATE()) 
                  AND YEAR(fecha_ingreso) = YEAR(CURRENT_DATE()) 
                  $condicionEstado
                  $condicionProveedor
                GROUP BY DATE(fecha_ingreso) ORDER BY fecha ASC";
            
            // Mes Anterior
            $queryAnterior = "
                SELECT DATE(fecha_ingreso) as fecha, SUM(precio_local) as total 
                FROM pedidos 
                WHERE MONTH(fecha_ingreso) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
                  AND YEAR(fecha_ingreso) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH) 
                  $condicionEstado
                  $condicionProveedor
                GROUP BY DATE(fecha_ingreso) ORDER BY fecha ASC";

            $stmtActual = $db->prepare($queryActual); $stmtActual->execute();
            $stmtAnterior = $db->prepare($queryAnterior); $stmtAnterior->execute();

            return [
                'actual' => $stmtActual->fetchAll(PDO::FETCH_ASSOC),
                'anterior' => $stmtAnterior->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (Exception $e) {
            return ['actual' => [], 'anterior' => []];
        }
    }

    /**
     * Obtener KPIs del mes actual (Total Vendido, Ticket Promedio, Total Pedidos).
     * @param int|null $proveedorId Si se proporciona, filtra por proveedor
     */
    public static function obtenerKPIsMesActual($proveedorId = null) {
        try {
            $db = (new Conexion())->conectar();
            if (!self::tableHasColumn($db, 'pedidos', 'precio_local')) return null;

            $idEntregado = self::obtenerIdEstadoPorNombre('Entregado');
            $condicionEstado = "";
            if ($idEntregado) {
                $condicionEstado = " AND id_estado = $idEntregado ";
            }

            // Filtro por proveedor si se proporciona
            $condicionProveedor = "";
            if ($proveedorId !== null) {
                $condicionProveedor = " AND id_proveedor = " . (int)$proveedorId;
            }

            $query = "
                SELECT 
                    COUNT(*) as total_pedidos,
                    COALESCE(SUM(precio_local), 0) as total_vendido,
                    COALESCE(AVG(precio_local), 0) as ticket_promedio
                FROM pedidos
                WHERE MONTH(fecha_ingreso) = MONTH(CURRENT_DATE()) 
                  AND YEAR(fecha_ingreso) = YEAR(CURRENT_DATE()) 
                  $condicionEstado
                  $condicionProveedor
            ";
            $stmt = $db->prepare($query);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Obtener ventas acumuladas del mes actual.
     */
    public static function obtenerVentasAcumuladasMesActual() {
        // Reutilizamos la lógica de comparativa para obtener los datos diarios y los acumulamos en PHP
        $datos = self::obtenerVentasComparativa();
        $diario = $datos['actual'];
        
        $acumulado = [];
        $suma = 0;
        foreach ($diario as $dia) {
            $suma += (float)$dia['total'];
            $acumulado[] = [
                'fecha' => $dia['fecha'],
                'total_acumulado' => $suma
            ];
        }
        return $acumulado;
    }

    /**
     * Obtener top 5 productos más vendidos del mes actual (Filtrado por Entregado).
     * @param int|null $proveedorId Si se proporciona, filtra por proveedor
     */
    public static function obtenerTopProductosMesActual($proveedorId = null)
    {
        try {
            $db = (new Conexion())->conectar();
            
            $idEntregado = self::obtenerIdEstadoPorNombre('Entregado');
            $condicionEstado = "";
            if ($idEntregado) {
                $condicionEstado = " AND ped.id_estado = $idEntregado ";
            }

            // Filtro por proveedor si se proporciona
            $condicionProveedor = "";
            if ($proveedorId !== null) {
                $condicionProveedor = " AND ped.id_proveedor = " . (int)$proveedorId;
            }

            $query = "
                SELECT 
                    p.nombre, 
                    SUM(pp.cantidad) as total 
                FROM pedidos_productos pp
                JOIN pedidos ped ON pp.id_pedido = ped.id
                JOIN productos p ON pp.id_producto = p.id
                WHERE MONTH(ped.fecha_ingreso) = MONTH(CURRENT_DATE()) 
                  AND YEAR(ped.fecha_ingreso) = YEAR(CURRENT_DATE()) 
                  $condicionEstado
                  $condicionProveedor
                GROUP BY pp.id_producto 
                ORDER BY total DESC 
                LIMIT 5
            ";
            $stmt = $db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

