<?php
/**
 * PedidoApiController
 * 
 * Controlador especializado para API REST de pedidos.
 * Responsabilidad: Manejar requests API y devolver respuestas JSON.
 */

class PedidoApiController
{
    public function crear(array $jsonData, bool $autoEnqueue = false, int $authUserId = 0, $authUserRole = ''): array
    {
        $data = $jsonData;

        // Determinar si el usuario es proveedor
        $isProvider = (is_numeric($authUserRole) && (int)$authUserRole === 4) || 
                      (is_string($authUserRole) && strcasecmp(trim($authUserRole), 'Proveedor') === 0);

        // Si el usuario es Proveedor, forzar su ID como proveedor del pedido
        if ($isProvider && $authUserId > 0) {
            $data['id_proveedor'] = $authUserId;
            $data['proveedor'] = $authUserId;
        }

        // Validar la estructura del pedido
        $validacion = $this->validar($data);
        if (!$validacion["success"]) {
            $msg = $validacion["message"];
            if (isset($validacion["data"]) && is_array($validacion["data"])) {
                $msg .= " Detalles: " . implode(", ", $validacion["data"]);
            }
            throw new Exception($msg, 400);
        }

        // Verificar si el número de orden ya existe
        if (PedidosModel::existeNumeroOrden($data["numero_orden"])) {
            throw new Exception("El número de orden ya existe en la base de datos.", 400);
        }

        // Procesar productos
        $items = $this->procesarProductos($data);
        
        // Procesar coordenadas
        list($latitud, $longitud) = $this->procesarCoordenadas($data);

        // Validar y procesar relaciones
        $this->validarRelaciones($data);

        // Calcular precios
        $precioLocal = $this->extraerPrecioLocal($data);
        $monedaId = isset($data['id_moneda']) ? (int)$data['id_moneda'] : null;
        if ($monedaId === 0) $monedaId = null;
        
        $precioUsd = $this->calcularPrecioUsd($precioLocal, $monedaId);

        // Construir payload
        $pedidoPayload = $this->construirPayload($data, $latitud, $longitud, $precioLocal, $precioUsd, $monedaId);

        // Crear pedido
        $nuevoId = PedidosModel::crearPedidoConProductos($pedidoPayload, $items);
        
        // Encolar si se solicita
        if ($autoEnqueue && $nuevoId) {
            try {
                require_once __DIR__ . '/../../services/LogisticsQueueService.php';
                LogisticsQueueService::queue('generar_guia', $nuevoId);
            } catch (Exception $e) {
                // No fallar la creación por error en la cola, pero registrar
                error_log("Error auto_enqueue: " . $e->getMessage());
            }
        }
        
        return [
            "success" => true,
            "message" => "Pedido creado correctamente.",
            "data" => $pedidoPayload['numero_orden']
        ];
    }

    public function crearMultiple(array $payload, bool $autoEnqueue = false, int $authUserId = 0, $authUserRole = ''): array
    {
        if (!isset($payload['pedidos']) || !is_array($payload['pedidos'])) {
            return ['error' => "JSON inválido o falta la clave 'pedidos' (esperado array)."];
        }

        $results = [];
        
        // Determinar si el usuario es proveedor
        $isProvider = (is_numeric($authUserRole) && (int)$authUserRole === 4) || 
                      (is_string($authUserRole) && strcasecmp(trim($authUserRole), 'Proveedor') === 0);

        foreach ($payload['pedidos'] as $pedido) {
            $itemResult = ['numero_orden' => $pedido['numero_orden'] ?? null, 'success' => false];

            // Si el usuario es Proveedor, forzar su ID
            if ($isProvider && $authUserId > 0) {
                $pedido['id_proveedor'] = $authUserId;
                $pedido['proveedor'] = $authUserId;
            }

            // Validación básica
            $valid = $this->validar($pedido);
            if (!$valid['success']) {
                $itemResult['error'] = is_string($valid['message']) ? $valid['message'] : json_encode($valid['data']);
                $results[] = $itemResult;
                continue;
            }

            // Construir payload
            $modelPayload = $this->construirPayloadMultiple($pedido);
            
            // Construir items
            $items = $this->procesarProductosMultiple($pedido);

            if (count($items) === 0) {
                $itemResult['error'] = 'El pedido debe incluir al menos un producto válido.';
                $results[] = $itemResult;
                continue;
            }

            try {
                $nuevoId = PedidosModel::crearPedidoConProductos($modelPayload, $items);
                $itemResult['success'] = true;
                $itemResult['id_pedido'] = $nuevoId;
                
                // Encolar si se solicita
                if ($autoEnqueue && $nuevoId) {
                    try {
                        require_once __DIR__ . '/../../services/LogisticsQueueService.php';
                        LogisticsQueueService::queue('generar_guia', $nuevoId);
                    } catch (Exception $qe) {
                        error_log("Error auto_enqueue multiple: " . $qe->getMessage());
                    }
                }
                
                $results[] = $itemResult;
            } catch (Exception $e) {
                $itemResult['error'] = 'Error al insertar pedido: ' . $e->getMessage();
                $results[] = $itemResult;
            }
        }

        return ['results' => $results];
    }

