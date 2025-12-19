<?php

// Atención: la configuración de visualización de errores fue removida del
// archivo. Controla la salida de logs con la constante DEBUG en
// `config/config.php` o mediante la configuración de PHP en el entorno.

ob_start();
require_once __DIR__ . '/../modelo/producto.php';
require_once __DIR__ . '/../modelo/usuario.php';
require_once __DIR__ . '/../modelo/moneda.php';
require_once __DIR__ . '/../modelo/municipio.php';
require_once __DIR__ . '/../modelo/barrio.php';
require_once __DIR__ . '/../modelo/pedido.php';

class PedidosController {


    /* ZONA API */

    /**
     * Crear un pedido desde la API.
     *
     * Entrada: $jsonData - arreglo/objeto con la estructura del pedido tal como
     * viene del cliente (numero_orden, destinatario, telefono, producto, cantidad,
     * coordenadas/datos de dirección, etc.).
     *
     * Salida: arreglo con la forma estándar: ['success' => bool, 'message' => string, 'data' => mixed]
     * - success: true si se creó el pedido.
     * - data: numero_orden del pedido en caso de éxito.
     *
     * Errores: devuelve ['success'=>false,'message'=>...] con detalles de validación
     * o errores de creación. El método atrapa excepciones y las convierte en mensaje
     * legible para la API.
     *
     * Notas: valida existencia de producto y crea uno rápido si hace falta. Calcula
     * precio USD cuando se proporciona moneda con tasa.
     *
     * @param array|object $jsonData
     * @return array
     */
    public function crearPedidoAPI($jsonData) {
        $data = $jsonData;

        // Si el usuario es Proveedor, forzar su ID como proveedor del pedido
        require_once __DIR__ . '/../utils/permissions.php';
        if (isProveedor()) {
            $data['id_proveedor'] = $_SESSION['user_id'];
            $data['proveedor'] = $_SESSION['user_id'];
        }

        // Validar la estructura del pedido
        $validacion = $this->validarDatosPedido($data);
        if (!$validacion["success"]) {
            // ...
            throw new Exception($msg, 400);
        }

        // Verificar si el número de orden ya existe
        if (PedidosModel::existeNumeroOrden($data["numero_orden"])) {
             throw new Exception("El número de orden ya existe en la base de datos.", 400);
        }

            // Support multiple products: either provide single 'producto' + 'cantidad'
            // or an array 'productos' with items { producto_id, cantidad }.
            $items = [];
            if (!empty($data['productos']) && is_array($data['productos'])) {
                foreach ($data['productos'] as $pi) {
                    if (empty($pi['producto_id']) || !is_numeric($pi['producto_id'])) {
                        return [
                            "success" => false,
                            "message" => "Cada item en 'productos' debe incluir 'producto_id' numérico."
                        ];
                    }
                    $prodId = (int)$pi['producto_id'];
                    $cantidadItem = isset($pi['cantidad']) ? (int)$pi['cantidad'] : 0;
                    if ($cantidadItem <= 0) {
                        return [
                            "success" => false,
                            "message" => "Cada item en 'productos' debe incluir 'cantidad' mayor a cero."
                        ];
                    }

                    $items[] = [
                        'id_producto' => $prodId,
                        'cantidad' => $cantidadItem,
                        'cantidad_devuelta' => isset($pi['cantidad_devuelta']) ? (int)$pi['cantidad_devuelta'] : 0,
                    ];
                }
            } else {
                // Single-product payload must provide producto_id
                if (empty($data['producto_id']) || !is_numeric($data['producto_id'])) {
                    return [
                        "success" => false,
                        "message" => "El campo 'producto_id' es obligatorio y debe ser numérico cuando no se usa 'productos'."
                    ];
                }
                $productoId = (int)$data['producto_id'];
                $items = [[
                    'id_producto' => $productoId,
                    'cantidad' => isset($data['cantidad']) ? (int)$data['cantidad'] : 1,
                    'cantidad_devuelta' => isset($data['cantidad_devuelta']) ? (int)$data['cantidad_devuelta'] : 0,
                ]];
            }

            $latitud = $data['latitud'] ?? null;
            $longitud = $data['longitud'] ?? null;
            if ($latitud === null || $longitud === null) {
                if (!empty($data["coordenadas"]) && strpos($data["coordenadas"], ',') !== false) {
                    $parts = array_map('trim', explode(',', $data['coordenadas']));
                    $latitud = $parts[0];
                    $longitud = $parts[1];
                }
            }

            if (!is_numeric($latitud) || !is_numeric($longitud)) {
                throw new Exception("Coordenadas inválidas para el pedido.", 400);
            }

            $precioLocal = null;
            if (isset($data['precio_local']) && $data['precio_local'] !== '') {
                $precioLocal = (float)$data['precio_local'];
            } elseif (isset($data['precio']) && $data['precio'] !== '') {
                $precioLocal = (float)$data['precio'];
            }

            $monedaId = isset($data['id_moneda']) ? (int)$data['id_moneda'] : null;
            if ($monedaId === 0) $monedaId = null;

            if ($monedaId !== null) {
                $m = MonedaModel::obtenerPorId($monedaId);
                if (!$m) {
                    throw new Exception("La moneda especificada no existe.", 400);
                }
            }

            $vendedorId = isset($data['id_vendedor']) && is_numeric($data['id_vendedor']) ? (int)$data['id_vendedor'] : null;
            if ($vendedorId !== null) {
                $uv = (new UsuarioModel())->obtenerPorId($vendedorId);
                if (!$uv) {
                    throw new Exception("El vendedor especificado no existe.", 400);
                }
            }

            $proveedorId = isset($data['id_proveedor']) && is_numeric($data['id_proveedor']) ? (int)$data['id_proveedor'] : null;
            if ($proveedorId !== null) {
                $up = (new UsuarioModel())->obtenerPorId($proveedorId);
                if (!$up) {
                    throw new Exception("El proveedor especificado no existe.", 400);
                }
            }

            $municipioId = isset($data['id_municipio']) && is_numeric($data['id_municipio']) ? (int)$data['id_municipio'] : null;
            if ($municipioId !== null) {
                $mm = MunicipioModel::obtenerPorId($municipioId);
                if (!$mm) {
                    throw new Exception("El municipio especificado no existe.", 400);
                }
            }

            $barrioId = isset($data['id_barrio']) && is_numeric($data['id_barrio']) ? (int)$data['id_barrio'] : null;
            if ($barrioId !== null) {
                $bb = BarrioModel::obtenerPorId($barrioId);
                if (!$bb) {
                    throw new Exception("El barrio especificado no existe.", 400);
                }
            }

            $precioUsd = null;
            if ($precioLocal !== null && $monedaId !== null) {
                $moneda = PedidosModel::obtenerMonedaPorId($monedaId);
                if ($moneda && isset($moneda['tasa_usd'])) {
                    $precioUsd = round($precioLocal * (float)$moneda['tasa_usd'], 2);
                }
            }

            $pedidoPayload = [
                'numero_orden' => $data['numero_orden'],
                'destinatario' => $data['destinatario'],
                'telefono' => $data['telefono'],
                'direccion' => $data['direccion'] ?? '',
                'comentario' => $data['comentario'] ?? null,
                'estado' => isset($data['id_estado']) ? (int)$data['id_estado'] : 1,
                'vendedor' => $vendedorId ?? null,
                'proveedor' => $proveedorId ?? null,
                'moneda' => $monedaId ?? 0,
                'latitud' => (float)$latitud,
                'longitud' => (float)$longitud,
                'id_pais' => isset($data['id_pais']) ? $data['id_pais'] : ($data['pais'] ?? null),
                'id_departamento' => isset($data['id_departamento']) ? $data['id_departamento'] : ($data['departamento'] ?? null),
                'municipio' => $municipioId ?? ($data['municipio'] ?? null),
                'barrio' => $barrioId ?? ($data['barrio'] ?? null),
                'zona' => $data['zona'] ?? null,
                'precio_local' => $precioLocal,
                'precio_usd' => $precioUsd,
            ];

            if ($pedidoPayload['vendedor'] === 0) {
                $pedidoPayload['vendedor'] = null;
            }
            if ($pedidoPayload['proveedor'] === 0) {
                $pedidoPayload['proveedor'] = null;
            }
            if ($pedidoPayload['moneda'] === 0) {
                $pedidoPayload['moneda'] = null;
            }

            $nuevoId = PedidosModel::crearPedidoConProductos($pedidoPayload, $items);
            return [
                "success" => true,
                "message" => "Pedido creado correctamente.",
                "data" => $pedidoPayload['numero_orden']
            ];

    }

