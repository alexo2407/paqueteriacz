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
                    $precio_usd = $row['precio_usd'] ?? null;

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
                                $params[':id_estado'] = $row['id_estado'] ?? 1;
                                break;
                            case 'id_moneda':
                                $params[':id_moneda'] = $row['id_moneda'] ?? null;
                                break;
                            case 'id_vendedor':
                                $params[':id_vendedor'] = $row['id_vendedor'] ?? null;
                                break;
                            case 'id_proveedor':
                                $params[':id_proveedor'] = $row['id_proveedor'] ?? null;
                                break;
                        }
                    }

                    $stmt->execute($params);

                    $pedidoId = (int)$db->lastInsertId();

                    // Resolver producto a id
                    $productoId = null;
                    if (isset($row['producto_id']) && is_numeric($row['producto_id'])) {
                        $productoId = (int)$row['producto_id'];
                    } elseif (!empty($row['producto'])) {
                        $p = ProductoModel::buscarPorNombre($row['producto']);
                        if ($p && isset($p['id'])) {
                            $productoId = (int)$p['id'];
                        } else {
                            // Crear producto rápido si no existe
                            $productoId = ProductoModel::crearRapido($row['producto']);
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
            // Crear el formato POINT para ST_GeomFromText
            $coordenadas = "POINT(" . $data['longitud'] . " " . $data['latitud'] . ")";
            // Aceptar valores faltantes usando null coalescing
            $pais = $data['pais'] ?? ($data['id_pais'] ?? null);
            $departamento = $data['departamento'] ?? ($data['id_departamento'] ?? null);
            $municipio = $data['municipio'] ?? ($data['id_municipio'] ?? null);
            $barrio = $data['barrio'] ?? ($data['id_barrio'] ?? null);
            $zona = $data['zona'] ?? null;

            // Construir la consulta UPDATE dinámicamente según las columnas existentes
            $fieldsToUpdate = [];
            $params = [];

            // Campos siempre presentes (o que asumimos presentes en base mínima)
            $fieldsToUpdate[] = "numero_orden = :numero_orden";
            $params[':numero_orden'] = $data['numero_orden'] ?? null;

            $fieldsToUpdate[] = "destinatario = :destinatario";
            $params[':destinatario'] = $data['destinatario'] ?? null;

            $fieldsToUpdate[] = "telefono = :telefono";
            $params[':telefono'] = $data['telefono'] ?? null;

            // Coordenadas
            if (self::tableHasColumn($db, 'pedidos', 'coordenadas')) {
                $fieldsToUpdate[] = "coordenadas = ST_GeomFromText(:coordenadas)";
                $params[':coordenadas'] = $coordenadas;
            }

            // Campos opcionales que verificamos
            // id_pais
            if (self::tableHasColumn($db, 'pedidos', 'id_pais')) {
                $fieldsToUpdate[] = "id_pais = :id_pais";
                $resolvedPaisId = self::resolvePaisId($pais);
                $params[':id_pais'] = ($resolvedPaisId !== null) ? (int)$resolvedPaisId : null;
            }

            // id_departamento
            if (self::tableHasColumn($db, 'pedidos', 'id_departamento')) {
                $fieldsToUpdate[] = "id_departamento = :id_departamento";
                $resolvedDepartamentoId = self::resolveDepartamentoId($departamento, $pais);
                $params[':id_departamento'] = ($resolvedDepartamentoId !== null) ? (int)$resolvedDepartamentoId : null;
            }

            // id_municipio vs municipio
            if (self::tableHasColumn($db, 'pedidos', 'id_municipio')) {
                $fieldsToUpdate[] = "id_municipio = :id_municipio";
                $municipioId = ($municipio !== null && $municipio !== '' && is_numeric($municipio)) ? (int)$municipio : null;
                $params[':id_municipio'] = $municipioId;
            } elseif (self::tableHasColumn($db, 'pedidos', 'municipio')) {
                $fieldsToUpdate[] = "municipio = :municipio";
                $params[':municipio'] = $municipio; // String value
            }

            // id_barrio vs barrio
            if (self::tableHasColumn($db, 'pedidos', 'id_barrio')) {
                $fieldsToUpdate[] = "id_barrio = :id_barrio";
                $barrioId = ($barrio !== null && $barrio !== '' && is_numeric($barrio)) ? (int)$barrio : null;
                $params[':id_barrio'] = $barrioId;
            } elseif (self::tableHasColumn($db, 'pedidos', 'barrio')) {
                $fieldsToUpdate[] = "barrio = :barrio";
                $params[':barrio'] = $barrio; // String value
            }

            // zona
            if (self::tableHasColumn($db, 'pedidos', 'zona')) {
                $fieldsToUpdate[] = "zona = :zona";
                $params[':zona'] = $zona;
            }

            // direccion
            if (self::tableHasColumn($db, 'pedidos', 'direccion')) {
                $fieldsToUpdate[] = "direccion = :direccion";
                $params[':direccion'] = $data['direccion'] ?? null;
            }

            // comentario
            if (self::tableHasColumn($db, 'pedidos', 'comentario')) {
                $fieldsToUpdate[] = "comentario = :comentario";
                $params[':comentario'] = $data['comentario'] ?? null;
            }

            // precios
            if (self::tableHasColumn($db, 'pedidos', 'precio_local')) {
                $fieldsToUpdate[] = "precio_local = :precio_local";
                $val = $data['precio_local'] ?? null;
                $params[':precio_local'] = ($val === '' ? null : $val);
            }
            if (self::tableHasColumn($db, 'pedidos', 'precio_usd')) {
                $fieldsToUpdate[] = "precio_usd = :precio_usd";
                $val = $data['precio_usd'] ?? null;
                $params[':precio_usd'] = ($val === '' ? null : $val);
            }

            // Foreign keys
            if (self::tableHasColumn($db, 'pedidos', 'id_estado')) {
                $fieldsToUpdate[] = "id_estado = :estado";
                $params[':estado'] = isset($data['estado']) ? (int)$data['estado'] : null;
            }
            if (self::tableHasColumn($db, 'pedidos', 'id_vendedor')) {
                $fieldsToUpdate[] = "id_vendedor = :vendedor";
                $params[':vendedor'] = isset($data['vendedor']) ? (int)$data['vendedor'] : null;
            }
            if (self::tableHasColumn($db, 'pedidos', 'id_proveedor')) {
                $fieldsToUpdate[] = "id_proveedor = :proveedor";
                $params[':proveedor'] = isset($data['proveedor']) ? (int)$data['proveedor'] : null;
            }
            if (self::tableHasColumn($db, 'pedidos', 'id_moneda')) {
                $fieldsToUpdate[] = "id_moneda = :moneda";
                $params[':moneda'] = isset($data['moneda']) ? (int)$data['moneda'] : null;
            }

            $query = "UPDATE pedidos SET " . implode(', ', $fieldsToUpdate) . " WHERE id = :id_pedido";
            
            $stmt = $db->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':id_pedido', isset($data['id_pedido']) ? (int)$data['id_pedido'] : null, PDO::PARAM_INT);

            // Ejecutar la consulta
            $stmt->execute();

            // Actualizar productos: soportar múltiples productos
            // Primero, eliminar todos los productos existentes del pedido
            $deleteStmt = $db->prepare('DELETE FROM pedidos_productos WHERE id_pedido = :id_pedido');
            $deleteStmt->bindValue(':id_pedido', (int)$data['id_pedido'], PDO::PARAM_INT);
            $deleteStmt->execute();

            // Procesar múltiples productos desde el array productos[][]
            if (isset($data['productos']) && is_array($data['productos']) && count($data['productos']) > 0) {
                $insertStmt = $db->prepare('
                    INSERT INTO pedidos_productos (id_pedido, id_producto, cantidad, cantidad_devuelta)
                    VALUES (:id_pedido, :id_producto, :cantidad, 0)
                ');
                
                foreach ($data['productos'] as $item) {
                    $productoId = isset($item['producto_id']) ? (int)$item['producto_id'] : null;
                    $cantidad = isset($item['cantidad']) ? (int)$item['cantidad'] : null;
                    
                    // Solo insertar si ambos valores son válidos
                    if ($productoId && $cantidad && $cantidad > 0) {
                        $insertStmt->bindValue(':id_pedido', (int)$data['id_pedido'], PDO::PARAM_INT);
                        $insertStmt->bindValue(':id_producto', $productoId, PDO::PARAM_INT);
                        $insertStmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
                        $insertStmt->execute();
                    }
                }
            } 
            // Fallback: si no hay productos[][] pero hay producto_id singular (compatibilidad con formularios antiguos)
            elseif (isset($data['producto_id']) && isset($data['cantidad_producto'])) {
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
            }
            if (empty($pedido['proveedor']) || $pedido['proveedor'] === null) {
                $pedido['proveedor'] = $resolvedFallbackUser;
            }

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

            // Construir INSERT dinámico para compatibilidad con esquemas
            $columns = ['fecha_ingreso','numero_orden','destinatario','telefono'];
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

            // Bind dinámico con tipos donde es necesario
            foreach ($columns as $col) {
                switch ($col) {
                    case 'numero_orden':
                        $stmt->bindValue(':numero_orden', $pedido['numero_orden'], PDO::PARAM_STR);
                        break;
                    case 'destinatario':
                        $stmt->bindValue(':destinatario', $pedido['destinatario'], PDO::PARAM_STR);
                        break;
                    case 'telefono':
                        $stmt->bindValue(':telefono', $pedido['telefono'], PDO::PARAM_STR);
                        break;
                    case 'precio_local':
                        if ($pedido['precio_local'] === null) {
                            $stmt->bindValue(':precio_local', null, PDO::PARAM_NULL);
                        } else {
                            $stmt->bindValue(':precio_local', $pedido['precio_local']);
                        }
                        break;
                    case 'precio_usd':
                        if ($pedido['precio_usd'] === null) {
                            $stmt->bindValue(':precio_usd', null, PDO::PARAM_NULL);
                        } else {
                            $stmt->bindValue(':precio_usd', $pedido['precio_usd']);
                        }
                        break;
                    case 'id_pais':
                        $resolvedIdPais = self::resolvePaisId($pedido['pais'] ?? null);
                        if ($resolvedIdPais !== null) {
                            $stmt->bindValue(':id_pais', (int)$resolvedIdPais, PDO::PARAM_INT);
                        } else {
                            $stmt->bindValue(':id_pais', null, PDO::PARAM_NULL);
                        }
                        break;
                    case 'id_departamento':
                        $resolvedIdDep = self::resolveDepartamentoId($pedido['departamento'] ?? null, $pedido['pais'] ?? null);
                        if ($resolvedIdDep !== null) {
                            $stmt->bindValue(':id_departamento', (int)$resolvedIdDep, PDO::PARAM_INT);
                        } else {
                            $stmt->bindValue(':id_departamento', null, PDO::PARAM_NULL);
                        }
                        break;
                        break;
                    case 'municipio':
                        $stmt->bindValue(':municipio', $pedido['municipio'] ?? null, empty($pedido['municipio']) ? PDO::PARAM_NULL : PDO::PARAM_STR);
                        break;
                    case 'barrio':
                        $stmt->bindValue(':barrio', $pedido['barrio'] ?? null, empty($pedido['barrio']) ? PDO::PARAM_NULL : PDO::PARAM_STR);
                        break;
                    case 'direccion':
                        $stmt->bindValue(':direccion', $pedido['direccion'], PDO::PARAM_STR);
                        break;
                    case 'zona':
                        $stmt->bindValue(':zona', $pedido['zona'] ?? null, empty($pedido['zona']) ? PDO::PARAM_NULL : PDO::PARAM_STR);
                        break;
                    case 'comentario':
                        $stmt->bindValue(':comentario', $pedido['comentario'] ?? null, empty($pedido['comentario']) ? PDO::PARAM_NULL : PDO::PARAM_STR);
                        break;
                    case 'coordenadas':
                        $stmt->bindValue(':coordenadas', $coordenadas, PDO::PARAM_STR);
                        break;
                    case 'id_estado':
                        if (isset($pedido['estado']) && $pedido['estado'] !== null && $pedido['estado'] !== '') {
                            $stmt->bindValue(':id_estado', (int)$pedido['estado'], PDO::PARAM_INT);
                        } else {
                            $stmt->bindValue(':id_estado', null, PDO::PARAM_NULL);
                        }
                        break;
                    case 'id_moneda':
                        if (isset($pedido['moneda']) && $pedido['moneda'] !== null && $pedido['moneda'] !== '') {
                            $stmt->bindValue(':id_moneda', (int)$pedido['moneda'], PDO::PARAM_INT);
                        } else {
                            $stmt->bindValue(':id_moneda', null, PDO::PARAM_NULL);
                        }
                        break;
                    case 'id_vendedor':
                        if (isset($pedido['vendedor']) && $pedido['vendedor'] !== null && $pedido['vendedor'] !== '') {
                            $stmt->bindValue(':id_vendedor', (int)$pedido['vendedor'], PDO::PARAM_INT);
                        } else {
                            $stmt->bindValue(':id_vendedor', null, PDO::PARAM_NULL);
                        }
                        break;
                    case 'id_proveedor':
                        if (isset($pedido['proveedor']) && $pedido['proveedor'] !== null && $pedido['proveedor'] !== '') {
                            $stmt->bindValue(':id_proveedor', (int)$pedido['proveedor'], PDO::PARAM_INT);
                        } else {
                            $stmt->bindValue(':id_proveedor', null, PDO::PARAM_NULL);
                        }
                        break;
                }
            }

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
     */
    public static function obtenerVentasComparativa() {
        try {
            $db = (new Conexion())->conectar();
            if (!self::tableHasColumn($db, 'pedidos', 'precio_local')) return [];

            $idEntregado = self::obtenerIdEstadoPorNombre('Entregado');
            $condicionEstado = "";
            if ($idEntregado) {
                $condicionEstado = " AND id_estado = $idEntregado ";
            }

            // Mes Actual
            $queryActual = "
                SELECT DATE(fecha_ingreso) as fecha, SUM(precio_local) as total 
                FROM pedidos 
                WHERE MONTH(fecha_ingreso) = MONTH(CURRENT_DATE()) 
                  AND YEAR(fecha_ingreso) = YEAR(CURRENT_DATE()) 
                  $condicionEstado
                GROUP BY DATE(fecha_ingreso) ORDER BY fecha ASC";
            
            // Mes Anterior
            $queryAnterior = "
                SELECT DATE(fecha_ingreso) as fecha, SUM(precio_local) as total 
                FROM pedidos 
                WHERE MONTH(fecha_ingreso) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
                  AND YEAR(fecha_ingreso) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH) 
                  $condicionEstado
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
     */
    public static function obtenerKPIsMesActual() {
        try {
            $db = (new Conexion())->conectar();
            if (!self::tableHasColumn($db, 'pedidos', 'precio_local')) return null;

            $idEntregado = self::obtenerIdEstadoPorNombre('Entregado');
            $condicionEstado = "";
            if ($idEntregado) {
                $condicionEstado = " AND id_estado = $idEntregado ";
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
     */
    public static function obtenerTopProductosMesActual()
    {
        try {
            $db = (new Conexion())->conectar();
            
            $idEntregado = self::obtenerIdEstadoPorNombre('Entregado');
            $condicionEstado = "";
            if ($idEntregado) {
                $condicionEstado = " AND ped.id_estado = $idEntregado ";
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

