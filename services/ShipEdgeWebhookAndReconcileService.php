<?php
/**
 * ShipEdgeWebhookAndReconcileService
 *
 * Servicio centralizado para:
 *  1. Recibir, autenticar, almacenar y procesar eventos de webhook entrantes de ShipEdge.
 *  2. Evitar reprocesar eventos duplicados (idempotencia).
 *  3. Homologar estados externos de ShipEdge con estados internos mediante forwarding_status_mapping.
 *     REGLA CRÍTICA: Nunca interpretar "shipped" como "Entregado" (ID 3). En un OMS/WMS como ShipEdge,
 *     "shipped" significa despachado de almacén ("En ruta o proceso", ID 2).
 *  4. Actualizar guía (forwarding_guias), tracking (pedidos.numero_traking) y transportista (pedidos.courier_service)
 *     cuando existan en el evento o en la consulta al API.
 *  5. Conservar historial de cambios (pedidos_historial_estados, AuditoriaModel, LogisticaNotificationModel)
 *     y activar los webhooks salientes del sistema (WebhookModel::dispararPorPedidoId).
 *  6. Ejecutar conciliación periódica de órdenes contra ShipEdge v41 (GET /orders/{order} y GET /orders paginado)
 *     cuando falle o no exista un webhook.
 */

require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/forwarding.php';
require_once __DIR__ . '/providers/ShipEdgeProvider.php';

class ShipEdgeWebhookAndReconcileService
{
    public const PROVIDER_SLUG = 'shipedge';

    // IDs de estados internos
    public const ESTADO_EN_BODEGA   = 1;
    public const ESTADO_EN_RUTA     = 2;
    public const ESTADO_ENTREGADO   = 3;
    public const ESTADO_INCIDENCIA  = 4;
    public const ESTADO_CANCELADO   = 6;
    public const ESTADO_RECHAZADO   = 7;
    public const ESTADO_RECOLECTADO = 12;

    /**
     * Estados externos de ShipEdge que significan procesamiento o despacho de almacén
     * y que JAMÁS deben homologarse como "Entregado" (ID 3).
     */
    private const NON_DELIVERED_WAREHOUSE_STATUSES = [
        'shipped',
        'dispatched',
        'in_transit',
        'ready_to_ship',
        'packed',
        'processing',
        'in_process',
        'pending',
    ];

    /**
     * Extrae de forma flexible los campos clave de un payload de webhook de ShipEdge.
     * Como el Swagger OMS v41 de creación de órdenes no fija un único esquema de webhook,
     * soporta tanto payloads planos como anidados en data / order / event / shipment.
     *
     * @param array $payload
     * @return array
     */
    public static function normalizarPayloadWebhook(array $payload): array
    {
        $node = $payload;
        if (isset($payload['data']) && is_array($payload['data'])) {
            $node = isset($payload['data'][0]) && is_array($payload['data'][0])
                ? $payload['data'][0]
                : $payload['data'];
        } elseif (isset($payload['order']) && is_array($payload['order'])) {
            $node = $payload['order'];
        }

        $shipment = is_array($node['shipment'] ?? null)
            ? $node['shipment']
            : (is_array($node['shipments'][0] ?? null) ? $node['shipments'][0] : []);

        $orderNumber = trim((string)(
            $node['order_number']
            ?? $node['order_num']
            ?? $node['reference_number']
            ?? $payload['order_number']
            ?? ''
        ));

        $externalOrderId = trim((string)(
            $node['order_id']
            ?? $node['id']
            ?? $node['oms_order_id']
            ?? $node['external_order_id']
            ?? $payload['order_id']
            ?? ''
        ));

        $externalStatus = trim((string)(
            $node['order_status']
            ?? $node['status']
            ?? $node['state']
            ?? $payload['order_status']
            ?? $payload['status']
            ?? $payload['event_type']
            ?? $payload['event']
            ?? $shipment['status']
            ?? ''
        ));

        $reasonCode = trim((string)(
            $node['reason_code']
            ?? $node['sub_status']
            ?? $node['reason']
            ?? $payload['reason_code']
            ?? ''
        ));

        $trackingNumber = trim((string)(
            $node['tracking_number']
            ?? $node['tracking']
            ?? $node['tracking_num']
            ?? $shipment['tracking_number']
            ?? $shipment['tracking']
            ?? $payload['tracking_number']
            ?? ''
        ));

        $guideNumber = trim((string)(
            $node['guide_number']
            ?? $node['label_id']
            ?? $node['shipment_id']
            ?? $shipment['guide_number']
            ?? $shipment['shipment_id']
            ?? $payload['guide_number']
            ?? $trackingNumber
            ?? ''
        ));

        $carrierName = trim((string)(
            $node['carrier_name']
            ?? $node['carrier']
            ?? $shipment['carrier_name']
            ?? $shipment['carrier']
            ?? $node['shipping_method']
            ?? $payload['carrier_name']
            ?? $payload['carrier']
            ?? ''
        ));

        $accountId = trim((string)(
            $payload['account_id']
            ?? $payload['AccountID']
            ?? $node['account_id']
            ?? $node['AccountID']
            ?? ''
        ));

        $eventId = trim((string)(
            $payload['event_id']
            ?? $payload['webhook_id']
            ?? $node['event_id']
            ?? ''
        ));

        return [
            'order_number'      => $orderNumber !== '' ? $orderNumber : null,
            'external_order_id' => $externalOrderId !== '' ? $externalOrderId : null,
            'external_status'   => $externalStatus !== '' ? $externalStatus : null,
            'reason_code'       => $reasonCode !== '' ? $reasonCode : null,
            'tracking_number'   => $trackingNumber !== '' ? $trackingNumber : null,
            'numero_guia'       => $guideNumber !== '' ? $guideNumber : null,
            'carrier_name'      => $carrierName !== '' ? $carrierName : null,
            'account_id'        => $accountId !== '' ? $accountId : null,
            'event_id'          => $eventId !== '' ? $eventId : null,
        ];
    }