    /**
     * Validar datos de pedido
     * 
     * @param array $data
     * @return array
     */
    public function validar(array $data): array
    {
        $errores = [];

        // Campos obligatorios
        $camposObligatorios = ["numero_orden", "destinatario"];
        foreach ($camposObligatorios as $campo) {
            if (!isset($data[$campo]) || $data[$campo] === '') {
                $errores[] = "El campo '$campo' es obligatorio.";
            }
        }

        // Validar productos
        $hasProductsArray = isset($data['productos']) && is_array($data['productos']) && count($data['productos']) > 0;
        $hasSingleProductId = isset($data['producto_id']) && is_numeric($data['producto_id']);
        
        if (!$hasSingleProductId && !$hasProductsArray) {
            $errores[] = "El campo 'producto_id' o 'productos' es obligatorio.";
        }

        // Validar estructura de productos array
        if ($hasProductsArray) {
            foreach ($data['productos'] as $i => $pi) {
                if (!isset($pi['producto_id']) || !is_numeric($pi['producto_id'])) {
                    $errores[] = "El elemento productos[$i] debe incluir 'producto_id' numérico.";
                }
                if (!isset($pi['cantidad']) || !is_numeric($pi['cantidad']) || (int)$pi['cantidad'] <= 0) {
                    $errores[] = "El elemento productos[$i] debe incluir 'cantidad' mayor a cero.";
                }
            }
        }

        // Validar coordenadas
        if (isset($data["coordenadas"]) && $data["coordenadas"] !== '') {
            $coords = explode(',', $data["coordenadas"]);
            if (count($coords) !== 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
                $errores[] = "El campo 'coordenadas' debe estar en el formato 'latitud,longitud'.";
            }
        }

        if (!empty($errores)) {
            return ["success" => false, "message" => "Datos inválidos.", "data" => $errores];
        }

        return ["success" => true];
    }

    // ========== Métodos privados de ayuda ==========

    private function procesarProductos(array $data): array
    {
        $items = [];
        
        if (!empty($data['productos']) && is_array($data['productos'])) {
            foreach ($data['productos'] as $pi) {
                if (empty($pi['producto_id']) || !is_numeric($pi['producto_id'])) {
                    throw new Exception("Cada item en 'productos' debe incluir 'producto_id' numérico.");
                }
                $prodId = (int)$pi['producto_id'];
                $cantidadItem = isset($pi['cantidad']) ? (int)$pi['cantidad'] : 0;
                if ($cantidadItem <= 0) {
                    throw new Exception("Cada item en 'productos' debe incluir 'cantidad' mayor a cero.");
                }

                $items[] = [
                    'id_producto' => $prodId,
                    'cantidad' => $cantidadItem,
                    'cantidad_devuelta' => isset($pi['cantidad_devuelta']) ? (int)$pi['cantidad_devuelta'] : 0,
                ];
            }
        } else {
            if (empty($data['producto_id']) || !is_numeric($data['producto_id'])) {
                throw new Exception("El campo 'producto_id' es obligatorio y debe ser numérico.");
            }
            $productoId = (int)$data['producto_id'];
            $items = [[
                'id_producto' => $productoId,
                'cantidad' => isset($data['cantidad']) ? (int)$data['cantidad'] : 1,
                'cantidad_devuelta' => isset($data['cantidad_devuelta']) ? (int)$data['cantidad_devuelta'] : 0,
            ]];
        }

        return $items;
    }

    private function procesarCoordenadas(array $data): array
    {
        $latitud = $data['latitud'] ?? null;
        $longitud = $data['longitud'] ?? null;
        
        if ($latitud === null || $longitud === null) {
            if (!empty($data["coordenadas"]) && strpos($data["coordenadas"], ',') !== false) {
                $parts = array_map('trim', explode(',', $data['coordenadas']));
                $latitud = $parts[0];
                $longitud = $parts[1];
            }
        }

        // Si no se proporcionan coordenadas válidas, usar valores por defecto (0.0, 0.0)
        if (!is_numeric($latitud) || !is_numeric($longitud)) {
            return [0.0, 0.0];
        }

        return [(float)$latitud, (float)$longitud];
    }

