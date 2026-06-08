<?php
/**
 * DynamicProvider
 *
 * Provider genérico que ejecuta integraciones configuradas enteramente
 * desde la base de datos (forwarding_api_fields + forwarding_api_mappings).
 *
 * No requiere código nuevo por proveedor. Todo el mapeo de campos es
 * configurado visualmente desde la UI de Mapeos de Forwarding.
 *
 * Compatible con:
 *   - payload_format = 'json'  → envía JSON + Authorization header
 *   - payload_format = 'xml'   → envía XML plano
 *   - payload_format = 'soap'  → envuelve XML en SOAP Envelope
 */

require_once __DIR__ . '/BaseProvider.php';
require_once __DIR__ . '/PayloadBuilderService.php';
require_once __DIR__ . '/../modelo/forwarding.php';

class DynamicProvider extends BaseProvider
{
    /** @var array Mapeos cargados desde la BD */
    private $mapeos = [];

    /** @var string Formato de salida: json | xml | soap */
    private $payloadFormat = 'json';

    /**
     * Constructor extendido para inyectar los mapeos.
     * @param string $baseUrl
     * @param array  $credentials
     * @param array  $config  Debe incluir 'id_provider' y opcionalmente 'payload_format'
     */
    public function __construct($baseUrl, array $credentials, array $config = [])
    {
        parent::__construct($baseUrl, $credentials, $config);

        $idProvider = (int)($config['id_provider'] ?? 0);
        if ($idProvider > 0) {
            $this->mapeos = ForwardingModel::obtenerMapeosDeProveedor($idProvider);
        }

        $this->payloadFormat = $config['payload_format'] ?? 'json';
    }

    /**
     * Autenticación genérica.
     * Soporta: bearer_jwt (login/password), api_key, basic.
     */
    public function authenticate()
    {
        $method  = $this->config['auth_method'] ?? 'api_key';
        $apiKey  = $this->credentials['password'] ?? $this->credentials['apiKey'] ?? '';
        $user    = $this->credentials['userName'] ?? $this->credentials['user'] ?? '';
        $pass    = $this->credentials['password'] ?? '';

        // API Key simple (sin request HTTP)
        if ($method === 'api_key') {
            if (empty($apiKey)) {
                throw new Exception("DynamicProvider auth: API Key no configurada.");
            }
            return ['token' => $apiKey, 'auth_method' => 'api_key'];
        }

        // Basic Auth (sin request HTTP — se usa en headers)
        if ($method === 'basic') {
            if (empty($user)) {
                throw new Exception("DynamicProvider auth: Usuario requerido para Basic Auth.");
            }
            return ['token' => base64_encode("{$user}:{$pass}"), 'auth_method' => 'basic'];
        }

        // Bearer JWT: hace POST al auth_endpoint para obtener token
        if ($method === 'bearer_jwt') {
            $authUrl = $this->baseUrl . ($this->config['auth_endpoint'] ?? '/auth/login');
            $body    = json_encode(['username' => $user, 'password' => $pass]);
            $resp    = $this->httpRequest('POST', $authUrl, [
                'Content-Type: application/json',
                'Accept: application/json',
            ], $body, 15);

            if ($resp['error']) {
                throw new Exception("DynamicProvider auth error: " . $resp['error']);
            }
            $data  = $resp['decoded'] ?? [];
            $token = $data['token'] ?? $data['access_token'] ?? $data['accessToken'] ?? null;
            if (!$token) {
                throw new Exception("DynamicProvider auth: No se pudo obtener token. Respuesta: " . substr($resp['body'], 0, 300));
            }
            return ['token' => $token, 'auth_method' => 'bearer_jwt'];
        }

        throw new Exception("DynamicProvider: auth_method '{$method}' no soportado.");
    }