    /**
     * Valida la autenticación entrante del webhook comparando el secreto recibido
     * (en encabezados HTTP o en el payload) y, cuando exista, el AccountID contra
     * los proveedores 'shipedge' configurados.
     *
     * @param array $headers Encabezados HTTP (claves en cualquier capitalización)
     * @param array $payload Payload JSON decodificado
     * @param array $providers Lista de proveedores shipedge desde BD
     * @return array|null Proveedor autenticado o null si falla
     */
    public static function autenticarProveedorWebhook(array $headers, array $payload, array $providers): ?array
    {
        $normHeaders = [];
        foreach ($headers as $k => $v) {
            $normHeaders[strtolower(trim((string)$k))] = is_array($v) ? reset($v) : trim((string)$v);
        }

        // Extraer token/secreto de encabezados admitidos
        $incomingSecret = '';
        $candidateHeaders = [
            'x-shipedge-secret',
            'x-webhook-secret',
            'x-token',
            'x-api-key',
            'key',
        ];
        foreach ($candidateHeaders as $hName) {
            if (!empty($normHeaders[$hName])) {
                $incomingSecret = $normHeaders[$hName];
                break;
            }
        }

        if ($incomingSecret === '' && !empty($normHeaders['authorization'])) {
            $authHeader = $normHeaders['authorization'];
            if (stripos($authHeader, 'Bearer ') === 0) {
                $incomingSecret = trim(substr($authHeader, 7));
            } else {
                $incomingSecret = trim($authHeader);
            }
        }

        if ($incomingSecret === '') {
            $incomingSecret = trim((string)(
                $payload['webhook_secret']
                ?? $payload['secret']
                ?? $payload['token']
                ?? ''
            ));
        }

        if ($incomingSecret === '') {
            return null;
        }

        $incomingAccountId = trim((string)(
            $normHeaders['accountid']
            ?? $normHeaders['x-account-id']
            ?? $payload['account_id']
            ?? $payload['AccountID']
            ?? ''
        ));

        foreach ($providers as $prov) {
            $creds = is_string($prov['credentials'] ?? null)
                ? (json_decode($prov['credentials'], true) ?: [])
                : (is_array($prov['credentials'] ?? null) ? $prov['credentials'] : []);
            $defCfg = is_string($prov['default_config'] ?? null)
                ? (json_decode($prov['default_config'], true) ?: [])
                : (is_array($prov['default_config'] ?? null) ? $prov['default_config'] : []);

            $expectedSecret = trim((string)($creds['webhook_secret'] ?? $defCfg['webhook_secret'] ?? ''));
            if ($expectedSecret === '') {
                continue;
            }

            if (!hash_equals($expectedSecret, $incomingSecret)) {
                continue;
            }

            // Si el webhook envió un AccountID explícito, validar que coincida con la cuenta del proveedor
            $provAccountId = trim((string)($creds['account_id'] ?? $creds['AccountID'] ?? $creds['userName'] ?? $defCfg['account_id'] ?? ''));
            if ($incomingAccountId !== '' && $provAccountId !== '' && !hash_equals($provAccountId, $incomingAccountId)) {
                continue;
            }

            return $prov;
        }

        return null;
    }

