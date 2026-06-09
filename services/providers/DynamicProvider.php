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
 *   - payload_format = 'soap'  → envuelve XML en SOAP Envelope completo
 *
 * Configuración SOAP (via default_config del proveedor en BD):
 *   - soap_action         (string)  Valor del header SOAPAction
 *   - soap_namespace      (string)  xmlns del tag raíz del body
 *   - soap_envelope_tag   (string)  Tag raíz del método SOAP (ej: 'GenerarGuia')
 *   - soap_item_tag       (string)  Tag para elementos de arrays (ej: 'Pieza', 'item')
 *   - soap_auth_in_body   (bool)    Si true, las credenciales van dentro del XML body
 *   - soap_auth_tag       (string)  Tag del nodo de autenticación (default: 'Autenticacion')
 *   - soap_auth_login_tag (string)  Sub-tag del usuario (default: 'Login')
 *   - soap_auth_pass_tag  (string)  Sub-tag del password (default: 'Password')
 */

require_once __DIR__ . '/BaseProvider.php';
require_once __DIR__ . '/../PayloadBuilderService.php';
require_once __DIR__ . '/../../modelo/forwarding.php';

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
     *
     * En modo SOAP con soap_auth_in_body = true, retorna directamente
     * las credenciales crudas para que se inyecten dentro del XML body.
     */
    public function authenticate()
    {
        $method = $this->config['auth_method'] ?? 'api_key';
        $user   = $this->credentials['userName'] ?? $this->credentials['user'] ?? '';
        $pass   = $this->credentials['password'] ?? '';
        $apiKey = $this->credentials['password'] ?? $this->credentials['apiKey'] ?? '';

        // SOAP con credenciales en body: no hay request HTTP de auth
        // Las credenciales se inyectan directamente en el envelope por soapEnvelope()
        if ($this->payloadFormat === 'soap' && !empty($this->config['soap_auth_in_body'])) {
            if (empty($user) || empty($pass)) {
                throw new Exception("DynamicProvider SOAP: Usuario y Contraseña requeridos para auth en body.");
            }
            return [
                'auth_method' => 'soap_body',
                'userName'    => $user,
                'password'    => $pass,
            ];
        }

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
        $body = $this->serializarPayload($payload, $authData);

        $response   = $this->httpRequest('POST', $endpoint, $headers, $body, 30);
        $httpStatus = $response['http_status'];

        if ($response['error']) {
            throw new Exception("DynamicProvider createOrder: error de red: " . $response['error']);
        }

        // Para SOAP, parsear la respuesta XML para extraer el ID externo
        if ($this->payloadFormat === 'soap') {
            return $this->parsearRespuestaSoap($response);
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

    /**
     * Construir los headers HTTP según el formato y método de auth.
     */
    private function buildHeaders(array $authData): array
    {
        $method = $authData['auth_method'] ?? 'api_key';

        $contentType = match($this->payloadFormat) {
            'xml', 'soap' => 'Content-Type: text/xml; charset=utf-8',
            default       => 'Content-Type: application/json',
        };

        $headers = [$contentType, 'Accept: application/json'];

        // En modo SOAP con auth en body, las credenciales van dentro del XML.
        // No se agrega Authorization header en ese caso.
        $soapAuthInBody = $this->payloadFormat === 'soap' && !empty($this->config['soap_auth_in_body']);

        if (!$soapAuthInBody) {
            switch ($method) {
                case 'api_key':
                    $headerName = $this->config['api_key_header'] ?? 'X-API-KEY';
                    $headers[]  = "{$headerName}: " . ($authData['token'] ?? '');
                    break;
                case 'basic':
                    $headers[] = "Authorization: Basic " . ($authData['token'] ?? '');
                    break;
                case 'bearer_jwt':
                    $headers[] = "Authorization: Bearer " . ($authData['token'] ?? '');
                    break;
            }
        }

        // SOAPAction header (solo en modo soap, si está configurado)
        if ($this->payloadFormat === 'soap') {
            $soapAction = $this->config['soap_action'] ?? '';
            if ($soapAction) {
                $headers[] = "SOAPAction: \"{$soapAction}\"";
            }
            // Content-Length para compatibilidad con algunos servidores SOAP
            // Se agrega en serializarPayload() ya que no tenemos el body aquí
        }

        return $headers;
    }

    /**
     * Serializar el payload según el formato configurado.
     *
     * @param array $payload  Array PHP del payload ya mapeado
     * @param array $authData Datos de autenticación (para SOAP con auth en body)
     * @return string         Body listo para enviar
     */
    private function serializarPayload(array $payload, array $authData = []): string
    {
        switch ($this->payloadFormat) {
            case 'xml':
                $rootTag = $this->config['xml_root'] ?? 'request';
                $itemTag = $this->config['soap_item_tag'] ?? 'item';
                return PayloadBuilderService::arrayToXml($payload, $rootTag, $itemTag);

            case 'soap':
                // Credenciales para inyectar en el body si soap_auth_in_body = true
                $credForBody = [];
                if (!empty($this->config['soap_auth_in_body'])) {
                    $credForBody = [
                        'userName' => $authData['userName'] ?? $this->credentials['userName'] ?? '',
                        'password' => $authData['password'] ?? $this->credentials['password'] ?? '',
                    ];
                }

                return PayloadBuilderService::soapEnvelope($payload, $this->config, $credForBody);

            default: // json
                return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * Parsear la respuesta SOAP y extraer el ID externo.
     * Busca tags comunes: GuiaID, CodigoGuia, NumeroGuia, OrderId, Id, TrackingId.
     *
     * @param array $response Respuesta de httpRequest()
     * @return array ['success', 'external_order_id', 'response', 'http_status']
     * @throws Exception si la respuesta indica error
     */
    private function parsearRespuestaSoap(array $response): array
    {
        $httpStatus = $response['http_status'];
        $body       = $response['body'];

        $externalId = null;
        $success    = false;
        $errorMsg   = 'Error desconocido en respuesta SOAP';

        // Tags candidatos para el ID externo (en orden de preferencia)
        $idTags = array_filter(array_map('trim', explode(',',
            $this->config['soap_response_id_tags'] ?? 'GuiaID,CodigoGuia,NumeroGuia,OrderId,Id,TrackingId'
        )));

        if ($httpStatus === 200 && !empty($body)) {
            try {
                $xml = new SimpleXMLElement($body);
                $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

                // Buscar error SOAP (Fault)
                $faultNode = $xml->xpath('//soap:Fault') ?: $xml->xpath('//*[local-name()="Fault"]');
                if (!empty($faultNode)) {
                    $errorMsg = (string)($faultNode[0]->faultstring ?? $faultNode[0]->Reason ?? 'SOAP Fault');
                } else {
                    // Buscar el ID externo con los tags configurados
                    foreach ($idTags as $tag) {
                        $nodes = $xml->xpath("//*[local-name()='{$tag}']");
                        if (!empty($nodes) && (string)$nodes[0] !== '') {
                            $externalId = (string)$nodes[0];
                            $success    = true;
                            break;
                        }
                    }

                    // Si no encontramos ID pero tampoco hay Fault, considerar éxito si HTTP 200
                    if (!$success) {
                        $success  = true; // HTTP 200 sin Fault = probablemente OK
                        $errorMsg = "Respuesta 200 pero no se encontró ID externo. Tags buscados: " . implode(', ', $idTags);
                    }
                }
            } catch (Exception $ex) {
                $errorMsg = "Error parseando XML SOAP de respuesta: " . $ex->getMessage();
            }
        } else {
            // HTTP != 200 o body vacío
            if (!empty($body)) {
                try {
                    $xml = new SimpleXMLElement($body);
                    $faultNode = $xml->xpath('//*[local-name()="Fault"]');
                    $errorMsg  = !empty($faultNode)
                        ? (string)($faultNode[0]->faultstring ?? 'SOAP Fault')
                        : "HTTP Status {$httpStatus}";
                } catch (Exception $ex) {
                    $errorMsg = "HTTP Status {$httpStatus}";
                }
            } else {
                $errorMsg = "HTTP Status {$httpStatus} — respuesta vacía";
            }
        }

        if (!$success) {
            throw new Exception("DynamicProvider SOAP falló: " . $errorMsg, (int)$httpStatus);
        }

        return [
            'success'           => true,
            'external_order_id' => $externalId,
            'response'          => ['raw_xml' => $body],
            'http_status'       => $httpStatus,
        ];
    }
}
