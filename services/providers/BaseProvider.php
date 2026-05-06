<?php
/**
 * BaseProvider
 *
 * Clase abstracta base que define la interfaz para todos los proveedores
 * externos de forwarding. Cada proveedor (LogisPro, etc.) debe extender
 * esta clase e implementar los métodos abstractos.
 */

abstract class BaseProvider
{
    /** @var string URL base de la API del proveedor */
    protected $baseUrl;

    /** @var array Credenciales de autenticación */
    protected $credentials;

    /** @var array Configuración adicional del proveedor */
    protected $config;

    /** @var array|null Última respuesta HTTP recibida (body + status), siempre disponible incluso tras excepción */
    protected $lastResponse = null;

    /**
     * Constructor.
     * @param string $baseUrl URL base
     * @param array $credentials Credenciales (userName, password, apiKey, etc.)
     * @param array $config Configuración adicional
     */
    public function __construct($baseUrl, array $credentials, array $config = [])
    {
        $this->baseUrl     = rtrim($baseUrl, '/');
        $this->credentials = $credentials;
        $this->config      = $config;
    }

    /**
     * Autenticarse con el proveedor externo.
     * Debe retornar un array con al menos 'token' y opcionalmente otros datos
     * como 'customersId', etc.
     *
     * @return array ['token' => string, ...datos extra]
     * @throws Exception si falla la autenticación
     */
    abstract public function authenticate();

    /**
     * Crear una orden en el proveedor externo.
     *
     * @param array $pedido Datos del pedido interno (de la tabla pedidos)
     * @param array $productos Productos del pedido (de pedidos_productos + productos)
     * @param array $authData Datos de autenticación (token, customersId, etc.)
     * @return array ['success' => bool, 'external_order_id' => string|null, 'response' => array]
     * @throws Exception si falla la creación
     */
    abstract public function createOrder(array $pedido, array $productos, array $authData);

    /**
     * Mapear campos del pedido interno al formato del proveedor externo.
     *
     * @param array $pedido Datos del pedido
     * @param array $productos Productos del pedido
     * @param array $authData Datos de auth (customersId, etc.)
     * @return array Payload listo para enviar al proveedor
     */
    abstract public function mapearCampos(array $pedido, array $productos, array $authData);

    /**
     * Realizar una petición HTTP genérica via cURL.
     *
     * @param string $method GET, POST, PUT, DELETE
     * @param string $url URL completa
     * @param array $headers Headers HTTP
     * @param mixed $body Body de la petición (string JSON o null)
     * @param int $timeout Timeout en segundos
     * @return array ['http_status' => int, 'body' => string, 'decoded' => array|null, 'error' => string|null]
     */
    protected function httpRequest($method, $url, array $headers = [], $body = null, $timeout = 30)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        $decoded = null;
        if ($response !== false) {
            $decoded = json_decode($response, true);
        }

        $result = [
            'http_status' => (int) $httpStatus,
            'body'        => $response ?: '',
            'decoded'     => $decoded,
            'error'       => $curlError ?: null,
        ];

        // Guardar siempre el último response para poder recuperarlo tras una excepción
        $this->lastResponse = $result;

        return $result;
    }

    /**
     * Retorna la última respuesta HTTP recibida por este provider.
     * Útil para loguear el body de error incluso cuando createOrder lanzó una excepción.
     *
     * @return array|null
     */
    public function getLastResponse(): ?array
    {
        return $this->lastResponse;
    }
}
