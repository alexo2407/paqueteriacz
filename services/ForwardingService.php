<?php
/**
 * ForwardingService
 *
 * Servicio orquestador que evalúa si un pedido debe ser reenviado a
 * proveedores externos y ejecuta el forwarding.
 *
 * Flujo:
 *  1. evaluarYReenviar($idPedido, $idCliente) — punto de entrada principal
 *  2. Busca reglas activas para el cliente
 *  3. Por cada regla, instancia el provider correspondiente
 *  4. Ejecuta authenticate() + createOrder()
 *  5. Registra resultado en forwarding_log
 *  6. Si falla y FORWARDING_SYNC_MODE = true, encola en logistics_queue
 */

require_once __DIR__ . '/../modelo/forwarding.php';

class ForwardingService
{
    /**
     * Mapa de slugs a clases de providers.
     * Para agregar un nuevo proveedor, solo añadir aquí y crear la clase.
     */
    private static $providerMap = [
        'logispro'  => 'LogisProProvider',
        'hlexpress' => 'HLExpressProvider',
        'caex'      => 'CAEXProvider',
        'dynamic'   => 'DynamicProvider',  // Motor dinámico configurable por UI
    ];

    /**
     * Evaluar si un pedido debe ser reenviado y ejecutar el forwarding.
     *
     * @param int $idPedido ID del pedido recién creado
     * @param int $idCliente ID del cliente dueño del pedido
     * @return array|null Resultados del forwarding o null si no aplica
     */
    public static function evaluarYReenviar($idPedido, $idCliente)
    {
        if (!$idCliente || $idCliente <= 0) return null;

        // Obtener reglas activas para este cliente
        $reglas = ForwardingModel::obtenerReglasActivasPorCliente($idCliente);
        if (empty($reglas)) return null;

        // Obtener datos del pedido
        $pedido = ForwardingModel::obtenerPedidoParaForwarding($idPedido);
        if (!$pedido) {
            error_log("ForwardingService: pedido $idPedido no encontrado para forwarding");
            return null;
        }

        $resultados = [];

        foreach ($reglas as $regla) {
            $resultado = self::ejecutarForwarding($pedido, $regla);
            $resultados[] = $resultado;
        }

        return $resultados;
    }

    /**
     * Ejecutar el forwarding de un pedido a un proveedor según una regla.
     *
     * @param array $pedido Datos del pedido
     * @param array $regla Regla con datos del proveedor (JOIN)
     * @return array Resultado: ['provider' => slug, 'success' => bool, 'message' => string, ...]
     */
    private static function ejecutarForwarding(array $pedido, array $regla)
    {
        $slug     = $regla['slug'];
        $logData = [
            'id_pedido'   => $pedido['id'],
            'id_provider' => $regla['id_provider'],
            'id_rule'     => $regla['id'],
            'status'      => 'pending',
        ];

        try {
            // Instanciar provider
            $provider = self::crearProvider($regla);

            // Merge config: provider_config + rule config_override
            $providerConfig = json_decode($regla['provider_config'] ?? '{}', true) ?: [];
            $ruleOverride   = json_decode($regla['config_override'] ?? '{}', true) ?: [];
            $config = array_merge($providerConfig, $ruleOverride);

            // Autenticarse
            $authData = $provider->authenticate();

            // Preparar payload y registrar request
            $payload = $provider->mapearCampos($pedido, $pedido['productos'] ?? [], $authData);
            $logData['request_payload'] = is_string($payload)
                ? $payload
                : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Registrar log como pending
            $logId = ForwardingModel::registrarLog($logData);

            // Crear la orden
            $resultado = $provider->createOrder($pedido, $pedido['productos'] ?? [], $authData);

            // Actualizar log con resultado exitoso
            ForwardingModel::actualizarLog($logId, [
                'status'            => 'success',
                'http_status'       => $resultado['http_status'] ?? 200,
                'response_payload'  => json_encode($resultado['response'] ?? [], JSON_UNESCAPED_UNICODE),
                'external_order_id' => $resultado['external_order_id'] ?? null,
            ]);

            return [
                'provider'          => $slug,
                'success'           => true,
                'message'           => 'Pedido reenviado exitosamente a ' . ($regla['provider_nombre'] ?? $slug),
                'external_order_id' => $resultado['external_order_id'] ?? null,
            ];

        } catch (Exception $e) {
            error_log("ForwardingService error [{$slug}] pedido {$pedido['id']}: " . $e->getMessage());

            // Capturar HTTP status desde el código de la excepción (si fue lanzada con él)
            $httpStatus = $e->getCode() ?: 0;

            // Intentar recuperar el body raw de la respuesta del proveedor para guardarlo en el log
            $rawResponse = null;
            if (isset($provider) && method_exists($provider, 'getLastResponse')) {
                $lastResp = $provider->getLastResponse();
                if ($lastResp) {
                    // Preferimos el body legible; si tiene decoded lo formateamos, si no, el body crudo
                    $rawResponse = $lastResp['body'] ?: null;
                    // Si el http_status no fue capturado vía código de excepción, usarlo del response
                    if (!$httpStatus && !empty($lastResp['http_status'])) {
                        $httpStatus = (int)$lastResp['http_status'];
                    }
                }
            }

            // Actualizar log con error
            $logData['status']        = 'failed';
            $logData['error_message'] = substr($e->getMessage(), 0, 1000);
            $logData['http_status']   = $httpStatus;

            if (isset($logId) && $logId) {
                $updateData = [
                    'status'           => 'failed',
                    'error_message'    => substr($e->getMessage(), 0, 1000),
                    'http_status'      => $httpStatus,
                ];
                if ($rawResponse !== null) {
                    $updateData['response_payload'] = $rawResponse;
                }
                ForwardingModel::actualizarLog($logId, $updateData);
            } else {
                if ($rawResponse !== null) {
                    $logData['response_payload'] = $rawResponse;
                }
                $logId = ForwardingModel::registrarLog($logData);
            }

            // Fallback a cola si está habilitado
            $retryQueued = self::encolarReintento($pedido['id'], $regla, $e->getMessage());

            return [
                'provider'     => $slug,
                'success'      => false,
                'message'      => 'Error al reenviar: ' . $e->getMessage(),
                'http_status'  => $httpStatus ?: null,
                'retry_queued' => $retryQueued,
                'log_id'       => $logId ?: null,
            ];
        }
    }

