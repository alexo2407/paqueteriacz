<?php
/**
 * HLExpressProvider
 *
 * Conector específico para la API de HL Express Panama.
 */

require_once __DIR__ . '/BaseProvider.php';

class HLExpressProvider extends BaseProvider
{
    /**
     * Autenticación con HL Express (Passthrough).
     * Retorna la API Key configurada.
     */
    public function authenticate()
    {
        // La API Key se almacena en el campo 'password' de credentials
        $apiKey = $this->credentials['password'] ?? $this->credentials['apiKey'] ?? '';
        
        if (empty($apiKey)) {
            throw new Exception("HL Express auth: API Key no configurada.");
        }
        return ['token' => $apiKey];
    }

    /**
     * Crear orden de envío en HL Express.
     */
    public function createOrder(array $pedido, array $productos, array $authData)
    {
        $url = $this->baseUrl . ($this->config['order_endpoint'] ?? '/shipments');
        $payload = $this->mapearCampos($pedido, $productos, $authData);
        $body    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->httpRequest('POST', $url, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-KEY: ' . $authData['token'],
        ], $body, 30);

        if ($response['error']) {
            throw new Exception("Error de conexión con HL Express (createOrder): " . $response['error']);
        }

        $data = $response['decoded'] ?? [];
        $httpStatus = $response['http_status'];

        // Se considera exitoso si retorna 200 o 201
        $success = ($httpStatus === 200 || $httpStatus === 201);

        $result = [
            'success'           => $success,
            'external_order_id' => null,
            'response'          => $data,
            'http_status'       => $httpStatus,
        ];

        // Extraer ID de la respuesta
        if (isset($data['id'])) {
            $result['external_order_id'] = (string)$data['id'];
        } elseif (isset($data['external_order_id'])) {
            $result['external_order_id'] = (string)$data['external_order_id'];
        }

        if (!$result['success']) {
            $errorMsg = $data['message'] ?? $data['error'] ?? 'Error desconocido';
            if (is_array($errorMsg)) $errorMsg = implode('; ', $errorMsg);
            throw new Exception("HL Express createOrder falló: " . $errorMsg, (int)$httpStatus);
        }