    private function validarRelaciones(array $data): void
    {
        // Validar moneda
        $monedaId = isset($data['id_moneda']) ? (int)$data['id_moneda'] : null;
        if ($monedaId !== null && $monedaId !== 0) {
            $m = MonedaModel::obtenerPorId($monedaId);
            if (!$m) {
                throw new Exception("La moneda especificada no existe.", 400);
            }
        }

        // Validar vendedor
        $vendedorId = isset($data['id_vendedor']) && is_numeric($data['id_vendedor']) ? (int)$data['id_vendedor'] : null;
        if ($vendedorId !== null) {
            $uv = (new UsuarioModel())->obtenerPorId($vendedorId);
            if (!$uv) {
                throw new Exception("El vendedor especificado no existe.", 400);
            }
        }

        // Validar proveedor
        $proveedorId = isset($data['id_proveedor']) && is_numeric($data['id_proveedor']) ? (int)$data['id_proveedor'] : null;
        if ($proveedorId !== null) {
            $up = (new UsuarioModel())->obtenerPorId($proveedorId);
            if (!$up) {
                throw new Exception("El proveedor especificado no existe.", 400);
            }
        }

        // Validar municipio
        $municipioId = isset($data['id_municipio']) && is_numeric($data['id_municipio']) ? (int)$data['id_municipio'] : null;
        if ($municipioId !== null) {
            $mm = MunicipioModel::obtenerPorId($municipioId);
            if (!$mm) {
                throw new Exception("El municipio especificado no existe.", 400);
            }
        }

        // Validar barrio
        $barrioId = isset($data['id_barrio']) && is_numeric($data['id_barrio']) ? (int)$data['id_barrio'] : null;
        if ($barrioId !== null) {
            $bb = BarrioModel::obtenerPorId($barrioId);
            if (!$bb) {
                throw new Exception("El barrio especificado no existe.", 400);
            }
        }
    }

    private function extraerPrecioLocal(array $data): ?float
    {
        if (isset($data['precio_local']) && $data['precio_local'] !== '') {
            return (float)$data['precio_local'];
        }
        if (isset($data['precio']) && $data['precio'] !== '') {
            return (float)$data['precio'];
        }
        return null;
    }

    private function calcularPrecioUsd(?float $precioLocal, ?int $monedaId): ?float
    {
        if ($precioLocal !== null && $monedaId !== null) {
            $moneda = PedidosModel::obtenerMonedaPorId($monedaId);
            if ($moneda && isset($moneda['tasa_usd'])) {
                return round($precioLocal * (float)$moneda['tasa_usd'], 2);
            }
        }
        return null;
    }