    /**
     * Resuelve el estado interno homologado a partir del estado externo de ShipEdge y la configuración
     * en forwarding_status_mapping.
     *
     * REGLA INQUEBRANTABLE: "shipped" jamás se interpreta como "Entregado" (ID 3).
     *
     * @param string $externalStatus
     * @param string|null $reasonCode
     * @param array|null $statusMappings Catálogo de forwarding_status_mapping (o null para consultar BD)
     * @return array ['mapped' => bool, 'id_estado_interno' => int|null, 'es_confirmado' => bool, 'descripcion' => string|null, 'warning' => string|null]
     */
    public static function homologarEstadoExterno(string $externalStatus, ?string $reasonCode = null, ?array $statusMappings = null): array
    {
        $extNorm = strtolower(trim($externalStatus));
        if ($extNorm === '') {
            return [
                'mapped'            => false,
                'id_estado_interno' => null,
                'es_confirmado'     => false,
                'descripcion'       => null,
                'warning'           => 'Estado externo vacío.',
            ];
        }

        if ($statusMappings === null) {
            $statusMappings = ForwardingModel::obtenerMapeosEstados(self::PROVIDER_SLUG);
        }

        $matchedRow = null;
        // 1. Buscar coincidencia exacta de codigo_externo + razon_codigo
        if ($reasonCode !== null && $reasonCode !== '') {
            foreach ($statusMappings as $row) {
                if (empty($row['activo'])) continue;
                if (
                    strtolower(trim((string)$row['codigo_externo'])) === $extNorm
                    && strtolower(trim((string)($row['razon_codigo'] ?? ''))) === strtolower(trim($reasonCode))
                ) {
                    $matchedRow = $row;
                    break;
                }
            }
        }

        // 2. Buscar coincidencia por codigo_externo con razon_codigo nulo/vacío
        if ($matchedRow === null) {
            foreach ($statusMappings as $row) {
                if (empty($row['activo'])) continue;
                if (
                    strtolower(trim((string)$row['codigo_externo'])) === $extNorm
                    && empty($row['razon_codigo'])
                ) {
                    $matchedRow = $row;
                    break;
                }
            }
        }

        // 3. Fallback a plantilla por defecto de ShipEdge si aún no estaba sembrada en BD
        if ($matchedRow === null) {
            $defaults = [
                'pending'       => ['id_estado_interno' => self::ESTADO_EN_BODEGA,   'es_confirmado' => 0, 'descripcion_externa' => 'Orden pendiente en OMS'],
                'processing'    => ['id_estado_interno' => self::ESTADO_EN_BODEGA,   'es_confirmado' => 0, 'descripcion_externa' => 'En preparación en almacén'],
                'in_process'    => ['id_estado_interno' => self::ESTADO_EN_BODEGA,   'es_confirmado' => 0, 'descripcion_externa' => 'En proceso en almacén'],
                'ready_to_ship' => ['id_estado_interno' => self::ESTADO_RECOLECTADO, 'es_confirmado' => 0, 'descripcion_externa' => 'Listo para despacho'],
                'packed'        => ['id_estado_interno' => self::ESTADO_RECOLECTADO, 'es_confirmado' => 0, 'descripcion_externa' => 'Empacado en almacén'],
                'shipped'       => ['id_estado_interno' => self::ESTADO_EN_RUTA,     'es_confirmado' => 1, 'descripcion_externa' => 'Despachado de almacén (En ruta — NO Entregado)'],
                'in_transit'    => ['id_estado_interno' => self::ESTADO_EN_RUTA,     'es_confirmado' => 0, 'descripcion_externa' => 'En tránsito con transportista'],
                'delivered'     => ['id_estado_interno' => self::ESTADO_ENTREGADO,   'es_confirmado' => 0, 'descripcion_externa' => 'Entregado reportado por transportista'],
                'on_hold'       => ['id_estado_interno' => self::ESTADO_INCIDENCIA,  'es_confirmado' => 0, 'descripcion_externa' => 'Orden retenida (On Hold)'],
                'backorder'     => ['id_estado_interno' => self::ESTADO_INCIDENCIA,  'es_confirmado' => 0, 'descripcion_externa' => 'Backorder / Sin stock'],
                'cancelled'     => ['id_estado_interno' => self::ESTADO_CANCELADO,   'es_confirmado' => 0, 'descripcion_externa' => 'Orden cancelada'],
                'canceled'      => ['id_estado_interno' => self::ESTADO_CANCELADO,   'es_confirmado' => 0, 'descripcion_externa' => 'Orden cancelada'],
                'returned'      => ['id_estado_interno' => self::ESTADO_RECHAZADO,   'es_confirmado' => 0, 'descripcion_externa' => 'Devolución'],
            ];
            if (isset($defaults[$extNorm])) {
                $matchedRow = $defaults[$extNorm];
            }
        }

        if ($matchedRow === null) {
            return [
                'mapped'            => false,
                'id_estado_interno' => null,
                'es_confirmado'     => false,
                'descripcion'       => null,
                'warning'           => "Código de estado externo '{$externalStatus}' no reconocido en forwarding_status_mapping.",
            ];
        }

        $idInterno = (int)$matchedRow['id_estado_interno'];
        $warning   = null;

        // Candado estricto: "shipped" u otros estados de almacén/tránsito NUNCA pueden convertirse en Entregado (ID 3)
        if (in_array($extNorm, self::NON_DELIVERED_WAREHOUSE_STATUSES, true) && $idInterno === self::ESTADO_ENTREGADO) {
            $idInterno = self::ESTADO_EN_RUTA;
            $warning   = "Protección activa: el estado '{$externalStatus}' corresponde a almacén/despacho y fue forzado a 'En ruta o proceso' (ID 2) en lugar de 'Entregado' (ID 3).";
        }

        return [
            'mapped'            => true,
            'id_estado_interno' => $idInterno,
            'es_confirmado'     => !empty($matchedRow['es_confirmado']),
            'descripcion'       => $matchedRow['descripcion_externa'] ?? $externalStatus,
            'warning'           => $warning,
        ];
    }

