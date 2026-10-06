<?php
/**
 * C807Provider
 *
 * Conector dedicado para la API de C807 Xpress (https://sites.google.com/c807.com/apidoc/xpress).
 *
 * Flujo:
 *  1. authenticate()  -> GET /admin.php/sesion/get_token con Basic Auth (usuario:password).
 *                       Extrae access_token (JWT/Bearer válido 24h) y lo almacena en caché.
 *  2. createOrder()   -> POST /guia.php/api/set_registro con Bearer Token.
 *                       Verifica previamente que no exista ya para la orden.
 *  3. getHistoria()   -> GET /guia.php/api/get_historia/{guia}
 *  4. getPdf()        -> POST /guia.php/api/get_pdf
 *  5. consultarGuiaPorOrden() -> GET /guia.php/reporte/guias?orden={orden}
 */

require_once __DIR__ . '/BaseProvider.php';

class C807Provider extends BaseProvider
{
    /** @var array Cache de tokens indexado por hash de credenciales */
    private static $tokenCache = [];

    /**
     * Clave única para la caché de autenticación.
     */
    private function getCacheKey(): string
    {
        return md5($this->baseUrl . '|' . ($this->credentials['userName'] ?? ''));
    }

    /**
     * Limpia la caché de tokens (útil para pruebas y renovación ante 401).
     */
    public static function clearAuthCache(): void
    {
        self::$tokenCache = [];
    }

    /**
     * Autenticarse contra C807 Xpress.
     * Realiza un GET a /admin.php/sesion/get_token con Basic Auth: Base64(userName:password)
     *
     * @param bool $forceRefresh
     * @return array ['token' => string, 'expires_at' => int]
     * @throws Exception
     */
    public function authenticate($forceRefresh = false)
    {
        $cacheKey = $this->getCacheKey();

        // Reutilizar token si aún tiene más de 10 minutos de vida (dura 24h)
        if (!$forceRefresh && isset(self::$tokenCache[$cacheKey])) {
            $cached = self::$tokenCache[$cacheKey];
            if (!empty($cached['expires_at']) && $cached['expires_at'] > (time() + 600)) {
                return $cached;
            }
        }

        $user = trim($this->credentials['userName'] ?? $this->credentials['user'] ?? '');
        $pass = trim($this->credentials['password'] ?? '');

        if ($user === '' || $pass === '') {
            throw new Exception("C807 Auth: Usuario y Contraseña son obligatorios para la autenticación Basic.");
        }

        $authEndpoint = $this->config['auth_endpoint'] ?? '/admin.php/sesion/get_token';
        $url = $this->baseUrl . $authEndpoint;

        $basicToken = base64_encode("{$user}:{$pass}");
        $headers = [
            "Authorization: Basic {$basicToken}",
            "Accept: application/json",
        ];

        // C807 autenticación requiere POST con Basic Auth
        $response = $this->httpRequest('POST', $url, $headers, null, 15);

        if ($response['error']) {
            throw new Exception("Error de conexión con C807 ({$this->baseUrl}): " . $response['error']);
        }

        if ($response['http_status'] === 401) {
            throw new Exception("C807 Auth (HTTP 401): Credenciales inválidas. Verifica usuario y contraseña.", 401);
        }

        if ($response['http_status'] < 200 || $response['http_status'] >= 300) {
            $msg = null;
            if (!empty($response['decoded']['errores']) && is_array($response['decoded']['errores'])) {
                $errMsgs = [];
                foreach ($response['decoded']['errores'] as $err) {
                    $errMsgs[] = ($err['mensaje'] ?? 'Error desconocido') . ' (código ' . ($err['codigo'] ?? '?') . ')';
                }
                $msg = implode(', ', $errMsgs);
            }
            if (!$msg) {
                $msg = $response['body'] ?: "HTTP {$response['http_status']}";
            }
            throw new Exception("C807 Auth falló con código {$response['http_status']}: {$msg}", $response['http_status']);
        }

        $data = $response['decoded'];
        if (!$data || !is_array($data)) {
            throw new Exception("C807 Auth: Respuesta no es un JSON válido: " . substr($response['body'], 0, 200));
        }

        // C807 retorna: {"access_token": "...", "token_type": "bearer"}
        $token = $data['access_token'] ?? $data['token'] ?? null;
        if (empty($token)) {
            throw new Exception("C807 Auth: La respuesta no contiene 'access_token'. Respuesta: " . substr($response['body'], 0, 250));
        }

        // Token expira en 24 horas (86400 segundos). Guardamos margen de 23 horas
        $expiresAt = time() + 82800;

        self::$tokenCache[$cacheKey] = [
            'token'      => $token,
            'token_type' => $data['token_type'] ?? 'bearer',
            'expires_at' => $expiresAt,
        ];

        return self::$tokenCache[$cacheKey];
    }

    /**
     * Indica si este proveedor soporta procesamiento y despacho en lote.
     * C807 permite consolidar múltiples guías bajo una única recolección.
     */
    public function supportsBatch(): bool
    {
        return true;
    }