    private function construirPayload(array $data, float $latitud, float $longitud, ?float $precioLocal, ?float $precioUsd, ?int $monedaId): array
    {
        $vendedorId = isset($data['id_vendedor']) && is_numeric($data['id_vendedor']) ? (int)$data['id_vendedor'] : null;
        $proveedorId = isset($data['id_proveedor']) && is_numeric($data['id_proveedor']) ? (int)$data['id_proveedor'] : null;
        $municipioId = isset($data['id_municipio']) && is_numeric($data['id_municipio']) ? (int)$data['id_municipio'] : null;
        $barrioId = isset($data['id_barrio']) && is_numeric($data['id_barrio']) ? (int)$data['id_barrio'] : null;
        $idCliente = isset($data['id_cliente']) && is_numeric($data['id_cliente']) ? (int)$data['id_cliente'] : null;

        // Combo pricing fields
        $precioTotalLocal = isset($data['precio_total_local']) && $data['precio_total_local'] !== '' ? (float)$data['precio_total_local'] : null;
        $precioTotalUsd = isset($data['precio_total_usd']) && $data['precio_total_usd'] !== '' ? (float)$data['precio_total_usd'] : null;
        $tasaConversionUsd = isset($data['tasa_conversion_usd']) && $data['tasa_conversion_usd'] !== '' ? (float)$data['tasa_conversion_usd'] : null;
        
        // Auto-calculate precio_total_usd if only local price is provided
        if ($precioTotalLocal !== null && $precioTotalUsd === null && $monedaId !== null) {
            $moneda = PedidosModel::obtenerMonedaPorId($monedaId);
            if ($moneda && isset($moneda['tasa_usd'])) {
                $tasa = (float)$moneda['tasa_usd'];
                $precioTotalUsd = round($precioTotalLocal / $tasa, 2);
                $tasaConversionUsd = $tasa;
            }
        }

        // Logic to auto-detect combo if not provided
        $esCombo = isset($data['es_combo']) ? (int)$data['es_combo'] : 0;
        
        // If not explicitly set to 1, check the product definition
        // This handles both "single product" (top-level id) and "product list" (first item)
        if ($esCombo === 0) {
            $prodId = $data['producto_id'] ?? $data['id_producto'] ?? null;
            
            // If top-level id missing, check first item in productos array
            if (!$prodId && !empty($data['productos']) && is_array($data['productos'])) {
                $firstItem = reset($data['productos']);
                $prodId = $firstItem['producto_id'] ?? $firstItem['id_producto'] ?? $firstItem['id'] ?? null;
            }

            if ($prodId) {
                 require_once __DIR__ . '/../../modelo/producto.php';
                 $prodData = ProductoModel::obtenerPorId($prodId);
                 if ($prodData && isset($prodData['es_combo']) && $prodData['es_combo'] == 1) {
                     $esCombo = 1;
                 }
            }
        }

        $payload = [
            'numero_orden' => $data['numero_orden'],
            'destinatario' => $data['destinatario'],
            'telefono' => $data['telefono'] ?? '',
            'direccion' => $data['direccion'] ?? '',
            'comentario' => $data['comentario'] ?? null,
            'estado' => isset($data['id_estado']) ? (int)$data['id_estado'] : 1,
            'vendedor' => $vendedorId,
            'proveedor' => $proveedorId,
            'id_moneda' => $monedaId ?? 0,
            'moneda' => $monedaId ?? 0,
            'id_vendedor' => $vendedorId,
            'vendedor' => $vendedorId,
            'id_proveedor' => $proveedorId,
            'proveedor' => $proveedorId,
            'id_cliente' => $idCliente,
            'latitud' => $latitud,
            'longitud' => $longitud,
            'id_pais' => $data['id_pais'] ?? $data['pais'] ?? null,
            'id_departamento' => $data['id_departamento'] ?? $data['departamento'] ?? null,
            'id_municipio' => $data['id_municipio'] ?? $data['municipio'] ?? null,
            'id_barrio' => $data['id_barrio'] ?? $data['barrio'] ?? null,
            'municipio' => $data['municipio_nombre'] ?? null,
            'barrio' => $data['barrio_nombre'] ?? null,
            'zona' => $data['zona'] ?? null,
            'precio_local' => $precioLocal,
            'precio_usd' => $precioUsd,
            // Combo pricing fields
            'precio_total_local' => $precioTotalLocal,
            'precio_total_usd' => $precioTotalUsd,
            'tasa_conversion_usd' => $tasaConversionUsd,
            'es_combo' => $esCombo,
        ];

        // Normalizar valores 0 a null
        if ($payload['vendedor'] === 0) $payload['vendedor'] = null;
        if ($payload['proveedor'] === 0) $payload['proveedor'] = null;
        if ($payload['moneda'] === 0) $payload['moneda'] = null;

        return $payload;
    }

    private function construirPayloadMultiple(array $pedido): array
    {
        // Logic to auto-detect combo if not provided
        $esCombo = isset($pedido['es_combo']) ? (int)$pedido['es_combo'] : 0;
        
        if ($esCombo === 0) {
            $prodId = $pedido['producto_id'] ?? $pedido['id_producto'] ?? null;
            
            // If top-level id missing, check first item in productos array
            if (!$prodId && !empty($pedido['productos']) && is_array($pedido['productos'])) {
                $firstItem = reset($pedido['productos']);
                $prodId = $firstItem['producto_id'] ?? $firstItem['id_producto'] ?? $firstItem['id'] ?? null;
            }

            if ($prodId) {
                 require_once __DIR__ . '/../../modelo/producto.php';
                 $prodData = ProductoModel::obtenerPorId($prodId);
                 if ($prodData && isset($prodData['es_combo']) && $prodData['es_combo'] == 1) {
                     $esCombo = 1;
                 }
            }
        }

        // Parsing coordenadas string (lat,lng) if provided
        $lat = isset($pedido['latitud']) ? (float)$pedido['latitud'] : (isset($pedido['latitude']) ? (float)$pedido['latitude'] : null);
        $lng = isset($pedido['longitud']) ? (float)$pedido['longitud'] : (isset($pedido['longitude']) ? (float)$pedido['longitude'] : null);

        if (($lat === null || $lng === null) && !empty($pedido['coordenadas'])) {
            $parts = array_map('trim', explode(',', $pedido['coordenadas']));
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $lat = (float)$parts[0];
                $lng = (float)$parts[1];
            }
        }