    /**
     * API endpoint: Crear múltiples pedidos desde JSON.
     * Lee php://input, decodifica JSON y espera la clave 'pedidos' como array.
    * Para cada pedido inserta una fila en `pedidos` y sus productos en `pedidos_productos`.
     * Continúa en errores por pedido y devuelve un resumen por pedido.
     *
     * Uso: POST /api/pedidos/multiple (o la ruta que corresponda) con body JSON.
     */
    public function createMultiple()
    {
        // Read and decode JSON payload
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        header('Content-Type: application/json');

        if (!is_array($payload) || !isset($payload['pedidos']) || !is_array($payload['pedidos'])) {
            http_response_code(400);
            echo json_encode(['error' => "JSON inválido o falta la clave 'pedidos' (esperado array)."], JSON_UNESCAPED_UNICODE);
            return;
        }

        $results = [];

        // Ensure model is available
        require_once __DIR__ . '/../modelo/pedido.php';

        foreach ($payload['pedidos'] as $pedido) {
            $itemResult = ['numero_orden' => $pedido['numero_orden'] ?? null, 'success' => false];

            // Basic validation using existing helper
            $valid = $this->validarDatosPedido($pedido);
            if (!$valid['success']) {
                $itemResult['error'] = is_string($valid['message']) ? $valid['message'] : json_encode($valid['data']);
                $results[] = $itemResult;
                continue;
            }

            // Build payload expected by the model
            $modelPayload = [
                'numero_orden' => isset($pedido['numero_orden']) ? (int)$pedido['numero_orden'] : null,
                'destinatario' => $pedido['destinatario'] ?? null,
                'telefono' => $pedido['telefono'] ?? null,
                'direccion' => $pedido['direccion'] ?? null,
                'comentario' => $pedido['comentario'] ?? null,
                'estado' => isset($pedido['estado']) ? (int)$pedido['estado'] : null,
                'vendedor' => isset($pedido['vendedor']) ? (int)$pedido['vendedor'] : null,
                'proveedor' => isset($pedido['proveedor']) ? (int)$pedido['proveedor'] : (isset($pedido['id_proveedor']) ? (int)$pedido['id_proveedor'] : null),
                'moneda' => isset($pedido['moneda']) ? (int)$pedido['moneda'] : null,
                'latitud' => isset($pedido['latitud']) ? (float)$pedido['latitud'] : (isset($pedido['latitude']) ? (float)$pedido['latitude'] : null),
                'longitud' => isset($pedido['longitud']) ? (float)$pedido['longitud'] : (isset($pedido['longitude']) ? (float)$pedido['longitude'] : null),
                'id_pais' => isset($pedido['id_pais']) ? $pedido['id_pais'] : ($pedido['pais'] ?? null),
                'id_departamento' => isset($pedido['id_departamento']) ? $pedido['id_departamento'] : ($pedido['departamento'] ?? null),
                'municipio' => $pedido['municipio'] ?? null,
                'barrio' => $pedido['barrio'] ?? null,
                'zona' => $pedido['zona'] ?? null,
                'precio_local' => isset($pedido['precio_local']) ? $pedido['precio_local'] : ($pedido['precio'] ?? null),
                'precio_usd' => $pedido['precio_usd'] ?? null,
            ];

            // Build items array.
            $items = [];
            if (isset($pedido['productos']) && is_array($pedido['productos']) && count($pedido['productos']) > 0) {
                foreach ($pedido['productos'] as $pi) {
                    // Accept different key names
                    $pid = $pi['producto_id'] ?? $pi['id_producto'] ?? $pi['id'] ?? null;
                    $qty = $pi['cantidad'] ?? $pi['qty'] ?? $pi['cantidad_producto'] ?? null;
                    if (!is_numeric($pid) || !is_numeric($qty) || (int)$qty <= 0) continue;
                    $items[] = ['id_producto' => (int)$pid, 'cantidad' => (int)$qty, 'cantidad_devuelta' => 0];
                }
            } else {
                // fallback to single product fields
                $pid = $pedido['producto_id'] ?? $pedido['id_producto'] ?? null;
                $qty = $pedido['cantidad'] ?? $pedido['cantidad_producto'] ?? null;
                if (is_numeric($pid) && is_numeric($qty) && (int)$qty > 0) {
                    $items[] = ['id_producto' => (int)$pid, 'cantidad' => (int)$qty, 'cantidad_devuelta' => 0];
                }
            }

            if (count($items) === 0) {
                $itemResult['error'] = 'El pedido debe incluir al menos un producto válido.';
                $results[] = $itemResult;
                continue;
            }

            try {
                // Delegate to model which sets fecha_ingreso and handles pivot inserts/transactions
                $nuevoId = PedidosModel::crearPedidoConProductos($modelPayload, $items);
                $itemResult['success'] = true;
                $itemResult['id_pedido'] = $nuevoId;
                $results[] = $itemResult;
            } catch (Exception $e) {
                $itemResult['error'] = 'Error al insertar pedido: ' . $e->getMessage();
                $results[] = $itemResult;
                continue;
            }
        }

        echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
        return;
    }

