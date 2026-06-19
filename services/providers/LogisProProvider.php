<?php
/**
 * LogisProProvider
 *
 * Conector específico para la API de LogisPro (https://apigateway.logispro.app).
 *
 * Flujo:
 *  1. authenticate() → POST /api/AccountApi → obtiene JwtToken + CustomersId
 *  2. createOrder()  → POST /api/Orders/OrderAndOrderDetail → crea la orden
 *
 * El mapeo de campos envía solo los datos necesarios; LogisPro auto-rellena
 * municipio, departamento, etc. a partir del código postal.
 */

require_once __DIR__ . '/BaseProvider.php';

class LogisProProvider extends BaseProvider
{
    /** @var array|null Cache de autenticación para esta ejecución */
    private static $authCache = null;

    /**
     * Autenticarse con LogisPro.
     *
     * @return array ['token' => string, 'customersId' => int]
     * @throws Exception si falla
     */
    public function authenticate()
    {
        // Reutilizar cache si existe (misma ejecución PHP)
        if (self::$authCache !== null) {
            return self::$authCache;
        }

        $url = $this->baseUrl . $this->config['auth_endpoint'] ?? '/api/AccountApi';

        $body = json_encode([
            'userName' => $this->credentials['userName'] ?? '',
            'password' => $this->credentials['password'] ?? '',
        ]);

        $response = $this->httpRequest('POST', $url, [
            'Content-Type: application/json',
            'Accept: application/json',
        ], $body, 15);

        // Validar respuesta
        if ($response['error']) {
            throw new Exception("Error de conexión con LogisPro: " . $response['error']);
        }

        if ($response['http_status'] !== 200) {
            $msg = $response['decoded']['Messages'] ?? 'HTTP ' . $response['http_status'];
            throw new Exception("LogisPro auth falló: " . $msg);
        }

        $data = $response['decoded'];
        if (!isset($data['Data']['JwtToken'])) {
            throw new Exception("LogisPro auth: respuesta sin JwtToken");
        }

        $hasError = $data['HasError'] ?? true;
        if ($hasError) {
            throw new Exception("LogisPro auth error: " . ($data['Messages'] ?? 'Error desconocido'));
        }

        self::$authCache = [
            'token'       => $data['Data']['JwtToken'],
            'customersId' => (int)($data['Data']['CustomersId'] ?? 0),
        ];

        return self::$authCache;
    }

    /**
     * Crear una orden en LogisPro.
     *
     * @param array $pedido Datos del pedido interno
     * @param array $productos Productos del pedido
     * @param array $authData ['token' => ..., 'customersId' => ...]
     * @return array ['success' => bool, 'external_order_id' => string|null,
     *                'response' => array, 'http_status' => int]
     * @throws Exception
     */
    public function createOrder(array $pedido, array $productos, array $authData)
    {
        $url = $this->baseUrl . ($this->config['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail');

        $payload = $this->mapearCampos($pedido, $productos, $authData);
        $body    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->httpRequest('POST', $url, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $authData['token'],
        ], $body, 30);

        if ($response['error']) {
            throw new Exception("Error de conexión con LogisPro (createOrder): " . $response['error']);
        }

        $data = $response['decoded'] ?? [];
        // Si no viene HasError, asumimos error si el status no es 200
        $hasError = $data['HasError'] ?? ($response['http_status'] !== 200);

        $result = [
            'success'           => !$hasError && $response['http_status'] === 200,
            'external_order_id' => null,
            'response'          => $data,
            'http_status'       => $response['http_status'],
        ];

        // Intentar extraer el ID externo de la respuesta
        if (isset($data['Data']['OrderId'])) {
            $result['external_order_id'] = (string)$data['Data']['OrderId'];
        } elseif (isset($data['Data']['Id'])) {
            $result['external_order_id'] = (string)$data['Data']['Id'];
        }

        if (!$result['success']) {
            $errorMsg = $data['Messages'] ?? $data['Message'] ?? 'Error desconocido';
            if (is_array($errorMsg)) $errorMsg = implode('; ', $errorMsg);
            // Pasar el HTTP status como código de excepción para que ForwardingService lo capture
            throw new Exception("LogisPro createOrder falló: " . $errorMsg, (int)$response['http_status']);
        }

        return $result;
    }