    /**
     * Mapear un pedido individual a la estructura de un elemento de 'guias' para C807.
     *
     * @param array $pedido
     * @param array $productos
     * @return array Estructura de guía individual
     * @throws Exception Si la homologación geográfica u otros datos requeridos fallan
     */
    public function mapearGuiaIndividual(array $pedido, array $productos = []): array
    {
        require_once __DIR__ . '/../C807CatalogService.php';

        $config = $this->config;

        // 1. Resolver correo (destinatario o fallback si no está presente)
        $correo = trim($pedido['correo'] ?? '');
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $correo = trim($config['correo_default'] ?? $config['correo'] ?? 'edona@rutaexlatam.com');
        }

        // 2. Resolver departamentos y municipios C807
        $depIdInterno  = $pedido['_raw_id_departamento'] ?? $pedido['id_departamento'] ?? null;
        $munIdInterno  = $pedido['_raw_id_municipio'] ?? $pedido['id_municipio'] ?? null;
        $depNombre     = !empty($pedido['departmentName']) ? trim($pedido['departmentName']) : (!empty($pedido['departamento']) ? trim($pedido['departamento']) : '');
        $munNombre     = !empty($pedido['municipalitiesName']) ? trim($pedido['municipalitiesName']) : (!empty($pedido['municipio_nombre']) ? trim($pedido['municipio_nombre']) : (!empty($pedido['municipio']) ? trim($pedido['municipio']) : ''));

        $geo = C807CatalogService::resolverUbicacionC807(
            $depIdInterno ? (int)$depIdInterno : null,
            $munIdInterno ? (int)$munIdInterno : null,
            $depNombre,
            $munNombre
        );

        if (!$geo['success']) {
            throw new Exception("C807 Homologación Geográfica: " . $geo['message']);
        }

        $c807DepId = (int)$geo['c807_departamento_id'];
        $c807MunId = (int)$geo['c807_municipio_id'];

        // 3. Resolver peso y paquetes físicos (guias[].detalle[])
        $unidadMedida = strtoupper(trim($config['unidad_medida'] ?? $pedido['unidad_peso'] ?? 'LB'));
        if (!in_array($unidadMedida, ['LB', 'KG'])) {
            $unidadMedida = 'LB';
        }

        // Construir descripción de contenido a partir de los productos comerciales
        $nombresItems = [];
        foreach ($productos as $p) {
            $cant = (int)($p['cantidad'] ?? 1);
            $nom  = trim($p['producto_nombre'] ?? $p['nombre'] ?? 'Item');
            $nombresItems[] = "{$cant}x {$nom}";
        }
        $contenido = !empty($nombresItems) ? implode(', ', $nombresItems) : 'Paquete de mercadería';
        if (mb_strlen($contenido, 'UTF-8') > 490) {
            $contenido = mb_substr($contenido, 0, 485, 'UTF-8') . '...';
        }

        // Validar peso real (usar default si no viene especificado en el pedido)
        $peso = isset($pedido['peso']) && is_numeric($pedido['peso']) ? (float)$pedido['peso'] : 0.0;
        if ($peso <= 0) {
            $peso = (float)($config['peso_default'] ?? 1.0);
        }

        // Detalle de paquetes
        $detallePaquetes = [];
        $paquetesRaw = $pedido['paquetes_detalle'] ?? null;
        if (!empty($paquetesRaw)) {
            $parsed = is_string($paquetesRaw) ? json_decode($paquetesRaw, true) : $paquetesRaw;
            if (is_array($parsed) && !empty($parsed)) {
                foreach ($parsed as $idx => $pkg) {
                    $pkgPeso = (float)($pkg['peso'] ?? 0);
                    if ($pkgPeso <= 0) throw new Exception("El paquete #" . ($idx + 1) . " tiene un peso inválido.");
                    $pkgItem = [
                        'peso'          => $pkgPeso,
                        'contenido'     => (string)($pkg['contenido'] ?? $contenido),
                        'unidad_medida' => strtoupper($pkg['unidad_medida'] ?? $unidadMedida),
                    ];
                    if (!empty($pkg['codigo'])) {
                        $pkgItem['codigo'] = mb_substr(trim((string)$pkg['codigo']), 0, 25, 'UTF-8');
                    }
                    $detallePaquetes[] = $pkgItem;
                }
            }
        }

        if (empty($detallePaquetes)) {
            $singlePkg = [
                'peso'          => $peso,
                'contenido'     => $contenido,
                'unidad_medida' => $unidadMedida,
            ];
            // Si hay un SKU identificable en el pedido o en el producto, enviarlo en codigo (máx 25 caracteres)
            $sku = trim((string)($pedido['sku'] ?? $productos[0]['sku'] ?? ''));
            if ($sku !== '') {
                $singlePkg['codigo'] = mb_substr($sku, 0, 25, 'UTF-8');
            }
            $detallePaquetes[] = $singlePkg;
        }