    /**
     * Localiza el pedido interno vinculándolo exclusivamente con el proveedor autenticado
     * para garantizar aislamiento entre cuentas y clientes.
     *
     * @param PDO $db
     * @param int $idProvider
     * @param array $normalized Datos normalizados (order_number, external_order_id, numero_guia, tracking_number)
     * @return array|null Fila del pedido (id, numero_orden, id_estado, id_cliente, numero_traking, courier_service)
     */
    public static function localizarPedidoAisladoPorProveedor(PDO $db, int $idProvider, array $normalized): ?array
    {
        $orderNumber     = $normalized['order_number'] ?? null;
        $externalOrderId = $normalized['external_order_id'] ?? null;
        $numeroGuia      = $normalized['numero_guia'] ?? null;
        $trackingNumber  = $normalized['tracking_number'] ?? null;

        $idPedido = null;

        // 1. Buscar en forwarding_guias del proveedor autenticado
        $candidatosGuia = array_values(array_filter(array_unique([
            $numeroGuia,
            $trackingNumber,
            $externalOrderId ? 'ORD-' . $externalOrderId : null,
            $orderNumber ? 'SE-' . $orderNumber : null,
        ])));

        if (!empty($candidatosGuia)) {
            $placeholders = implode(',', array_fill(0, count($candidatosGuia), '?'));
            $stmtG = $db->prepare("
                SELECT id_pedido FROM forwarding_guias
                WHERE id_provider = ? AND numero_guia IN ({$placeholders})
                ORDER BY id DESC LIMIT 1
            ");
            $stmtG->execute(array_merge([$idProvider], $candidatosGuia));
            $idPedido = (int)$stmtG->fetchColumn() ?: null;
        }

        // 2. Buscar en forwarding_log del proveedor autenticado por external_order_id
        if (!$idPedido && !empty($externalOrderId)) {
            $stmtL = $db->prepare("
                SELECT id_pedido FROM forwarding_log
                WHERE id_provider = :id_provider AND external_order_id = :ext_id
                ORDER BY id DESC LIMIT 1
            ");
            $stmtL->execute([
                ':id_provider' => $idProvider,
                ':ext_id'      => $externalOrderId,
            ]);
            $idPedido = (int)$stmtL->fetchColumn() ?: null;
        }

        // 3. Buscar por numero_orden en pedidos que tengan log o regla activa asociada a este id_provider
        //    (respeta el aislamiento entre clientes: no permite modificar pedidos de otros clientes/proveedores)
        if (!$idPedido && !empty($orderNumber)) {
            $stmtP = $db->prepare("
                SELECT p.id
                FROM pedidos p
                WHERE p.numero_orden = :numero_orden
                  AND (
                      EXISTS (
                          SELECT 1 FROM forwarding_log fl
                          WHERE fl.id_pedido = p.id AND fl.id_provider = :id_prov1
                      )
                      OR EXISTS (
                          SELECT 1 FROM forwarding_rules fr
                          WHERE fr.id_cliente = p.id_cliente AND fr.id_provider = :id_prov2 AND fr.activo = 1
                      )
                  )
                ORDER BY p.id DESC LIMIT 1
            ");
            $stmtP->execute([
                ':numero_orden' => $orderNumber,
                ':id_prov1'     => $idProvider,
                ':id_prov2'     => $idProvider,
            ]);
            $idPedido = (int)$stmtP->fetchColumn() ?: null;
        }

        if (!$idPedido) {
            return null;
        }

        $stmtPed = $db->prepare("
            SELECT id, numero_orden, id_estado, id_cliente, numero_traking, courier_service
            FROM pedidos
            WHERE id = :id
            LIMIT 1
        ");
        $stmtPed->execute([':id' => $idPedido]);
        $pedido = $stmtPed->fetch(PDO::FETCH_ASSOC);

        return $pedido ?: null;
    }

    /**
     * Procesa un evento de cambio de estado/guía/tracking de ShipEdge (tanto desde Webhook como desde Conciliación).
     *
     * @param PDO $db
     * @param array $provider Fila de forwarding_providers
     * @param array $payload Payload recibido
     * @param string $rawJson Payload crudo para auditoría en forwarding_webhook_events
     * @param string $origen 'webhook' o 'reconciliacion'
     * @param array|null $statusMappingsOverride Para inyección en pruebas unitarias
     * @return array Resultado detallado del procesamiento
     */
    public static function procesarEventoOrden(
        PDO $db,
        array $provider,
        array $payload,
        string $rawJson,
        string $origen = 'webhook',
        ?array $statusMappingsOverride = null
    ): array {
        $idProvider   = (int)$provider['id'];
        $providerName = $provider['nombre'] ?? 'ShipEdge';
        $norm         = self::normalizarPayloadWebhook($payload);

        $orderRef = $norm['numero_guia']
            ?? $norm['tracking_number']
            ?? ($norm['external_order_id'] ? 'ORD-' . $norm['external_order_id'] : null)
            ?? ($norm['order_number'] ? 'SE-' . $norm['order_number'] : null);

        if ($orderRef === null && empty($norm['order_number']) && empty($norm['external_order_id'])) {
            return [
                'success'   => false,
                'http_code' => 422,
                'message'   => 'Payload de ShipEdge no contiene order_number, order_id ni tracking_number.',
            ];
        }

        // Localizar pedido respetando aislamiento de cuenta/cliente
        $pedido = self::localizarPedidoAisladoPorProveedor($db, $idProvider, $norm);
        $idPedido = $pedido ? (int)$pedido['id'] : null;

        // Verificar idempotencia antes de insertar/procesar si es un evento repetido
        $esDuplicado = self::esEventoDuplicado($db, $idPedido, $orderRef, $norm['external_status'], $norm['tracking_number'], $rawJson);

        // Registrar siempre el evento original en forwarding_webhook_events (con credenciales sanitizadas)
        $sanitizedRaw = ShipEdgeProvider::sanitizeSensitiveData($rawJson);
        $idWebhookEvent = self::insertarWebhookEvent($db, [
            'provider_slug' => self::PROVIDER_SLUG,
            'numero_guia'   => $orderRef,
            'id_pedido'     => $idPedido,
            'codigo_estado' => $norm['external_status'],
            'razon_codigo'  => $norm['reason_code'],
            'payload_raw'   => is_string($sanitizedRaw) ? $sanitizedRaw : json_encode($sanitizedRaw, JSON_UNESCAPED_UNICODE),
        ]);

        if (!$pedido) {
            self::marcarWebhookEvent($db, $idWebhookEvent, 0, 'Orden no encontrada o pertenece a otro cliente/cuenta.');
            return [
                'success'          => false,
                'http_code'        => 404,
                'webhook_event_id' => $idWebhookEvent,
                'message'          => 'Orden no encontrada para la cuenta ShipEdge autenticada.',
            ];
        }

        if ($esDuplicado) {
            self::marcarWebhookEvent($db, $idWebhookEvent, 1, 'Evento duplicado omitido (idempotencia).');
            return [
                'success'          => true,
                'http_code'        => 200,
                'duplicate'        => true,
                'webhook_event_id' => $idWebhookEvent,
                'id_pedido'        => $idPedido,
                'message'          => 'Evento duplicado registrado y omitido sin reprocesar.',
            ];
        }

        // Actualizar guía, tracking y transportista cuando existan
        $datosActualizados = self::actualizarGuiaTrackingYCarrier($db, $idPedido, $idProvider, $pedido, $norm, $providerName);

        // Homologar estado externo si fue enviado
        $estadoAnteriorId = (int)$pedido['id_estado'];
        $nuevoEstadoId    = null;
        $estadoCambiado   = false;
        $homologacionInfo = null;

        if (!empty($norm['external_status'])) {
            $homologacionInfo = self::homologarEstadoExterno($norm['external_status'], $norm['reason_code'], $statusMappingsOverride);
            if ($homologacionInfo['mapped'] && !empty($homologacionInfo['id_estado_interno'])) {
                $candidatoEstadoId = (int)$homologacionInfo['id_estado_interno'];

                // No retroceder un pedido que ya está en estado final (Entregado=3, Cancelado=6) a un estado previo (1 o 2)
                $esFinalActual = in_array($estadoAnteriorId, [self::ESTADO_ENTREGADO, self::ESTADO_CANCELADO], true);
                if (!$esFinalActual && $candidatoEstadoId !== $estadoAnteriorId) {
                    $nuevoEstadoId  = $candidatoEstadoId;
                    $estadoCambiado = true;
                } else {
                    $nuevoEstadoId = $estadoAnteriorId;
                }
            }
        }

        // Aplicar cambio de estado en pedidos y conservar historial
        $nombreNuevoEstado = null;
        if ($estadoCambiado && $nuevoEstadoId !== null) {
            $stmtUpdEstado = $db->prepare("UPDATE pedidos SET id_estado = :id_estado WHERE id = :id");
            $stmtUpdEstado->execute([
                ':id_estado' => $nuevoEstadoId,
                ':id'        => $idPedido,
            ]);

            $obs = sprintf(
                'ShipEdge [%s] → Estado externo "%s"%s%s%s',
                $origen,
                $norm['external_status'],
                !empty($norm['tracking_number']) ? " | Tracking: {$norm['tracking_number']}" : '',
                !empty($norm['carrier_name']) ? " | Carrier: {$norm['carrier_name']}" : '',
                !empty($homologacionInfo['warning']) ? " | Nota: {$homologacionInfo['warning']}" : ''
            );

            self::registrarHistorialEstado($db, $idPedido, $estadoAnteriorId, $nuevoEstadoId, substr($obs, 0, 255));
            $nombreNuevoEstado = self::obtenerNombreEstado($db, $nuevoEstadoId);

            // Registrar en auditoría y notificaciones si están disponibles
            if (file_exists(__DIR__ . '/../modelo/auditoria.php')) {
                require_once __DIR__ . '/../modelo/auditoria.php';
                if (class_exists('AuditoriaModel')) {
                    AuditoriaModel::registrar(
                        'pedidos',
                        $idPedido,
                        'webhook_shipedge_estado',
                        null,
                        ['id_estado' => $estadoAnteriorId, 'numero_traking' => $pedido['numero_traking'] ?? null],
                        [
                            'id_estado'       => $nuevoEstadoId,
                            'external_status' => $norm['external_status'],
                            'tracking_number' => $datosActualizados['tracking_number'],
                            'carrier_name'    => $datosActualizados['carrier_name'],
                            'origen'          => $origen,
                        ]
                    );
                }
            }

            if (file_exists(__DIR__ . '/../modelo/logistica_notification.php')) {
                require_once __DIR__ . '/../modelo/logistica_notification.php';
                if (class_exists('LogisticaNotificationModel') && method_exists('LogisticaNotificationModel', 'agregar') && !empty($pedido['id_cliente'])) {
                    try {
                        LogisticaNotificationModel::agregar(
                            (int)$pedido['id_cliente'],
                            'estado_cambiado',
                            "Actualización ShipEdge (#{$pedido['numero_orden']})",
                            "Orden #{$pedido['numero_orden']} pasó a estado {$nombreNuevoEstado}",
                            $idPedido,
                            [
                                'proveedor'       => 'shipedge',
                                'estado_externo'  => $norm['external_status'],
                                'estado_interno'  => $nuevoEstadoId,
                                'tracking_number' => $datosActualizados['tracking_number'],
                            ]
                        );
                    } catch (Exception $notifEx) {
                        error_log('ShipEdgeWebhookAndReconcileService::notificacion aviso: ' . $notifEx->getMessage());
                    }
                }
            }
        }

        // Activar los webhooks salientes actuales del sistema cuando cambie el estado o se asigne tracking nuevo
        $webhookSalienteDisparado = false;
        if (($estadoCambiado || $datosActualizados['tracking_nuevo']) && file_exists(__DIR__ . '/../modelo/webhook.php')) {
            require_once __DIR__ . '/../modelo/webhook.php';
            if (class_exists('WebhookModel')) {
                $idEstadoParaWebhook = (int)($nuevoEstadoId ?? $estadoAnteriorId);
                $notaWebhook = $obs ?? ("ShipEdge [{$origen}] → Tracking: " . ($datosActualizados['tracking_number'] ?? ''));
                try {
                    WebhookModel::dispararPorPedidoId((int)$idPedido, $idEstadoParaWebhook, $notaWebhook);
                    $webhookSalienteDisparado = true;
                } catch (Throwable $whEx) {
                    error_log('ShipEdgeWebhookAndReconcileService error al disparar webhook saliente: ' . $whEx->getMessage());
                }
            }
        }

        $msgNota = null;
        if ($homologacionInfo && !$homologacionInfo['mapped']) {
            $msgNota = $homologacionInfo['warning'];
        } elseif ($homologacionInfo && !empty($homologacionInfo['warning'])) {
            $msgNota = $homologacionInfo['warning'];
        }

        self::marcarWebhookEvent($db, $idWebhookEvent, 1, $msgNota);

        $effectiveInternalId = $nuevoEstadoId ?? $estadoAnteriorId;
        $effectiveInternalName = $nombreNuevoEstado ?: self::obtenerNombreEstado($db, $effectiveInternalId);

        return [
            'success'                    => true,
            'http_code'                  => 200,
            'duplicate'                  => false,
            'webhook_event_id'           => $idWebhookEvent,
            'id_pedido'                  => $idPedido,
            'numero_orden'               => $pedido['numero_orden'],
            'external_status'            => $norm['external_status'],
            'estado_anterior_id'         => $estadoAnteriorId,
            'estado_nuevo_id'            => $effectiveInternalId,
            'internal_status_id'         => $effectiveInternalId,
            'internal_status_name'       => $effectiveInternalName,
            'estado_cambiado'            => $estadoCambiado,
            'tracking_number'            => $datosActualizados['tracking_number'],
            'numero_guia'                => $datosActualizados['numero_guia'],
            'carrier_name'               => $datosActualizados['carrier_name'],
            'webhook_saliente_disparado' => $webhookSalienteDisparado,
            'warning'                    => $msgNota,
            'message'                    => 'Evento de ShipEdge procesado correctamente.',
        ];
    }

    /**
     * Punto de entrada de alto nivel para procesar un webhook de ShipEdge autenticando
     * los encabezados/secreto contra los proveedores configurados.
     */
    public static function procesarWebhook(array $payload, array $headers = [], ?string $rawJson = null): array
    {
        $db = (new Conexion())->conectar();
        $providers = ForwardingModel::obtenerProveedoresPorSlug(self::PROVIDER_SLUG);
        if (empty($providers)) {
            return [
                'success'   => false,
                'http_code' => 503,
                'message'   => 'No hay proveedores ShipEdge configurados.',
            ];
        }

        $authenticatedProvider = self::autenticarProveedorWebhook($headers, $payload, $providers);
        if ($authenticatedProvider === null) {
            return [
                'success'   => false,
                'http_code' => 401,
                'message'   => 'No autorizado: secreto de webhook o AccountID inválido.',
            ];
        }

        if ($rawJson === null) {
            $rawJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return self::procesarEventoOrden($db, $authenticatedProvider, $payload, (string)$rawJson, 'webhook');
    }

    /**
     * Verifica si ya se procesó previamente un evento equivalente para el mismo pedido, estado y tracking.
     */
    private static function esEventoDuplicado(
        PDO $db,
        ?int $idPedido,
        ?string $numeroGuia,
        ?string $codigoEstado,
        ?string $trackingNumber,
        string $rawJson
    ): bool {
        if (!$idPedido) {
            return false;
        }
        try {
            $stmt = $db->prepare("
                SELECT codigo_estado, numero_guia, payload_raw
                FROM forwarding_webhook_events
                WHERE provider_slug = :slug
                  AND id_pedido = :id_pedido
                  AND procesado = 1
                ORDER BY id DESC
                LIMIT 10
            ");
            $stmt->execute([
                ':slug'      => self::PROVIDER_SLUG,
                ':id_pedido' => $idPedido,
            ]);
            $previos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($previos as $prev) {
                // Si el payload crudo sanitizado es idéntico, es un reenvío duplicado exacto
                if (!empty($prev['payload_raw']) && md5(trim($prev['payload_raw'])) === md5(trim($rawJson))) {
                    return true;
                }
                // Si tiene el mismo estado externo y la misma guía/tracking y el último evento ya fue procesado
                $mismoEstado = strtolower(trim((string)($prev['codigo_estado'] ?? ''))) === strtolower(trim((string)($codigoEstado ?? '')));
                $mismaGuia   = trim((string)($prev['numero_guia'] ?? '')) === trim((string)($numeroGuia ?? ''));
                if ($mismoEstado && $mismaGuia && $codigoEstado !== null && $codigoEstado !== '') {
                    $prevNorm = self::normalizarPayloadWebhook(json_decode($prev['payload_raw'] ?? '{}', true) ?: []);
                    if (($prevNorm['tracking_number'] ?? null) === ($trackingNumber ?? null)) {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('ShipEdgeWebhookAndReconcileService::esEventoDuplicado aviso: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Actualiza forwarding_guias y los campos numero_traking / courier_service del pedido cuando existan.
     */
    private static function actualizarGuiaTrackingYCarrier(
        PDO $db,
        int $idPedido,
        int $idProvider,
        array $pedidoActual,
        array $norm,
        string $defaultProviderName
    ): array {
        $trackingNumber = $norm['tracking_number'];
        $numeroGuia     = $norm['numero_guia'];
        $carrierName    = $norm['carrier_name'];
        $extOrderId     = $norm['external_order_id'];
        $extStatus      = $norm['external_status'];

        $trackingAnterior = trim((string)($pedidoActual['numero_traking'] ?? ''));
        $trackingNuevo    = (!empty($trackingNumber) && $trackingNumber !== $trackingAnterior);

        // 1. Actualizar forwarding_guias
        try {
            $stmtG = $db->prepare("
                SELECT id, numero_guia, status, metadata
                FROM forwarding_guias
                WHERE id_pedido = :id_pedido AND id_provider = :id_provider
                ORDER BY id DESC LIMIT 1
            ");
            $stmtG->execute([
                ':id_pedido'   => $idPedido,
                ':id_provider' => $idProvider,
            ]);
            $guiaRow = $stmtG->fetch(PDO::FETCH_ASSOC);

            $metaPrev = $guiaRow ? (json_decode($guiaRow['metadata'] ?? '{}', true) ?: []) : [];
            $mergedMeta = array_merge($metaPrev, array_filter([
                'order_number'       => $norm['order_number'] ?? ($pedidoActual['numero_orden'] ?? null),
                'external_order_id'  => $extOrderId ?? ($metaPrev['external_order_id'] ?? null),
                'numero_guia'        => $numeroGuia ?? ($metaPrev['numero_guia'] ?? null),
                'tracking_number'    => $trackingNumber ?? ($metaPrev['tracking_number'] ?? null),
                'carrier_name'       => $carrierName ?? ($metaPrev['carrier_name'] ?? null),
                'external_status'    => $extStatus ?? ($metaPrev['external_status'] ?? null),
                'order_created'      => true,
                'guide_generated'    => !empty($numeroGuia) || !empty($metaPrev['guide_generated']),
                'tracking_generated' => !empty($trackingNumber) || !empty($metaPrev['tracking_generated']),
                'updated_at'         => date('Y-m-d H:i:s'),
            ], fn($v) => $v !== null));

            $guiaKey = $numeroGuia
                ?: ($trackingNumber
                ?: ($guiaRow['numero_guia'] ?? ($extOrderId ? 'ORD-' . $extOrderId : 'SE-' . $pedidoActual['numero_orden'])));

            $newGuiaStatus = $extStatus ?: ($guiaRow['status'] ?? 'updated');

            if ($guiaRow) {
                $stmtUpdG = $db->prepare("
                    UPDATE forwarding_guias
                    SET numero_guia = :numero_guia,
                        status      = :status,
                        metadata    = :metadata,
                        updated_at  = NOW()
                    WHERE id = :id
                ");
                $stmtUpdG->execute([
                    ':numero_guia' => $guiaKey,
                    ':status'      => $newGuiaStatus,
                    ':metadata'    => json_encode($mergedMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ':id'          => (int)$guiaRow['id'],
                ]);
            } else {
                $stmtInsG = $db->prepare("
                    INSERT INTO forwarding_guias
                        (id_pedido, id_provider, numero_guia, status, metadata, created_at)
                    VALUES
                        (:id_pedido, :id_provider, :numero_guia, :status, :metadata, NOW())
                ");
                $stmtInsG->execute([
                    ':id_pedido'   => $idPedido,
                    ':id_provider' => $idProvider,
                    ':numero_guia' => $guiaKey,
                    ':status'      => $newGuiaStatus,
                    ':metadata'    => json_encode($mergedMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        } catch (Exception $e) {
            error_log('ShipEdgeWebhookAndReconcileService::actualizarGuiaTrackingYCarrier guia aviso: ' . $e->getMessage());
        }

        // 2. Actualizar pedidos.numero_traking y pedidos.courier_service cuando existan
        try {
            $sets = [];
            $params = [':id' => $idPedido];

            if (!empty($trackingNumber)) {
                $sets[] = 'numero_traking = :tracking';
                $params[':tracking'] = $trackingNumber;
            }
            if (!empty($carrierName)) {
                $sets[] = 'courier_service = :courier';
                $params[':courier'] = $carrierName;
            } elseif (empty($pedidoActual['courier_service']) && $defaultProviderName !== '') {
                $sets[] = 'courier_service = :courier';
                $params[':courier'] = $defaultProviderName;
            }

            if (!empty($sets)) {
                $sql = 'UPDATE pedidos SET ' . implode(', ', $sets) . ' WHERE id = :id';
                $db->prepare($sql)->execute($params);
            }
        } catch (Exception $e) {
            error_log('ShipEdgeWebhookAndReconcileService::actualizarGuiaTrackingYCarrier pedido aviso: ' . $e->getMessage());
        }

        return [
            'tracking_number' => $trackingNumber ?: ($pedidoActual['numero_traking'] ?? null),
            'numero_guia'     => $numeroGuia,
            'carrier_name'    => $carrierName ?: ($pedidoActual['courier_service'] ?? null),
            'tracking_nuevo'  => $trackingNuevo,
        ];
    }

    private static function insertarWebhookEvent(PDO $db, array $data): int
    {
        try {
            $stmt = $db->prepare("
                INSERT INTO forwarding_webhook_events
                    (provider_slug, numero_guia, id_pedido, codigo_estado, razon_codigo, payload_raw, procesado, created_at)
                VALUES
                    (:provider_slug, :numero_guia, :id_pedido, :codigo_estado, :razon_codigo, :payload_raw, 0, NOW())
            ");
            $stmt->execute([
                ':provider_slug' => $data['provider_slug'],
                ':numero_guia'   => $data['numero_guia'],
                ':id_pedido'     => $data['id_pedido'],
                ':codigo_estado' => $data['codigo_estado'],
                ':razon_codigo'  => $data['razon_codigo'],
                ':payload_raw'   => $data['payload_raw'],
            ]);
            return (int)$db->lastInsertId();
        } catch (Exception $e) {
            error_log('ShipEdgeWebhookAndReconcileService::insertarWebhookEvent error: ' . $e->getMessage());
            return 0;
        }
    }

    private static function marcarWebhookEvent(PDO $db, int $idEvent, int $procesado, ?string $errorMsg): void
    {
        if ($idEvent <= 0) return;
        try {
            $stmt = $db->prepare("
                UPDATE forwarding_webhook_events
                SET procesado = :procesado,
                    error_mensaje = :error_mensaje,
                    processed_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':procesado'     => $procesado,
                ':error_mensaje' => $errorMsg,
                ':id'            => $idEvent,
            ]);
        } catch (Exception $e) {
            error_log('ShipEdgeWebhookAndReconcileService::marcarWebhookEvent error: ' . $e->getMessage());
        }
    }

    private static function registrarHistorialEstado(PDO $db, int $idPedido, int $idEstadoAnterior, int $idEstadoNuevo, string $observaciones): void
    {
        try {
            $histId = false;
            try {
                // Verificar si el trigger de pedidos ya insertó el registro en los últimos 3 segundos
                $stmtCheck = $db->prepare("
                    SELECT id FROM pedidos_historial_estados
                    WHERE id_pedido = :id_pedido
                      AND (id_estado_nuevo = :id_estado1 OR id_estado = :id_estado2)
                      AND created_at >= (NOW() - INTERVAL 3 SECOND)
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtCheck->execute([
                    ':id_pedido'  => $idPedido,
                    ':id_estado1' => $idEstadoNuevo,
                    ':id_estado2' => $idEstadoNuevo,
                ]);
                $histId = $stmtCheck->fetchColumn();
            } catch (Exception $innerEx) {
                $histId = false;
            }

            if ($histId) {
                $stmtUpd = $db->prepare("
                    UPDATE pedidos_historial_estados
                    SET observaciones = :obs
                    WHERE id = :id
                ");
                $stmtUpd->execute([
                    ':obs' => $observaciones,
                    ':id'  => (int)$histId,
                ]);
            } else {
                try {
                    $stmtIns = $db->prepare("
                        INSERT INTO pedidos_historial_estados
                            (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario, observaciones, created_at)
                        VALUES
                            (:id_pedido, :id_estado_anterior, :id_estado_nuevo, NULL, :obs, NOW())
                    ");
                    $stmtIns->execute([
                        ':id_pedido'          => $idPedido,
                        ':id_estado_anterior' => $idEstadoAnterior,
                        ':id_estado_nuevo'    => $idEstadoNuevo,
                        ':obs'                => $observaciones,
                    ]);
                } catch (Exception $colEx) {
                    $stmtIns = $db->prepare("
                        INSERT INTO pedidos_historial_estados (id_pedido, id_estado, id_usuario, observaciones, created_at)
                        VALUES (:id_pedido, :id_estado, NULL, :obs, NOW())
                    ");
                    $stmtIns->execute([
                        ':id_pedido' => $idPedido,
                        ':id_estado' => $idEstadoNuevo,
                        ':obs'       => $observaciones,
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log('ShipEdgeWebhookAndReconcileService::registrarHistorialEstado aviso: ' . $e->getMessage());
        }
    }

    private static function obtenerNombreEstado(PDO $db, int $idEstado): string
    {
        try {
            $stmt = $db->prepare("SELECT nombre_estado FROM estados_pedidos WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $idEstado]);
            $nombre = $stmt->fetchColumn();
            if ($nombre) {
                return (string)$nombre;
            }
        } catch (Exception $e) {}

        $fallback = [
            1  => 'En bodega',
            2  => 'En ruta o proceso',
            3  => 'Entregado',
            4  => 'Con incidencia',
            6  => 'Cancelado',
            7  => 'Rechazado por cliente',
            12 => 'Recolectado por mensajería',
        ];
        return $fallback[$idEstado] ?? ('Estado #' . $idEstado);
    }

    // =========================================================================
    // 6. CONCILIACIÓN PERIÓDICA DE ÓRDENES SHIPEDGE (FALLBACK DE WEBHOOKS)
    // =========================================================================

    /**
     * Concilia periódicamente las órdenes activas de ShipEdge para detectar cambios de estado,
     * generación de guía o número de tracking cuando falle o se retrase un webhook,
     * respetando el contrato v41 y la paginación del API.
     *
     * @param PDO $db
     * @param int|null $idProviderFilter Filtrar un proveedor específico (opcional)
     * @param int $maxOrdersPerRun Límite de órdenes por ejecución
     * @param callable|null $httpClientFactory Factory opcional para inyectar cliente HTTP en pruebas
     * @return array Resumen de conciliación
     */
    public static function conciliarOrdenesPendientes(
        PDO $db,
        ?int $idProviderFilter = null,
        int $maxOrdersPerRun = 50,
        ?callable $httpClientFactory = null
    ): array {
        $summary = [
            'providers_checked' => 0,
            'orders_checked'    => 0,
            'orders_updated'    => 0,
            'errors'            => [],
            'details'           => [],
        ];

        $sqlProv = "SELECT * FROM forwarding_providers WHERE slug = 'shipedge' AND activo = 1";
        $paramsProv = [];
        if ($idProviderFilter !== null && $idProviderFilter > 0) {
            $sqlProv .= " AND id = :id";
            $paramsProv[':id'] = $idProviderFilter;
        }

        $stmtProv = $db->prepare($sqlProv);
        $stmtProv->execute($paramsProv);
        $providers = $stmtProv->fetchAll(PDO::FETCH_ASSOC);

        foreach ($providers as $provRow) {
            $idProvider = (int)$provRow['id'];
            $summary['providers_checked']++;

            try {
                $provider = new ShipEdgeProvider($provRow);
                if ($httpClientFactory !== null) {
                    $provider->setHttpClient($httpClientFactory($provRow));
                }

                // Buscar pedidos enviados a este proveedor que aún no estén en estado final (3=Entregado, 6=Cancelado, 7=Rechazado)
                $stmtPend = $db->prepare("
                    SELECT p.id AS id_pedido, p.numero_orden, p.id_estado, p.numero_traking,
                           fg.numero_guia, fg.metadata, fl.external_order_id
                    FROM pedidos p
                    LEFT JOIN forwarding_guias fg ON fg.id_pedido = p.id AND fg.id_provider = :id_prov1
                    LEFT JOIN forwarding_log fl ON fl.id_pedido = p.id AND fl.id_provider = :id_prov2 AND fl.status = 'success'
                    WHERE (fg.id IS NOT NULL OR fl.id IS NOT NULL)
                      AND p.id_estado NOT IN (3, 6, 7)
                    GROUP BY p.id
                    ORDER BY p.id ASC
                    LIMIT " . max(1, (int)$maxOrdersPerRun)
                );
                $stmtPend->execute([
                    ':id_prov1' => $idProvider,
                    ':id_prov2' => $idProvider,
                ]);
                $pendientes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);

                foreach ($pendientes as $item) {
                    $orderNumber = (string)$item['numero_orden'];
                    $meta        = json_decode($item['metadata'] ?? '{}', true) ?: [];
                    $extOrderId  = $meta['external_order_id'] ?? $item['external_order_id'] ?? null;

                    $summary['orders_checked']++;

                    $remota = $provider->consultarOrden($orderNumber, 'order_number');
                    if ($remota === null && !empty($extOrderId)) {
                        $remota = $provider->consultarOrden((string)$extOrderId, 'order_id');
                    }

                    if ($remota === null || empty($remota['exists'])) {
                        continue;
                    }

                    $eventoPayload = [
                        'order_number'    => $remota['order_number'] ?? $orderNumber,
                        'order_id'        => $remota['external_order_id'] ?? $extOrderId,
                        'order_status'    => $remota['external_status'] ?? null,
                        'tracking_number' => $remota['tracking_number'] ?? null,
                        'guide_number'    => $remota['numero_guia'] ?? null,
                        'carrier_name'    => $remota['carrier_name'] ?? null,
                    ];

                    $rawJson = json_encode($eventoPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $proc = self::procesarEventoOrden($db, $provRow, $eventoPayload, $rawJson, 'reconciliacion');

                    if (!empty($proc['success']) && empty($proc['duplicate']) && (!empty($proc['estado_cambiado']) || !empty($proc['tracking_number']))) {
                        $summary['orders_updated']++;
                    }
                    $summary['details'][] = [
                        'numero_orden'    => $orderNumber,
                        'external_status' => $remota['external_status'] ?? null,
                        'tracking_number' => $remota['tracking_number'] ?? null,
                        'estado_cambiado' => $proc['estado_cambiado'] ?? false,
                        'duplicate'       => $proc['duplicate'] ?? false,
                    ];
                }
            } catch (Exception $e) {
                $summary['errors'][] = "Proveedor #{$idProvider}: " . $e->getMessage();
            }
        }

        return $summary;
    }
}