        return [
            'numero_orden' => isset($pedido['numero_orden']) ? (int)$pedido['numero_orden'] : null,
            'destinatario' => $pedido['destinatario'] ?? null,
            'telefono' => $pedido['telefono'] ?? null,
            'direccion' => $pedido['direccion'] ?? null,
            'comentario' => $pedido['comentario'] ?? null,
            'estado' => isset($pedido['estado']) ? (int)$pedido['estado'] : null,
            'id_moneda' => isset($pedido['id_moneda']) ? (int)$pedido['id_moneda'] : (isset($pedido['moneda']) ? (int)$pedido['moneda'] : null),
            'moneda' => isset($pedido['id_moneda']) ? (int)$pedido['id_moneda'] : (isset($pedido['moneda']) ? (int)$pedido['moneda'] : null),
            'id_vendedor' => isset($pedido['id_vendedor']) ? (int)$pedido['id_vendedor'] : (isset($pedido['vendedor']) ? (int)$pedido['vendedor'] : null),
            'vendedor' => isset($pedido['id_vendedor']) ? (int)$pedido['id_vendedor'] : (isset($pedido['vendedor']) ? (int)$pedido['vendedor'] : null),
            'id_proveedor' => isset($pedido['id_proveedor']) ? (int)$pedido['id_proveedor'] : (isset($pedido['proveedor']) ? (int)$pedido['proveedor'] : null),
            'proveedor' => isset($pedido['id_proveedor']) ? (int)$pedido['id_proveedor'] : (isset($pedido['proveedor']) ? (int)$pedido['proveedor'] : null),
            'id_cliente' => isset($pedido['id_cliente']) ? (int)$pedido['id_cliente'] : (isset($pedido['cliente']) ? (int)$pedido['cliente'] : null),
            'latitud' => $lat,
            'longitud' => $lng,
            'id_pais' => $pedido['id_pais'] ?? $pedido['pais'] ?? null,
            'id_departamento' => $pedido['id_departamento'] ?? $pedido['departamento'] ?? null,
            'id_municipio' => $pedido['id_municipio'] ?? $pedido['municipio'] ?? null,
            'id_barrio' => $pedido['id_barrio'] ?? $pedido['barrio'] ?? null,
            'municipio' => $pedido['municipio_nombre'] ?? null,
            'barrio' => $pedido['barrio_nombre'] ?? null,
            'zona' => $pedido['zona'] ?? null,
            'precio_local' => $pedido['precio_local'] ?? $pedido['precio'] ?? null,
            'precio_usd' => $pedido['precio_usd'] ?? null,
            // Combo pricing fields
            'precio_total_local' => $pedido['precio_total_local'] ?? null,
            'precio_total_usd' => $pedido['precio_total_usd'] ?? null,
            'tasa_conversion_usd' => $pedido['tasa_conversion_usd'] ?? null,
            'es_combo' => $esCombo,
        ];
    }

    private function procesarProductosMultiple(array $pedido): array
    {
        $items = [];
        
        if (isset($pedido['productos']) && is_array($pedido['productos']) && count($pedido['productos']) > 0) {
            foreach ($pedido['productos'] as $pi) {
                $pid = $pi['producto_id'] ?? $pi['id_producto'] ?? $pi['id'] ?? null;
                $qty = $pi['cantidad'] ?? $pi['qty'] ?? $pi['cantidad_producto'] ?? null;
                if (!is_numeric($pid) || !is_numeric($qty) || (int)$qty <= 0) continue;
                $items[] = ['id_producto' => (int)$pid, 'cantidad' => (int)$qty, 'cantidad_devuelta' => 0];
            }
        } else {
            $pid = $pedido['producto_id'] ?? $pedido['id_producto'] ?? null;
            $qty = $pedido['cantidad'] ?? $pedido['cantidad_producto'] ?? null;
            if (is_numeric($pid) && is_numeric($qty) && (int)$qty > 0) {
                $items[] = ['id_producto' => (int)$pid, 'cantidad' => (int)$qty, 'cantidad_devuelta' => 0];
            }
        }

        return $items;
    }
}