        // 4. Parámetros operativos
        $montoTotal = isset($pedido['precio_total_local']) && is_numeric($pedido['precio_total_local'])
            ? (float)$pedido['precio_total_local']
            : 0.0;

        // guias[].tipo_servicio: regla dinámica -> SER / CCE
        // Si precio_total_local > 0 => CCE (Cobro contra entrega)
        // Si precio_total_local <= 0 => SER (Servicio Regular sin cobro)
        $cfgTipoServicio = strtoupper(trim($config['tipo_servicio'] ?? 'AUTO'));
        if (!empty($pedido['tipo_servicio'])) {
            $tipoServicio = strtoupper(trim($pedido['tipo_servicio']));
        } elseif ($cfgTipoServicio === 'CCE') {
            $tipoServicio = 'CCE';
        } elseif ($cfgTipoServicio === 'SER') {
            $tipoServicio = 'SER';
        } elseif ($cfgTipoServicio === 'SEC') {
            $tipoServicio = 'SEC';
        } else {
            // Dinámico según importe
            $tipoServicio = ($montoTotal > 0) ? 'CCE' : 'SER';
        }

        // guias[].monto_cce: precio_total_local + condición: tipo_servicio == CCE
        $montoCce = null;
        if ($tipoServicio === 'CCE') {
            $montoCce = $montoTotal;
            if ($montoCce <= 0) {
                throw new Exception("El tipo de servicio es CCE (Cobro contra entrega), pero el importe del pedido (precio_total_local) es 0 o menor.");
            }
        }

        // guias[].referencia: Transformación combinada Location + betweenStreets + zona
        $refPartes = array_filter([
            trim($pedido['Location'] ?? ''),
            trim($pedido['betweenStreets'] ?? ''),
            trim($pedido['zona'] ?? ''),
        ], fn($s) => $s !== '');
        $referencia = implode(' - ', $refPartes);
        if (mb_strlen($referencia, 'UTF-8') > 500) {
            $referencia = mb_substr($referencia, 0, 500, 'UTF-8');
        }

        // guias[].indicaciones: comentario
        $indicaciones = !empty($pedido['comentario']) ? mb_substr(trim($pedido['comentario']), 0, 500, 'UTF-8') : '';

        // Estructura de la Guía individual
        $guiaItem = [
            'orden'                  => (string)$pedido['numero_orden'],
            'nombre'                 => mb_substr(trim($pedido['destinatario'] ?? ''), 0, 75, 'UTF-8'),
            'direccion'              => mb_substr(trim($pedido['direccion'] ?? ''), 0, 250, 'UTF-8'),
            'telefono'               => mb_substr(trim($pedido['telefono'] ?? ''), 0, 25, 'UTF-8'),
            'correo'                 => mb_substr($correo, 0, 75, 'UTF-8'),
            'tipo_servicio'          => $tipoServicio,
            'departamento_id'        => $c807DepId,
            'municipio_id'           => $c807MunId,
            'referencia'             => $referencia,
            'indicaciones'           => $indicaciones,
            'liquidacion_documentos' => false,
            'seguro'                 => false,
            'detalle'                => $detallePaquetes,
        ];

        // guias[].monto_cce: sólo incluir si tipo_servicio == CCE
        if ($tipoServicio === 'CCE' && $montoCce !== null) {
            $guiaItem['monto_cce'] = $montoCce;
        }

        // Si hay agencia destino configurada en regla/override
        if (!empty($config['agencia_destino'])) {
            $guiaItem['agencia_destino'] = (int)$config['agencia_destino'];
        }

