<?php
/**
 * ShipEdgeProvider — Driver de integración para ShipEdge OMS API v41
 *
 * Características principales:
 *  - Base URL configurable (por defecto: https://mlacostarica.shipedge.com/apirest/v41/oms)
 *    con normalización automática para evitar duplicar /apirest/v41/oms.
 *  - Bloqueo explícito del dominio prohibido mlamia.shipedge.com.
 *  - Autenticación por encabezados HTTP: AccountID y Key (nunca expuestos en logs ni errores).
 *  - Prueba de conexión mediante POST /ping.
 *  - Construcción de payload POST /orders con {"data": [ ... ]} aplicando primero el mapeo
 *    configurable (PayloadBuilderService + forwarding_api_fields/mappings + overrides por regla)
 *    y después la validación estricta de obligatorios de ShipEdge v41.
 *  - Preservación de códigos postales como texto (con ceros iniciales) y prohibición de
 *    inventar datos faltantes, enviar cod_amount por defecto o dividir precio_total_local.
 *  - Control de duplicados, reintentos y conciliación vía GET /orders/{order}?identify_by=order_number.
 *  - Distinción explícita entre: orden creada (external_order_id), guía generada (numero_guia)
 *    y número de tracking (tracking_number).
 */

require_once __DIR__ . '/BaseProvider.php';
require_once __DIR__ . '/../PayloadBuilderService.php';
require_once __DIR__ . '/../../modelo/forwarding.php';
require_once __DIR__ . '/../../modelo/conexion.php';

class ShipEdgeProvider extends BaseProvider
{
    public const API_PATH_V41 = '/apirest/v41/oms';
    public const FORBIDDEN_DOMAIN = 'mlamia.shipedge.com';

    /** @var int|null ID del proveedor en forwarding_providers */
    private $providerId;

    /** @var callable|null Cliente HTTP inyectable para pruebas unitarias sin tráfico real */
    private $customHttpClient = null;

    /**
     * Campos permitidos en el objeto de orden de ShipEdge v41 (POST /orders -> data[]).
     */
    private const ALLOWED_ORDER_FIELDS = [
        'order_number',
        'shipping_method',
        'customer',
        'items',
        'comment',
        'notes',
        'store',
        'channel',
        'carrier',
        'carrier_name',
        'warehouse',
        'reference_number',
    ];

    /**
     * Campos permitidos dentro de customer{} en ShipEdge v41.
     */
    private const ALLOWED_CUSTOMER_FIELDS = [
        'firstname',
        'lastname',
        'company',
        'address1',
        'address2',
        'city',
        'state',
        'zip',
        'country',
        'phone',
        'email',
    ];

    /**
     * Campos permitidos dentro de items[] en ShipEdge v41.
     */
    private const ALLOWED_ITEM_FIELDS = [
        'sku',
        'quantity',
        'sold_price',
        'description',
    ];

    public function __construct($baseUrlOrRow, array $credentials = [], array $config = [])
    {
        if (is_array($baseUrlOrRow)) {
            $providerRow = $baseUrlOrRow;
            $baseUrl = (string)($providerRow['base_url'] ?? '');
            $creds = is_string($providerRow['credentials'] ?? null)
                ? (json_decode($providerRow['credentials'], true) ?: [])
                : (is_array($providerRow['credentials'] ?? null) ? $providerRow['credentials'] : []);
            $cfg = array_merge(
                is_string($providerRow['default_config'] ?? null) ? (json_decode($providerRow['default_config'], true) ?: []) : [],
                $credentials // 2nd arg acts as configOverride when 1st arg is $providerRow
            );
            parent::__construct($baseUrl, $creds, $cfg);
            $this->providerId = isset($providerRow['id']) ? (int)$providerRow['id'] : (isset($cfg['id_provider']) ? (int)$cfg['id_provider'] : null);
        } else {
            parent::__construct((string)$baseUrlOrRow, $credentials, $config);
            $this->providerId = isset($config['id_provider']) ? (int)$config['id_provider'] : null;
        }
        $this->baseUrl = self::normalizeBaseUrl($this->baseUrl);
    }

    /**
     * Permite inyectar un cliente HTTP mock/stub para pruebas automatizadas.
     * Firma del callable: function(string $method, string $url, array $headers, ?string $rawBody, int $timeout): array
     */
    public function setHttpClient(?callable $httpClient): void
    {
        $this->customHttpClient = $httpClient;
    }

    /**
     * Normaliza la URL base de ShipEdge asegurando que:
     * 1. No utilice el dominio prohibido mlamia.shipedge.com.
     * 2. No duplique /apirest/v41/oms al construir las URLs.
     *
     * @param string $rawUrl
     * @return string
     * @throws InvalidArgumentException
     */
    public static function normalizeBaseUrl(string $rawUrl): string
    {
        $url = trim($rawUrl);
        if ($url === '') {
            $url = 'https://mlacostarica.shipedge.com/apirest/v41/oms';
        }

        // Bloquear dominio explícitamente prohibido
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === self::FORBIDDEN_DOMAIN || stripos($url, self::FORBIDDEN_DOMAIN) !== false) {
            throw new InvalidArgumentException(
                'ShipEdgeProvider: El dominio mlamia.shipedge.com está prohibido para esta integración. Utilice el dominio configurado de la cuenta (ej. mlacostarica.shipedge.com).'
            );
        }

        $url = rtrim($url, '/');

        // Eliminar repeticiones accidentales de /apirest/v41/oms
        $pattern = '#(/apirest/v41/oms)+$#i';
        if (preg_match($pattern, $url)) {
            $url = preg_replace($pattern, self::API_PATH_V41, $url);
        } elseif (stripos($url, '/apirest/v41/oms') === false) {
            $url .= self::API_PATH_V41;
        }

