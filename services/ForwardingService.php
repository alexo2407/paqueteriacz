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
        'c807'      => 'C807Provider',
        'dynamic'   => 'DynamicProvider',  // Motor dinámico configurable por UI
    ];

    /**
     * Evaluar si un pedido debe ser reenviado y ejecutar el forwarding.
     *
     * @param int $idPedido ID del pedido recién creado
     * @param int $idCliente ID del cliente dueño del pedido
     * @return array|null Resultados del forwarding o null si no aplica
     */
    public static function evaluarYReenviar($idPedido, $idCliente, $fromQueue = false)
    {
        if (!$idCliente || $idCliente <= 0) return null;

        // Obtener reglas activas para este cliente
        $reglas = ForwardingModel::obtenerReglasActivasPorCliente($idCliente);
        if (empty($reglas)) {
            error_log("ForwardingService::evaluarYReenviar: sin reglas activas para id_cliente={$idCliente}, id_pedido={$idPedido}. Forwarding omitido.");
            return null;
        }

        // Obtener datos del pedido
        $pedido = ForwardingModel::obtenerPedidoParaForwarding($idPedido);
        if (!$pedido) {
            error_log("ForwardingService: pedido $idPedido no encontrado para forwarding");
            return null;
        }

        $resultados = [];

        foreach ($reglas as $regla) {
            // Skip limpio: HL Express requiere code_city para crear el envío.
            // Si todavía no está asignado, se difiere sin registrar error en el log.
            if (($regla['slug'] ?? '') === 'hlexpress' && empty($pedido['code_city'])) {
                error_log("ForwardingService: pedido {$idPedido} diferido para hlexpress (sin code_city).");
                $resultados[] = [
                    'provider' => 'hlexpress',
                    'success'  => false,
                    'skipped'  => true,
                    'message'  => 'Forwarding diferido: se enviará cuando el proveedor asigne el código de ciudad (code_city).',
                ];
                continue;
            }

            // Evitar doble envío: si ya existe un log exitoso para este pedido+regla, omitir.
            if (ForwardingModel::yaFueEnviadoExitosamente((int)$idPedido, (int)$regla['id'])) {
                error_log("ForwardingService: pedido {$idPedido} ya fue enviado exitosamente a {$regla['slug']} (regla {$regla['id']}). Omitido.");
                $resultados[] = [
                    'provider' => $regla['slug'],
                    'success'  => true,
                    'skipped'  => true,
                    'message'  => 'Forwarding omitido: el pedido ya fue enviado exitosamente anteriormente.',
                ];
                continue;
            }

            $resultado = self::ejecutarForwarding($pedido, $regla, $fromQueue);
            $resultados[] = $resultado;
        }

        return $resultados;
    }

    /**
     * Evaluar y reenviar un lote de pedidos recién importados masivamente (CSV/Excel).
     * Si el proveedor soporta despacho en lote (como C807), se agrupan en una única
     * solicitud con un solo código de recolección para todo el lote.
     * Si el proveedor no soporta lote, se procesan según la política individual.
     *
     * @param array $todosPedidosCreados Array de pedidos creados [['id' => int, 'numero_orden' => string, 'id_cliente' => int, ...]]
     * @return array Resumen de resultados de forwarding por lote
     */
    public static function evaluarYReenviarLote(array $todosPedidosCreados): array
    {
        if (empty($todosPedidosCreados) || !defined('FORWARDING_ENABLED') || !FORWARDING_ENABLED) {
            return [];
        }

        // Agrupar pedidos por id_cliente
        $pedidosPorCliente = [];
        foreach ($todosPedidosCreados as $ped) {
            $idCliente = (int)($ped['id_cliente'] ?? 0);
            if ($idCliente > 0) {
                $pedidosPorCliente[$idCliente][] = $ped;
            }
        }

        $resumenLote = [];

        foreach ($pedidosPorCliente as $idCliente => $pedidosCliente) {
            $reglas = ForwardingModel::obtenerReglasActivasPorCliente($idCliente);
            if (empty($reglas)) {
                error_log("ForwardingService::evaluarYReenviarLote: sin reglas activas para cliente {$idCliente}. Forwarding omitido.");
                continue;
            }

            foreach ($reglas as $regla) {
                $slug = $regla['slug'] ?? '';
                $provider = self::crearProvider($regla);

                // Si el proveedor tiene soporte para despacho en lote (ej. C807Provider)
                if (method_exists($provider, 'createOrdersBatch')) {
                    $res = self::ejecutarForwardingLote($pedidosCliente, $regla, $provider);
                    $resumenLote[] = $res;
                } else {
                    // Proveedores tradicionales individuales (LogisPro, CAEX, HL Express)
                    $syncMode = defined('FORWARDING_SYNC_MODE') && FORWARDING_SYNC_MODE && count($pedidosCliente) <= 50;
                    foreach ($pedidosCliente as $ped) {
                        if ($syncMode) {
                            self::evaluarYReenviar((int)$ped['id'], $idCliente);
                        } else {
                            if (file_exists(__DIR__ . '/LogisticsQueueService.php')) {
                                require_once __DIR__ . '/LogisticsQueueService.php';
                                LogisticsQueueService::queue('forwarding_eval', $ped['id'], [
                                    'id_cliente' => $idCliente
                                ]);
                            }
                        }
                    }
                    $resumenLote[] = [
                        'provider' => $slug,
                        'mode'     => 'individual',
                        'count'    => count($pedidosCliente),
                    ];
                }
            }
        }

        return $resumenLote;
    }

    /**
     * Ejecutar el forwarding en lote para un conjunto de pedidos y una regla batch (como C807).
     *
     * @param array $pedidosBasicos Array de pedidos recién creados (con 'id', 'numero_orden', etc.)
     * @param array $regla Datos de la regla de forwarding
     * @param BaseProvider $provider Instancia del proveedor (ej. C807Provider)
     * @return array Resumen de la ejecución en lote
     */
    public static function ejecutarForwardingLote(array $pedidosBasicos, array $regla, $provider): array
    {
        $slug = $regla['slug'] ?? 'unknown';

        // 1. Filtrar los que ya fueron enviados previamente
        $pedidosPendientes = [];
        foreach ($pedidosBasicos as $pb) {
            $idPed = (int)($pb['id'] ?? 0);
            if ($idPed > 0 && !ForwardingModel::yaFueEnviadoExitosamente($idPed, (int)$regla['id'])) {
                $pedidosPendientes[] = $pb;
            } else {
                error_log("ForwardingService::ejecutarForwardingLote: Pedido {$idPed} ya enviado previamente a {$slug}. Omitido.");
            }
        }

        if (empty($pedidosPendientes)) {
            return [
                'provider' => $slug,
                'success'  => true,
                'skipped'  => true,
                'message'  => 'Todos los pedidos del lote ya fueron enviados exitosamente con anterioridad.',
            ];
        }

        // 2. Cargar información completa de cada pedido para forwarding
        $pedidosCompletos = [];
        foreach ($pedidosPendientes as $pb) {
            $pComp = ForwardingModel::obtenerPedidoParaForwarding((int)$pb['id']);
            if ($pComp) {
                $pedidosCompletos[] = $pComp;
            }
        }

        if (empty($pedidosCompletos)) {
            return [
                'provider' => $slug,
                'success'  => false,
                'message'  => 'No se pudieron cargar los datos completos de los pedidos para forwarding.',
            ];
        }

        try {
            // 3. Autenticación con el proveedor
            $authData = $provider->authenticate();

            // 4. Dividir en chunks si el lote es muy grande (máximo 100 pedidos por solicitud)
            $chunks = array_chunk($pedidosCompletos, 100);
            $totalExitosos = 0;
            $totalFallidos = 0;
            $recolectas = [];

            foreach ($chunks as $chunk) {
                // Registrar log inicial en estado 'pending' para cada pedido del chunk
                $logIdsPorOrden = [];
                foreach ($chunk as $p) {
                    $logId = ForwardingModel::registrarLog([
                        'id_pedido'       => (int)$p['id'],
                        'id_provider'     => (int)$regla['id_provider'],
                        'id_rule'         => (int)$regla['id'],
                        'status'          => 'pending',
                        'request_payload' => 'Preparando despacho en lote (' . $slug . ')...',
                    ]);
                    $logIdsPorOrden[(string)$p['numero_orden']] = $logId;
                }

                try {
                    $batchResult = $provider->createOrdersBatch($chunk, $authData);
                    $recolecta = $batchResult['recolecta'] ?? null;
                    if ($recolecta) {
                        $recolectas[] = $recolecta;
                    }
                    $porOrden      = $batchResult['por_orden'] ?? [];
                    $erroresMapeo  = $batchResult['errores_mapeo'] ?? [];
                    $jsonPayload   = $batchResult['request_payload'] ?? null;

                    foreach ($chunk as $p) {
                        $numOrden = (string)$p['numero_orden'];
                        $logId    = $logIdsPorOrden[$numOrden] ?? null;
                        if (!$logId) continue;

                        if (isset($porOrden[$numOrden]) && !empty($porOrden[$numOrden])) {
                            $guiasEstaOrden = $porOrden[$numOrden];
                            $guiasNums = array_filter(array_map(fn($g) => $g['guia'] ?? null, $guiasEstaOrden));
                            $extId = implode(', ', $guiasNums);

                            ForwardingModel::actualizarLog($logId, [
                                'status'            => 'success',
                                'http_status'       => $batchResult['http_status'] ?? 200,
                                'external_order_id' => $extId,
                                'response_payload'  => json_encode([
                                    'recolecta' => $recolecta,
                                    'guias'     => $guiasEstaOrden,
                                ], JSON_UNESCAPED_UNICODE),
                                'request_payload'   => $jsonPayload,
                                'error_message'     => null,
                            ]);
                            ForwardingModel::marcarLogsPreviosResueltos((int)$p['id'], (int)$regla['id'], (int)$logId);

                            // Sincronizar tracking y courier en la tabla pedidos
                            if ($extId !== '') {
                                try {
                                    $dbPed = (new Conexion())->conectar();
                                    $stUpd = $dbPed->prepare("
                                        UPDATE pedidos 
                                        SET numero_traking = :traking,
                                            courier_service = COALESCE(NULLIF(courier_service, ''), :courier)
                                        WHERE id = :id AND (numero_traking IS NULL OR numero_traking = '')
                                    ");
                                    $stUpd->execute([
                                        ':traking' => $extId,
                                        ':courier' => $regla['provider_nombre'] ?? 'C807 Xpress',
                                        ':id'      => (int)$p['id'],
                                    ]);
                                } catch (Exception $exUpd) {
                                    error_log("ForwardingService::ejecutarForwardingLote error al actualizar tracking en pedidos: " . $exUpd->getMessage());
                                }
                            }

                            $totalExitosos++;
                        } elseif (isset($erroresMapeo[$numOrden])) {
                            ForwardingModel::actualizarLog($logId, [
                                'status'          => 'failed',
                                'http_status'     => 422,
                                'error_message'   => substr($erroresMapeo[$numOrden], 0, 1000),
                                'request_payload' => $jsonPayload,
                            ]);
                            $totalFallidos++;
                        } else {
                            ForwardingModel::actualizarLog($logId, [
                                'status'           => 'failed',
                                'http_status'      => 200,
                                'error_message'    => 'El proveedor no incluyó la guía para esta orden en la respuesta del lote.',
                                'response_payload' => json_encode($batchResult['response'] ?? [], JSON_UNESCAPED_UNICODE),
                                'request_payload'  => $jsonPayload,
                            ]);
                            $totalFallidos++;
                        }
                    }

                } catch (Throwable $chunkEx) {
                    error_log("ForwardingService::ejecutarForwardingLote error en chunk de {$slug}: " . $chunkEx->getMessage());
                    $httpStatus = $chunkEx->getCode() ?: 500;
                    $rawResp = method_exists($provider, 'getLastResponse') ? ($provider->getLastResponse()['body'] ?? null) : null;

                    foreach ($chunk as $p) {
                        $numOrden = (string)$p['numero_orden'];
                        $logId    = $logIdsPorOrden[$numOrden] ?? null;
                        if ($logId) {
                            ForwardingModel::actualizarLog($logId, [
                                'status'           => 'failed',
                                'http_status'      => $httpStatus,
                                'error_message'    => substr($chunkEx->getMessage(), 0, 1000),
                                'response_payload' => $rawResp,
                            ]);
                        }
                        $totalFallidos++;
                        // Si es modo sync y falla el batch por red/servidor, encolar reintento
                        if ($httpStatus < 400 || $httpStatus >= 500 || $httpStatus === 429) {
                            self::encolarReintento((int)$p['id'], $regla, $chunkEx->getMessage());
                        }
                    }
                }
            }

            return [
                'provider'       => $slug,
                'success'        => $totalFallidos === 0,
                'total_orders'   => count($pedidosCompletos),
                'successful'     => $totalExitosos,
                'failed'         => $totalFallidos,
                'recolectas'     => array_values(array_unique($recolectas)),
                'message'        => "Lote procesado para {$slug}: {$totalExitosos} guías generadas exitosamente" . (!empty($recolectas) ? " bajo recolección: " . implode(', ', array_unique($recolectas)) : "") . ($totalFallidos > 0 ? " ({$totalFallidos} fallidas)" : ""),
            ];

        } catch (Throwable $e) {
            error_log("ForwardingService::ejecutarForwardingLote fallo general [{$slug}]: " . $e->getMessage());
            return [
                'provider' => $slug,
                'success'  => false,
                'message'  => 'Error al inicializar lote: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Ejecutar el forwarding de un pedido a un proveedor según una regla.
     *
     * @param array $pedido Datos del pedido
     * @param array $regla Regla con datos del proveedor (JOIN)
     * @param bool $fromQueue Indica si se está ejecutando desde la cola de reintentos
     * @return array Resultado: ['provider' => slug, 'success' => bool, 'message' => string, ...]
     */
    private static function ejecutarForwarding(array $pedido, array $regla, $fromQueue = false, $logIdToUpdate = null)
    {
        $slug     = $regla['slug'];
        $logData = [
            'id_pedido'   => $pedido['id'],
            'id_provider' => $regla['id_provider'],
            'id_rule'     => $regla['id'],
            'status'      => 'pending',
        ];

        $logId = null;
        $currentAttempts = 1;

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

            if ($logIdToUpdate) {
                try {
                    $db = (new Conexion())->conectar();
                    $stAtt = $db->prepare("SELECT attempts FROM forwarding_log WHERE id = :id");
                    $stAtt->execute([':id' => $logIdToUpdate]);
                    $attVal = $stAtt->fetchColumn();
                    if ($attVal !== false) {
                        $currentAttempts = max(1, (int)$attVal) + 1;
                        $logId = (int)$logIdToUpdate;
                    }
                } catch (Exception $ignored) {}
            }

            if ($logId) {
                ForwardingModel::actualizarLog($logId, [
                    'request_payload' => $logData['request_payload'],
                    'status'          => 'pending',
                    'error_message'   => null,
                    'attempts'        => $currentAttempts,
                ]);
            } else {
                $logId = ForwardingModel::registrarLog($logData);
            }

            // Crear la orden
            $resultado = $provider->createOrder($pedido, $pedido['productos'] ?? [], $authData);

            // Actualizar log con resultado exitoso
            ForwardingModel::actualizarLog($logId, [
                'status'            => 'success',
                'http_status'       => $resultado['http_status'] ?? 200,
                'response_payload'  => json_encode($resultado['response'] ?? [], JSON_UNESCAPED_UNICODE),
                'external_order_id' => $resultado['external_order_id'] ?? null,
                'error_message'     => null,
                'attempts'          => $currentAttempts,
            ]);

            // Marcar cualquier otro log previo fallido/pendiente de este pedido y regla como resuelto
            ForwardingModel::marcarLogsPreviosResueltos((int)$pedido['id'], (int)$regla['id'], (int)$logId);

            // Sincronizar tracking y courier en la tabla pedidos si vino external_order_id
            $extId = (string)($resultado['external_order_id'] ?? $resultado['numero_guia'] ?? '');
            if ($extId !== '') {
                try {
                    $dbPed = (new Conexion())->conectar();
                    $stUpd = $dbPed->prepare("
                        UPDATE pedidos 
                        SET numero_traking = :traking,
                            courier_service = COALESCE(NULLIF(courier_service, ''), :courier)
                        WHERE id = :id AND (numero_traking IS NULL OR numero_traking = '')
                    ");
                    $stUpd->execute([
                        ':traking' => $extId,
                        ':courier' => $regla['provider_nombre'] ?? 'C807 Xpress',
                        ':id'      => (int)$pedido['id'],
                    ]);
                } catch (Exception $exUpd) {
                    error_log("ForwardingService::ejecutarForwarding error al actualizar tracking en pedidos: " . $exUpd->getMessage());
                }
            }

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
                    'attempts'         => $currentAttempts,
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

            // Fallback a cola si está habilitado y no venimos ya de la cola
            $retryQueued = false;
            if (!$fromQueue) {
                $retryQueued = self::encolarReintento($pedido['id'], $regla, $e->getMessage());
            }

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

        // Si el slug tiene una clase dedicada, usarla.
        // Si no, usar DynamicProvider como fallback genérico (configurable por UI).
        if (isset(self::$providerMap[$slug])) {
            $className = self::$providerMap[$slug];
            $filePath  = __DIR__ . '/providers/' . $className . '.php';
            if (!file_exists($filePath)) {
                throw new Exception("Archivo de provider no encontrado: $filePath");
            }
            require_once $filePath;
        } else {
            // Fallback a DynamicProvider para cualquier slug creado desde la UI
            $className = 'DynamicProvider';
            require_once __DIR__ . '/providers/DynamicProvider.php';
        }

        $credentials = json_decode($regla['credentials'] ?? '{}', true) ?: [];
        // Usar provider_config (alias del query JOIN) con fallback a default_config (columna directa de r.*)
        // Ambas rutas son seguras: si la columna no existe, el ?? '{}' retorna array vacío.
        $defaultConfig = json_decode($regla['provider_config'] ?? $regla['default_config'] ?? '{}', true) ?: [];
        $ruleOverride  = json_decode($regla['config_override'] ?? '{}', true) ?: [];
        $config = array_merge($defaultConfig, $ruleOverride, [
            'auth_endpoint'   => $regla['auth_endpoint']  ?? '/api/AccountApi',
            'order_endpoint'  => $regla['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail',
            'auth_method'     => $regla['auth_method']    ?? 'bearer_jwt',
            // Para DynamicProvider: ID del proveedor y formato de payload
            'id_provider'     => (int)($regla['id_provider'] ?? 0),
            'payload_format'  => $regla['payload_format'] ?? 'json',
        ]);

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

            // Si el slug tiene clase dedicada, usarla; si no, DynamicProvider.
            if (isset(self::$providerMap[$slug])) {
                $className = self::$providerMap[$slug];
                require_once __DIR__ . '/providers/' . $className . '.php';
            } else {
                $className = 'DynamicProvider';
                require_once __DIR__ . '/providers/DynamicProvider.php';
            }

            $credentials = is_string($providerData['credentials'] ?? '')
                ? (json_decode($providerData['credentials'], true) ?: [])
                : ($providerData['credentials'] ?? []);

            $defaultConfig = json_decode($providerData['default_config'] ?? '{}', true) ?: [];
            $config = array_merge($defaultConfig, [
                'auth_endpoint'  => $providerData['auth_endpoint'] ?? '/api/AccountApi',
                'order_endpoint' => $providerData['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail',
                'auth_method'    => $providerData['auth_method']    ?? 'bearer_jwt',
                'payload_format' => $providerData['payload_format'] ?? 'json',
                'id_provider'    => (int)($providerData['id'] ?? 0),
            ]);

            $provider = new $className($providerData['base_url'], $credentials, $config);

            // Limpiar cache para forzar re-autenticación
            if (method_exists($className, 'clearAuthCache')) {
                $className::clearAuthCache();
            }

            $authData = $provider->authenticate();

            $msg = 'Conexión exitosa';
            if ($slug === 'c807') {
                $msg = 'Autenticación con C807 Xpress exitosa (Basic Auth a ' . ($config['auth_endpoint'] ?? '/admin.php/sesion/get_token') . '). Se validaron las credenciales y se obtuvo el Bearer Token correctamente (no se creó ninguna guía).';
            }

            return [
                'success'       => true,
                'message'       => $msg,
                'customersId'   => $authData['customersId'] ?? null,
                'token_preview' => substr($authData['token'] ?? $authData['userName'] ?? '', 0, 20) . '...',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reintentar el forwarding para una regla específica de un pedido.
     *
     * @param int $idPedido
     * @param int $idRegla
     * @param bool $fromQueue
     * @param int|null $logIdToUpdate ID del log específico a actualizar
     * @return array Resultado
     */
    public static function reintentarRegla($idPedido, $idRegla, $fromQueue = false, $logIdToUpdate = null)
    {
        // 1. Candado anti-duplicados: verificar si ya fue enviado exitosamente
        $logExitoso = ForwardingModel::obtenerLogExitoso((int)$idPedido, (int)$idRegla);
        if ($logExitoso) {
            $extId = $logExitoso['external_order_id'] ?? '';
            // Si se intentó reintentar desde un log fallido que había quedado previo, marcarlo para limpiar la vista
            if ($logIdToUpdate && (int)$logIdToUpdate !== (int)$logExitoso['id']) {
                ForwardingModel::actualizarLog($logIdToUpdate, [
                    'status'        => 'cancelled',
                    'error_message' => 'Superado: este pedido ya fue enviado exitosamente (ID: ' . $extId . ')',
                ]);
            }
            return [
                'success'           => true,
                'skipped'           => true,
                'message'           => 'Este pedido ya fue enviado exitosamente anteriormente' . ($extId ? " (ID Externo: #$extId)" : "") . '. Reintento omitido para evitar duplicados.',
                'external_order_id' => $extId,
            ];
        }

        try {
            $db = (new Conexion())->conectar();

            // Intento 1: query completa con default_config
            try {
                $stmt = $db->prepare("
                    SELECT r.*, p.nombre AS provider_nombre, p.slug, p.base_url,
                           p.auth_endpoint, p.order_endpoint, p.auth_method,
                           p.credentials, p.default_config AS provider_config
                    FROM forwarding_rules r
                    INNER JOIN forwarding_providers p ON p.id = r.id_provider
                    WHERE r.id = :id_rule
                      AND p.activo = 1
                ");
                $stmt->execute([':id_rule' => $idRegla]);
                $regla = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $pdoEx) {
                // Fallback sin default_config
                error_log("ForwardingService::reintentarRegla: query principal falló ("
                    . $pdoEx->getMessage() . "). Usando fallback para id_rule={$idRegla}.");
                $stmt = $db->prepare("
                    SELECT r.*, p.nombre AS provider_nombre, p.slug, p.base_url,
                           p.auth_endpoint, p.order_endpoint, p.auth_method,
                           p.credentials, NULL AS provider_config
                    FROM forwarding_rules r
                    INNER JOIN forwarding_providers p ON p.id = r.id_provider
                    WHERE r.id = :id_rule
                      AND p.activo = 1
                ");
                $stmt->execute([':id_rule' => $idRegla]);
                $regla = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener regla para reintento: ' . $e->getMessage()
            ];
        }

        if (!$regla) {
            return [
                'success' => false,
                'message' => "Regla #{$idRegla} no encontrada o proveedor inactivo"
            ];
        }

        $pedido = ForwardingModel::obtenerPedidoParaForwarding($idPedido);
        if (!$pedido) {
            return [
                'success' => false,
                'message' => "Pedido #{$idPedido} no encontrado para forwarding"
            ];
        }

        return self::ejecutarForwarding($pedido, $regla, $fromQueue, $logIdToUpdate);
    }
}