        return $result;
    }

    /**
     * Mapear campos internos al JSON esperado por HL Express Panama.
     */
    public function mapearCampos(array $pedido, array $productos, array $authData)
    {
        // 1. Resolver el valor declarado
        $declaredValue = (float)($pedido['precio_total_local'] ?? 0);

        // 2. Resolver método de pago y si es COD (32: Pagado, 34: Efectivo)
        // Se puede sobreescribir desde la configuración de la regla o proveedor
        $paymentMethodId = (int)($this->config['payment_method_id'] ?? 34); 
        $isCod = ($paymentMethodId === 34);

        // 3. Destinatario y dirección
        $destNombre = $pedido['destinatario'] ?? '';
        $destDir    = $pedido['direccion'] ?? '';
        $destTel    = $pedido['telefono'] ?? '';

        // Coordenadas
        $lat = (float)($pedido['lat'] ?? 9.101254);
        $lng = (float)($pedido['lng'] ?? -79.397980);

        // Si existen coordenadas en formato POINT (deserializar si es necesario)
        if (!empty($pedido['coordenadas'])) {
            // point format is usually binary or string like "POINT(x y)"
            if (is_string($pedido['coordenadas']) && preg_match('/POINT\(([^ ]+) ([^)]+)\)/i', $pedido['coordenadas'], $mCoord)) {
                $lat = (float)$mCoord[1];
                $lng = (float)$mCoord[2];
            }
        }

        $destination = [
            'address'        => $destDir,
            'address_line'   => $pedido['comentario'] ?? '',
            'city_dane_code' => !empty($pedido['postalCode']) ? $pedido['postalCode'] : ($pedido['codigo_postal'] ?? '100075918'),
            'full_name'      => $destNombre,
            'phone_number'   => $destTel,
            'lat'            => $lat,
            'lng'            => $lng,
        ];

        // 4. Mapear contenidos (productos)
        // Enviamos el formato estructurado con nombre y cantidad
        $contains = [];
        foreach ($productos as $p) {
            $cantidad = max(0, (int)($p['cantidad'] ?? 0) - (int)($p['cantidad_devuelta'] ?? 0));
            if ($cantidad <= 0) continue;

            $nombreProd = $p['producto_nombre'] ?? 'Producto';
            $contains[] = [
                'name'     => $nombreProd,
                'quantity' => $cantidad
            ];
        }

        // Si no hay productos (fallback de seguridad)
        if (empty($contains)) {
            $contains[] = [
                'name'     => 'Envío',
                'quantity' => 1
            ];
        }

        return [
            'declared_value'             => $declaredValue,
            'is_cod'                     => $isCod,
            'number_of_packages'         => 1,
            'shipment_destination'       => $destination,
            'shipment_payment_method_id' => $paymentMethodId,
            'total'                      => $declaredValue,
            'total_cod'                  => $isCod ? $declaredValue : 0,
            'contains'                   => $contains,
            'order_number'               => (string)$pedido['numero_orden'],
        ];
    }

    /**
     * Consultar novedades/incidentes para una guía específica (por tracking number).
     * Para uso en la vista de detalle de pedido.
     */
    public function getIncidents($trackingNumber)
    {
        return $this->getIncidentsFiltered(['tracking_number' => $trackingNumber, 'limit' => 50])['data'] ?? [];
    }

    /**
     * Consultar novedades con filtros avanzados y paginación.
     * Retorna la respuesta paginada completa: data, total, current_page, last_page, per_page.
     *
     * Filtros disponibles:
     *  - limit         int     Resultados por página
     *  - page          int     Número de página
     *  - start_date    string  Fecha inicio (YYYY-MM-DD HH:mm:ss)
     *  - end_date      string  Fecha fin
     *  - status_id     int     5 = Novedad, 17 = Novedad (En bodega)
     *  - tracking_number string  Número de seguimiento parcial
     *  - order_number  string  Número de orden
     *  - is_solved     string  Yes_Applied | Yes | No
     */
    public function getIncidentsFiltered(array $filters = [])
    {
        $authData = $this->authenticate();

        $params = array_filter($filters, fn($v) => $v !== null && $v !== '');
        if (!isset($params['limit'])) {
            $params['limit'] = 20;
        }

        $url = $this->baseUrl . '/shipments/incidents/filtered?' . http_build_query($params);

        $response = $this->httpRequest('GET', $url, [
            'Accept: application/json',
            'X-API-KEY: ' . $authData['token'],
        ], null, 30);

        if ($response['error']) {
            throw new Exception("Error de conexión con HL Express (getIncidentsFiltered): " . $response['error']);
        }

        $raw = $response['decoded'] ?? [];
        if ($response['http_status'] !== 200) {
            $errorMsg = is_array($raw) ? ($raw['message'] ?? $raw['error'] ?? 'Error desconocido') : 'Error desconocido';
            throw new Exception("HL Express getIncidentsFiltered falló: " . $errorMsg, (int)$response['http_status']);
        }

        // La API de HL Express devuelve un array plano en lugar de un objeto paginado.
        // Normalizamos a la estructura paginada que espera el controlador/dashboard.
        if (is_array($raw) && isset($raw[0])) {
            // Array plano — cada elemento ES el envío con novedad
            $items = $raw;
        } elseif (is_array($raw) && isset($raw['data'])) {
            // Si algún día cambia a objeto paginado real
            $items = $raw['data'];
        } else {
            $items = [];
        }

        // Normalizar cada item al formato que espera el JS del dashboard:
        // El JS accede a inc.shipment.order_number, inc.shipment.tracking_number,
        // inc.shipment.shipment_destination, inc.shipment.id,
        // inc.incident_type.name, inc.created_at, inc.is_solved
        $isSolvedFilter = $params['is_solved'] ?? 'No';
        $normalized = array_map(function (array $item) use ($isSolvedFilter) {
            return [
                // Campos de la novedad
                'id'         => $item['id']         ?? null,
                'created_at' => $item['created_at']  ?? null,
                'is_solved'  => $isSolvedFilter,
                'incident_type' => [
                    'name' => $this->resolverNombreNovedad((int)($item['shipment_status_id'] ?? 0)),
                ],
                // Sub-objeto shipment con los campos que renderiza el JS
                'shipment' => [
                    'id'                   => $item['id']              ?? null,
                    'order_number'         => $item['order_number']    ?? '',
                    'tracking_number'      => $item['tracking_number'] ?? '',
                    'shipment_destination' => $item['shipment_destination'] ?? [],
                ],
            ];
        }, $items);

        $total   = count($normalized);
        $perPage = max(1, (int)($params['limit'] ?? 20));

        return [
            'data'         => $normalized,
            'total'        => $total,
            'current_page' => 1,
            'last_page'    => max(1, (int)ceil($total / $perPage)),
            'per_page'     => $perPage,
        ];
    }

    /**
     * Resuelve el nombre legible de una novedad a partir del shipment_status_id de HL Express.
     */
    private function resolverNombreNovedad(int $statusId): string
    {
        $map = [
            1  => 'En bodega',
            2  => 'En ruta',
            3  => 'Entregado',
            4  => 'No entregado',
            5  => 'Novedad',
            6  => 'Cancelado',
            7  => 'Devuelto',
            8  => 'En tránsito',
            9  => 'Recolectado',
            10 => 'Pendiente recolección',
            17 => 'Novedad – En bodega',
        ];
        return $map[$statusId] ?? "Estado #{$statusId}";
    }

    /**
     * Resolver una novedad/incidente de entrega.
     *
     * Payload esperado por HL Express (snake_case):
     *  guide_number                    - Guía asignada por HL Express (ej. WCO2801, V4000021620)
     *  instructions                    - Instrucciones de resolución (antes solve_description)
     *  customer_destination_full_name  - Nombre del destinatario
     *  customer_destination_phone_number
     *  customer_destination_address
     *  customer_destination_address_line (opcional)
     *  customer_destination_city_code
     *  is_return                       - true = devolver al remitente, false = reintentar entrega
     */
    public function solveReturn(array $payload)
    {
        $authData = $this->authenticate();
        $url = $this->baseUrl . '/shipments/solve-return';

        // Mapear campos internos → nombres exactos que espera la API de HL Express
        $body = json_encode([
            'guide_number'                       => $payload['guide_number']                       ?? $payload['tracking_number']        ?? '',
            'instructions'                       => $payload['instructions']                       ?? $payload['solve_description']       ?? '',
            'customer_destination_full_name'     => $payload['customer_destination_full_name']     ?? $payload['contact_name']            ?? '',
            'customer_destination_phone_number'  => $payload['customer_destination_phone_number']  ?? $payload['contact_phone']           ?? '',
            'customer_destination_address'       => $payload['customer_destination_address']       ?? $payload['contact_address']         ?? '',
            'customer_destination_address_line'  => $payload['customer_destination_address_line']  ?? '',
            'customer_destination_city_code'     => $payload['customer_destination_city_code']     ?? '',
            'is_return'                          => (bool)($payload['is_return']                   ?? false),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->httpRequest('POST', $url, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-KEY: ' . $authData['token'],
        ], $body, 30);

        if ($response['error']) {
            throw new Exception("Error de conexión con HL Express (solveReturn): " . $response['error']);
        }

        $data = $response['decoded'] ?? [];
        if ($response['http_status'] !== 200 && $response['http_status'] !== 201) {
            $errorMsg = $data['message'] ?? $data['error'] ?? 'Error desconocido';
            if (is_array($errorMsg)) $errorMsg = implode('; ', $errorMsg);
            // Mostrar errores de validación detallados si existen
            if (!empty($data['errors']) && is_array($data['errors'])) {
                $details = array_map(fn($e) => ($e['Key'] ?? '') . ': ' . ($e['Message'] ?? ''), $data['errors']);
                $errorMsg = implode(' | ', $details);
            }
            throw new Exception("HL Express solveReturn falló: " . $errorMsg, (int)$response['http_status']);
        }

        return $data;
    }
}

