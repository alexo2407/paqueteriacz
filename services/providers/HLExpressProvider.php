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
            'address_line'   => $destDir,
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
}