        return $url;
    }

    /**
     * Construye la URL final hacia un endpoint de ShipEdge v41 sin duplicar /apirest/v41/oms.
     *
     * @param string $endpoint Ej: '/ping', '/orders', '/orders/ORD-123'
     * @param array $queryParams Parámetros GET opcionales (ej. ['identify_by' => 'order_number'])
     * @return string
     */
    public function buildEndpointUrl(string $endpoint, array $queryParams = []): string
    {
        $base = self::normalizeBaseUrl($this->baseUrl);
        $cleanEndpoint = '/' . ltrim($endpoint, '/');

        // Si el endpoint recibido ya traía /apirest/v41/oms al inicio, removerlo para no duplicar
        if (stripos($cleanEndpoint, self::API_PATH_V41 . '/') === 0) {
            $cleanEndpoint = substr($cleanEndpoint, strlen(self::API_PATH_V41));
        } elseif (strcasecmp($cleanEndpoint, self::API_PATH_V41) === 0) {
            $cleanEndpoint = '';
        }

        $fullUrl = $base . $cleanEndpoint;
        if (!empty($queryParams)) {
            $fullUrl .= (strpos($fullUrl, '?') === false ? '?' : '&') . http_build_query($queryParams);
        }

        return $fullUrl;
    }

    /**
     * Obtiene AccountID desde credenciales (soportando account_id, AccountID o userName).
     */
    private function getAccountId(): string
    {
        return trim((string)(
            $this->config['account_id']
            ?? $this->config['AccountID']
            ?? $this->credentials['account_id']
            ?? $this->credentials['AccountID']
            ?? $this->credentials['userName']
            ?? ''
        ));
    }

    /**
     * Obtiene API Key desde credenciales (soportando api_key, Key o password).
     */
    private function getApiKey(): string
    {
        return trim((string)(
            $this->config['api_key']
            ?? $this->config['Key']
            ?? $this->config['key']
            ?? $this->credentials['api_key']
            ?? $this->credentials['Key']
            ?? $this->credentials['key']
            ?? $this->credentials['password']
            ?? ''
        ));
    }

    /**
     * Obtiene configuración efectiva combinando credenciales/config del proveedor y overrides de la regla.
     */
    public function getEffectiveConfig(): array
    {
        $merged = array_merge(
            is_array($this->credentials) ? $this->credentials : [],
            is_array($this->config) ? $this->config : []
        );

        return [
            'shipping_method'    => trim((string)($merged['shipping_method'] ?? '')),
            'carrier_name'       => trim((string)($merged['carrier_name'] ?? '')),
            'store_channel'      => trim((string)($merged['store_channel'] ?? $merged['store'] ?? $merged['channel'] ?? '')),
            'default_country'    => trim((string)($merged['default_country'] ?? '')),
            'currency_code'      => strtoupper(trim((string)($merged['currency_code'] ?? ''))),
            'enable_sold_price'  => filter_var($merged['enable_sold_price'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'mappings_override'  => is_array($merged['mappings_override'] ?? null) ? $merged['mappings_override'] : [],
        ];
    }

    /**
     * Construye encabezados HTTP de autenticación ShipEdge v41.
     * @throws Exception Si faltan credenciales
     */
    private function buildAuthHeaders(): array
    {
        $accountId = $this->getAccountId();
        $apiKey    = $this->getApiKey();

        if ($accountId === '' || $apiKey === '') {
            throw new Exception('ShipEdge: Faltan credenciales obligatorias (AccountID y/o Key) en la configuración del proveedor.', 401);
        }

        return [
            'AccountID: ' . $accountId,
            'Key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
    }

    /**
     * Oculta cualquier dato sensible (AccountID, Key, webhook_secret, tokens) de arreglos o textos.
     *
     * @param mixed $data
     * @return mixed
     */
    public static function sanitizeSensitiveData($data)
    {
        $sensitiveKeys = [
            'key', 'api_key', 'apikey', 'account_id', 'accountid',
            'password', 'webhook_secret', 'secret', 'token', 'authorization',
        ];

        if (is_array($data)) {
            $clean = [];
            foreach ($data as $k => $v) {
                if (in_array(strtolower((string)$k), $sensitiveKeys, true)) {
                    $clean[$k] = '***REDACTED***';
                } else {
                    $clean[$k] = self::sanitizeSensitiveData($v);
                }
            }
            return $clean;
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                return json_encode(self::sanitizeSensitiveData($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $data;
    }

    /**
     * Ejecuta petición HTTP (usando cliente inyectado en pruebas o cURL en ejecución normal).
     */
    protected function executeRequest(string $method, string $url, array $headers = [], ?array $body = null, int $timeout = 30): array
    {
        // Verificar dominio prohibido antes de cualquier llamada
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === self::FORBIDDEN_DOMAIN || stripos($url, self::FORBIDDEN_DOMAIN) !== false) {
            throw new InvalidArgumentException('ShipEdgeProvider: Solicitud bloqueada hacia dominio no permitido (mlamia.shipedge.com).');
        }

        $rawBody = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        if ($this->customHttpClient !== null) {
            $res = call_user_func($this->customHttpClient, $method, $url, $headers, $rawBody, $timeout);
            $httpCode = (int)($res['http_code'] ?? $res['http_status'] ?? 0);
            $decodedBody = is_array($res['body'] ?? null)
                ? self::sanitizeSensitiveData($res['body'])
                : (is_string($res['body'] ?? null) ? self::sanitizeSensitiveData(json_decode($res['body'], true)) : null);
            $sanitizedRaw = is_string($res['raw'] ?? null)
                ? self::sanitizeSensitiveData($res['raw'])
                : json_encode($decodedBody ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $this->lastResponse = [
                'http_code'   => $httpCode,
                'http_status' => $httpCode,
                'body'        => $decodedBody ?? $sanitizedRaw,
                'decoded'     => $decodedBody,
                'raw'         => $sanitizedRaw,
                'error'       => $res['error'] ?? null,
            ];
            return $this->lastResponse;
        }

        $res = $this->httpRequest($method, $url, $headers, $rawBody, $timeout);
        $httpCode = (int)($res['http_status'] ?? $res['http_code'] ?? 0);
        $decodedBody = is_array($res['decoded'] ?? null)
            ? self::sanitizeSensitiveData($res['decoded'])
            : (is_array($res['body'] ?? null) ? self::sanitizeSensitiveData($res['body']) : null);
        $sanitizedRaw = is_string($res['body'] ?? null) ? self::sanitizeSensitiveData($res['body']) : null;

        $this->lastResponse = [
            'http_code'   => $httpCode,
            'http_status' => $httpCode,
            'body'        => $decodedBody ?? $sanitizedRaw,
            'decoded'     => $decodedBody,
            'raw'         => $sanitizedRaw,
            'error'       => $res['error'] ?? null,
        ];
        return $this->lastResponse;
    }

    // =========================================================================
    // 1. AUTENTICACIÓN Y PRUEBA DE CONEXIÓN (POST /ping)
    // =========================================================================

    /**
     * Valida la conexión y credenciales contra POST /ping de ShipEdge v41.
     * Nunca expone AccountID ni Key en el retorno ni en excepciones.
     *
     * @return array
     * @throws Exception
     */
    public function authenticate(): array
    {
        return $this->ping();
    }

    /**
     * Llama a POST /ping en ShipEdge OMS v41.
     *
     * @return array Resultado seguro sin credenciales
     * @throws Exception
     */
    public function ping(): array
    {
        $headers = $this->buildAuthHeaders();
        $url     = $this->buildEndpointUrl('/ping');

        $res = $this->executeRequest('POST', $url, $headers, []);

        if (!empty($res['error'])) {
            throw new Exception('ShipEdge POST /ping error de conexión: ' . $res['error'], 504);
        }

        $httpCode = (int)($res['http_code'] ?? 0);
        $body     = $res['body'];

        if ($httpCode === 401 || $httpCode === 403) {
            throw new Exception("ShipEdge POST /ping rechazado (HTTP {$httpCode}): Credenciales AccountID o Key inválidas.", 401);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = is_array($body)
                ? ($body['message'] ?? $body['error'] ?? 'Respuesta inesperada del servidor')
                : 'HTTP ' . $httpCode;
            throw new Exception("ShipEdge POST /ping falló (HTTP {$httpCode}): {$msg}", $httpCode ?: 502);
        }

        // Verificar si el cuerpo JSON indica error explícito de autenticación o estado
        if (is_array($body)) {
            $statusField = strtolower((string)($body['status'] ?? $body['result'] ?? ''));
            if (in_array($statusField, ['error', 'failed', 'unauthorized', 'denied'], true)) {
                $errDetail = $body['message'] ?? $body['error'] ?? 'Error reportado por ShipEdge en /ping';
                throw new Exception("ShipEdge POST /ping reportó error: {$errDetail}", 401);
            }
        }

        $sanitizedBody = self::sanitizeSensitiveData($body);

        return [
            'success'       => true,
            'authenticated' => true,
            'provider'      => 'shipedge',
            'api_version'   => 'v41',
            'endpoint'      => $url,
            'http_code'     => $httpCode,
            'response'      => $sanitizedBody,
            'ping_response' => $sanitizedBody,
        ];
    }

    // =========================================================================
    // 2. MAPEO CONFIGURABLE Y VALIDACIÓN ESTRICTA DE PAYLOAD (POST /orders)
    // =========================================================================

    /**
     * Obtiene las reglas de mapeo configuradas para este proveedor (o la plantilla inicial ShipEdge),
     * aplicando cualquier override definido a nivel de regla por cliente.
     *
     * @return array
     */
    public function obtenerMapeosConfigurados(): array
    {
        $mapeos = [];
        if ($this->providerId) {
            $mapeos = ForwardingModel::obtenerMapeosDeProveedor($this->providerId);
        }

        if (empty($mapeos)) {
            $mapeos = ForwardingModel::getPlantillaInicialShipEdge();
        }

        // Aplicar overrides por regla/cliente si existen en config_override['mappings_override']
        $effectiveConfig = $this->getEffectiveConfig();
        $overrides = $effectiveConfig['mappings_override'] ?? [];
        if (!empty($overrides) && is_array($overrides)) {
            $indexByPath = [];
            foreach ($mapeos as $idx => $m) {
                $indexByPath[$m['field_path']] = $idx;
            }
            foreach ($overrides as $fieldPath => $overrideData) {
                if (is_string($overrideData)) {
                    $overrideData = ['internal_key' => $overrideData];
                }
                if (!is_array($overrideData)) {
                    continue;
                }
                if (isset($indexByPath[$fieldPath])) {
                    $i = $indexByPath[$fieldPath];
                    $mapeos[$i] = array_merge($mapeos[$i], $overrideData);
                } else {
                    $mapeos[] = array_merge([
                        'field_path'     => $fieldPath,
                        'field_type'     => 'string',
                        'is_required'    => 0,
                        'default_value'  => null,
                        'internal_key'   => '',
                        'transform_rule' => null,
                    ], $overrideData);
                }
            }
        }

        return $mapeos;
    }

    /**
     * Construye y valida el payload de creación de orden para ShipEdge OMS v41.
     *
     * Orden de ejecución requerido:
     *  1. Preparar variables de contexto y configuración de cuenta/regla (sin inventar datos).
     *  2. Aplicar el mapeo configurado mediante PayloadBuilderService::build().
     *  3. Normalizar estructura {"data": [ $orderObject ]} y filtrar solo campos reconocidos por v41.
     *  4. Preservar código postal (customer.zip) y order_number como texto (manteniendo ceros iniciales).
     *  5. Validar estrictamente todos los campos obligatorios de ShipEdge v41 antes del envío.
     *
     * @param array $pedido
     * @param array $productos
     * @param mixed $authData
     * @return array Estructura {"data": [ { ... } ]}
     * @throws Exception Si falta algún dato obligatorio o hay violación de reglas de negocio.
     */
    public function mapearCampos(array $pedido, array $productos = [], $authData = [], bool $validateRequired = true)
    {
        $effectiveConfig = $this->getEffectiveConfig();

        // Clonar pedido e inyectar productos y claves de configuración de cuenta/regla
        $pedidoContext = $pedido;
        $pedidoContext['productos'] = !empty($productos) ? $productos : ($pedido['productos'] ?? []);

        $pedidoContext['_config_shipping_method'] = $effectiveConfig['shipping_method'];
        $pedidoContext['_config_carrier_name']    = $effectiveConfig['carrier_name'];
        $pedidoContext['_config_store_channel']   = $effectiveConfig['store_channel'];
        $pedidoContext['_config_default_country'] = $effectiveConfig['default_country'];

        // Validar si la moneda del pedido coincide con la moneda confirmada para sold_price
        $monedaPedido = strtoupper(trim((string)($pedido['moneda_codigo'] ?? $pedido['currency'] ?? '')));
        $monedaConfig = $effectiveConfig['currency_code'];
        $pedidoContext['_currency_validated'] = (
            !empty($effectiveConfig['enable_sold_price'])
            && $monedaConfig !== ''
            && $monedaPedido !== ''
            && $monedaPedido === $monedaConfig
        );

        // 1. Obtener mapeos configurados y construir estructura base
        $mapeos = $this->obtenerMapeosConfigurados();

        // Verificar que ningún mapeo intente usar precio_total_local como items[].sold_price
        // ni enviar cod_amount por defecto
        foreach ($mapeos as $m) {
            $fp = trim((string)($m['field_path'] ?? ''));
            $ik = trim((string)($m['internal_key'] ?? ''));
            if ((stripos($fp, 'sold_price') !== false) && $ik === 'precio_total_local') {
                throw new Exception(
                    'ShipEdge validación: No está permitido transformar precio_total_local en precio unitario (items[].sold_price).',
                    422
                );
            }
            if (stripos($fp, 'cod_amount') !== false && empty($this->config['allow_cod_amount'])) {
                throw new Exception(
                    'ShipEdge validación: No se permite enviar cod_amount por defecto sin confirmación comercial y de moneda con ShipEdge.',
                    422
                );
            }
        }

        $built = PayloadBuilderService::build($pedidoContext, $mapeos, [
            'omit_empty_optional'       => true,
            'require_items'             => true,
            'defer_required_validation' => true,
        ]);

        // Si el mapeo tenía prefijo data[]. o data.0., extraer el objeto de orden
        if (isset($built['data']) && is_array($built['data']) && isset($built['data'][0]) && is_array($built['data'][0])) {
            $orderObj = $built['data'][0];
        } else {
            $orderObj = $built;
        }

        // Aplicar overrides opcionales de canal/tienda o carrier si están configurados y mapeados
        if (!empty($effectiveConfig['store_channel']) && !isset($orderObj['store']) && !empty($this->config['send_store_field'])) {
            $orderObj['store'] = $effectiveConfig['store_channel'];
        }
        if (!empty($effectiveConfig['carrier_name']) && !isset($orderObj['carrier']) && !empty($this->config['send_carrier_field'])) {
            $orderObj['carrier'] = $effectiveConfig['carrier_name'];
        }

        // 2. Filtrar campos no reconocidos por ShipEdge v41 y limpiar opcionales vacíos
        $orderObj = self::filtrarCamposReconocidosV41($orderObj);

        // 3. Preservar order_number y customer.zip estrictamente como string (conservando ceros iniciales)
        if (isset($orderObj['order_number'])) {
            $orderObj['order_number'] = trim((string)$orderObj['order_number']);
        }
        if (isset($orderObj['customer']['zip'])) {
            $orderObj['customer']['zip'] = trim((string)$orderObj['customer']['zip']);
        }

        // 4. Validar obligatorios de ShipEdge v41 después de aplicar el mapeo
        if ($validateRequired) {
            self::validarOrdenShipEdgeV41($orderObj, true);
        }

        return [
            'data' => [
                $orderObj,
            ],
        ];
    }

    /**
     * Filtra el objeto de orden para asegurar que solo contenga campos admitidos por ShipEdge OMS v41
     * y omite campos opcionales vacíos.
     *
     * @param array $orderObj
     * @return array
     */
    public static function filtrarCamposReconocidosV41(array $orderObj): array
    {
        $clean = [];
        foreach ($orderObj as $key => $val) {
            if (!in_array($key, self::ALLOWED_ORDER_FIELDS, true)) {
                continue;
            }
            if ($key === 'customer' && is_array($val)) {
                $cust = [];
                foreach ($val as $ck => $cv) {
                    if (!in_array($ck, self::ALLOWED_CUSTOMER_FIELDS, true)) {
                        continue;
                    }
                    $strVal = trim((string)($cv ?? ''));
                    // Para obligatorios mantenemos la clave aunque esté vacía para que el validador lo reporte;
                    // para opcionales (email, phone, address2, lastname, company) omitimos si está vacío.
                    $isCustRequired = in_array($ck, ['firstname', 'address1', 'city', 'state', 'zip', 'country'], true);
                    if ($strVal === '' && !$isCustRequired) {
                        continue;
                    }
                    $cust[$ck] = $strVal;
                }
                $clean['customer'] = $cust;
            } elseif ($key === 'items' && is_array($val)) {
                $items = [];
                foreach ($val as $itemRow) {
                    if (!is_array($itemRow)) {
                        continue;
                    }
                    $cleanItem = [];
                    foreach ($itemRow as $ik => $iv) {
                        if (!in_array($ik, self::ALLOWED_ITEM_FIELDS, true)) {
                            continue;
                        }
                        if ($ik === 'sku') {
                            $cleanItem['sku'] = trim((string)($iv ?? ''));
                        } elseif ($ik === 'quantity') {
                            $cleanItem['quantity'] = (int)$iv;
                        } elseif ($ik === 'sold_price') {
                            if ($iv !== null && $iv !== '' && (float)$iv > 0) {
                                $cleanItem['sold_price'] = round((float)$iv, 2);
                            }
                        } elseif ($ik === 'description') {
                            $desc = trim((string)($iv ?? ''));
                            if ($desc !== '') {
                                $cleanItem['description'] = $desc;
                            }
                        }
                    }
                    $items[] = $cleanItem;
                }
                $clean['items'] = $items;
            } else {
                $strVal = is_scalar($val) ? trim((string)$val) : '';
                $isTopRequired = in_array($key, ['order_number', 'shipping_method'], true);
                if ($strVal === '' && !$isTopRequired) {
                    continue;
                }
                $clean[$key] = $strVal;
            }
        }

        return $clean;
    }

    /**
     * Valida los campos obligatorios del contrato ShipEdge v41:
     * order_number, shipping_method, customer.firstname, customer.address1,
     * customer.city, customer.state, customer.zip, customer.country,
     * al menos un producto en items[], y sku + quantity (> 0) en cada ítem.
     *
     * Prohibido inventar municipios, departamentos, códigos postales, SKU, métodos de envío ni países.
     *
     * @param array $orderObj
     * @param bool  $throwOnError Si es true (default), lanza Exception; si es false, retorna el arreglo de errores.
     * @return array Lista de campos obligatorios faltantes
     * @throws Exception (código 422) si falta cualquier campo obligatorio y $throwOnError es true
     */
    public static function validarOrdenShipEdgeV41(array $orderObj, bool $throwOnError = true): array
    {
        $faltantes = [];

        if (trim((string)($orderObj['order_number'] ?? '')) === '') {
            $faltantes[] = 'order_number (numero_orden)';
        }
        if (trim((string)($orderObj['shipping_method'] ?? '')) === '') {
            $faltantes[] = 'shipping_method (método de envío configurado en la cuenta/regla)';
        }

        $customer = $orderObj['customer'] ?? [];
        if (!is_array($customer)) {
            $customer = [];
        }

        $requiredCustomer = [
            'firstname' => 'customer.firstname (destinatario)',
            'address1'  => 'customer.address1 (direccion)',
            'city'      => 'customer.city (municipio_nombre / municipalitiesName)',
            'state'     => 'customer.state (departamento / departmentName)',
            'zip'       => 'customer.zip (codigo_postal / postalCode)',
            'country'   => 'customer.country (país de destino)',
        ];

        foreach ($requiredCustomer as $field => $label) {
            $val = isset($customer[$field]) ? trim((string)$customer[$field]) : '';
            if ($val === '') {
                $faltantes[] = $label;
            }
        }

        $items = $orderObj['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            $faltantes[] = 'items (al menos un producto en el pedido)';
        } else {
            foreach ($items as $idx => $item) {
                $pos = $idx + 1;
                $sku = isset($item['sku']) ? trim((string)$item['sku']) : '';
                $qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;

                if ($sku === '') {
                    $faltantes[] = "items[{$pos}].sku (SKU del producto #{$pos})";
                }
                if ($qty <= 0) {
                    $faltantes[] = "items[{$pos}].quantity (cantidad > 0 en producto #{$pos})";
                }
            }
        }

        if (!empty($faltantes) && $throwOnError) {
            throw new Exception(
                'ShipEdge v41 validación fallida — Faltan campos obligatorios: ' . implode(', ', $faltantes) . '.',
                422
            );
        }

        return $faltantes;
    }

    /**
     * Genera una vista previa segura del payload para mostrar al administrador sin enviar el pedido.
     *
     * @param array $pedido
     * @param array $productos
     * @return array ['payload' => array, 'valid' => bool, 'errors' => array, 'warnings' => array]
     */
    public function previsualizarPayload(array $pedido, array $productos = []): array
    {
        $warnings = [];
        $effectiveConfig = $this->getEffectiveConfig();

        if ($effectiveConfig['shipping_method'] === '') {
            $warnings[] = 'El parámetro shipping_method aún no está configurado en el proveedor o regla.';
        }
        if ($effectiveConfig['default_country'] === '' && empty($pedido['pais_codigo_iso']) && empty($pedido['pais_nombre'])) {
            $warnings[] = 'El pedido no tiene país asociado y no hay un país predeterminado configurado.';
        }
        if (!empty($effectiveConfig['enable_sold_price']) && $effectiveConfig['currency_code'] === '') {
            $warnings[] = 'items[].sold_price está habilitado pero falta configurar currency_code para validar la moneda.';
        }

        try {
            $payload = $this->mapearCampos($pedido, $productos, [], false);
            $orderObj = $payload['data'][0] ?? [];
            $faltantes = self::validarOrdenShipEdgeV41($orderObj, false);
            return [
                'valid'    => empty($faltantes),
                'payload'  => self::sanitizeSensitiveData($payload),
                'errors'   => $faltantes,
                'warnings' => $warnings,
            ];
        } catch (Exception $e) {
            return [
                'valid'    => false,
                'payload'  => null,
                'errors'   => [$e->getMessage()],
                'warnings' => $warnings,
            ];
        }
    }

    // =========================================================================
    // 3. CONSULTA Y CONCILIACIÓN DE ÓRDENES (GET /orders/{order} y GET /orders)
    // =========================================================================

    /**
     * Consulta una orden en ShipEdge OMS v41 mediante GET /orders/{order}?identify_by={identify_by}.
     *
     * @param string $orderIdentifier Número de orden (order_number) o ID interno de ShipEdge (order_id)
     * @param string $identifyBy      'order_number' (default) o 'order_id'
     * @return array|null             Datos normalizados de la orden si existe, o null si no existe (404)
     * @throws Exception              En caso de error de red o autenticación (401/5xx)
     */
    public function consultarOrden(string $orderIdentifier, string $identifyBy = 'order_number'): ?array
    {
        $orderIdentifier = trim($orderIdentifier);
        if ($orderIdentifier === '') {
            throw new InvalidArgumentException('ShipEdge consultarOrden: El identificador de la orden no puede estar vacío.');
        }

        $allowedIdentifyBy = ['order_number', 'order_id'];
        if (!in_array($identifyBy, $allowedIdentifyBy, true)) {
            $identifyBy = 'order_number';
        }

        $headers = $this->buildAuthHeaders();
        $url     = $this->buildEndpointUrl('/orders/' . rawurlencode($orderIdentifier), [
            'identify_by' => $identifyBy,
        ]);

        $res = $this->executeRequest('GET', $url, $headers, null);

        if (!empty($res['error'])) {
            throw new Exception('ShipEdge GET /orders error de conexión: ' . $res['error'], 504);
        }

        $httpCode = (int)($res['http_code'] ?? 0);
        $body     = $res['body'];

        if ($httpCode === 404) {
            return null;
        }

        if ($httpCode === 401 || $httpCode === 403) {
            throw new Exception("ShipEdge GET /orders no autorizado (HTTP {$httpCode}).", 401);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("ShipEdge GET /orders falló con HTTP {$httpCode}.", $httpCode ?: 502);
        }

        return self::extraerDatosOrdenDeRespuesta($body, $orderIdentifier);
    }

    /**
     * Lista órdenes de ShipEdge v41 respetando paginación para la conciliación periódica.
     *
     * @param array $params Ej: ['page' => 1, 'limit' => 50, 'status' => 'shipped']
     * @return array
     * @throws Exception
     */
    public function listarOrdenes(array $params = []): array
    {
        $headers = $this->buildAuthHeaders();
        $query   = [];

        if (isset($params['page'])) {
            $query['page'] = max(1, (int)$params['page']);
        }
        if (isset($params['limit'])) {
            $query['limit'] = min(100, max(1, (int)$params['limit']));
        }
        if (!empty($params['status'])) {
            $query['status'] = (string)$params['status'];
        }
        if (!empty($params['date_from'])) {
            $query['date_from'] = (string)$params['date_from'];
        }
        if (!empty($params['date_to'])) {
            $query['date_to'] = (string)$params['date_to'];
        }

        $url = $this->buildEndpointUrl('/orders', $query);
        $res = $this->executeRequest('GET', $url, $headers, null);

        if (!empty($res['error'])) {
            throw new Exception('ShipEdge GET /orders (list) error de conexión: ' . $res['error'], 504);
        }

        $httpCode = (int)($res['http_code'] ?? 0);
        if ($httpCode === 401 || $httpCode === 403) {
            throw new Exception("ShipEdge GET /orders (list) no autorizado (HTTP {$httpCode}).", 401);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("ShipEdge GET /orders (list) falló con HTTP {$httpCode}.", $httpCode ?: 502);
        }

        $body = $res['body'];
        $rawOrders = [];
        if (is_array($body)) {
            if (isset($body['data']) && is_array($body['data'])) {
                $rawOrders = $body['data'];
            } elseif (isset($body['orders']) && is_array($body['orders'])) {
                $rawOrders = $body['orders'];
            } elseif (array_is_list($body)) {
                $rawOrders = $body;
            }
        }

        $normalized = [];
        foreach ($rawOrders as $row) {
            if (is_array($row)) {
                $parsed = self::extraerDatosOrdenDeRespuesta($row);
                if ($parsed !== null) {
                    $normalized[] = $parsed;
                }
            }
        }

        return [
            'orders'     => $normalized,
            'pagination' => [
                'page'       => $body['page'] ?? $body['pagination']['page'] ?? ($query['page'] ?? 1),
                'total'      => $body['total'] ?? $body['pagination']['total'] ?? count($normalized),
                'has_more'   => !empty($body['has_more']) || !empty($body['pagination']['has_more']) || !empty($body['next_page']),
            ],
            'raw'        => self::sanitizeSensitiveData($body),
        ];
    }

    /**
     * Extrae de forma segura los metadatos de una orden desde la respuesta de GET /orders/{order}.
     * Distingue claramente entre:
     *  - external_order_id (ID de la orden en ShipEdge OMS)
     *  - numero_guia (Guía/etiqueta generada si existe)
     *  - tracking_number (Número de rastreo del carrier si existe)
     *
     * @param mixed $body
     * @param string|null $expectedOrderNumber
     * @return array|null
     */
    public static function extraerDatosOrdenDeRespuesta($body, ?string $expectedOrderNumber = null): ?array
    {
        if (!is_array($body) || empty($body)) {
            return null;
        }

        // Si el cuerpo indica explícitamente que no se encontró la orden
        $statusTop = strtolower((string)($body['status'] ?? $body['result'] ?? ''));
        if (in_array($statusTop, ['error', 'not_found', 'not found', 'failed'], true) && empty($body['order_id']) && empty($body['id']) && empty($body['data'])) {
            return null;
        }

        // Desempaquetar contenedor data / order / orders[0]
        $orderNode = $body;
        if (isset($body['data']) && is_array($body['data'])) {
            if (isset($body['data'][0]) && is_array($body['data'][0])) {
                $orderNode = $body['data'][0];
            } elseif (!array_is_list($body['data'])) {
                $orderNode = $body['data'];
            } else {
                return null;
            }
        } elseif (isset($body['order']) && is_array($body['order'])) {
            $orderNode = $body['order'];
        } elseif (isset($body['orders'][0]) && is_array($body['orders'][0])) {
            $orderNode = $body['orders'][0];
        }

        $externalOrderId = trim((string)(
            $orderNode['order_id']
            ?? $orderNode['id']
            ?? $orderNode['oms_order_id']
            ?? $orderNode['external_order_id']
            ?? ''
        ));

        $orderNumber = trim((string)(
            $orderNode['order_number']
            ?? $orderNode['order_num']
            ?? $orderNode['reference_number']
            ?? $expectedOrderNumber
            ?? ''
        ));

        if ($externalOrderId === '' && ($orderNumber === '' || (!isset($orderNode['order_number']) && !isset($orderNode['status'])))) {
            return null;
        }

        // Extraer guía y tracking (sin confundirlos con external_order_id)
        $shipmentNode = is_array($orderNode['shipment'] ?? null)
            ? $orderNode['shipment']
            : (is_array($orderNode['shipments'][0] ?? null) ? $orderNode['shipments'][0] : []);

        $trackingNumber = trim((string)(
            $orderNode['tracking_number']
            ?? $orderNode['tracking']
            ?? $orderNode['tracking_num']
            ?? $shipmentNode['tracking_number']
            ?? $shipmentNode['tracking']
            ?? ''
        ));

        $guideNumber = trim((string)(
            $orderNode['guide_number']
            ?? $orderNode['label_id']
            ?? $orderNode['shipment_id']
            ?? $shipmentNode['guide_number']
            ?? $shipmentNode['shipment_id']
            ?? $trackingNumber
            ?? ''
        ));

        $carrierName = trim((string)(
            $orderNode['carrier_name']
            ?? $orderNode['carrier']
            ?? $shipmentNode['carrier_name']
            ?? $shipmentNode['carrier']
            ?? $orderNode['shipping_method']
            ?? ''
        ));

        $externalStatus = trim((string)(
            $orderNode['order_status']
            ?? $orderNode['status']
            ?? $shipmentNode['status']
            ?? ''
        ));

        return [
            'exists'             => true,
            'external_order_id'  => $externalOrderId !== '' ? $externalOrderId : null,
            'order_number'       => $orderNumber !== '' ? $orderNumber : null,
            'external_status'    => $externalStatus !== '' ? $externalStatus : null,
            'numero_guia'        => $guideNumber !== '' ? $guideNumber : null,
            'tracking_number'    => $trackingNumber !== '' ? $trackingNumber : null,
            'carrier_name'       => $carrierName !== '' ? $carrierName : null,
            'order_created'      => true,
            'guide_generated'    => $guideNumber !== '',
            'tracking_generated' => $trackingNumber !== '',
            'raw'                => self::sanitizeSensitiveData($orderNode),
        ];
    }

    // =========================================================================
    // 4. ENVÍO DE PEDIDOS, CONTROL DE DUPLICADOS Y REINTENTOS (POST /orders)
    // =========================================================================

    /**
     * Crea o concilia una orden en ShipEdge OMS v41.
     *
     * Reglas de negocio aplicadas:
     *  - Mantiene un order_number estable por pedido ($pedido['numero_orden']).
     *  - Antes de reenviar, verifica el registro local (forwarding_guias / forwarding_log)
     *    y cuando corresponda consulta GET /orders/{order}?identify_by=order_number.
     *  - Si ShipEdge indica que no importa una orden repetida, lo trata como conciliación
     *    (recupera su estado/ID externo en lugar de inventar otro order_number).
     *  - Distingue entre: orden creada (external_order_id), guía generada (numero_guia)
     *    y número de tracking (tracking_number).
     *  - No marca el pedido como enviado exitosamente si ShipEdge rechazó su creación.
     *
     * @param array $pedido
     * @param array $productos
     * @param mixed $authData
     * @return array
     * @throws Exception
     */
    public function createOrder(array $pedido, array $productos = [], array $authData = [])
    {
        // 1. Construir y validar el payload v41 (lanza excepción 422 si faltan obligatorios)
        $payload     = $this->mapearCampos($pedido, $productos, $authData);
        $orderObj    = $payload['data'][0];
        $orderNumber = (string)$orderObj['order_number'];
        $idPedido    = isset($pedido['id']) ? (int)$pedido['id'] : 0;

        // 2. Verificar registro local previo o intentos anteriores para evitar duplicados
        $localGuia = $this->buscarGuiaLocal($idPedido);
        $huboIntentoPrevio = $localGuia !== null || $this->huboIntentoPrevioEnLog($idPedido);

        if ($huboIntentoPrevio) {
            $existenteRemoto = $this->intentarConciliarOrdenRemota($orderNumber, $localGuia['external_order_id'] ?? null);
            if ($existenteRemoto !== null) {
                $this->registrarGuiaLocal($idPedido, $orderNumber, $existenteRemoto);
                return $this->construirResultadoExitoso($existenteRemoto, true);
            }

            // Si ya tenemos guía/orden local confirmada y la consulta remota no devolvió 404 (ej. modo offline), retornar conciliado local
            if ($localGuia !== null && !empty($localGuia['external_order_id'])) {
                return [
                    'success'            => true,
                    'reconciled'         => true,
                    'order_created'      => true,
                    'guide_generated'    => !empty($localGuia['guide_generated']),
                    'tracking_generated' => !empty($localGuia['tracking_number']),
                    'external_order_id'  => $localGuia['external_order_id'],
                    'numero_guia'        => $localGuia['numero_guia_real'] ?? null,
                    'tracking_number'    => $localGuia['tracking_number'] ?? null,
                    'carrier_name'       => $localGuia['carrier_name'] ?? null,
                ];
            }
        }

        // 3. Enviar POST /orders a ShipEdge v41
        $headers = $this->buildAuthHeaders();
        $url     = $this->buildEndpointUrl('/orders');

        $res = $this->executeRequest('POST', $url, $headers, $payload);

        // 4. Manejo de timeouts o corte de red: verificar mediante GET /orders/{order} si ShipEdge alcanzó a crearla
        if (!empty($res['error'])) {
            $conciliadoTrasTimeout = $this->intentarConciliarOrdenRemota($orderNumber);
            if ($conciliadoTrasTimeout !== null) {
                $this->registrarGuiaLocal($idPedido, $orderNumber, $conciliadoTrasTimeout);
                return $this->construirResultadoExitoso($conciliadoTrasTimeout, true);
            }
            throw new Exception('ShipEdge POST /orders error de red/timeout: ' . $res['error'], 504);
        }

        $httpCode = (int)($res['http_code'] ?? 0);
        $body     = $res['body'];

        if ($httpCode === 401 || $httpCode === 403) {
            throw new Exception("ShipEdge POST /orders no autorizado (HTTP {$httpCode}): Verifique AccountID y Key.", 401);
        }

        // 5. Analizar respuesta de POST /orders (detectando rechazos, respuestas parciales o duplicados)
        $analisis = self::analizarRespuestaPostOrders($body, $httpCode, $orderNumber);

        // Caso: ShipEdge reporta que la orden ya existe / está repetida -> Conciliar con GET /orders/{order}
        if ($analisis['is_duplicate']) {
            $conciliado = $this->intentarConciliarOrdenRemota($orderNumber, $analisis['external_order_id']);
            if ($conciliado !== null) {
                $this->registrarGuiaLocal($idPedido, $orderNumber, $conciliado);
                return $this->construirResultadoExitoso($conciliado, true);
            }

            // Si ShipEdge confirmó que es duplicada y nos dio su ID en la respuesta
            if (!empty($analisis['external_order_id'])) {
                $datosMinimos = [
                    'external_order_id'  => $analisis['external_order_id'],
                    'order_number'       => $orderNumber,
                    'numero_guia'        => $analisis['numero_guia'],
                    'tracking_number'    => $analisis['tracking_number'],
                    'carrier_name'       => $analisis['carrier_name'],
                    'external_status'    => $analisis['external_status'] ?? 'pending',
                    'order_created'      => true,
                    'guide_generated'    => !empty($analisis['numero_guia']),
                    'tracking_generated' => !empty($analisis['tracking_number']),
                    'raw'                => self::sanitizeSensitiveData($body),
                ];
                $this->registrarGuiaLocal($idPedido, $orderNumber, $datosMinimos);
                return $this->construirResultadoExitoso($datosMinimos, true);
            }

            throw new Exception(
                "ShipEdge indicó que la orden '{$orderNumber}' está repetida, pero no fue posible conciliar su estado vía GET /orders.",
                409
            );
        }

        // Caso: ShipEdge rechazó la creación de la orden -> NUNCA marcar como enviado exitosamente
        if (!$analisis['created']) {
            $errCode = ($httpCode >= 400 && $httpCode < 600) ? $httpCode : 422;
            throw new Exception(
                'ShipEdge rechazó la creación de la orden: ' . ($analisis['error_message'] ?: 'Respuesta sin confirmación de creación'),
                $errCode
            );
        }

        // Caso: Orden creada exitosamente en ShipEdge
        // Si POST /orders no devolvió el external_order_id en el cuerpo inmediato, intentar conciliarlo con GET /orders/{order}
        if (empty($analisis['external_order_id'])) {
            $conciliadoPostCreacion = $this->intentarConciliarOrdenRemota($orderNumber);
            if ($conciliadoPostCreacion !== null) {
                $analisis = array_merge($analisis, array_filter($conciliadoPostCreacion, fn($v) => $v !== null));
            }
        }

        $this->registrarGuiaLocal($idPedido, $orderNumber, $analisis);
        return $this->construirResultadoExitoso($analisis, false);
    }

    /**
     * Analiza detalladamente el cuerpo devuelto por POST /orders de ShipEdge v41.
     * Distingue entre:
     *  - Orden creada exitosamente
     *  - Orden repetida / omitida por duplicidad (is_duplicate = true)
     *  - Orden rechazada por error de validación, SKU inexistente, método inválido, etc. (created = false)
     *
     * @param mixed $body
     * @param int $httpCode
     * @param string $orderNumber
     * @return array
     */
    public static function analizarRespuestaPostOrders($body, int $httpCode, string $orderNumber): array
    {
        $result = [
            'created'            => false,
            'is_duplicate'       => false,
            'external_order_id'  => null,
            'order_number'       => $orderNumber,
            'numero_guia'        => null,
            'tracking_number'    => null,
            'carrier_name'       => null,
            'external_status'    => 'pending',
            'order_created'      => false,
            'guide_generated'    => false,
            'tracking_generated' => false,
            'error_message'      => null,
            'raw'                => self::sanitizeSensitiveData($body),
        ];

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = is_array($body)
                ? ($body['message'] ?? $body['error'] ?? json_encode(self::sanitizeSensitiveData($body), JSON_UNESCAPED_UNICODE))
                : "HTTP {$httpCode}";
            if (self::textoIndicaOrdenDuplicada((string)$msg)) {
                $result['is_duplicate'] = true;
            }
            $result['error_message'] = (string)$msg;
            return $result;
        }

        if (!is_array($body)) {
            $result['error_message'] = 'Respuesta de ShipEdge no es un JSON válido.';
            return $result;
        }

        // Revisar listas de errores / órdenes denegadas o repetidas que ShipEdge v41 puede devolver en HTTP 200
        $errorBuckets = [];
        foreach (['errors', 'error', 'denied', 'failed', 'rejected', 'skipped', 'warnings'] as $errKey) {
            if (!empty($body[$errKey])) {
                $errorBuckets[$errKey] = $body[$errKey];
            }
        }
        if (isset($body['data']['errors']) && !empty($body['data']['errors'])) {
            $errorBuckets['data.errors'] = $body['data']['errors'];
        }
        if (isset($body['data']['denied']) && !empty($body['data']['denied'])) {
            $errorBuckets['data.denied'] = $body['data']['denied'];
        }
        if (isset($body['orders']['denied']) && !empty($body['orders']['denied'])) {
            $errorBuckets['orders.denied'] = $body['orders']['denied'];
        }
        if (isset($body['orders']['errors']) && !empty($body['orders']['errors'])) {
            $errorBuckets['orders.errors'] = $body['orders']['errors'];
        }

        $flatErrorText = !empty($errorBuckets) ? json_encode($errorBuckets, JSON_UNESCAPED_UNICODE) : '';
        if ($flatErrorText !== '' && self::textoIndicaOrdenDuplicada($flatErrorText)) {
            $result['is_duplicate']  = true;
            $result['error_message'] = $flatErrorText;
        }

        // Verificar bandera explícita de fallo en nivel raíz
        $rootStatus = strtolower((string)($body['status'] ?? $body['result'] ?? ''));
        if (
            in_array($rootStatus, ['error', 'failed', 'denied', 'false'], true)
            || (isset($body['success']) && $body['success'] === false)
        ) {
            $errMsg = $body['message'] ?? $body['error'] ?? ($flatErrorText ?: 'ShipEdge reportó fallo al procesar la orden');
            if (is_array($errMsg)) {
                $errMsg = json_encode($errMsg, JSON_UNESCAPED_UNICODE);
            }
            if (self::textoIndicaOrdenDuplicada((string)$errMsg)) {
                $result['is_duplicate'] = true;
            }
            $result['error_message'] = (string)$errMsg;
            return $result;
        }

        // Buscar nodo de la orden creada
        $orderNode = null;
        if (isset($body['data']) && is_array($body['data'])) {
            if (isset($body['data']['created'][0]) && is_array($body['data']['created'][0])) {
                $orderNode = $body['data']['created'][0];
            } elseif (isset($body['data']['orders'][0]) && is_array($body['data']['orders'][0])) {
                $orderNode = $body['data']['orders'][0];
            } elseif (isset($body['data'][0]) && is_array($body['data'][0])) {
                $orderNode = $body['data'][0];
            } elseif (!array_is_list($body['data']) && (isset($body['data']['order_id']) || isset($body['data']['id']) || isset($body['data']['order_number']))) {
                $orderNode = $body['data'];
            }
        } elseif (isset($body['created'][0]) && is_array($body['created'][0])) {
            $orderNode = $body['created'][0];
        } elseif (isset($body['orders']['allow'][0]) && is_array($body['orders']['allow'][0])) {
            $orderNode = $body['orders']['allow'][0];
        } elseif (isset($body['orders'][0]) && is_array($body['orders'][0])) {
            $orderNode = $body['orders'][0];
        } elseif (isset($body['order_id']) || isset($body['id'])) {
            $orderNode = $body;
        }

        // Si el propio elemento dentro de data[] indica error o rechazo individual
        if (is_array($orderNode)) {
            $itemStatus = strtolower((string)($orderNode['status'] ?? $orderNode['result'] ?? ''));
            $itemError  = $orderNode['error'] ?? $orderNode['errors'] ?? $orderNode['message'] ?? null;

            if (in_array($itemStatus, ['error', 'failed', 'denied', 'rejected', 'skipped', 'duplicate'], true)) {
                $msg = is_array($itemError) ? json_encode($itemError, JSON_UNESCAPED_UNICODE) : (string)($itemError ?: $itemStatus);
                if ($itemStatus === 'duplicate' || self::textoIndicaOrdenDuplicada($msg)) {
                    $result['is_duplicate'] = true;
                    $result['external_order_id'] = isset($orderNode['order_id']) ? (string)$orderNode['order_id'] : (isset($orderNode['id']) ? (string)$orderNode['id'] : null);
                }
                $result['error_message'] = $msg;
                return $result;
            }
        }

        // Si no hay orden creada y existen errores/denegados en el lote, rechazar
        if ($orderNode === null && !empty($errorBuckets)) {
            $result['error_message'] = $flatErrorText;
            return $result;
        }

        // Verificar contadores de lote (ej. total_created / created_count == 0)
        foreach (['total_created', 'created_count', 'imported'] as $cntKey) {
            if (isset($body[$cntKey]) && (int)$body[$cntKey] === 0 && $orderNode === null) {
                $msg = $body['message'] ?? ($flatErrorText ?: 'ShipEdge reportó 0 órdenes importadas.');
                if (self::textoIndicaOrdenDuplicada((string)$msg)) {
                    $result['is_duplicate'] = true;
                }
                $result['error_message'] = (string)$msg;
                return $result;
            }
        }

        if (is_array($orderNode)) {
            $extId = trim((string)(
                $orderNode['order_id']
                ?? $orderNode['id']
                ?? $orderNode['oms_order_id']
                ?? $orderNode['external_order_id']
                ?? ''
            ));
            $tracking = trim((string)(
                $orderNode['tracking_number']
                ?? $orderNode['tracking']
                ?? $orderNode['shipment']['tracking_number']
                ?? ''
            ));
            $guia = trim((string)(
                $orderNode['guide_number']
                ?? $orderNode['label_id']
                ?? $orderNode['shipment_id']
                ?? $tracking
                ?? ''
            ));
            $carrier = trim((string)(
                $orderNode['carrier_name']
                ?? $orderNode['carrier']
                ?? ''
            ));

            $result['created']            = true;
            $result['order_created']      = true;
            $result['external_order_id']  = $extId !== '' ? $extId : null;
            $result['numero_guia']        = $guia !== '' ? $guia : null;
            $result['tracking_number']    = $tracking !== '' ? $tracking : null;
            $result['carrier_name']       = $carrier !== '' ? $carrier : null;
            $result['guide_generated']    = $guia !== '';
            $result['tracking_generated'] = $tracking !== '';
            $result['external_status']    = trim((string)($orderNode['order_status'] ?? $orderNode['status'] ?? 'pending'));
            return $result;
        }

        // Si la respuesta fue 200 con status="success" / "ok" explícito aunque sin nodo detallado
        if (in_array($rootStatus, ['success', 'ok', 'created', 'imported'], true) || (!empty($body['success']) && $body['success'] === true)) {
            $result['created']       = true;
            $result['order_created'] = true;
            return $result;
        }

        $result['error_message'] = 'Respuesta de ShipEdge no confirmó la creación de la orden.';
        return $result;
    }

    /**
     * Detecta si un mensaje de error/advertencia de ShipEdge indica que la orden ya existe (repetida).
     */
    public static function textoIndicaOrdenDuplicada(string $text): bool
    {
        $t = strtolower($text);
        $patterns = [
            'already exists',
            'already imported',
            'duplicate',
            'duplicated',
            'repeated',
            'order_number already',
            'order already',
            'ya existe',
            'orden repetida',
            'duplicad',
            'not import a repeated',
        ];
        foreach ($patterns as $p) {
            if (strpos($t, $p) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Intenta conciliar una orden contra ShipEdge vía GET /orders/{order}?identify_by=...
     */
    private function intentarConciliarOrdenRemota(string $orderNumber, ?string $externalOrderId = null): ?array
    {
        try {
            $remota = $this->consultarOrden($orderNumber, 'order_number');
            if ($remota !== null && !empty($remota['exists'])) {
                return $remota;
            }
            if (!empty($externalOrderId)) {
                $remotaPorId = $this->consultarOrden($externalOrderId, 'order_id');
                if ($remotaPorId !== null && !empty($remotaPorId['exists'])) {
                    return $remotaPorId;
                }
            }
        } catch (Exception $e) {
            error_log('ShipEdgeProvider::intentarConciliarOrdenRemota aviso: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Construye el arreglo de retorno estandarizado para ForwardingService,
     * distinguiendo entre orden creada, guía generada y número de tracking.
     */
    private function construirResultadoExitoso(array $datosOrden, bool $reconciled): array
    {
        $externalOrderId = $datosOrden['external_order_id'] ?? null;
        $numeroGuia      = $datosOrden['numero_guia'] ?? null;
        $trackingNumber  = $datosOrden['tracking_number'] ?? null;

        $responseSummary = [
            'reconciled'         => $reconciled,
            'order_created'      => true,
            'guide_generated'    => !empty($numeroGuia),
            'tracking_generated' => !empty($trackingNumber),
            'external_order_id'  => $externalOrderId,
            'numero_guia'        => $numeroGuia,
            'tracking_number'    => $trackingNumber,
            'carrier_name'       => $datosOrden['carrier_name'] ?? null,
            'external_status'    => $datosOrden['external_status'] ?? 'pending',
            'shipedge_raw'       => self::sanitizeSensitiveData($datosOrden['raw'] ?? null),
        ];

        return [
            'success'            => true,
            'http_status'        => 200,
            'reconciled'         => $reconciled,
            'order_created'      => true,
            'guide_generated'    => !empty($numeroGuia),
            'tracking_generated' => !empty($trackingNumber),
            'order_number'       => $datosOrden['order_number'] ?? null,
            'external_order_id'  => $externalOrderId,
            // IMPORTANTE: numero_guia y tracking_number solo contienen valor si ShipEdge ya los generó.
            // Nunca se sobrescribe numero_traking del pedido con el ID interno del OMS si aún no hay tracking.
            'numero_guia'        => $numeroGuia,
            'tracking_number'    => $trackingNumber,
            'carrier_name'       => $datosOrden['carrier_name'] ?? null,
            'external_status'    => $datosOrden['external_status'] ?? 'pending',
            'estado_externo'     => $datosOrden['external_status'] ?? 'pending',
            'raw_response'       => json_encode(self::sanitizeSensitiveData($datosOrden['raw'] ?? null), JSON_UNESCAPED_UNICODE),
            'response'           => $responseSummary,
        ];
    }

    // =========================================================================
    // 5. PERSISTENCIA EN TABLAS LOCALES (forwarding_guias / forwarding_log)
    // =========================================================================

    /**
     * Busca si el pedido ya tiene un registro en forwarding_guias para este proveedor.
     */
    private function buscarGuiaLocal(int $idPedido): ?array
    {
        if ($idPedido <= 0 || !$this->providerId) {
            return null;
        }
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT * FROM forwarding_guias
                WHERE id_pedido = :id_pedido AND id_provider = :id_provider
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([
                ':id_pedido'   => $idPedido,
                ':id_provider' => $this->providerId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            $meta = json_decode($row['metadata'] ?? '{}', true) ?: [];
            return [
                'id'                => (int)$row['id'],
                'numero_guia_db'    => $row['numero_guia'],
                'numero_guia_real'  => $meta['numero_guia'] ?? null,
                'external_order_id' => $meta['external_order_id'] ?? null,
                'tracking_number'   => $meta['tracking_number'] ?? null,
                'carrier_name'      => $meta['carrier_name'] ?? null,
                'guide_generated'   => !empty($meta['guide_generated']),
                'status'            => $row['status'],
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Verifica si existe algún intento previo en forwarding_log para este pedido y proveedor.
     */
    private function huboIntentoPrevioEnLog(int $idPedido): bool
    {
        if ($idPedido <= 0 || !$this->providerId) {
            return false;
        }
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM forwarding_log
                WHERE id_pedido = :id_pedido AND id_provider = :id_provider
            ");
            $stmt->execute([
                ':id_pedido'   => $idPedido,
                ':id_provider' => $this->providerId,
            ]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Registra o actualiza la orden/guía en forwarding_guias y actualiza carrier/tracking en pedidos cuando existan.
     */
    private function registrarGuiaLocal(int $idPedido, string $orderNumber, array $datos): void
    {
        if ($idPedido <= 0 || !$this->providerId) {
            return;
        }
        try {
            $db = (new Conexion())->conectar();

            $externalOrderId = $datos['external_order_id'] ?? null;
            $numeroGuiaReal  = $datos['numero_guia'] ?? null;
            $trackingNumber  = $datos['tracking_number'] ?? null;
            $carrierName     = $datos['carrier_name'] ?? ($this->getEffectiveConfig()['carrier_name'] ?: null);

            // En forwarding_guias.numero_guia (NOT NULL UNIQUE por proveedor):
            // Si ya existe guía o tracking, usamos ese valor; si aún solo existe la orden en OMS,
            // usamos un identificador estable de orden "ORD-{external_order_id}" o "SE-{order_number}"
            // y marcamos guide_generated = false en metadata para distinguirlos claramente.
            $guiaKey = $numeroGuiaReal ?: ($trackingNumber ?: ($externalOrderId ? 'ORD-' . $externalOrderId : 'SE-' . $orderNumber));
            $statusGuia = !empty($trackingNumber) ? 'shipped' : (!empty($numeroGuiaReal) ? 'guide_generated' : 'order_created');

            $metadata = json_encode([
                'order_number'       => $orderNumber,
                'external_order_id'  => $externalOrderId,
                'numero_guia'        => $numeroGuiaReal,
                'tracking_number'    => $trackingNumber,
                'carrier_name'       => $carrierName,
                'external_status'    => $datos['external_status'] ?? 'pending',
                'order_created'      => true,
                'guide_generated'    => !empty($numeroGuiaReal),
                'tracking_generated' => !empty($trackingNumber),
                'updated_at'         => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Verificar si ya había fila en forwarding_guias para este pedido y proveedor
            $stmtExist = $db->prepare("
                SELECT id FROM forwarding_guias
                WHERE id_pedido = :id_pedido AND id_provider = :id_provider
                LIMIT 1
            ");
            $stmtExist->execute([
                ':id_pedido'   => $idPedido,
                ':id_provider' => $this->providerId,
            ]);
            $existingId = $stmtExist->fetchColumn();

            if ($existingId) {
                $stmtUpd = $db->prepare("
                    UPDATE forwarding_guias
                    SET numero_guia = :numero_guia,
                        status      = :status,
                        metadata    = :metadata,
                        updated_at  = NOW()
                    WHERE id = :id
                ");
                $stmtUpd->execute([
                    ':numero_guia' => $guiaKey,
                    ':status'      => $statusGuia,
                    ':metadata'    => $metadata,
                    ':id'          => (int)$existingId,
                ]);
            } else {
                $stmtIns = $db->prepare("
                    INSERT INTO forwarding_guias
                        (id_pedido, id_provider, numero_guia, status, metadata, created_at)
                    VALUES
                        (:id_pedido, :id_provider, :numero_guia, :status, :metadata, NOW())
                ");
                $stmtIns->execute([
                    ':id_pedido'   => $idPedido,
                    ':id_provider' => $this->providerId,
                    ':numero_guia' => $guiaKey,
                    ':status'      => $statusGuia,
                    ':metadata'    => $metadata,
                ]);
            }

            // Actualizar pedidos.numero_traking SOLO si ya existe un tracking_number real
            // y pedidos.courier_service si existe carrier_name o nombre del proveedor
            $setsPedido = [];
            $paramsPedido = [':id' => $idPedido];
            if (!empty($trackingNumber)) {
                $setsPedido[] = 'numero_traking = :tracking';
                $paramsPedido[':tracking'] = $trackingNumber;
            }
            if (!empty($carrierName)) {
                $setsPedido[] = 'courier_service = :courier';
                $paramsPedido[':courier'] = $carrierName;
            }
            if (!empty($setsPedido)) {
                $sqlPed = 'UPDATE pedidos SET ' . implode(', ', $setsPedido) . ' WHERE id = :id';
                $db->prepare($sqlPed)->execute($paramsPedido);
            }
        } catch (Exception $e) {
            error_log('ShipEdgeProvider::registrarGuiaLocal aviso: ' . $e->getMessage());
        }
    }
}