        return $guiaItem;
    }

    /**
     * Construir la cabecera del payload con el arreglo de guías.
     *
     * @param array $guias Arreglo de items de guías ya mapeadas
     * @return array Payload listo para set_registro
     */
    public function construirPayloadLote(array $guias): array
    {
        $config = $this->config;
        $tipoEntrega = strtoupper(trim($config['tipo_entrega'] ?? 'NRML'));
        $recolectaFecha = $this->calcularRecolectaFecha($config);

        $payload = [
            'recolecta_fecha' => $recolectaFecha,
            'tipo_entrega'    => $tipoEntrega,
            'guias'           => array_values($guias),
        ];

        if (!empty($config['recolecta_comentario'])) {
            $payload['recolecta_comentario'] = mb_substr(trim($config['recolecta_comentario']), 0, 1000, 'UTF-8');
        }

        if (!empty($config['sede'])) {
            $payload['sede'] = (int)$config['sede'];
        }

        if (!empty($config['provisional'])) {
            $payload['provisional'] = true;
        }

        return $payload;
    }

    /**
     * Mapear campos de un pedido interno a la estructura JSON requerida por C807 /set_registro.
     *
     * @param array $pedido
     * @param array $productos
     * @param array $authData
     * @return array
     * @throws Exception
     */
    public function mapearCampos(array $pedido, array $productos, array $authData)
    {
        $guiaItem = $this->mapearGuiaIndividual($pedido, $productos);
        return $this->construirPayloadLote([$guiaItem]);
    }

    /**
     * Calcular recolecta_fecha según la configuración configurada.
     */
    private function calcularRecolectaFecha(array $config): string
    {
        $politica = $config['politica_recolecta_fecha'] ?? 'siguiente_dia_habil';

        $tz = new DateTimeZone('America/Guatemala'); // o zona centroamericana
        $now = new DateTime('now', $tz);

        if ($politica === 'hoy_actual') {
            // Hoy a las 14:00 o dentro de 2 horas
            $now->modify('+2 hours');
            return $now->format('Y-m-d H:i');
        }

        if ($politica === 'personalizada' && !empty($config['recolecta_fecha_fija'])) {
            return $config['recolecta_fecha_fija'];
        }

        // Por defecto: siguiente día hábil a las 09:00
        $now->modify('+1 day');
        // Si cae domingo (0), saltar a lunes
        if ((int)$now->format('w') === 0) {
            $now->modify('+1 day');
        }
        $now->setTime(9, 0, 0);

        return $now->format('Y-m-d H:i');
    }

    /**
     * Crear una orden en C807 Xpress.
     *
     * @param array $pedido
     * @param array $productos
     * @param array $authData
     * @return array
     * @throws Exception
     */
    public function createOrder(array $pedido, array $productos, array $authData)
    {
        $numeroOrden = (string)$pedido['numero_orden'];
        $idPedido = (int)($pedido['id'] ?? 0);

        // 0. Verificación previa en BD local (anti-duplicados local)
        $guiaLocal = $this->obtenerGuiaLocal($idPedido, $numeroOrden);
        if ($guiaLocal) {
            return [
                'success'           => true,
                'external_order_id' => $guiaLocal['numero_guia'],
                'numero_guia'       => $guiaLocal['numero_guia'],
                'recolecta'         => $guiaLocal['recolecta'] ?? null,
                'seguimiento'       => $guiaLocal['seguimiento_url'] ?? null,
                'response'          => [
                    'mensaje'   => 'Guía recuperada desde base de datos local (anti-duplicado)',
                    'recolecta' => $guiaLocal['recolecta'] ?? null,
                    'guias'     => [[
                        'orden'       => $numeroOrden,
                        'guia'        => $guiaLocal['numero_guia'],
                        'seguimiento' => $guiaLocal['seguimiento_url'] ?? null,
                    ]],
                ],
                'http_status'       => 200,
            ];
        }

        // 1. Verificación previa anti-duplicados consultando a C807 si la orden ya existe
        $guiaExistente = $this->consultarGuiaPorOrden($numeroOrden, $authData['token']);
        if ($guiaExistente) {
            $this->guardarGuiasEnBD($idPedido, [
                'recolecta' => $guiaExistente['solicitud'] ?? null,
                'guias'     => [$guiaExistente],
            ]);
            return [
                'success'           => true,
                'external_order_id' => $guiaExistente['guia'],
                'numero_guia'       => $guiaExistente['guia'],
                'recolecta'         => $guiaExistente['solicitud'] ?? null,
                'seguimiento'       => $guiaExistente['seguimiento'] ?? null,
                'response'          => [
                    'mensaje'   => 'Guía recuperada pre-existente en C807',
                    'recolecta' => $guiaExistente['solicitud'] ?? null,
                    'guias'     => [$guiaExistente],
                ],
                'http_status'       => 200,
            ];
        }

        // 2. Construir payload
        $payload = $this->mapearCampos($pedido, $productos, $authData);
        $orderEndpoint = $this->config['order_endpoint'] ?? '/guia.php/api/set_registro';
        $url = $this->baseUrl . $orderEndpoint;

        $headers = [
            "Authorization: Bearer " . $authData['token'],
            "Content-Type: application/json",
            "Accept: application/json",
        ];

        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // 3. Ejecutar llamada HTTP
        $response = $this->httpRequest('POST', $url, $headers, $jsonBody, 30);

        // Si ocurre error de timeout o conexión, intentar conciliar antes de fallar
        if ($response['error']) {
            // Consultar a C807 si a pesar del timeout la guía fue creada
            $reconciled = $this->consultarGuiaPorOrden($numeroOrden, $authData['token']);
            if ($reconciled) {
                $this->guardarGuiasEnBD($idPedido, [
                    'recolecta' => $reconciled['solicitud'] ?? null,
                    'guias'     => [$reconciled],
                ]);
                return [
                    'success'           => true,
                    'external_order_id' => $reconciled['guia'],
                    'numero_guia'       => $reconciled['guia'],
                    'recolecta'         => $reconciled['solicitud'] ?? null,
                    'seguimiento'       => $reconciled['seguimiento'] ?? null,
                    'response'          => ['reconciled' => true, 'guias' => [$reconciled]],
                    'http_status'       => 200,
                ];
            }
            throw new Exception("Error de red con C807: " . $response['error']);
        }

        // Si retorna 401, re-intentar con token fresco una vez
        if ($response['http_status'] === 401) {
            self::clearAuthCache();
            $authData = $this->authenticate(true);
            $headers[0] = "Authorization: Bearer " . $authData['token'];
            $response = $this->httpRequest('POST', $url, $headers, $jsonBody, 30);
        }

        if ($response['http_status'] < 200 || $response['http_status'] >= 300) {
            $msg = is_array($response['decoded']) ? json_encode($response['decoded']) : ($response['body'] ?: "HTTP {$response['http_status']}");
            throw new Exception("C807 set_registro error (HTTP {$response['http_status']}): {$msg}", $response['http_status']);
        }

        $decoded = $response['decoded'];
        if (!$decoded || !is_array($decoded)) {
            throw new Exception("C807 set_registro: Respuesta inválida (no JSON): " . substr($response['body'], 0, 200));
        }

        // Validar que la respuesta contenga el array de guías con la guía esperada
        $guias = $decoded['guias'] ?? [];
        if (empty($guias) || !is_array($guias)) {
            throw new Exception("C807 set_registro: Respuesta HTTP {$response['http_status']} pero no incluye arreglo 'guias'. Body: " . substr($response['body'], 0, 250));
        }

        $primeraGuia = $guias[0] ?? null;
        $numGuia = $primeraGuia['guia'] ?? null;
        if (empty($numGuia)) {
            throw new Exception("C807 set_registro: Respuesta sin número de guía válido. Body: " . substr($response['body'], 0, 250));
        }

        // Guardar relación en forwarding_guias y pedidos para trazabilidad
        $this->guardarGuiasEnBD($idPedido, $decoded);

        return [
            'success'           => true,
            'external_order_id' => $numGuia,
            'numero_guia'       => $numGuia,
            'recolecta'         => $decoded['recolecta'] ?? null,
            'seguimiento'       => $primeraGuia['seguimiento'] ?? null,
            'response'          => $decoded,
            'http_status'       => $response['http_status'],
        ];
    }

    /**
     * Crear múltiples órdenes en lote en C807 Xpress bajo una única recolección.
     * Consolida todas las órdenes del CSV/Excel en una sola llamada a /guia.php/api/set_registro.
     *
     * @param array $pedidos Lista de pedidos completos con productos y datos de recolección
     * @param array $authData Datos de autenticación
     * @return array
     * @throws Exception
     */
    public function createOrdersBatch(array $pedidos, array $authData): array
    {
        if (empty($pedidos)) {
            return [
                'success'         => true,
                'recolecta'       => null,
                'guias'           => [],
                'por_orden'       => [],
                'errores_mapeo'   => [],
                'pedidos_validos' => [],
                'http_status'     => 200,
            ];
        }

        require_once __DIR__ . '/../C807CatalogService.php';

        $guiasPayload    = [];
        $erroresMapeo    = [];
        $pedidosValidos  = [];
        $guiasExistentes = [];
        $porOrden        = [];

        foreach ($pedidos as $pedido) {
            $numOrden = (string)($pedido['numero_orden'] ?? '');
            $idPed    = (int)($pedido['id'] ?? 0);

            // 1. Verificación previa en BD local (anti-duplicado local)
            $guiaLocal = $this->obtenerGuiaLocal($idPed, $numOrden);
            if ($guiaLocal) {
                $gItem = [
                    'orden'       => $numOrden,
                    'guia'        => $guiaLocal['numero_guia'],
                    'seguimiento' => $guiaLocal['seguimiento_url'] ?? null,
                    'codigo'      => $guiaLocal['codigo_paquete'] ?? null,
                ];
                $guiasExistentes[] = $gItem;
                $porOrden[$numOrden][] = $gItem;
                $pedidosValidos[$numOrden] = $pedido;
                error_log("C807Provider::createOrdersBatch: Orden {$numOrden} ya posee guía local {$guiaLocal['numero_guia']}. Omitida de set_registro.");
                continue;
            }

            // 2. Verificación remota en C807 si ya existe la guía para esta orden
            $guiaRemota = $this->consultarGuiaPorOrden($numOrden, $authData['token']);
            if ($guiaRemota) {
                $guiasExistentes[] = $guiaRemota;
                $porOrden[$numOrden][] = $guiaRemota;
                $pedidosValidos[$numOrden] = $pedido;
                // Guardar en BD para persistencia futura
                $this->guardarGuiasEnBD($idPed, [
                    'recolecta' => $guiaRemota['solicitud'] ?? null,
                    'guias'     => [$guiaRemota],
                ]);
                error_log("C807Provider::createOrdersBatch: Orden {$numOrden} recuperada pre-existente en C807 ({$guiaRemota['guia']}). Omitida de set_registro.");
                continue;
            }

            // 3. Mapear para envío a C807
            try {
                $guiaItem = $this->mapearGuiaIndividual($pedido, $pedido['productos'] ?? []);
                $guiasPayload[] = $guiaItem;
                $pedidosValidos[$numOrden] = $pedido;
            } catch (Throwable $e) {
                $erroresMapeo[$numOrden] = $e->getMessage();
                error_log("C807Provider::createOrdersBatch - Orden {$numOrden} no incluida en lote: " . $e->getMessage());
            }
        }

        // Si ninguna orden requiere llamada a set_registro porque todas ya existían
        if (empty($guiasPayload)) {
            if (!empty($guiasExistentes)) {
                return [
                    'success'         => true,
                    'recolecta'       => null,
                    'guias'           => $guiasExistentes,
                    'por_orden'       => $porOrden,
                    'errores_mapeo'   => $erroresMapeo,
                    'pedidos_validos' => $pedidosValidos,
                    'response'        => [
                        'mensaje' => 'Todas las órdenes contaban con guía en C807 o base de datos (anti-duplicado)',
                        'guias'   => $guiasExistentes,
                    ],
                    'request_payload' => json_encode(['info' => 'Guías pre-existentes recuperadas']),
                    'http_status'     => 200,
                ];
            }
            return [
                'success'         => false,
                'recolecta'       => null,
                'guias'           => [],
                'por_orden'       => [],
                'errores_mapeo'   => $erroresMapeo,
                'pedidos_validos' => [],
                'message'         => 'Ninguna orden del lote superó la validación u homologación geográfica.',
                'http_status'     => 422,
            ];
        }

        $payload = $this->construirPayloadLote($guiasPayload);
        $orderEndpoint = $this->config['order_endpoint'] ?? '/guia.php/api/set_registro';
        $url = $this->baseUrl . $orderEndpoint;

        $headers = [
            "Authorization: Bearer " . $authData['token'],
            "Content-Type: application/json",
            "Accept: application/json",
        ];

        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // En lote permitimos hasta 60 segundos de timeout
        $response = $this->httpRequest('POST', $url, $headers, $jsonBody, 60);

        if ($response['error']) {
            throw new Exception("Error de red con C807 en lote: " . $response['error']);
        }

        // Si retorna 401, re-intentar con token fresco una vez
        if ($response['http_status'] === 401) {
            self::clearAuthCache();
            $authData = $this->authenticate(true);
            $headers[0] = "Authorization: Bearer " . $authData['token'];
            $response = $this->httpRequest('POST', $url, $headers, $jsonBody, 60);
        }

        if ($response['http_status'] < 200 || $response['http_status'] >= 300) {
            $msg = null;
            if (!empty($response['decoded']['errores']) && is_array($response['decoded']['errores'])) {
                $errMsgs = [];
                foreach ($response['decoded']['errores'] as $err) {
                    $errMsgs[] = ($err['mensaje'] ?? 'Error') . ' (código ' . ($err['codigo'] ?? '?') . ')';
                }
                $msg = implode(', ', $errMsgs);
            }
            if (!$msg) {
                $msg = is_array($response['decoded']) ? json_encode($response['decoded']) : ($response['body'] ?: "HTTP {$response['http_status']}");
            }
            throw new Exception("C807 set_registro lote error (HTTP {$response['http_status']}): {$msg}", $response['http_status']);
        }

        $decoded = $response['decoded'];
        if (!$decoded || !is_array($decoded)) {
            throw new Exception("C807 set_registro lote: Respuesta inválida (no JSON): " . substr($response['body'], 0, 200));
        }

        $nuevasGuias = $decoded['guias'] ?? [];
        if (empty($nuevasGuias) || !is_array($nuevasGuias)) {
            throw new Exception("C807 set_registro lote: Respuesta HTTP {$response['http_status']} pero no incluye arreglo 'guias'. Body: " . substr($response['body'], 0, 250));
        }

        $recolecta = $decoded['recolecta'] ?? null;

        // Guardar guías en forwarding_guias y pedidos para todos los pedidos del lote
        $this->guardarGuiasLoteEnBD($decoded, $pedidosValidos);

        // Agrupar guías retornadas por número de orden
        foreach ($nuevasGuias as $g) {
            $numOrd = (string)($g['orden'] ?? '');
            if ($numOrd !== '') {
                $porOrden[$numOrd][] = $g;
            }
        }

        $todasLasGuias = array_merge($guiasExistentes, $nuevasGuias);

        return [
            'success'         => true,
            'recolecta'       => $recolecta,
            'guias'           => $todasLasGuias,
            'por_orden'       => $porOrden,
            'errores_mapeo'   => $erroresMapeo,
            'pedidos_validos' => $pedidosValidos,
            'response'        => $decoded,
            'request_payload' => $jsonBody,
            'http_status'     => $response['http_status'],
        ];
    }

    /**
     * Consultar si una guía ya existe para un número de orden en C807.
     * Endpoint: GET /guia.php/reporte/guias?orden={orden}
     *
     * @param string $numeroOrden
     * @param string $bearerToken
     * @return array|null
     */
    public function consultarGuiaPorOrden(string $numeroOrden, string $bearerToken): ?array
    {
        try {
            $url = $this->baseUrl . "/guia.php/reporte/guias?orden=" . urlencode($numeroOrden);
            $headers = [
                "Authorization: Bearer {$bearerToken}",
                "Accept: application/json",
            ];
            $res = $this->httpRequest('GET', $url, $headers, null, 10);
            if ($res['http_status'] >= 200 && $res['http_status'] < 300 && is_array($res['decoded']) && !empty($res['decoded'])) {
                // Retorna arreglo de guías encontradas
                foreach ($res['decoded'] as $g) {
                    if (isset($g['orden']) && (string)$g['orden'] === $numeroOrden && !empty($g['guia'])) {
                        return $g;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("C807Provider::consultarGuiaPorOrden error: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Obtener el historial completo de una guía para conciliación.
     * Endpoint: GET /guia.php/api/get_historia/{guia}
     *
     * @param string $numeroGuia
     * @return array Lista de eventos de historia
     * @throws Exception
     */
    public function getHistoria(string $numeroGuia): array
    {
        $authData = $this->authenticate();
        $url = $this->baseUrl . "/guia.php/api/get_historia/" . urlencode($numeroGuia);
        $headers = [
            "Authorization: Bearer " . $authData['token'],
            "Accept: application/json",
        ];

        $res = $this->httpRequest('GET', $url, $headers, null, 15);
        if ($res['http_status'] < 200 || $res['http_status'] >= 300) {
            throw new Exception("Error al obtener historia C807 de guía {$numeroGuia} (HTTP {$res['http_status']}): " . $res['body']);
        }

        return is_array($res['decoded']) ? $res['decoded'] : [];
    }

    /**
     * Obtener el PDF o ZPL en base64 de una o más guías.
     * Endpoint: POST /guia.php/api/get_pdf
     *
     * @param array $numerosGuias
     * @param string $formato 'pdf' o 'zpl'
     * @return string Base64 string
     * @throws Exception
     */
    public function getPdf(array $numerosGuias, string $formato = 'pdf'): string
    {
        $authData = $this->authenticate();
        $url = $this->baseUrl . "/guia.php/api/get_pdf";
        $headers = [
            "Authorization: Bearer " . $authData['token'],
            "Content-Type: application/json",
            "Accept: application/json",
        ];

        $body = ['guias' => array_values($numerosGuias)];
        if (strtolower($formato) === 'zpl') {
            $body['formato'] = 'zpl';
        }

        $res = $this->httpRequest('POST', $url, $headers, json_encode($body), 20);
        if ($res['http_status'] < 200 || $res['http_status'] >= 300) {
            throw new Exception("Error al obtener PDF C807 (HTTP {$res['http_status']}): " . $res['body']);
        }

        $decoded = $res['decoded'];
        $base64 = $decoded['pdf'] ?? $decoded['zpl'] ?? null;
        if (!$base64) {
            throw new Exception("Respuesta C807 get_pdf sin contenido codificado.");
        }

        return $base64;
    }

    /**
     * Buscar si una orden ya tiene guía registrada localmente en forwarding_guias o en pedidos.
     * Candado local anti-duplicados.
     *
     * @param int $idPedido
     * @param string $numeroOrden
     * @return array|null
     */
    public function obtenerGuiaLocal(int $idPedido, string $numeroOrden): ?array
    {
        try {
            require_once __DIR__ . '/../../modelo/conexion.php';
            $db = (new Conexion())->conectar();
            $idProvider = (int)($this->config['id_provider'] ?? 0);

            // 1. Buscar en forwarding_guias
            $sql = "SELECT numero_guia, recolecta, seguimiento_url, codigo_paquete 
                    FROM forwarding_guias 
                    WHERE numero_guia IS NOT NULL AND numero_guia != '' ";
            $params = [];
            if ($idProvider > 0) {
                $sql .= " AND id_provider = :id_prov ";
                $params[':id_prov'] = $idProvider;
            }
            if ($idPedido > 0 && $numeroOrden !== '') {
                $sql .= " AND (id_pedido = :id_ped OR numero_orden = :num_ord) ";
                $params[':id_ped'] = $idPedido;
                $params[':num_ord'] = $numeroOrden;
            } elseif ($idPedido > 0) {
                $sql .= " AND id_pedido = :id_ped ";
                $params[':id_ped'] = $idPedido;
            } else {
                $sql .= " AND numero_orden = :num_ord ";
                $params[':num_ord'] = $numeroOrden;
            }
            $sql .= " ORDER BY id DESC LIMIT 1";

            $st = $db->prepare($sql);
            $st->execute($params);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['numero_guia'])) {
                return $row;
            }

            // 2. Buscar en pedidos si ya tiene tracking asignado de C807 (EXLATA-)
            if ($idPedido > 0 || $numeroOrden !== '') {
                $sqlP = "SELECT numero_traking FROM pedidos WHERE numero_traking LIKE 'EXLATA-%' ";
                $pParams = [];
                if ($idPedido > 0) {
                    $sqlP .= " AND id = :id ";
                    $pParams[':id'] = $idPedido;
                } else {
                    $sqlP .= " AND numero_orden = :ord ";
                    $pParams[':ord'] = $numeroOrden;
                }
                $sqlP .= " LIMIT 1";
                $stP = $db->prepare($sqlP);
                $stP->execute($pParams);
                $trk = $stP->fetchColumn();
                if ($trk) {
                    return [
                        'numero_guia'     => $trk,
                        'recolecta'       => null,
                        'seguimiento_url' => "https://app.c807.com/guia.php/seguimiento/" . urlencode($trk),
                        'codigo_paquete'  => null,
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log("C807Provider::obtenerGuiaLocal error: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Guardar las guías retornadas por C807 en la tabla forwarding_guias para un solo pedido.
     */
    private function guardarGuiasEnBD(int $idPedido, array $c807Response): void
    {
        $firstGuia = $c807Response['guias'][0] ?? [];
        $orden = (string)($firstGuia['orden'] ?? '');
        $this->guardarGuiasLoteEnBD($c807Response, [$orden => ['id' => $idPedido]]);
    }

    /**
     * Guardar las guías retornadas por C807 en la tabla forwarding_guias para múltiples pedidos.
     * Vincula cada guía con su pedido respectivo mediante el número de orden y sincroniza pedidos.
     */
    public function guardarGuiasLoteEnBD(array $c807Response, array $pedidosPorOrden): void
    {
        try {
            require_once __DIR__ . '/../../modelo/conexion.php';
            $db = (new Conexion())->conectar();

            $idProvider = (int)($this->config['id_provider'] ?? 0);
            $recolecta  = $c807Response['recolecta'] ?? null;

            $stmt = $db->prepare("
                INSERT INTO forwarding_guias 
                    (id_pedido, id_provider, numero_orden, numero_guia, codigo_paquete, 
                     recolecta, seguimiento_url, entrega_min, entrega_max)
                VALUES 
                    (:id_pedido, :id_provider, :numero_orden, :numero_guia, :codigo_paquete,
                     :recolecta, :seguimiento_url, :entrega_min, :entrega_max)
                ON DUPLICATE KEY UPDATE 
                    seguimiento_url = VALUES(seguimiento_url),
                    entrega_min     = VALUES(entrega_min),
                    entrega_max     = VALUES(entrega_max),
                    recolecta       = VALUES(recolecta)
            ");

            $stmtUpdatePedido = $db->prepare("
                UPDATE pedidos 
                SET numero_traking = :guia, 
                    courier_service = COALESCE(NULLIF(courier_service, ''), 'C807 Xpress') 
                WHERE id = :id_pedido AND (numero_traking IS NULL OR numero_traking = '')
            ");

            // Cache auxiliar para resolver id_pedido si no viniera en el mapa
            $cacheIds = [];

            foreach ($c807Response['guias'] ?? [] as $g) {
                if (empty($g['guia'])) continue;
                $numOrden = (string)($g['orden'] ?? '');
                $idPedido = 0;

                if (isset($pedidosPorOrden[$numOrden]['id'])) {
                    $idPedido = (int)$pedidosPorOrden[$numOrden]['id'];
                } elseif (isset($cacheIds[$numOrden])) {
                    $idPedido = $cacheIds[$numOrden];
                } else {
                    $st = $db->prepare("SELECT id FROM pedidos WHERE numero_orden = :ord LIMIT 1");
                    $st->execute([':ord' => $numOrden]);
                    $idPedido = (int)$st->fetchColumn();
                    $cacheIds[$numOrden] = $idPedido;
                }

                if ($idPedido <= 0) {
                    error_log("C807Provider::guardarGuiasLoteEnBD: no se encontró id_pedido para la orden {$numOrden}");
                    continue;
                }

                $stmt->execute([
                    ':id_pedido'       => $idPedido,
                    ':id_provider'     => $idProvider,
                    ':numero_orden'    => $numOrden,
                    ':numero_guia'     => $g['guia'],
                    ':codigo_paquete'  => $g['codigo'] ?? null,
                    ':recolecta'       => $recolecta,
                    ':seguimiento_url' => $g['seguimiento'] ?? null,
                    ':entrega_min'     => !empty($g['entrega_min']) ? $g['entrega_min'] : null,
                    ':entrega_max'     => !empty($g['entrega_max']) ? $g['entrega_max'] : null,
                ]);

                try {
                    $stmtUpdatePedido->execute([
                        ':guia'      => $g['guia'],
                        ':id_pedido' => $idPedido,
                    ]);
                } catch (Throwable $exP) {}
            }
        } catch (Throwable $e) {
            error_log("C807Provider::guardarGuiasLoteEnBD error: " . $e->getMessage());
        }
    }
}
