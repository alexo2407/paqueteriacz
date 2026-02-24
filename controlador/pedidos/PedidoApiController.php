<?php
/**
 * PedidoApiController
 * 
 * Controlador especializado para API REST de pedidos.
 * Responsabilidad: Manejar requests API y devolver respuestas JSON.
 */

require_once __DIR__ . '/../../modelo/usuario.php';
require_once __DIR__ . '/../../modelo/departamento.php';
require_once __DIR__ . '/../../modelo/municipio.php';
require_once __DIR__ . '/../../modelo/moneda.php';
require_once __DIR__ . '/../../modelo/barrio.php';

class PedidoApiController
{
    public function crear(array $jsonData, bool $autoEnqueue = false, int $authUserId = 0, $authUserRole = ''): array
    {
        $data = $jsonData;

        // Autocompletar ubicación desde código postal antes de validar
        $this->autoCompletarDesdeCP($data);

        // Determinar si el usuario es proveedor
        $isProvider = (is_numeric($authUserRole) && (int)$authUserRole === 4) || 
                      (is_string($authUserRole) && strcasecmp(trim($authUserRole), 'Proveedor') === 0);

        // Auto-detectar moneda del proveedor si no se envió id_moneda y el usuario es proveedor
        if ($isProvider && $authUserId > 0) {
            if (!isset($data['id_moneda']) || $data['id_moneda'] === '' || $data['id_moneda'] === 0) {
                $monedaProveedor = $this->obtenerMonedaDeProveedor($authUserId);
                if ($monedaProveedor) {
                    $data['id_moneda'] = $monedaProveedor;
                }
            }
        }

        // Validar la estructura del pedido
        $validacion = $this->validar($data);
        if (!$validacion["success"]) {
            return [
                "success" => false,
                "message" => "VALIDATION_ERROR",
                "fields" => $validacion["fields"] ?? []
            ];
        }

        // Normalización de Código Postal y Homologación
        if (!empty($data['codigo_postal'])) {
             require_once __DIR__ . '/../../services/AddressService.php';
             $cp_norm = AddressService::normalizarCP($data['codigo_postal']);
             $data['codigo_postal'] = $cp_norm;
             
             // Intentar resolver id_codigo_postal
             $homologacion = AddressService::resolverHomologacion(
                 isset($data['id_pais']) ? (int)$data['id_pais'] : 0,
                 $cp_norm,
                 [
                     'id_departamento' => $data['id_departamento'] ?? null,
                     'id_municipio' => $data['id_municipio'] ?? null,
                     'zona' => $data['zona'] ?? null
                 ]
             );
             
             if ($homologacion && isset($homologacion['id'])) {
                 $data['id_codigo_postal'] = $homologacion['id'];
             }
        }

        // El chequeo de existeNumeroOrden ahora está dentro de validar(), 
        // pero por seguridad si por alguna razón fallara allí:
        if (PedidosModel::existeNumeroOrden($data["numero_orden"], $data['id_cliente'])) {
            return [
                "success" => false,
                "message" => "VALIDATION_ERROR",
                "fields" => ["numero_orden" => "El número de orden ya existe para este cliente."]
            ];
        }

        // Procesar productos
        $items = $this->procesarProductos($data);
        
        // Procesar coordenadas
        list($latitud, $longitud) = $this->procesarCoordenadas($data);

        // Validar y procesar relaciones
        $this->validarRelaciones($data);

        // Calcular precios
        $precioLocal = $this->extraerPrecioLocal($data);
        $monedaId = isset($data['id_moneda']) && is_numeric($data['id_moneda']) ? (int)$data['id_moneda'] : null;
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
        
        // Determinar si el usuario es proveedor (comparar SOLO por nombre, no por ID numérico
        // porque el ID numérico de rol varía entre sistemas y puede colisionar con otros roles)
        $isProvider = is_string($authUserRole) && strcasecmp(trim($authUserRole), 'Proveedor') === 0;

        foreach ($payload['pedidos'] as $pedido) {
            $itemResult = ['numero_orden' => $pedido['numero_orden'] ?? null, 'success' => false];

            // Autocompletar ubicación desde código postal antes de validar
            $this->autoCompletarDesdeCP($pedido);

            // Si el usuario es Proveedor, forzar su ID (seguridad: un proveedor no puede crear
            // pedidos bajo otro proveedor)
            if ($isProvider && $authUserId > 0) {
                $pedido['id_proveedor'] = $authUserId;
                $pedido['proveedor'] = $authUserId;
            }

            // Auto-detectar moneda del proveedor si no se envió id_moneda
            if (!isset($pedido['id_moneda']) || $pedido['id_moneda'] === '' || (int)($pedido['id_moneda'] ?? 0) === 0) {
                $provId = $pedido['id_proveedor'] ?? $pedido['proveedor'] ?? null;
                if ($provId) {
                    $monedaAuto = $this->obtenerMonedaDeProveedor((int)$provId);
                    if ($monedaAuto) {
                        $pedido['id_moneda'] = $monedaAuto;
                    }
                }
            }


            // Validación básica
            $valid = $this->validar($pedido);
            if (!$valid['success']) {
                $itemResult['error'] = "VALIDATION_ERROR";
                $itemResult['fields'] = $valid['fields'] ?? [];
                $results[] = $itemResult;
                continue;
            }

            // Normalización de Código Postal y Homologación
            if (!empty($pedido['codigo_postal'])) {
                 require_once __DIR__ . '/../../services/AddressService.php';
                 $cp_norm = AddressService::normalizarCP($pedido['codigo_postal']);
                 $pedido['codigo_postal'] = $cp_norm;
                 
                 $homologacion = AddressService::resolverHomologacion(
                     isset($pedido['id_pais']) ? (int)$pedido['id_pais'] : 0,
                     $cp_norm,
                     [
                         'id_departamento' => $pedido['id_departamento'] ?? null,
                         'id_municipio' => $pedido['id_municipio'] ?? null,
                         'zona' => $pedido['zona'] ?? null
                     ]
                 );
                 
                 if ($homologacion && isset($homologacion['id'])) {
                     $pedido['id_codigo_postal'] = $homologacion['id'];
                 }
            }

            // Unicidad
            if (PedidosModel::existeNumeroOrden($pedido["numero_orden"], $pedido['id_cliente'])) {
                $itemResult['error'] = "VALIDATION_ERROR";
                $itemResult['fields'] = ["numero_orden" => "El número de orden ya existe para este cliente."];
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
     * Autocompletar campos de ubicación (país, depto, municipio) desde CP
     */
    private function autoCompletarDesdeCP(&$data) {
        if (empty($data['codigo_postal'])) return;

        require_once __DIR__ . '/../../services/AddressService.php';
        $cp_norm = AddressService::normalizarCP($data['codigo_postal']);
        
        $cp_info = null;

        // 1. Si tenemos id_pais, buscar específico
        if (!empty($data['id_pais']) && is_numeric($data['id_pais'])) {
            $cp_info = CodigosPostalesModel::buscar((int)$data['id_pais'], $cp_norm);
        } else {
            // 2. Si no tenemos id_pais, buscar globalmente
            $global_results = CodigosPostalesModel::buscarGlobal($cp_norm);
            if (count($global_results) > 0) {
                // Si hay resultados, e.g. todos pertenecen al mismo país o solo hay uno, lo tomamos
                $first = $global_results[0];
                $all_same_location = true;
                foreach ($global_results as $res) {
                    if ($res['id_pais'] != $first['id_pais'] || 
                        $res['id_departamento'] != $first['id_departamento'] || 
                        $res['id_municipio'] != $first['id_municipio']) {
                        $all_same_location = false;
                        break;
                    }
                }
                if ($all_same_location) {
                    $cp_info = $first;
                }
            }
        }

        if ($cp_info) {
            if (empty($data['id_pais'])) $data['id_pais'] = $cp_info['id_pais'];
            if (empty($data['id_departamento'])) $data['id_departamento'] = $cp_info['id_departamento'];
            if (empty($data['id_municipio'])) $data['id_municipio'] = $cp_info['id_municipio'];
            if (empty($data['id_barrio']) && !empty($cp_info['id_barrio'])) $data['id_barrio'] = $cp_info['id_barrio'];
        }
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

        // 1. Campos obligatorios y sus reglas básicas (STRICT REQUIRED)
        $rules = [
            'numero_orden' => ['required' => true, 'numeric' => true, 'min' => 1],
            'destinatario' => ['required' => true, 'min_len' => 2],
            'id_cliente'   => ['required' => true, 'numeric' => true],
            'id_proveedor' => ['required' => true, 'numeric' => true],
            'telefono'     => ['required' => true, 'min_len' => 7],
            'direccion'    => ['required' => true, 'min_len' => 5],
            'comentario'   => ['required' => true, 'min_len' => 1],
            'id_pais'      => ['required' => false, 'numeric' => true],
            'id_departamento' => ['required' => false, 'numeric' => true],
            'id_municipio' => ['required' => false, 'numeric' => true],
            'zona'         => ['required' => false, 'max_len' => 100],
            'codigo_postal'=> ['required' => false],
            'fecha_entrega' => ['required' => false, 'date' => true],
            'precio_total_local' => ['required' => true, 'numeric' => true, 'min_val' => 0.01],
            'es_combo'     => ['required' => true, 'in' => [0, 1]]
        ];

        foreach ($rules as $field => $config) {
            $val = isset($data[$field]) ? $data[$field] : null;

            if ($config['required'] && ($val === null || $val === '')) {
                $errores[$field] = "El campo '$field' es obligatorio.";
                continue;
            }

            if (!$config['required'] && ($val === null || $val === '')) {
                continue;
            }

            if (isset($config['numeric']) && !is_numeric($val)) {
                $errores[$field] = "El campo '$field' debe ser numérico.";
                continue;
            }

            if (isset($config['min']) && (int)$val < $config['min']) {
                $errores[$field] = "El campo '$field' debe ser al menos " . $config['min'] . ".";
            }

            if (isset($config['min_val']) && (float)$val < $config['min_val']) {
                $errores[$field] = "El campo '$field' debe ser mayor a 0.";
            }

            if (isset($config['min_len']) && strlen(trim((string)$val)) < $config['min_len']) {
                $errores[$field] = "El campo '$field' debe tener al menos " . $config['min_len'] . " caracteres.";
            }

            if (isset($config['max_len']) && strlen((string)$val) > $config['max_len']) {
                $errores[$field] = "El campo '$field' no debe exceder los " . $config['max_len'] . " caracteres.";
            }

            if (isset($config['in']) && !in_array((int)$val, $config['in'], true)) {
                $errores[$field] = "El campo '$field' solo acepta los valores: " . implode(', ', $config['in']) . ".";
            }
        }

        // 2. Validar productos (producto_id como array o con al menos 1 elemento)
        $hasProductsArray = isset($data['productos']) && is_array($data['productos']) && count($data['productos']) > 0;
        $hasSingleProductId = isset($data['producto_id']) && is_numeric($data['producto_id']);
        
        if (!$hasSingleProductId && !$hasProductsArray) {
            $errores['producto_id'] = "El campo 'producto_id' o 'productos' es obligatorio y debe contener al menos 1 producto.";
        }

        if ($hasProductsArray) {
            foreach ($data['productos'] as $i => $pi) {
                if (!isset($pi['producto_id']) || !is_numeric($pi['producto_id'])) {
                    $errores["productos[$i][producto_id]"] = "Debe ser numérico.";
                }
                if (!isset($pi['cantidad']) || !is_numeric($pi['cantidad']) || (int)$pi['cantidad'] <= 0) {
                    $errores["productos[$i][cantidad]"] = "Debe ser mayor a cero.";
                }
            }
        }

        // 3. Validar existencia de cliente
        if (!isset($errores['id_cliente'])) {
            $cliente = (new UsuarioModel())->obtenerPorId((int)$data['id_cliente']);
            if (!$cliente) {
                $errores['id_cliente'] = "El cliente especificado no existe.";
            }
        }

        // 4. Validar unicidad de numero_orden por cliente
        if (!isset($errores['numero_orden']) && !isset($errores['id_cliente'])) {
            if (PedidosModel::existeNumeroOrden($data['numero_orden'], $data['id_cliente'])) {
                $errores['numero_orden'] = "El número de orden ya existe para este cliente.";
            }
        }

        // 5. Validar Jerarquía Geográfica (solo si se proporcionan los campos)
        if (!isset($errores['id_departamento']) && !empty($data['id_departamento']) && !empty($data['id_pais'])) {
            $depto = DepartamentoModel::obtenerPorId((int)$data['id_departamento']);
            if (!$depto) {
                $errores['id_departamento'] = "El departamento especificado no existe.";
            } elseif ((int)$depto['id_pais'] !== (int)$data['id_pais']) {
                $errores['id_departamento'] = "El departamento no pertenece al país seleccionado.";
            }
        }

        if (!isset($errores['id_municipio']) && !empty($data['id_municipio']) && !empty($data['id_departamento'])) {
            $muni = MunicipioModel::obtenerPorId((int)$data['id_municipio']);
            if (!$muni) {
                $errores['id_municipio'] = "El municipio especificado no existe.";
            } elseif ((int)$muni['id_departamento'] !== (int)$data['id_departamento']) {
                $errores['id_municipio'] = "El municipio no pertenece al departamento seleccionado.";
            }
        }

        if (!empty($errores)) {
            return ["success" => false, "message" => "VALIDATION_ERROR", "fields" => $errores];
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
            'codigo_postal' => $data['codigo_postal'] ?? null,
            'precio_local' => $precioLocal,
            'precio_usd' => $precioUsd,
            // Combo pricing fields
            'precio_total_local' => $precioTotalLocal,
            'precio_total_usd' => $precioTotalUsd,
            'tasa_conversion_usd' => $tasaConversionUsd,
            'es_combo' => $esCombo,
            'fecha_entrega' => $data['fecha_entrega'] ?? null,
            'id_codigo_postal' => $data['id_codigo_postal'] ?? null,
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
            'estado' => isset($pedido['id_estado']) ? (int)$pedido['id_estado'] : (isset($pedido['estado']) ? (int)$pedido['estado'] : 1),
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
            'codigo_postal' => $pedido['codigo_postal'] ?? null,
            'precio_local' => $pedido['precio_local'] ?? $pedido['precio'] ?? null,
            'precio_usd' => $pedido['precio_usd'] ?? null,
            // Combo pricing fields
            'precio_total_local' => $pedido['precio_total_local'] ?? null,
            'precio_total_usd' => $pedido['precio_total_usd'] ?? null,
            'tasa_conversion_usd' => $pedido['tasa_conversion_usd'] ?? null,
            'es_combo' => $esCombo,
            'fecha_entrega' => $pedido['fecha_entrega'] ?? null,
            'id_codigo_postal' => $pedido['id_codigo_postal'] ?? null,
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

    /**
     * Obtener la moneda local del proveedor basada en su país
     * 
     * @param int $proveedorId
     * @return int|null ID de la moneda o null si no se encuentra
     */
    private function obtenerMonedaDeProveedor(int $proveedorId): ?int
    {
        try {
            $conexion = new Conexion();
            $db = $conexion->conectar();
            
            // Obtener el id_pais del proveedor
            $stmt = $db->prepare("
                SELECT u.id_pais 
                FROM usuarios u 
                WHERE u.id = :proveedor_id
            ");
            $stmt->bindValue(':proveedor_id', $proveedorId, PDO::PARAM_INT);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("DEBUG - Proveedor ID: $proveedorId, id_pais: " . ($usuario['id_pais'] ?? 'NULL'));
            
            if (!$usuario || !$usuario['id_pais']) {
                error_log("DEBUG - Proveedor sin id_pais configurado");
                return null;
            }
            
            // Obtener la moneda local del país
            $stmt = $db->prepare("
                SELECT id_moneda_local 
                FROM paises 
                WHERE id = :id_pais
            ");
            $stmt->bindValue(':id_pais', $usuario['id_pais'], PDO::PARAM_INT);
            $stmt->execute();
            $pais = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("DEBUG - País ID: " . $usuario['id_pais'] . ", id_moneda_local: " . ($pais['id_moneda_local'] ?? 'NULL'));
            
            if ($pais && $pais['id_moneda_local']) {
                error_log("DEBUG - Moneda detectada: " . $pais['id_moneda_local']);
                return (int)$pais['id_moneda_local'];
            }
            
            error_log("DEBUG - País sin id_moneda_local configurado");
            return null;
        } catch (Exception $e) {
            error_log("Error obteniendo moneda del proveedor: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validar permisos de edición de pedido
     * 
     * @param int $pedidoId ID del pedido a editar
     * @param int $userId ID del usuario que intenta editar
     * @param mixed $userRole Rol del usuario (1 o 'Admin' para admin, 4 o 'Proveedor' para proveedor)
     * @param array $camposAEditar Lista de campos que se intentan editar
     * @return array ['permitido' => bool, 'mensaje' => string (si no permitido)]
     */
    public function validarPermisosEdicion(int $pedidoId, int $userId, $userRole, array $camposAEditar): array
    {
        // Admin puede editar todo siempre
        $isAdmin = (is_numeric($userRole) && (int)$userRole === 1) || 
                   (is_string($userRole) && strcasecmp(trim($userRole), 'Admin') === 0);
        
        if ($isAdmin) {
            return ['permitido' => true];
        }
        
        // Obtener datos del pedido
        $pedido = PedidosModel::obtenerPedidoPorId($pedidoId);
        
        if (!$pedido) {
            return [
                'permitido' => false,
                'mensaje' => 'Pedido no encontrado'
            ];
        }
        
        // Verificar que sea el proveedor del pedido
        if ($pedido['id_proveedor'] != $userId) {
            return [
                'permitido' => false,
                'mensaje' => 'Solo puedes editar tus propios pedidos'
            ];
        }
        
        // Verificar si está bloqueado
        if (isset($pedido['bloqueado_edicion']) && $pedido['bloqueado_edicion'] == 1) {
            return [
                'permitido' => false,
                'mensaje' => 'Este pedido está bloqueado para edición. Contacta al administrador.'
            ];
        }
        
        // Verificar estado (solo En bodega = 1)
        if ($pedido['id_estado'] != 1) {
            return [
                'permitido' => false,
                'mensaje' => 'Solo puedes editar pedidos en estado En bodega'
            ];
        }
        
        // Validar campos prohibidos para proveedores
        $camposProhibidos = [
            'producto_id', 'productos', 'cantidad', 'precio_total_local',
            'precio_total_usd', 'tasa_conversion_usd', 'id_moneda',
            'es_combo', 'numero_orden', 'id_estado', 'id_vendedor', 
            'id_proveedor', 'estado', 'vendedor', 'proveedor', 'moneda'
        ];
        
        $camposInvalidos = array_intersect($camposAEditar, $camposProhibidos);
        if (!empty($camposInvalidos)) {
            return [
                'permitido' => false,
                'mensaje' => 'No puedes editar estos campos: ' . implode(', ', $camposInvalidos)
            ];
        }
        
        return ['permitido' => true];
    }
}