    /**
     * Buscar un pedido por su número de orden.
     * Devuelve un arreglo con la estructura: { success, message, data }
     * donde data es el resultado de PedidosModel::obtenerPedidoPorNumero o null.
     */
    /**
     * Buscar un pedido por su número de orden.
     *
     * @param int|string $numeroOrden Número de orden a buscar (se acepta string o int).
     * @return array Envelope: ['success' => bool, 'message' => string, 'data' => array|null]
     */
    public function buscarPedidoPorNumero($numeroOrden)
    {
        try {
            $model = new PedidosModel();
            $res = $model->obtenerPedidoPorNumero($numeroOrden);
            if ($res) {
                return [
                    'success' => true,
                    'message' => 'Pedido encontrado',
                    'data' => $res
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Pedido no encontrado',
                    'data' => null
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al buscar pedido: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    

    /**
     * Validar la estructura mínima de un pedido recibido vía API o formulario.
     *
     * Comprueba la presencia de campos obligatorios y el formato de coordenadas.
     * Devuelve ['success'=>true] cuando todo está bien o
     * ['success'=>false,'message'=>..., 'data'=>[errores]] cuando hay problemas.
     *
     * @param array $data Datos del pedido
     * @return array
     */
    private function validarDatosPedido($data) {
        $errores = [];

        // Minimal required fields for provider API: número de orden and destinatario.
        // Productos must be provided by id (array 'productos' or top-level 'producto_id').
        $camposObligatorios = ["numero_orden", "destinatario"];
        foreach ($camposObligatorios as $campo) {
            if (!isset($data[$campo]) || $data[$campo] === '') {
                $errores[] = "El campo '$campo' es obligatorio.";
            }
        }

        // If no products array is provided, the top-level cantidad is not required here
        // because products may be sent as an array. Quantity is validated per-item below.

        // Producto: require producto_id (either top-level or inside 'productos'). No longer accept product names.
        $hasProductsArray = isset($data['productos']) && is_array($data['productos']) && count($data['productos']) > 0;
        $hasSingleProductId = isset($data['producto_id']) && is_numeric($data['producto_id']);
        if (!$hasSingleProductId && !$hasProductsArray) {
            $errores[] = "El campo 'producto_id' o 'productos' es obligatorio.";
        }

        // Si se envía 'productos' validar su estructura mínima: cada item debe incluir producto_id numérico y cantidad>0
        if ($hasProductsArray) {
            foreach ($data['productos'] as $i => $pi) {
                if (!isset($pi['producto_id']) || !is_numeric($pi['producto_id'])) $errores[] = "El elemento productos[$i] debe incluir 'producto_id' numérico.";
                if (!isset($pi['cantidad']) || !is_numeric($pi['cantidad']) || (int)$pi['cantidad'] <= 0) $errores[] = "El elemento productos[$i] debe incluir 'cantidad' mayor a cero.";
            }
        }


        // Validar formato de las coordenadas si se proporcionan
        if (isset($data["coordenadas"]) && $data["coordenadas"] !== '') {
            $coords = explode(',', $data["coordenadas"]);
            if (count($coords) !== 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
                $errores[] = "El campo 'coordenadas' debe estar en el formato 'latitud,longitud'.";
            }
        }

        // Devolver errores si los hay
        if (!empty($errores)) {
            return ["success" => false, "message" => "Tus datos tienen errores de procedencia arreglalos.", "data" => $errores];
        }

        return ["success" => true];
    }



    /* ZONA DEL FRONT END */

    /**
     * Obtener listado de pedidos con información extendida para la vista.
     *
     * Retorna un array listo para renderizar en el frontend.
     * Si el usuario es Proveedor, solo retorna sus propios pedidos.
     * @return array
     */
    public function listarPedidosExtendidos() {
        require_once __DIR__ . '/../utils/permissions.php';
        
        // Llamar al modelo para obtener los pedidos
        $pedidos = PedidosModel::obtenerPedidosExtendidos();
        
        // Si es admin, mostrar todos los pedidos (tiene prioridad sobre proveedor)
        if (isSuperAdmin()) {
            return $pedidos;
        }
        
        // Si es proveedor (y NO es admin), filtrar solo sus pedidos
        if (isProveedor()) {
            $userId = (int)$_SESSION['user_id'];
            $pedidosFiltrados = [];
            
            foreach ($pedidos as $pedido) {
                $pedidoProveedor = isset($pedido['id_proveedor']) ? (int)$pedido['id_proveedor'] : null;
                if ($pedidoProveedor === $userId) {
                    $pedidosFiltrados[] = $pedido;
                }
            }
            
            return $pedidosFiltrados;
        }
        
        // Otros roles (vendedor, repartidor) no tienen acceso a lista de pedidos
        return [];
    }


    /**
     * Obtener un pedido por su id (uso en vistas y controladores internos).
     *
     * @param int $id_pedido
     * @return array|null
     */
    public function obtenerPedido($id_pedido) {
        if (!$id_pedido) {
            echo "<div class='alert alert-danger'>No order ID provided.</div>";
            exit;
        }

        return PedidosModel::obtenerPedidoPorId($id_pedido);
    }


    /**
     * Actualizar un pedido con datos proporcionados.
     *
     * @param array $data Campos a actualizar (debe incluir id y campos editables).
     * @return array Envelope con success/message
     */
    public function actualizarPedido($data) {
        $resultado = PedidosModel::actualizarPedido($data);
        if ($resultado) {
            return [
                "success" => true,
                "message" => "Pedido actualizado correctamente."
            ];
        } else {
            return [
                "success" => false,
                "message" => "No se realizaron cambios en el pedido."
            ];
        }
    }
    
    /**
     * Obtener lista de estados posibles de pedidos.
     * @return array
     */
    public function obtenerEstados() {
        return PedidosModel::obtenerEstados();
    }
    
    /**
     * Obtener la lista de vendedores/repartidores disponibles.
     * @return array
     */
    public function obtenerVendedores() {
        // "Usuario asignado" corresponde a Repartidor
        return PedidosModel::obtenerVendedores();
    }

    // Exponer explícitamente repartidores para mayor claridad en vistas
    /**
     * Alias explícito para obtener repartidores (útil en vistas).
     * @return array
     */
    public function obtenerRepartidores() {
        return PedidosModel::obtenerRepartidores();
    }

    /**
     * Obtener productos disponibles.
     * @return array
     */
    public function obtenerProductos() {
        return PedidosModel::obtenerProductos();
    }

    /**
     * Obtener proveedores registrados.
     * @return array
     */
    public function obtenerProveedores() {
        return PedidosModel::obtenerProveedores();
    }

    /**
     * Obtener monedas existentes (incluye tasa_usd si aplica).
     * @return array
     */
    public function obtenerMonedas() {
        return PedidosModel::obtenerMonedas();
    }

    // Listado de pedidos asignados al usuario (seguimiento para repartidor)
    /**
     * Listar pedidos asignados a un usuario (repartidor).
     * @param int $userId
     * @return array
     */
    public function listarPedidosAsignados($userId)
    {
        if (!$userId || !is_numeric($userId)) return [];
        // Nota: por ahora usamos id_vendedor como asignación. Si se agrega id_repartidor,
        // actualizar a ese campo.
        return PedidosModel::listarPorUsuarioAsignado((int)$userId);
    }

    /**
     * Guardar pedido proveniente desde el formulario del frontend.
     *
     * Realiza validaciones server-side, logging en DEBUG y llama al modelo para
     * persistir. Devuelve envelope con success/message o hace redirect según contexto.
     *
     * @param array $data
     * @return array|void
     */
    public function guardarPedidoFormulario(array $data) {
        // CSRF Protection
        require_once __DIR__ . '/../utils/csrf.php';
        require_csrf_token($data['csrf_token'] ?? null);
        
        $errores = [];

        // Logging condicional (solo en modo DEBUG). Guardamos información
        // sanitizada para depuración local. DEBUG debe configurarse en
        // `config/config.php` y mantenerse en false en producción.
        if (defined('DEBUG') && DEBUG) {
            try {
                $logDir = __DIR__ . '/../logs';
                if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

                // Sanitizar datos sensibles antes de escribir en disco
                $sanitized = $data;
                if (isset($sanitized['telefono'])) {
                    $tel = preg_replace('/\D+/', '', (string)$sanitized['telefono']);
                    $len = strlen($tel);
                    if ($len > 4) {
                        $sanitized['telefono'] = substr($tel, 0, 4) . str_repeat('*', $len - 4);
                    } else {
                        $sanitized['telefono'] = str_repeat('*', $len);
                    }
                }
                if (isset($sanitized['direccion'])) $sanitized['direccion'] = '[SANITIZED]';
                if (isset($sanitized['comentario'])) $sanitized['comentario'] = '[SANITIZED]';

                $dbg = '[' . date('c') . '] guardarPedidoFormulario DEBUG: ' . php_sapi_name() . "\n";
                $dbg .= json_encode($sanitized, JSON_UNESCAPED_UNICODE) . "\n";
                file_put_contents($logDir . '/pedido_debug.log', $dbg . "\n", FILE_APPEND | LOCK_EX);
            } catch (Exception $e) {
                // no interrumpir la ejecución por errores de logging
            }
        }

        $numeroOrden = trim($data['numero_orden'] ?? '');
        $destinatario = trim($data['destinatario'] ?? '');
        $telefono = preg_replace('/\s+/', '', (string)($data['telefono'] ?? ''));

        // Helper: parsear enteros positivos enviados como string o int.
        $parse_positive_int = function($arr, $key) {
            if (!isset($arr[$key])) return null;
            $v = $arr[$key];
            if ($v === null || $v === '') return null;
            // Allow numeric strings like "3"
            if (is_numeric($v) && (int)$v >= 1) return (int)$v;
            return false;
        };

        $productoId = $parse_positive_int($data, 'producto_id');
        $cantidadProducto = $parse_positive_int($data, 'cantidad_producto');
        $estado = $parse_positive_int($data, 'estado');
        $vendedor = $parse_positive_int($data, 'vendedor');
        $proveedor = $parse_positive_int($data, 'proveedor');
        
        // Si el usuario es Proveedor, forzar su ID como proveedor del pedido
        require_once __DIR__ . '/../utils/permissions.php';
        if (isProveedor()) {
            $proveedor = $_SESSION['user_id'];
        }
        
        $moneda = $parse_positive_int($data, 'moneda');

        $comentario = trim($data['comentario'] ?? '');
        $direccion = trim($data['direccion'] ?? '');

        // Coordenadas (opcionales ahora)
        $latitud = isset($data['latitud']) && $data['latitud'] !== '' ? (float)$data['latitud'] : null;
        $longitud = isset($data['longitud']) && $data['longitud'] !== '' ? (float)$data['longitud'] : null;

        // Geo IDs (opcionales)
        $idPais = $parse_positive_int($data, 'id_pais');
        $idDepartamento = $parse_positive_int($data, 'id_departamento');
        $idMunicipio = $parse_positive_int($data, 'id_municipio');
        $idBarrio = $parse_positive_int($data, 'id_barrio');

        $precioLocal = null;
        $precioUsdEntrada = null;

        // Precios (opcionales)
        if (isset($data['precio_local']) && $data['precio_local'] !== '') {
            $precioLocal = (float)str_replace(',', '.', (string)$data['precio_local']);
        }
        if (isset($data['precio_usd']) && $data['precio_usd'] !== '') {
            $precioUsdEntrada = (float)str_replace(',', '.', (string)$data['precio_usd']);
        }

        // Validaciones de campos REQUERIDOS
        if ($numeroOrden === '') {
            $errores[] = 'El número de orden es obligatorio.';
        } else {
            // Asegurar que sea un entero positivo (la columna en BD espera un entero)
            if (!preg_match('/^\d+$/', (string)$numeroOrden) || (int)$numeroOrden < 1) {
                $errores[] = 'El número de orden debe ser un entero positivo.';
            }
        }
        // Destinatario y teléfono ahora son opcionales
        // if ($destinatario === '') {
        //     $errores[] = 'El destinatario es obligatorio.';
        // }
        // if ($telefono === '' || !preg_match('/^[0-9]{8,15}$/', $telefono)) {
        //     $errores[] = 'El teléfono debe contener entre 8 y 15 dígitos.';
        // }
        if ($productoId === null || $productoId === false) {
            $errores[] = 'Selecciona un producto válido.';
        }
        if ($cantidadProducto === null || $cantidadProducto === false) {
            $errores[] = 'La cantidad debe ser un número entero mayor a cero.';
        }
        // Estado y vendedor ahora son opcionales
        // if ($estado === null || $estado === false) {
        //     $errores[] = 'Selecciona un estado válido.';
        // }
        // if ($vendedor === null || $vendedor === false) {
        //     $errores[] = 'Selecciona un usuario asignado válido.';
        // }
        if ($proveedor === null || $proveedor === false) {
            $errores[] = 'Selecciona un proveedor válido.';
        }
        if ($moneda === null || $moneda === false) {
            $errores[] = 'Selecciona una moneda válida.';
        }
        // Dirección y coordenadas ahora son opcionales
        // if ($direccion === '') {
        //     $errores[] = 'La dirección es obligatoria.';
        // }
        // if ($latitud === false || $longitud === false) {
        //     $errores[] = 'Las coordenadas no tienen un formato válido.';
        // }

        if (empty($errores) && PedidosModel::existeNumeroOrden((int)$numeroOrden)) {
            $errores[] = 'El número de orden ya existe en la base de datos.';
        }

        // Validación server-side de stock: si el producto tiene stock registrado, no permitir cantidad mayor al stock
        if (is_int($productoId) && is_int($cantidadProducto)) {
            try {
                $stockDisponible = ProductoModel::obtenerStockTotal((int)$productoId);
                if ($stockDisponible !== null && $stockDisponible >= 0) {
                    if ($stockDisponible > 0 && (int)$cantidadProducto > $stockDisponible) {
                        $errores[] = 'La cantidad solicitada supera el stock disponible (' . $stockDisponible . ').';
                    }
                }
            } catch (Exception $e) {
                // no interrumpir el flujo, pero loguear
                error_log('Error comprobando stock del producto: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            }
        }

        $monedaSeleccionada = null;
        if (is_int($moneda)) {
            $monedaSeleccionada = PedidosModel::obtenerMonedaPorId($moneda);
            if (!$monedaSeleccionada) {
                $errores[] = 'No fue posible encontrar la moneda seleccionada.';
            }
        }

        if (!empty($errores)) {
            return [
                'success' => false,
                'message' => implode(' ', $errores)
            ];
        }

        $precioUsd = null;
        if ($monedaSeleccionada) {
            $tasa = (float)($monedaSeleccionada['tasa_usd'] ?? 0.0);
            if (($precioLocal !== null || $precioUsdEntrada !== null) && $tasa <= 0) {
                $errores[] = 'La tasa de cambio para la moneda seleccionada no es válida.';
            } else {
                if ($precioLocal !== null && $tasa > 0) {
                    $precioUsd = round($precioLocal * $tasa, 2);
                } elseif ($precioUsdEntrada !== null && $tasa > 0) {
                    $precioUsd = round($precioUsdEntrada, 2);
                    $precioLocal = round($precioUsdEntrada / $tasa, 2);
                }
            }
        } elseif ($precioUsdEntrada !== null) {
            $precioUsd = round($precioUsdEntrada, 2);
        }

        if (!empty($errores)) {
            return [
                'success' => false,
                'message' => implode(' ', $errores)
            ];
        }

        $payload = [
            'numero_orden' => (int)$numeroOrden,
            'destinatario' => $destinatario,
            'telefono' => $telefono,
            'direccion' => $direccion,
            'comentario' => $comentario !== '' ? $comentario : null,
            'estado' => is_int($estado) ? (int)$estado : null,
            'vendedor' => is_int($vendedor) ? (int)$vendedor : null,
            'proveedor' => is_int($proveedor) ? (int)$proveedor : null,
            'moneda' => is_int($moneda) ? (int)$moneda : null,
            'latitud' => (float)$latitud,
            'longitud' => (float)$longitud,
            'id_pais' => isset($data['id_pais']) ? $data['id_pais'] : ($data['pais'] ?? null),
            'id_departamento' => isset($data['id_departamento']) ? $data['id_departamento'] : ($data['departamento'] ?? null),
            'municipio' => $data['municipio'] ?? null,
            'barrio' => $data['barrio'] ?? null,
            'zona' => $data['zona'] ?? null,
            'precio_local' => $precioLocal,
            'precio_usd' => $precioUsd,
        ];

        // Support multiple products from the form: productos is an array of { producto_id, cantidad }
        $items = [];
        if (isset($data['productos']) && is_array($data['productos']) && count($data['productos']) > 0) {
            foreach ($data['productos'] as $i => $it) {
                $pid = $it['producto_id'] ?? null;
                $qty = $it['cantidad'] ?? null;
                if (!is_numeric($pid) || !is_numeric($qty) || (int)$qty <= 0) continue;
                $items[] = [
                    'id_producto' => (int)$pid,
                    'cantidad' => (int)$qty,
                    'cantidad_devuelta' => 0,
                ];
            }

            // If productos[] was provided but no valid items were parsed, return a clear validation
            // error instead of letting the model throw a generic exception later.
            if (count($items) === 0) {
                return [
                    'success' => false,
                    'message' => 'El pedido debe incluir al menos un producto válido en la lista.'
                ];
            }
        } else {
            // Fallback to single-product fields
            $items = [[
                'id_producto' => (int)$productoId,
                'cantidad' => (int)$cantidadProducto,
                'cantidad_devuelta' => 0,
            ]];
        }

        try {
            $nuevoId = PedidosModel::crearPedidoConProductos($payload, $items);
            return [
                'success' => true,
                'message' => 'Pedido guardado correctamente.',
                'id' => $nuevoId
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar el pedido: ' . $e->getMessage()
            ];
        }
    }


    /**
     * Guardar edición de un pedido (uso desde formularios/AJAX).
     *
     * Si la petición es AJAX devuelve JSON. Si no, redirige a la lista con flash.
     * @param array $data
     * @return void
     */
    public function guardarEdicion($data) {
        // CSRF Protection
        require_once __DIR__ . '/../utils/csrf.php';
        require_csrf_token($data['csrf_token'] ?? null);
        
        // Soporte para peticiones AJAX: si el header X-Requested-With == XMLHttpRequest
        // o el cliente solicita JSON por Accept, devolvemos JSON en lugar de hacer
        // redirect + set_flash. Esto facilita que el frontend maneje la respuesta
        // y muestre SweetAlert
        // DEBUG: Log controller entry

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                 || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        // Helper to persist the submitted edit data in session for non-AJAX redirects
        $persistOldEdit = function($d) {
            try {
                require_once __DIR__ . '/../utils/session.php'; start_secure_session();
                $id = isset($d['id_pedido']) ? (int)$d['id_pedido'] : 'new';
                $_SESSION['old_pedido_edit_' . $id] = $d;
            } catch (Exception $e) {
                // ignore session persistence errors
            }
        };

        // Start output buffering to catch any spurious output (warnings, notices)
        ob_start();

        // Helper to send JSON response cleanly
        $sendJson = function($data, $code = 200) {
            // Discard any previous output
            $buffered = ob_get_clean();
            if (!empty($buffered) && defined('DEBUG') && DEBUG) {
                // Log the spurious output for debugging
                error_log("Spurious output in guardarEdicion: " . $buffered);
            }
            header('Content-Type: application/json', true, $code);
            echo json_encode($data);
            exit;
        };

        try {
            // Validaciones mínimas
            if (!isset($data['id_pedido']) || !is_numeric($data['id_pedido'])) {
                $resp = ['success' => false, 'message' => 'ID de pedido inválido.'];
                if ($isAjax) { $sendJson($resp); }
                // persist submitted data for repopulation when redirecting back
                $persistOldEdit($data);
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/listar'); exit;
            }

            if (!is_numeric($data['latitud']) || !is_numeric($data['longitud'])) {
                $resp = ['success' => false, 'message' => 'Las coordenadas no tienen un formato válido.'];
                if ($isAjax) { $sendJson($resp); }
                $persistOldEdit($data);
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorLatLong'); exit;
            }

            // Validación básica para cantidad y precio (si vienen)
            if (isset($data['cantidad_producto']) && $data['cantidad_producto'] !== '' && !is_numeric($data['cantidad_producto'])) {
                $resp = ['success' => false, 'message' => 'La cantidad debe ser un número.'];
                if ($isAjax) { $sendJson($resp); }
                $persistOldEdit($data);
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorCantidad'); exit;
            }
            if (isset($data['precio_local']) && $data['precio_local'] !== '' && !is_numeric($data['precio_local'])) {
                $resp = ['success' => false, 'message' => 'El precio local debe ser un valor numérico.'];
                if ($isAjax) { $sendJson($resp); }
                $persistOldEdit($data);
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorPrecio'); exit;
            }

            // Llama al modelo para actualizar el pedido
            $resultado = PedidosModel::actualizarPedido($data);

            if ($resultado) {
                $resp = ['success' => true, 'message' => 'Pedido actualizado correctamente.'];
                if ($isAjax) { $sendJson($resp); }
                require_once __DIR__ . '/../utils/session.php'; set_flash('success', $resp['message']); header('Location: '. RUTA_URL . 'pedidos/editar/' . $data['id_pedido']); exit;
            } else {
                $resp = ['success' => false, 'message' => 'No se realizaron cambios en el pedido.'];
                if ($isAjax) { $sendJson($resp); }
                // persist submitted edit payload so the edit page can repopulate fields
                $persistOldEdit($data);
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/error'); exit;
            }
        } catch (Exception $e) {
            $msg = 'Error interno: ' . $e->getMessage();
            if ($isAjax) { $sendJson(['success' => false, 'message' => $msg], 500); }
            // persist submitted edit payload before redirecting back
            $persistOldEdit($data);
            header('Location: ' . RUTA_URL . 'pedidos/editar/' . ($data['id_pedido'] ?? '') . '/error' . urlencode($e->getMessage()));
        }
        exit;
    }
    
    /* cambiar estados en los datatable */
    /**
     * Actualizar el estado de un pedido vía AJAX.
     *
     * Seguridad: sólo usuarios autenticados con permisos pueden modificar.
     * Responde siempre JSON con ['success'=>bool,'message'=>string].
     *
     * @param array $datos Contiene id_pedido y estado
     * @return void (imprime JSON y hace exit)
     */
    public static function actualizarEstadoAjax($datos) {
        // Seguridad: sólo usuarios autenticados pueden cambiar estados
        require_once __DIR__ . '/../utils/session.php';
        start_secure_session();

        // Detectar si la petición viene por AJAX (fetch/XHR)
        $isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        // Si no hay sesión activa, devolver JSON 401 para AJAX en lugar de redirigir.
        if (empty($_SESSION['registrado'])) {
            if ($isAjaxRequest) {
                header('Content-Type: application/json', true, 401);
                echo json_encode(["success" => false, "message" => "No autenticado. Inicia sesión e intenta de nuevo."]);
                exit();
            }
            // Para peticiones normales, mantener el comportamiento histórico
            require_login();
        }

        $id_pedido = intval($datos["id_pedido"] ?? 0);
        $nuevo_estado = intval($datos["estado"] ?? 0);

        header('Content-Type: application/json');

        if ($id_pedido <= 0 || $nuevo_estado <= 0) {
            echo json_encode(["success" => false, "message" => "Datos inválidos. ID o Estado vacío."]);
            exit();
        }

    // Detectar roles: si el usuario es Admin, tiene permisos completos.
    $isAdmin = user_has_any_role_names([ROL_NOMBRE_ADMIN]);
    // Si el usuario es Repartidor y no es Admin, sólo permitir cambiar el estado
    // para pedidos que estén asignados a ese repartidor (id_vendedor).
    $isRepartidor = user_has_any_role_names([ROL_NOMBRE_REPARTIDOR]) && !$isAdmin;
    if ($isRepartidor) {
            try {
                $pedido = PedidosModel::obtenerPedidoPorId($id_pedido);
            } catch (Exception $e) {
                echo json_encode(["success" => false, "message" => "Error al obtener el pedido."]);
                exit();
            }

            if (empty($pedido)) {
                echo json_encode(["success" => false, "message" => "Pedido no encontrado."]);
                exit();
            }

            $userId = $_SESSION['user_id'] ?? null;
            $asignado = isset($pedido['id_vendedor']) ? (int)$pedido['id_vendedor'] : null;

            if ($asignado === null || $asignado === 0) {
                // No está asignado a nadie: no permitir que un repartidor no asignado lo modifique
                echo json_encode(["success" => false, "message" => "No tienes permiso para cambiar el estado de este pedido."]);
                exit();
            }

            if ($userId === null || (int)$userId !== (int)$asignado) {
                echo json_encode(["success" => false, "message" => "No tienes permiso para cambiar el estado de este pedido."]);
                exit();
            }
        }

        // Ejecutar la actualización (admins y otros roles pasan sin restricciones adicionales)
        $resultado = PedidosModel::actualizarEstado($id_pedido, $nuevo_estado);

        // Normalizar respuesta: el modelo puede devolver true/false o un array con error
        if (is_array($resultado)) {
            // Modelo devolvió un array con error
            $success = !empty($resultado['success']);
            $message = $resultado['message'] ?? ($success ? 'Estado actualizado.' : 'Error al actualizar el estado.');
        } else {
            $success = (bool)$resultado;
            $message = $success ? 'Estado actualizado correctamente.' : 'No se realizó ningún cambio en el estado.';
        }

        echo json_encode(["success" => $success, "message" => $message]);
        exit();
    }

    /**
     * Importar pedidos desde un CSV subido por formulario.
     *
     * MEJORAS v2.0:
     * - Modo vista previa (preview=1)
     * - Procesamiento por chunks (CHUNK_SIZE = 100)
     * - Validación robusta con CSVPedidoValidator
     * - Valores por defecto configurables
     * - Exportación de errores como CSV
     * - Auditoría completa en tabla importaciones_csv
     *
     * @return void
     */
    public function importarPedidosCSV()
    {
        require_once __DIR__ . '/../utils/session.php';
        require_once __DIR__ . '/../utils/CSVPedidoValidator.php';
        require_once __DIR__ . '/../utils/CSVHelper.php';
        require_once __DIR__ . '/../modelo/importacion.php';
        
        $tiempoInicio = microtime(true);
        $isPreview = isset($_POST['preview']) && $_POST['preview'] === '1';
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        // Constante para tamaño de chunk
        if (!defined('CHUNK_SIZE')) define('CHUNK_SIZE', 100);
        
        try {
            // Validar archivo subido
            if (!isset($_FILES['csv_file'])) {
                $resp = ['success' => false, 'message' => 'No se recibió el archivo CSV.'];
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode($resp);
                    exit;
                }
                set_flash('error', $resp['message']);
                header('Location: ' . RUTA_URL . 'pedidos/listar');
                exit;
            }
            
            $validacion = CSVHelper::validateUploadedFile($_FILES['csv_file']);
            if (!$validacion['valido']) {
                $resp = ['success' => false, 'message' => $validacion['error']];
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode($resp);
                    exit;
                }
                set_flash('error', $resp['message']);
                header('Location: ' . RUTA_URL . 'pedidos/listar');
                exit;
            }
            
            $tmp = $_FILES['csv_file']['tmp_name'];
            $filename = $_FILES['csv_file']['name'];
            $filesize = $_FILES['csv_file']['size'];
            
            $handle = fopen($tmp, 'r');
            if ($handle === false) {
                throw new Exception('No se pudo abrir el archivo CSV');
            }
            
            // Detectar delimitador
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                fclose($handle);
                throw new Exception('El CSV parece estar vacío');
            }
            
            $delimiter = CSVHelper::detectDelimiter($firstLine);
            rewind($handle);
            
            // Leer y normalizar header
            $header = fgetcsv($handle, 0, $delimiter);
            if ($header === false) {
                fclose($handle);
                throw new Exception('No se pudo leer la cabecera del CSV');
            }
            
            $cols = CSVHelper::normalizeHeaders($header);
            
            // Validar columnas mínimas requeridas
            $required = ['numero_orden', 'latitud', 'longitud'];
            $missing = [];
            foreach ($required as $r) {
                if (!in_array($r, $cols)) $missing[] = $r;
            }
            
            if (!empty($missing)) {
                fclose($handle);
                $msg = 'Faltan columnas requeridas en el CSV: ' . implode(', ', $missing);
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
                set_flash('error', $msg);
                header('Location: ' . RUTA_URL . 'pedidos/listar');
                exit;
            }
            
            // Obtener valores por defecto desde POST
            $defaultValues = [];
            if (!empty($_POST['default_estado'])) {
                $defaultValues['estado'] = (int)$_POST['default_estado'];
            }
            if (!empty($_POST['default_proveedor'])) {
                $defaultValues['proveedor'] = (int)$_POST['default_proveedor'];
            }
            if (!empty($_POST['default_moneda'])) {
                $defaultValues['moneda'] = (int)$_POST['default_moneda'];
            }
            if (!empty($_POST['default_vendedor'])) {
                $defaultValues['vendedor'] = (int)$_POST['default_vendedor'];
            }
            
            $autoCreateProducts = !isset($_POST['auto_create_products']) || $_POST['auto_create_products'] !== '0';
            
            // Leer todas las filas
            $allRows = [];
            $line = 1; // Header es línea 1
            
            while (!feof($handle)) {
                $raw = fgets($handle);
                if ($raw === false) break;
                $line++;
                
                // Quitar BOM si aparece
                if ($line === 2) {
                    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
                }
                
                $row = str_getcsv(rtrim($raw, "\r\n"), $delimiter, '"');
                
                // Si no coincide el número de columnas, intentar delimitador alternativo
                if (count($row) !== count($cols)) {
                    $alt = $delimiter === ',' ? ';' : ',';
                    $altRow = str_getcsv(rtrim($raw, "\r\n"), $alt, '"');
                    if (count($altRow) === count($cols)) {
                        $row = $altRow;
                    }
                }
                
                // Mapear a asociativo
                $dataRow = [];
                foreach ($cols as $i => $colName) {
                    $val = isset($row[$i]) ? trim($row[$i]) : '';
                    $dataRow[$colName] = $val;
                }
                
                // Omitir filas completamente vacías
                $allEmpty = true;
                foreach ($dataRow as $v) {
                    if ($v !== null && $v !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) continue;
                
                $allRows[] = $dataRow;
            }
            
            fclose($handle);
            
            // VALIDACIÓN COMPLETA
            $validator = new CSVPedidoValidator();
            $resumenValidacion = $validator->validarLote($allRows);
            
            // MODO PREVIEW: Retornar resumen sin insertar
            if ($isPreview) {
                $previewData = [
                    'success' => true,
                    'preview' => true,
                    'resumen' => $resumenValidacion,
                    'primeras_filas' => array_slice($allRows, 0, 20),
                    'puede_importar' => $resumenValidacion['puede_importar'],
                    'default_values' => $defaultValues,
                    'auto_create_products' => $autoCreateProducts
                ];
                
                header('Content-Type: application/json');
                echo json_encode($previewData, JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Si NO puede importar (todos con errores), detener
            if (!$resumenValidacion['puede_importar']) {
                $msg = 'No hay filas válidas para importar. Total de errores: ' . count($resumenValidacion['errores']);
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => $msg,
                        'errores' => $resumenValidacion['errores']
                    ]);
                    exit;
                }
                $_SESSION['import_errors'] = $resumenValidacion['errores'];
                set_flash('error', $msg);
                header('Location: ' . RUTA_URL . 'pedidos/listar');
                exit;
            }
            
            // PROCESAMIENTO POR CHUNKS
            $totalFilas = count($allRows);
            $totalExitosas = 0;
            $todosErrores = [];
            $filasErrorneas = [];
            $erroresFilasErrorneas = [];
            $todosProductosCreados = [];
            
            for ($i = 0; $i < $totalFilas; $i += CHUNK_SIZE) {
                $chunk = array_slice($allRows, $i, CHUNK_SIZE);
                
                // Filtrar solo filas válidas del chunk
                $chunkValido = [];
                foreach ($chunk as $index => $fila) {
                    $lineNumber = $i + $index + 2; // +2 porque header es 1 y arrays empiezan en 0
                    $validacion = $validator->validarFila($fila, $lineNumber);
                    
                    if ($validacion['valido']) {
                        // Completar IDs si se usaron nombres
                        $validator->completarIDs($fila);
                        
                        // Aplicar valores por defecto a la fila
                        if (!empty($defaultValues['estado']) && empty($fila['id_estado'])) {
                            $fila['id_estado'] = $defaultValues['estado'];
                        }
                        if (!empty($defaultValues['proveedor']) && empty($fila['id_proveedor'])) {
                            $fila['id_proveedor'] = $defaultValues['proveedor'];
                        }
                        if (!empty($defaultValues['moneda']) && empty($fila['id_moneda'])) {
                            $fila['id_moneda'] = $defaultValues['moneda'];
                        }
                        if (!empty($defaultValues['vendedor']) && empty($fila['id_vendedor'])) {
                            $fila['id_vendedor'] = $defaultValues['vendedor'];
                        }
                        
                        $chunkValido[] = $fila;
                    } else {
                        // Guardar fila errónea para exportar
                        $filasErrorneas[] = $fila;
                        $errorMsg = "Línea {$lineNumber}: " . implode('; ', $validacion['errores']);
                        $erroresFilasErrorneas[] = $errorMsg;
                        $todosErrores[] = $errorMsg;
                    }
                }
                
                // Insertar chunk válido
                if (!empty($chunkValido)) {
                    $resultado = PedidosModel::insertarPedidosLote($chunkValido, $autoCreateProducts, $defaultValues);
                    $totalExitosas += $resultado['inserted'];
                    
                    if (!empty($resultado['errors'])) {
                        $todosErrores = array_merge($todosErrores, $resultado['errors']);
                    }
                    
                    if (!empty($resultado['productos_creados'])) {
                        $todosProductosCreados = array_merge($todosProductosCreados, $resultado['productos_creados']);
                    }
                }
            }
            
            $tiempoFin = microtime(true);
            $tiempoProcesamiento = round($tiempoFin - $tiempoInicio, 3);
            
            // Exportar filas erróneas como CSV si existen
            $archivoErrores = null;
            if (!empty($filasErrorneas)) {
                try {
                    $archivoErrores = CSVHelper::exportErrorsToCSV($filasErrorneas, $cols, $erroresFilasErrorneas, $delimiter);
                } catch (Exception $e) {
                    error_log('Error al exportar CSV de errores: ' . $e->getMessage());
                }
            }
            
            // REGISTRAR AUDITORÍA
            try {
                $auditData = [
                    'id_usuario' => $_SESSION['user_id'] ?? 1,
                    'archivo_nombre' => $filename,
                    'archivo_size_bytes' => $filesize,
                    'tipo_plantilla' => 'custom', // Determinar según header si coincide con templates
                    'filas_totales' => $totalFilas,
                    'filas_exitosas' => $totalExitosas,
                    'filas_error' => count($todosErrores),
                    'filas_advertencias' => count($resumenValidacion['advertencias'] ?? []),
                    'tiempo_procesamiento_segundos' => $tiempoProcesamiento,
                    'valores_defecto' => $defaultValues,
                    'productos_creados' => array_unique($todosProductosCreados),
                    'errores_detallados' => array_slice($todosErrores, 0, 50), // Solo primeros 50 errores
                    'archivo_errores' => $archivoErrores
                ];
                
                ImportacionModel::registrar($auditData);
            } catch (Exception $e) {
                error_log('Error al registrar auditoría de importación: ' . $e->getMessage());
            }
            
            // RESPUESTA
            $msg = "{$totalExitosas} pedidos importados correctamente de {$totalFilas} filas.";
            $estadoFinal = 'completado';
            
            if (count($todosErrores) > 0) {
                $msg .= " " . count($todosErrores) . " filas con error.";
                $estadoFinal = $totalExitosas > 0 ? 'parcial' : 'fallido';
            }
            
            $responseData = [
                'success' => $totalExitosas > 0,
                'message' => $msg,
                'stats' => [
                    'total' => $totalFilas,
                    'inserted' => $totalExitosas,
                    'errors' => count($todosErrores),
                    'warnings' => count($resumenValidacion['advertencias'] ?? []),
                    'tiempo_segundos' => $tiempoProcesamiento
                ],
                'productos_creados' => array_unique($todosProductosCreados),
                'estado' => $estadoFinal,
                'errors_list' => array_slice($todosErrores, 0, 10) // Enviar primeros 10 errores para debug
            ];
            
            if ($archivoErrores) {
                $responseData['error_file_url'] = 'logs/' . $archivoErrores;
            }
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode($responseData, JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Para peticiones normales
            if (!empty($todosErrores)) {
                $_SESSION['import_errors'] = array_slice($todosErrores, 0, 100); // Máximo 100 errores en sesión
                set_flash('warning', $msg);
            } else {
                set_flash('success', $msg);
            }
            
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
            
        } catch (Exception $e) {
            // Logging detallado del error
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $errorId = date('YmdHis') . '-' . bin2hex(random_bytes(4));
            $dump = "[{$errorId}] " . date('c') . "\n";
            $dump .= "Message: " . $e->getMessage() . "\n";
            $dump .= "Trace: \n" . $e->getTraceAsString() . "\n";
            @file_put_contents($logDir . '/import_errors.log', $dump . "\n\n", FILE_APPEND | LOCK_EX);
            
            if ($isAjax) {
                header('Content-Type: application/json', true, 500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error interno durante la importación. ID: ' . $errorId,
                    'error_id' => $errorId
                ]);
                exit;
            }
            
            set_flash('error', 'Ocurrió un error interno durante la importación. Contacta al administrador con el ID: ' . $errorId);
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }
    }
    
    
    
}