    /**
     * Mapear campos del pedido interno al formato LogisPro.
     *
     * Campos enviados en 'order' (13 campos):
     *   customersId, orderNumber, clientName, municipalitiesName,
     *   postalCode, departmentName, address, Location, betweenStreets,
     *   phone, notes, totalPrice, dateToReceive.
     *
     * Campos enviados en 'orderDetail[]':
     *   productName, quantity, price (siempre 0 según indicación del proveedor).
     *
     * @param array $pedido
     * @param array $productos
     * @param array $authData
     * @return array Payload listo para enviar
     */
    public function mapearCampos(array $pedido, array $productos, array $authData)
    {
        // Resolver postalCode (debe ser un entero para la API de LogisPro)
        $postalCodeVal = $pedido['postalCode'] ?? $pedido['codigo_postal'] ?? 0;
        $postalCode = (is_numeric($postalCodeVal) && (int)$postalCodeVal > 0) ? (int)$postalCodeVal : 0;

        // Fecha de entrega: si no existe, usar fecha actual + 3 días
        $fechaEntrega = $pedido['fecha_entrega'] ?? null;
        if (!empty($fechaEntrega)) {
            $dt = new DateTime($fechaEntrega);
            $dateToReceive = $dt->format('Y-m-d\TH:i:s.000\Z');
        } else {
            $dt = new DateTime();
            $dt->modify('+3 days');
            $dateToReceive = $dt->format('Y-m-d\TH:i:s.000\Z');
        }

        // ── Resolución de campos geográficos de texto ─────────────────────────
        // Cuando el cliente sube un Excel con texto libre (Municipio, Barrio, Depto.),
        // el sistema guarda ese texto en id_municipio / id_barrio / id_departamento.
        // Aquí los recuperamos si son texto no-numérico (_raw_id_* viene del query).

        $rawMunicipio = $pedido['_raw_id_municipio'] ?? null;
        $municipalitiesName =
            !empty($pedido['municipalitiesName'])        ? $pedido['municipalitiesName']
            : (!empty($pedido['municipio'])              ? $pedido['municipio']
            : (($rawMunicipio && !is_numeric($rawMunicipio)) ? $rawMunicipio : ''));

        $rawDepto = $pedido['_raw_id_departamento'] ?? null;
        $departmentName =
            !empty($pedido['departmentName'])            ? $pedido['departmentName']
            : (!empty($pedido['departamento'])           ? $pedido['departamento']
            : (($rawDepto && !is_numeric($rawDepto))    ? $rawDepto : ''));

        $rawBarrio = $pedido['_raw_id_barrio'] ?? null;
        $location =
            !empty($pedido['Location'])                  ? $pedido['Location']
            : (!empty($pedido['barrio'])                 ? $pedido['barrio']
            : (($rawBarrio && !is_numeric($rawBarrio))  ? $rawBarrio : ''));
        // ─────────────────────────────────────────────────────────────────────

        // Construir order con todos los campos requeridos por LogisPro.
        $order = [
            'customersId'        => $authData['customersId'],
            'orderNumber'        => (string)$pedido['numero_orden'],
            'clientName'         => $pedido['destinatario'] ?? '',
            'municipalitiesName' => $municipalitiesName,
            'postalCode'         => $postalCode,
            'departmentName'     => $departmentName,
            'address'            => $pedido['direccion'] ?? '',
            'Location'           => $location,
            'betweenStreets'     => $pedido['betweenStreets'] ?? '',
            'phone'              => $pedido['telefono'] ?? '',
            'notes'              => $pedido['comentario'] ?? '',
            'totalPrice'         => (float)($pedido['precio_total_local'] ?? 0),
            'dateToReceive'      => $dateToReceive,
        ];

        // Construir orderDetail — price siempre en 0 según indicación del proveedor
        $orderDetail = [];
        foreach ($productos as $p) {
            $cantidad = max(0, (int)($p['cantidad'] ?? 0) - (int)($p['cantidad_devuelta'] ?? 0));
            if ($cantidad <= 0) continue;

            $orderDetail[] = [
                'productName' => $p['sku'] ?? $p['producto_nombre'] ?? 'Producto',
                'quantity'    => $cantidad,
                'price'       => 0,
            ];
        }

        return [
            'order'       => $order,
            'orderDetail' => $orderDetail,
        ];
    }

    /**
     * Limpiar cache de autenticación (útil para tests o cuando cambian credenciales).
     */
    public static function clearAuthCache()
    {
        self::$authCache = null;
    }
}