    /**
     * Construir el payload usando el PayloadBuilderService.
     */
    public function mapearCampos(array $pedido, array $productos, array $authData)
    {
        if (empty($this->mapeos)) {
            throw new Exception("DynamicProvider: No hay mapeos configurados para este proveedor. Configure los campos en Forwarding → Mapeos.");
        }

        return PayloadBuilderService::build($pedido, $this->mapeos);
    }

    /**
     * Crear la orden en el proveedor externo.
     */
    public function createOrder(array $pedido, array $productos, array $authData)
    {
        $endpoint = $this->baseUrl . ($this->config['order_endpoint'] ?? '/orders');
        $payload  = $this->mapearCampos($pedido, $productos, $authData);

        // Construir headers según formato y método de auth
        $headers = $this->buildHeaders($authData);

        // Serializar payload según formato
        $body = $this->serializarPayload($payload);

        $response   = $this->httpRequest('POST', $endpoint, $headers, $body, 30);
        $httpStatus = $response['http_status'];

        if ($response['error']) {
            throw new Exception("DynamicProvider createOrder: error de red: " . $response['error']);
        }

        $success = in_array($httpStatus, [200, 201]);
        $data    = $response['decoded'] ?? [];

        // Intentar extraer el ID externo de la respuesta con claves comunes
        $externalId = null;
        foreach (['id', 'order_id', 'orderId', 'external_order_id', 'numero', 'trackingNumber'] as $k) {
            if (!empty($data[$k])) {
                $externalId = (string)$data[$k];
                break;
            }
        }

        if (!$success) {
            $errorMsg = $data['message'] ?? $data['error'] ?? $data['detail'] ?? ("HTTP {$httpStatus}");
            if (is_array($errorMsg)) $errorMsg = implode('; ', $errorMsg);
            throw new Exception("DynamicProvider createOrder falló: " . $errorMsg, (int)$httpStatus);
        }

        return [
            'success'           => true,
            'external_order_id' => $externalId,
            'response'          => $data,
            'http_status'       => $httpStatus,
        ];
    }

    // -------------------------------------------------------------------------
    // Privados
    // -------------------------------------------------------------------------

    private function buildHeaders(array $authData): array
    {
        $method = $authData['auth_method'] ?? 'api_key';
        $token  = $authData['token'] ?? '';

        $contentType = match($this->payloadFormat) {
            'xml', 'soap' => 'Content-Type: text/xml; charset=utf-8',
            default       => 'Content-Type: application/json',
        };

        $headers = [$contentType, 'Accept: application/json'];

        switch ($method) {
            case 'api_key':
                // Soporte para header personalizado via config (ej: X-API-KEY, Authorization, etc.)
                $headerName = $this->config['api_key_header'] ?? 'X-API-KEY';
                $headers[]  = "{$headerName}: {$token}";
                break;
            case 'basic':
                $headers[] = "Authorization: Basic {$token}";
                break;
            case 'bearer_jwt':
                $headers[] = "Authorization: Bearer {$token}";
                break;
        }

        if ($this->payloadFormat === 'soap') {
            $soapAction = $this->config['soap_action'] ?? '';
            if ($soapAction) $headers[] = "SOAPAction: \"{$soapAction}\"";
        }

        return $headers;
    }

    private function serializarPayload(array $payload): string
    {
        switch ($this->payloadFormat) {
            case 'xml':
                return PayloadBuilderService::arrayToXml($payload, $this->config['xml_root'] ?? 'request');

            case 'soap':
                $innerXml   = PayloadBuilderService::arrayToXml($payload, $this->config['soap_body_tag'] ?? 'Body');
                $namespace  = $this->config['soap_namespace'] ?? 'http://schemas.xmlsoap.org/soap/envelope/';
                return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
                    . "<soap:Envelope xmlns:soap=\"{$namespace}\">\n"
                    . "  <soap:Body>\n"
                    . "    " . $innerXml . "\n"
                    . "  </soap:Body>\n"
                    . "</soap:Envelope>";

            default: // json
                return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