    /**
     * Crear una instancia del provider según el slug.
     *
     * @param array $regla Datos de la regla con info del proveedor
     * @return BaseProvider
     * @throws Exception si el provider no está soportado
     */
    private static function crearProvider(array $regla)
    {
        $slug = $regla['slug'];

        if (!isset(self::$providerMap[$slug])) {
            throw new Exception("Provider no soportado: $slug");
        }

        $className = self::$providerMap[$slug];
        $filePath  = __DIR__ . '/providers/' . $className . '.php';

        if (!file_exists($filePath)) {
            throw new Exception("Archivo de provider no encontrado: $filePath");
        }

        require_once $filePath;

        $credentials = json_decode($regla['credentials'] ?? '{}', true) ?: [];
        $config = [
            'auth_endpoint'   => $regla['auth_endpoint']  ?? '/api/AccountApi',
            'order_endpoint'  => $regla['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail',
            'auth_method'     => $regla['auth_method']    ?? 'bearer_jwt',
            // Para DynamicProvider: ID del proveedor y formato de payload
            'id_provider'     => (int)($regla['id_provider'] ?? 0),
            'payload_format'  => $regla['payload_format'] ?? 'json',
        ];

        return new $className($regla['base_url'], $credentials, $config);
    }

    /**
     * Encolar un reintento de forwarding en logistics_queue.
     *
     * @param int $idPedido
     * @param array $regla
     * @param string $error
     * @return bool True si se encoó correctamente, false si no
     */
    private static function encolarReintento($idPedido, array $regla, $error): bool
    {
        try {
            $queueFile = __DIR__ . '/LogisticsQueueService.php';
            if (file_exists($queueFile)) {
                require_once $queueFile;
                LogisticsQueueService::queue('forwarding_retry', $idPedido, [
                    'id_rule'       => $regla['id'],
                    'id_provider'   => $regla['id_provider'],
                    'slug'          => $regla['slug'],
                    'error_message' => substr($error, 0, 500),
                ]);
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("ForwardingService: error al encolar reintento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Probar conexión con un proveedor (test de login).
     * Usado desde la GUI admin para validar credenciales.
     *
     * @param array $providerData Datos del proveedor (base_url, credentials, auth_endpoint, etc.)
     * @return array ['success' => bool, 'message' => string, 'customersId' => int|null]
     */
    public static function testConexion(array $providerData)
    {
        try {
            $slug = $providerData['slug'] ?? 'logispro';

            if (!isset(self::$providerMap[$slug])) {
                return ['success' => false, 'message' => "Provider '$slug' no soportado"];
            }

            $className = self::$providerMap[$slug];
            require_once __DIR__ . '/providers/' . $className . '.php';

            $credentials = is_string($providerData['credentials'] ?? '')
                ? (json_decode($providerData['credentials'], true) ?: [])
                : ($providerData['credentials'] ?? []);

            $config = [
                'auth_endpoint'  => $providerData['auth_endpoint'] ?? '/api/AccountApi',
                'order_endpoint' => $providerData['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail',
            ];

            $provider = new $className($providerData['base_url'], $credentials, $config);

            // Limpiar cache para forzar re-autenticación
            if (method_exists($className, 'clearAuthCache')) {
                $className::clearAuthCache();
            }

            $authData = $provider->authenticate();

            return [
                'success'     => true,
                'message'     => 'Conexión exitosa',
                'customersId' => $authData['customersId'] ?? null,
                'token_preview' => substr($authData['token'] ?? '', 0, 20) . '...',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
