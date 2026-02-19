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

    // Specialized controllers for separation of concerns
    private $queryService;
    private $apiController;

    public function __construct() {
        // Load specialized classes
        require_once __DIR__ . '/pedidos/PedidoQueryService.php';
        require_once __DIR__ . '/pedidos/PedidoApiController.php';
        
        // Initialize services
        $this->queryService = new PedidoQueryService();
        $this->apiController = new PedidoApiController();
    }


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
    public function crearPedidoAPI($jsonData, $autoEnqueue = false, $authUserId = 0, $authUserRole = '') {
        // Delegate to specialized API controller
        return $this->apiController->crear($jsonData, $autoEnqueue, $authUserId, $authUserRole);
    }

    /**
     * API endpoint: Crear múltiples pedidos desde JSON.
     * Lee php://input, decodifica JSON y espera la clave 'pedidos' como array.
    * Para cada pedido inserta una fila en `pedidos` y sus productos en `pedidos_productos`.
     * Continúa en errores por pedido y devuelve un resumen por pedido.
     *
     * Uso: POST /api/pedidos/multiple (o la ruta que corresponda) con body JSON.
     */
    public function createMultiple($authUserId = 0, $authUserRole = '', $autoEnqueue = false)
    {
        // DEBUG LOGGING
        error_log("createMultiple called with UserID: " . json_encode($authUserId) . " Role: " . json_encode($authUserRole) . " AutoEnqueue: " . json_encode($autoEnqueue));

        // Read and decode JSON payload
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        header('Content-Type: application/json');

        if (!is_array($payload) || !isset($payload['pedidos']) || !is_array($payload['pedidos'])) {
            http_response_code(400);
            echo json_encode(['error' => "JSON inválido o falta la clave 'pedidos' (esperado array)."], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            return;
        }

        // Delegar al controlador especializado
        $results = $this->apiController->crearMultiple($payload, $autoEnqueue, $authUserId, $authUserRole);
        
        // Generar resumen
        $summary = [
            'total' => count($payload['pedidos']),
            'processed' => count($results['results'] ?? []),
            'success' => count(array_filter($results['results'] ?? [], function($r){ return $r['success']; })),
            'failed' => count(array_filter($results['results'] ?? [], function($r){ return !$r['success']; }))
        ];

        echo json_encode(['summary' => $summary, 'results' => $results['results'] ?? []], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
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
            $msj = count($errores) === 1 ? $errores[0] : "Se encontraron " . count($errores) . " errores de validación.";
            return ["success" => false, "message" => $msj, "details" => $errores];
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
    public function listarPedidosExtendidos()
    {
        // Delegate to query service
        return $this->queryService->listarExtendidos();
    }

    /**
     * Listar pedidos paginados (para API).
     * @param int $page Página actual (1-indexed)
     * @param int $limit Registros por página
     * @return array Estructura de paginación
     */
    public function listarPedidosPaginados($page = 1, $limit = 20)
    {
        $page = max(1, (int)$page);
        $limit = max(1, min(100, (int)$limit));
        $offset = ($page - 1) * $limit;

        $filtros = [
            'limit' => $limit,
            'offset' => $offset
        ];
        
        // Ownership Security Filters
        require_once __DIR__ . '/../utils/permissions.php';
        if (isProveedor() || (isset($GLOBALS['API_USER_ROLE']) && $GLOBALS['API_USER_ROLE'] == ROL_PROVEEDOR_CRM)) {
            $filtros['id_proveedor'] = getCurrentUserId();
        } elseif (isCliente() || (isset($GLOBALS['API_USER_ROLE']) && $GLOBALS['API_USER_ROLE'] == ROL_CLIENTE_CRM)) {
            $filtros['id_cliente'] = getCurrentUserId();
        }

        // Add support for filters from GET if needed
        if (isset($_GET['estado'])) $filtros['id_estado'] = (int)$_GET['estado'];
        if (isset($_GET['destinatario'])) $filtros['destinatario'] = $_GET['destinatario']; 
        
        // Nuevos filtros
        if (isset($_GET['numero_orden'])) $filtros['numero_orden'] = $_GET['numero_orden'];
        if (isset($_GET['numero_cliente'])) $filtros['id_cliente'] = (int)$_GET['numero_cliente']; 
        
        $data = PedidosModel::obtenerConFiltros($filtros);
        $total = PedidosModel::contarConFiltros($filtros);
        $totalPages = ceil($total / $limit);

        return [
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int)$totalPages
            ]
        ];
    }

    /**
     * Obtener un pedido por su id (uso en vistas y controladores internos).
     *
     * @param int $id_pedido
     * @return array|null
     */
    public function obtenerPedido($id_pedido)
    {
        // Delegate to query service
        return $this->queryService->obtenerPorId($id_pedido);
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
     * Obtener clientes registrados.
     * @return array
     */
    public function obtenerClientes() {
        return PedidosModel::obtenerClientes();
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
     * Obtener detalles de un pedido para la vista ver.php
     * Retorna un array con un solo elemento para compatibilidad con la vista
     * @param int $id_pedido
     * @return array
     */
    public function verPedido($id_pedido)
    {
        $pedido = $this->obtenerPedido($id_pedido);
        if (!$pedido) {
            return [];
        }
        // Retornar como array para compatibilidad con la vista que espera $detallesPedido[0]
        return [$pedido];
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
                $dbg .= json_encode($sanitized, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n";
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
        
        // FORZAR ESTADO INICIAL "EN BODEGA" (ID 1)
        // Regla de negocio estricta: todo pedido nuevo nace en bodega.
        $estado = 1; 
        
        $vendedor = $parse_positive_int($data, 'vendedor');
        
        // Leer valores de los campos del formulario
        // NOTA: Los nombres en BD están invertidos históricamente:
        // - Rol ID 4 en BD se llama "Cliente" pero son PROVEEDORES de mensajería
        // - Rol ID 5 en BD se llama "Proveedor" pero son CLIENTES que solicitan envíos
        // Las etiquetas de la vista son correctas semánticamente, así que mapeamos directo
        $proveedor = $parse_positive_int($data, 'proveedor');
        $idCliente = $parse_positive_int($data, 'id_cliente');
        if (!$idCliente) $idCliente = $parse_positive_int($data, 'cliente');
        
        // Aplicar auto-asignación por defecto si no viene valor, pero permitir override
        require_once __DIR__ . '/../utils/permissions.php';
        
        $currentUserId = $_SESSION['user_id'];
        
        // 1. Validar ID Cliente
        if ($idCliente) {
            // Si viene un ID, solo validar que sea un Cliente válido
            if (!PedidosModel::esCliente($idCliente)) {
                $errores[] = "El usuario seleccionado como Cliente no tiene el rol adecuado.";
            }
        } else {
             // Si no viene cliente, intentar auto-asignar si soy cliente
             if (isCliente() && !isSuperAdmin() && !isVendedor()) {
                 $idCliente = $currentUserId;
             }
        }
        
        // 2. Validar ID Proveedor
        if ($proveedor) {
            // Si viene un ID, solo validar que sea un Proveedor válido
            if (!PedidosModel::esProveedor($proveedor)) {
                $errores[] = "El usuario seleccionado como Proveedor no tiene el rol adecuado.";
            }
        } else {
            // Si no viene proveedor...
            // Antes auto-asignábamos forzosamente. Ahora permitimos que venga vacío.
            // PERO si el usuario QUIERE auto-asignarse (porque el front lo pre-seleccionó), vendrá en el POST.
            // Si viene vacío, asumimos que no quiere proveedor (o no seleccionó).
            
            // Opcional: Si es proveedor estricto y no mandó nada, ¿lo forzamos?
            // El usuario dijo "evitar auto asignación". Así que si lo deja vacío, es vacío.
        }
        
        // Regla de Negocio: Cliente no puede ser Proveedor de su propio pedido (generalmente)
        // (Mantenemos esta validación lógica)
        if ($idCliente && $proveedor && $idCliente == $proveedor) {
             $errores[] = "El Cliente y el Proveedor no pueden ser el mismo usuario.";
        }
        
        // Validar que haya un proveedor (antes del swap)
        if ($proveedor === null || $proveedor === false) {
            $errores[] = 'Selecciona un proveedor válido.';
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
        
        // Nuevos campos de pricing de combo
        $precioTotalLocal = null;
        $precioTotalUsd = null;
        $tasaConversionUsd = null;
        $esCombo = false;

        // Precios legacy (opcionales, para compatibilidad)
        if (isset($data['precio_local']) && $data['precio_local'] !== '') {
            $precioLocal = (float)str_replace(',', '.', (string)$data['precio_local']);
        }
        if (isset($data['precio_usd']) && $data['precio_usd'] !== '') {
            $precioUsdEntrada = (float)str_replace(',', '.', (string)$data['precio_usd']);
        }
        
        // Nuevos campos de combo pricing
        if (isset($data['precio_total_local']) && $data['precio_total_local'] !== '') {
            $precioTotalLocal = (float)str_replace(',', '.', (string)$data['precio_total_local']);
        }
        if (isset($data['precio_total_usd']) && $data['precio_total_usd'] !== '') {
            $precioTotalUsd = (float)str_replace(',', '.', (string)$data['precio_total_usd']);
        }
        if (isset($data['tasa_conversion_usd']) && $data['tasa_conversion_usd'] !== '') {
            $tasaConversionUsd = (float)str_replace(',', '.', (string)$data['tasa_conversion_usd']);
        }
        if (isset($data['es_combo'])) {
            $esCombo = (bool)$data['es_combo'];
        }

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
        } 
        
        // Fallback or Legacy: if no items found in 'productos', try single fields
        if (empty($items)) {
             $productoId = $parse_positive_int($data, 'producto_id');
             $cantidadProducto = $parse_positive_int($data, 'cantidad_producto');
             if ($productoId && $cantidadProducto) {
                $items = [[
                    'id_producto' => (int)$productoId,
                    'cantidad' => (int)$cantidadProducto,
                    'cantidad_devuelta' => 0,
                ]];
             }
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
        
        if (empty($items)) {
            $errores[] = 'El pedido debe incluir al menos un producto válido (ya sea por lista o campos individuales).';
        }
        
        // Estado y vendedor ahora son opcionales
        if ($proveedor === null || $proveedor === false) {
            $errores[] = 'Selecciona un proveedor válido.';
        }
        if ($moneda === null || $moneda === false) {
            $errores[] = 'Selecciona una moneda válida.';
        }

        if (empty($errores) && PedidosModel::existeNumeroOrden((int)$numeroOrden)) {
            $errores[] = 'El número de orden ya existe en la base de datos.';
        }

        // Validación server-side de stock: iterar sobre $items ya procesados
        foreach ($items as $item) {
             $pid = (int)$item['id_producto'];
             $qty = (int)$item['cantidad'];
             try {
                $stockDisponible = ProductoModel::obtenerStockTotal($pid);
                if ($stockDisponible !== null && $stockDisponible >= 0) {
                    if ($stockDisponible > 0 && $qty > $stockDisponible) {
                        $errores[] = 'La cantidad solicitada para el producto ID ' . $pid . ' supera el stock disponible (' . $stockDisponible . ').';
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
            error_log("guardarPedidoFormulario ERRORES VALIDACION: " . implode(' ', $errores));
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
            error_log("guardarPedidoFormulario ERRORES VALIDACION: " . implode(' ', $errores));
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
            'id_cliente' => is_int($idCliente) ? (int)$idCliente : null,
            'moneda' => is_int($moneda) ? (int)$moneda : null,
            'latitud' => (float)$latitud,
            'longitud' => (float)$longitud,
            'id_pais' => isset($data['id_pais']) ? $data['id_pais'] : ($data['pais'] ?? null),
            'id_departamento' => isset($data['id_departamento']) ? $data['id_departamento'] : ($data['departamento'] ?? null),
            'id_municipio' => isset($data['id_municipio']) ? $data['id_municipio'] : ($data['municipio'] ?? null),
            'id_barrio' => isset($data['id_barrio']) ? $data['id_barrio'] : ($data['barrio'] ?? null),
            'codigo_postal' => $data['codigo_postal'] ?? null,
            'municipio' => $data['municipio'] ?? null,
            'barrio' => $data['barrio'] ?? null,
            'zona' => $data['zona'] ?? null,
            'precio_local' => $precioLocal,
            'precio_usd' => $precioUsd,
            // Nuevos campos de combo pricing
            'precio_total_local' => $precioTotalLocal,
            'precio_total_usd' => $precioTotalUsd,
            'tasa_conversion_usd' => $tasaConversionUsd,
            'es_combo' => $esCombo ? 1 : 0,
        ];


        try {
            $nuevoId = PedidosModel::crearPedidoConProductos($payload, $items);
            error_log("guardarPedidoFormulario EXITO. Nuevo ID: " . $nuevoId);
            return [
                'success' => true,
                'message' => 'Pedido guardado correctamente.',
                'id' => $nuevoId
            ];
        } catch (Exception $e) {
            error_log("guardarPedidoFormulario EXCEPTION: " . $e->getMessage());
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
        file_put_contents(__DIR__ . '/../debug_dump.txt', date('Y-m-d H:i:s') . " - guardarEdicion POST: " . print_r($data, true) . "\n", FILE_APPEND);


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
            echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            exit;
        };

        try {
        // Validaciones mínimas
            if (!isset($data['id_pedido']) || !is_numeric($data['id_pedido'])) {
                $resp = ['success' => false, 'message' => 'ID de pedido inválido.'];
                if ($isAjax) { $sendJson($resp); }
                $persistOldEdit($data);
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/listar'); exit;
            }

            // [SECURITY] Validar permisos de edición
            require_once __DIR__ . '/../utils/session.php'; start_secure_session();
            $checkPerms = $this->apiController->validarPermisosEdicion(
                (int)$data['id_pedido'], 
                $_SESSION['user_id'] ?? 0, 
                $_SESSION['rol'] ?? '',
                [] // Campos a editar (vacío para usar validación de estado solamente)
            );

            if (!$checkPerms['permitido']) {
                $resp = ['success' => false, 'message' => $checkPerms['mensaje']];
                if ($isAjax) { $sendJson($resp, 403); }
                $persistOldEdit($data);
                set_flash('error', $resp['message']); 
                header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido']); 
                exit;
            }

            // DEBUG: Log POST data specifically for persistence debugging
            if (defined('DEBUG') && DEBUG) {
                 file_put_contents(__DIR__ . '/../logs/debug_pedido.log', date('Y-m-d H:i:s') . " - ID: {$data['id_pedido']} - DATA: " . json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
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
            
            // Validaciones de precios legacy
            if (isset($data['precio_local']) && $data['precio_local'] !== '' && !is_numeric($data['precio_local'])) {
                $resp = ['success' => false, 'message' => 'El precio local debe ser un valor numérico.'];
                if ($isAjax) { $sendJson($resp); }
                $persistOldEdit($data);
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorPrecio'); exit;
            }
            
            // Validaciones de nuevos campos de combo pricing
            if (isset($data['precio_total_local']) && $data['precio_total_local'] !== '' && !is_numeric($data['precio_total_local'])) {
                $resp = ['success' => false, 'message' => 'El precio total local debe ser un valor numérico.'];
                if ($isAjax) { $sendJson($resp); }
                $persistOldEdit($data);
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorPrecioTotalLocal'); exit;
            }
            if (isset($data['precio_total_usd']) && $data['precio_total_usd'] !== '' && !is_numeric($data['precio_total_usd'])) {
                $resp = ['success' => false, 'message' => 'El precio total USD debe ser un valor numérico.'];
                if ($isAjax) { $sendJson($resp); }
                $persistOldEdit($data);
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorPrecioTotalUsd'); exit;
            }
            if (isset($data['tasa_conversion_usd']) && $data['tasa_conversion_usd'] !== '' && !is_numeric($data['tasa_conversion_usd'])) {
                $resp = ['success' => false, 'message' => 'La tasa de conversión debe ser un valor numérico.'];
                if ($isAjax) { $sendJson($resp); }
                $persistOldEdit($data);
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorTasaConversion'); exit;
            }

            // Asegurar que es_combo se actualice correctamente (si no está en POST es 0)
            // Esto es necesario para permitir desmarcar el checkbox
            $data['es_combo'] = isset($data['es_combo']) ? 1 : 0;

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
                echo json_encode(["success" => false, "message" => "No autenticado. Inicia sesión e intenta de nuevo."], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
                exit();
            }
            // Para peticiones normales, mantener el comportamiento histórico
            require_login();
        }

        $id_pedido = intval($datos["id_pedido"] ?? 0);
        $nuevo_estado = intval($datos["estado"] ?? 0);
        $observaciones = $datos["observaciones"] ?? null;

        header('Content-Type: application/json');

        if ($id_pedido <= 0 || $nuevo_estado <= 0) {
            echo json_encode(["success" => false, "message" => "Datos inválidos. ID o Estado vacío."], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
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
                echo json_encode(["success" => false, "message" => "Error al obtener el pedido."], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
                exit();
            }

            if (empty($pedido)) {
                echo json_encode(["success" => false, "message" => "Pedido no encontrado."], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
                exit();
            }

            $userId = $_SESSION['user_id'] ?? null;
            $asignado = isset($pedido['id_vendedor']) ? (int)$pedido['id_vendedor'] : null;

            if ($asignado === null || $asignado === 0) {
                // No está asignado a nadie: no permitir que un repartidor no asignado lo modifique
                echo json_encode(["success" => false, "message" => "No tienes permiso para cambiar el estado de este pedido."], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
                exit();
            }

            if ($userId === null || (int)$userId !== (int)$asignado) {
                echo json_encode(["success" => false, "message" => "No tienes permiso para cambiar el estado de este pedido."], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
                exit();
            }
        }

        // Si el usuario es Cliente y no es Admin, sólo permitir cambiar el estado
        // para pedidos que le pertenezcan (id_cliente).
        $isClienteRole = user_has_any_role_names([ROL_NOMBRE_CLIENTE]) && !$isAdmin;
        if ($isClienteRole) {
            try {
                $pedido = PedidosModel::obtenerPedidoPorId($id_pedido);
            } catch (Exception $e) {
                echo json_encode(["success" => false, "message" => "Error al obtener el pedido."], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
                exit();
            }

            if (empty($pedido)) {
                echo json_encode(["success" => false, "message" => "Pedido no encontrado."], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
                exit();
            }

            $userId = $_SESSION['user_id'] ?? null;
            $idCliente = isset($pedido['id_cliente']) ? (int)$pedido['id_cliente'] : null;

            if ($idCliente === null || $idCliente === 0 || $userId === null || (int)$userId !== (int)$idCliente) {
                echo json_encode(["success" => false, "message" => "No tienes permiso para cambiar el estado de este pedido."], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
                exit();
            }
        }

        // Ejecutar la actualización (admins y otros roles pasan sin restricciones adicionales)
        $resultado = PedidosModel::actualizarEstado($id_pedido, $nuevo_estado, $observaciones);

        // Normalizar respuesta: el modelo puede devolver true/false o un array con error
        if (is_array($resultado)) {
            // Modelo devolvió un array con error
            $success = !empty($resultado['success']);
            $message = $resultado['message'] ?? ($success ? 'Estado actualizado.' : 'Error al actualizar el estado.');
        } else {
            $success = (bool)$resultado;
            $message = $success ? 'Estado actualizado correctamente.' : 'No se realizó ningún cambio en el estado.';
        }

        echo json_encode(["success" => $success, "message" => $message], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
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
                    echo json_encode($resp, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
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
                    echo json_encode($resp, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
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
                    echo json_encode(['success' => false, 'message' => $msg], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
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
                echo json_encode($previewData, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
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
                    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
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
                echo json_encode($responseData, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
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
                ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            set_flash('error', 'Ocurrió un error interno durante la importación. Contacta al administrador con el ID: ' . $errorId);
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }
    }
    
    
    
    /**
     * Endpoint API para obtener el historial de estados de un pedido
     * GET /pedidos/historial/<id> o ?id=<id>
     */
    public function historial($id = null) {
        // Soporte para llamar historial($id) desde router o historial() con $_GET
        if ($id === null && isset($_GET['id'])) {
            $id = $_GET['id'];
        }

        require_once __DIR__ . '/../utils/session.php';
        // start_secure_session(); 

        header('Content-Type: application/json');

        if (!$id || !is_numeric($id)) {
            echo json_encode(['success' => false, 'message' => 'ID de pedido inválido'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $historial = PedidosModel::obtenerHistorialEstados((int)$id);
            
            echo json_encode([
                'success' => true,
                'data' => $historial
            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => 'Error al obtener historial: ' . $e->getMessage()
            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}

