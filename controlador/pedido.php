<?php

// Atención: la configuración de visualización de errores fue removida del
// archivo. Controla la salida de logs con la constante DEBUG en
// `config/config.php` o mediante la configuración de PHP en el entorno.

ob_start();
require_once __DIR__ . '/../modelo/producto.php';

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
     * - data: id del nuevo pedido en caso de éxito.
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
    
        // Validar la estructura del pedido
        $validacion = $this->validarDatosPedido($data);
        if (!$validacion["success"]) {
            return $validacion;
        }
    
        try {
            // Verificar si el número de orden ya existe
            if (PedidosModel::existeNumeroOrden($data["numero_orden"])) {
                return [
                    "success" => false,
                    "message" => "El número de orden ya existe en la base de datos."
                ];
            }
    
            // Support multiple products: either provide single 'producto' + 'cantidad'
            // or an array 'productos' with items { producto_id|producto, cantidad }.
            $items = [];
            // Require product IDs only: do NOT create products from names.
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
                    [$latitud, $longitud] = array_map('trim', explode(',', $data['coordenadas']));
                }
            }

            if (!is_numeric($latitud) || !is_numeric($longitud)) {
                return [
                    "success" => false,
                    "message" => "Coordenadas inválidas para el pedido."
                ];
            }

            $precioLocal = null;
            if (isset($data['precio_local']) && $data['precio_local'] !== '') {
                $precioLocal = (float)$data['precio_local'];
            } elseif (isset($data['precio']) && $data['precio'] !== '') {
                $precioLocal = (float)$data['precio'];
            }

            $monedaId = isset($data['id_moneda']) ? (int)$data['id_moneda'] : null;
            if ($monedaId === 0) {
                $monedaId = null;
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
                'vendedor' => isset($data['id_vendedor']) ? (int)$data['id_vendedor'] : 0,
                'proveedor' => isset($data['id_proveedor']) ? (int)$data['id_proveedor'] : 0,
                'moneda' => $monedaId ?? 0,
                'latitud' => (float)$latitud,
                'longitud' => (float)$longitud,
                'pais' => $data['pais'] ?? null,
                'departamento' => $data['departamento'] ?? null,
                'municipio' => $data['municipio'] ?? null,
                'barrio' => $data['barrio'] ?? null,
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
                "data" => $nuevoId
            ];
    
        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => "Error al crear el pedido: " . $e->getMessage()
            ];
        }
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

        // Validar campos obligatorios (aceptamos 'producto' como nombre o 'producto_id')
        $camposObligatorios = [
            "numero_orden", "destinatario", "telefono",
            "pais", "departamento", "municipio", "direccion", "coordenadas"
        ];

        // Si se envía un array 'productos' entonces la cantidad se valida por item
        if (!isset($data['productos']) || !is_array($data['productos']) || count($data['productos']) === 0) {
            // en el payload simple se espera campo 'cantidad' a nivel superior
            $camposObligatorios[] = 'cantidad';
        }
        foreach ($camposObligatorios as $campo) {
            if (!isset($data[$campo]) || $data[$campo] === '') {
                $errores[] = "El campo '$campo' es obligatorio.";
            }
        }

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

        // Validar formato de las coordenadas
        if (isset($data["coordenadas"])) {
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
     * @return array
     */
    public function listarPedidosExtendidos() {
        // Llamar al modelo para obtener los pedidos
        $pedidos = PedidosModel::obtenerPedidosExtendidos();
        return $pedidos;
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
        $moneda = $parse_positive_int($data, 'moneda');

        $comentario = trim($data['comentario'] ?? '');
        $direccion = trim($data['direccion'] ?? '');
        $latitud = isset($data['latitud']) ? filter_var($data['latitud'], FILTER_VALIDATE_FLOAT) : false;
        $longitud = isset($data['longitud']) ? filter_var($data['longitud'], FILTER_VALIDATE_FLOAT) : false;

        $precioLocal = null;
        $precioUsdEntrada = null;

        if (isset($data['precio_local']) && $data['precio_local'] !== '') {
            $precioLocalSanitized = str_replace(',', '.', (string)$data['precio_local']);
            if (!is_numeric($precioLocalSanitized)) {
                $errores[] = 'El precio local debe ser un número válido.';
            } else {
                $precioLocal = round((float)$precioLocalSanitized, 2);
                if ($precioLocal < 0) {
                    $errores[] = 'El precio local no puede ser negativo.';
                }
            }
        }

        if (isset($data['precio_usd']) && $data['precio_usd'] !== '') {
            $precioUsdSanitized = str_replace(',', '.', (string)$data['precio_usd']);
            if (!is_numeric($precioUsdSanitized)) {
                $errores[] = 'El precio en USD debe ser un número válido.';
            } else {
                $precioUsdEntrada = round((float)$precioUsdSanitized, 2);
                if ($precioUsdEntrada < 0) {
                    $errores[] = 'El precio en USD no puede ser negativo.';
                }
            }
        }

        if ($numeroOrden === '') {
            $errores[] = 'El número de orden es obligatorio.';
        } else {
            // Asegurar que sea un entero positivo (la columna en BD espera un entero)
            if (!preg_match('/^\d+$/', (string)$numeroOrden) || (int)$numeroOrden < 1) {
                $errores[] = 'El número de orden debe ser un entero positivo.';
            }
        }
        if ($destinatario === '') {
            $errores[] = 'El destinatario es obligatorio.';
        }
        if ($telefono === '' || !preg_match('/^[0-9]{8,15}$/', $telefono)) {
            $errores[] = 'El teléfono debe contener entre 8 y 15 dígitos.';
        }
        if ($productoId === null || $productoId === false) {
            $errores[] = 'Selecciona un producto válido.';
        }
        if ($cantidadProducto === null || $cantidadProducto === false) {
            $errores[] = 'La cantidad debe ser un número entero mayor a cero.';
        }
        if ($estado === null || $estado === false) {
            $errores[] = 'Selecciona un estado válido.';
        }
        if ($vendedor === null || $vendedor === false) {
            $errores[] = 'Selecciona un usuario asignado válido.';
        }
        if ($proveedor === null || $proveedor === false) {
            $errores[] = 'Selecciona un proveedor válido.';
        }
        if ($moneda === null || $moneda === false) {
            $errores[] = 'Selecciona una moneda válida.';
        }
        if ($direccion === '') {
            $errores[] = 'La dirección es obligatoria.';
        }
        if ($latitud === false || $longitud === false) {
            $errores[] = 'Las coordenadas no tienen un formato válido.';
        }

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
            'pais' => $data['pais'] ?? null,
            'departamento' => $data['departamento'] ?? null,
            'municipio' => $data['municipio'] ?? null,
            'barrio' => $data['barrio'] ?? null,
            'zona' => $data['zona'] ?? null,
            'precio_local' => $precioLocal,
            'precio_usd' => $precioUsd,
        ];

        $items = [[
            'id_producto' => (int)$productoId,
            'cantidad' => (int)$cantidadProducto,
            'cantidad_devuelta' => 0,
        ]];

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
        // Soporte para peticiones AJAX: si el header X-Requested-With == XMLHttpRequest
        // o el cliente solicita JSON por Accept, devolvemos JSON en lugar de hacer
        // redirect + set_flash. Esto facilita que el frontend maneje la respuesta
        // y muestre SweetAlert sin recargar la página.
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                 || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        try {
            // Validaciones mínimas
            if (!isset($data['id_pedido']) || !is_numeric($data['id_pedido'])) {
                $resp = ['success' => false, 'message' => 'ID de pedido inválido.'];
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode($resp); exit; }
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/listar'); exit;
            }

            if (!is_numeric($data['latitud']) || !is_numeric($data['longitud'])) {
                $resp = ['success' => false, 'message' => 'Las coordenadas no tienen un formato válido.'];
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode($resp); exit; }
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorLatLong'); exit;
            }

            // Validación básica para cantidad y precio (si vienen)
            if (isset($data['cantidad_producto']) && $data['cantidad_producto'] !== '' && !is_numeric($data['cantidad_producto'])) {
                $resp = ['success' => false, 'message' => 'La cantidad debe ser un número.'];
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode($resp); exit; }
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorCantidad'); exit;
            }
            if (isset($data['precio_local']) && $data['precio_local'] !== '' && !is_numeric($data['precio_local'])) {
                $resp = ['success' => false, 'message' => 'El precio local debe ser un valor numérico.'];
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode($resp); exit; }
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorPrecio'); exit;
            }

            // Llama al modelo para actualizar el pedido
            $resultado = PedidosModel::actualizarPedido($data);

            if ($resultado) {
                $resp = ['success' => true, 'message' => 'Pedido actualizado correctamente.'];
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode($resp); exit; }
                require_once __DIR__ . '/../utils/session.php'; set_flash('success', $resp['message']); header('Location: '. RUTA_URL . 'pedidos/listar'); exit;
            } else {
                $resp = ['success' => false, 'message' => 'No se realizaron cambios en el pedido.'];
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode($resp); exit; }
                require_once __DIR__ . '/../utils/session.php'; set_flash('error', $resp['message']); header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/error'); exit;
            }
        } catch (Exception $e) {
            $msg = 'Error interno: ' . $e->getMessage();
            if ($isAjax) { header('Content-Type: application/json', true, 500); echo json_encode(['success' => false, 'message' => $msg]); exit; }
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
         * Importar pedidos desde archivo CSV subido por formulario
         */
    /**
     * Importar pedidos desde un CSV subido por formulario.
     *
     * Soporta delimitadores comunes, normaliza cabeceras y valida filas antes de
     * insertar en lote. Registra errores en session/logs para revisión.
     *
     * Responde con redirect y flash o JSON en caso de AJAX.
     *
     * @return void
     */
    public function importarPedidosCSV()
    {
        require_once __DIR__ . '/../utils/session.php';

        // Envolver en try/catch global para capturar errores inesperados y retornar JSON/logs
        try {

        // Manejo de errores de subida más descriptivo
        $uploadErrorMsg = function($code) {
            $messages = [
                UPLOAD_ERR_OK => 'Sin error',
                UPLOAD_ERR_INI_SIZE => 'El archivo excede upload_max_filesize.',
                UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño permitido por el formulario.',
                UPLOAD_ERR_PARTIAL => 'El archivo fue subido parcialmente.',
                UPLOAD_ERR_NO_FILE => 'No se envió ningún archivo.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal en el servidor.',
                UPLOAD_ERR_CANT_WRITE => 'Fallo al escribir el archivo en disco.',
                UPLOAD_ERR_EXTENSION => 'Subida detenida por extensión PHP.'
            ];
            return $messages[$code] ?? 'Error desconocido al subir el archivo.';
        };

        if (!isset($_FILES['csv_file'])) {
            $resp = ['success' => false, 'message' => 'No se recibió el archivo CSV.'];
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode($resp);
                exit;
            }
            set_flash('error', $resp['message']);
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }
        if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $msg = $uploadErrorMsg($_FILES['csv_file']['error']);
            $resp = ['success' => false, 'message' => 'Error al subir el archivo: ' . $msg];
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode($resp);
                exit;
            }
            set_flash('error', $resp['message']);
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }

    $tmp = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmp, 'r');
        if ($handle === false) {
            set_flash('error', 'No se pudo abrir el archivo CSV.');
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }

        // Detectar delimiter leyendo la primera línea cruda (y quitar BOM si existe)
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            set_flash('error', 'El CSV parece estar vacío.');
            fclose($handle);
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }

        // Eliminar BOM UTF-8 si existe
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);

        $countComma = substr_count($firstLine, ',');
        $countSemi = substr_count($firstLine, ';');
        // Si hay más ; que , asumimos punto y coma como separador (locales que usan ;)
        $delimiter = ($countSemi > $countComma) ? ';' : ',';

        // Volver al inicio para usar fgetcsv correctamente
        rewind($handle);

        // Leer header con el delimitador detectado
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            set_flash('error', 'No se pudo leer la cabecera del CSV.');
            fclose($handle);
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }

        // Normalizar cabeceras (trim y lowercase)
        $cols = array_map(function($c){ return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $c))); }, $header);

        // Campos mínimos requeridos
        $required = ['numero_orden','destinatario','telefono','producto','cantidad','direccion','latitud','longitud'];
        $missing = [];
        foreach ($required as $r) {
            if (!in_array($r, $cols)) $missing[] = $r;
        }
        if (!empty($missing)) {
            // Si faltan columnas requeridas, informar indicando el delimitador detectado
            $detected = $delimiter === ',' ? 'coma (,)' : 'punto y coma (;)';
            set_flash('error', 'Faltan columnas requeridas en el CSV: ' . implode(', ', $missing) . ". Detectado separador: $detected. Asegúrate de que el archivo esté correctamente tabulado. Recomendado: usar comas.");
            fclose($handle);
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }

    $line = 1;
    $success = 0;
    $errors = [];
    $lotePendiente = [];

        // Leer filas usando coma como delimitador explícito
        // Si el delimitador detectado no es coma, avisar al usuario pero procesar igual
        $warnSemicolon = ($delimiter !== ',');

        // Leer línea por línea y usar str_getcsv para permitir intentar distintos delimitadores
        while (!feof($handle)) {
            $raw = fgets($handle);
            if ($raw === false) break;
            $line++;

            // Quitar BOM en primera columna si aparece aquí
            if ($line === 2) {
                $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
            }

            // Intentar parsear con el delimitador detectado
            $row = str_getcsv(rtrim($raw, "\r\n"), $delimiter, '"');

            // Si el número de columnas no coincide con la cabecera, intentar con el otro delimitador
            $expectedCols = count($cols);
            if (count($row) !== $expectedCols) {
                $alt = $delimiter === ',' ? ';' : ',';
                $altRow = str_getcsv(rtrim($raw, "\r\n"), $alt, '"');
                if (count($altRow) === $expectedCols) {
                    $row = $altRow;
                    // marcar advertencia sobre delimitador
                    $errors[] = "Línea $line: se detectó un delimitador alternativo ('$alt') en la fila — se procesó, pero revisa consistencia del archivo.";
                }
            }

            // Mapear fila a asociativo según cabeceras
            $dataRow = [];
            foreach ($cols as $i => $colName) {
                $val = isset($row[$i]) ? $row[$i] : null;
                // Normalizar: trim y convertir comas decimales a punto en números
                if (is_string($val)) {
                    $val = trim($val);
                }
                $dataRow[$colName] = $val;
            }

            // Omitir filas vacías (todas las columnas vacías)
            $allEmpty = true;
            foreach ($dataRow as $v) { if ($v !== null && $v !== '') { $allEmpty = false; break; } }
            if ($allEmpty) continue;

            // Preparar datos para crearPedido
            $lat = $dataRow['latitud'] ?? null;
            $lng = $dataRow['longitud'] ?? null;
            // Normalizar decimales con coma -> punto
            if (is_string($lat)) $lat = str_replace(',', '.', $lat);
            if (is_string($lng)) $lng = str_replace(',', '.', $lng);

            if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
                $rowPreview = implode($delimiter === ',' ? ',' : ';', array_slice($row, 0, 6));
                $errors[] = "Línea $line: coordenadas inválidas. Fila: $rowPreview";
                continue;
            }

            // Coerce to float and detect possible swapped lat/long (user provided long,lat)
            $latF = (float)$lat;
            $lngF = (float)$lng;
            // Latitude valid range is -90..90, longitude -180..180. If first value outside lat range
            // but second is inside lat range, it's likely the CSV used long,lat order — swap.
            if (abs($latF) > 90 && abs($lngF) <= 90) {
                // swap
                $tmp = $latF; $latF = $lngF; $lngF = $tmp;
                $errors[] = "Línea $line: se detectó posible inversión de lat/long y se corrigió.";
            }

            $pedidoData = [
                'numero_orden' => $dataRow['numero_orden'] ?? null,
                'destinatario' => $dataRow['destinatario'] ?? null,
                'telefono' => $dataRow['telefono'] ?? null,
                'precio' => $dataRow['precio'] ?? null,
                'producto' => $dataRow['producto'] ?? null,
                'cantidad' => isset($dataRow['cantidad']) && $dataRow['cantidad'] !== '' ? (int)$dataRow['cantidad'] : 1,
                'pais' => $dataRow['pais'] ?? null,
                'departamento' => $dataRow['departamento'] ?? null,
                'municipio' => $dataRow['municipio'] ?? null,
                'barrio' => $dataRow['barrio'] ?? null,
                'direccion' => $dataRow['direccion'] ?? null,
                'zona' => $dataRow['zona'] ?? null,
                'comentario' => $dataRow['comentario'] ?? null,
                'latitud' => $latF,
                'longitud' => $lngF,
                'line' => $line
            ];

            // Evitar duplicados por numero_orden
            if (empty($pedidoData['numero_orden'])) {
                $errors[] = "Línea $line: numero_orden vacío.";
                continue;
            }

            if (PedidosModel::existeNumeroOrden($pedidoData['numero_orden'])) {
                $errors[] = "Línea $line: el número de orden {$pedidoData['numero_orden']} ya existe.";
                continue;
            }

            $lotePendiente[] = $pedidoData;
        }

        fclose($handle);

        if (!empty($lotePendiente)) {
            $resultadoLote = PedidosModel::insertarPedidosLote($lotePendiente);
            $success += $resultadoLote['inserted'];
            if (!empty($resultadoLote['errors'])) {
                $errors = array_merge($errors, $resultadoLote['errors']);
            }
        }

        $msg = "$success pedidos importados correctamente.";
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (!empty($errors)) {
            $msg .= " Problemas: " . count($errors) . ". Revisa errores detallados.";
            // Guardar errores extendidos en sesión para revisar
            $_SESSION['import_errors'] = $errors;
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg, 'errors' => $errors]);
                exit;
            }
            set_flash('error', $msg);
        } else {
            // Si se detectó punto y coma como separador, avisar que la importación se realizó pero se recomienda usar comas
            if ($warnSemicolon) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => $msg . ' (Se detectó punto y coma como separador; se procesó, pero se recomienda usar comas para compatibilidad.)']);
                    exit;
                }
                set_flash('success', $msg . ' (Se detectó punto y coma como separador; se procesó, pero se recomienda usar comas para compatibilidad.)');
            } else {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => $msg]);
                    exit;
                }
                set_flash('success', $msg);
            }
        }

        header('Location: ' . RUTA_URL . 'pedidos/listar');
        exit;
        } catch (Exception $e) {
            // Registrar error detallado en un archivo de log para depuración
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/import_errors.log';
            $errorId = date('YmdHis') . '-' . bin2hex(random_bytes(4));
            $dump = "[{$errorId}] " . date('c') . "\n";
            $dump .= "Message: " . $e->getMessage() . "\n";
            $dump .= "Trace: \n" . $e->getTraceAsString() . "\n";
            $dump .= "\
";
            $dump .= "\
";
            $dump .= "\
";
            $dump .= "\$_FILES: " . var_export($_FILES, true) . "\n";
            @file_put_contents($logFile, $dump, FILE_APPEND | LOCK_EX);

            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json', true, 500);
                echo json_encode(['success' => false, 'message' => 'Error interno durante la importación. ID: ' . $errorId]);
                exit;
            }
            // Para peticiones normales, mostrar un mensaje amigable y redirigir
            set_flash('error', 'Ocurrió un error interno durante la importación. Contacta al administrador con el ID: ' . $errorId);
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }
    }
    
    
}

