<?php
/**
 * AJAX endpoint para gestionar proveedores de forwarding.
 *
 * Acciones soportadas (POST):
 *   crear, actualizar, toggle, test
 *
 * GET: listar todos los proveedores
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../modelo/forwarding.php';

start_secure_session();
header('Content-Type: application/json');

// Verificar autenticación y rol admin
if (empty($_SESSION['registrado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
$isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: Listar proveedores ────────────────────────────────────
if ($method === 'GET') {
    $proveedores = ForwardingModel::obtenerProveedores();
    // Ocultar credenciales sensibles en la respuesta
    foreach ($proveedores as &$p) {
        $creds = json_decode($p['credentials'] ?? '{}', true) ?: [];
        if (isset($creds['password']) && $creds['password'] !== '') {
            $creds['password'] = str_repeat('*', min(strlen((string)$creds['password']), 8));
        }
        if (isset($creds['api_key']) && $creds['api_key'] !== '') {
            $creds['api_key'] = str_repeat('*', min(strlen((string)$creds['api_key']), 8));
        }
        if (isset($creds['account_id']) && $creds['account_id'] !== '') {
            $accStr = (string)$creds['account_id'];
            $creds['account_id_masked'] = strlen($accStr) > 2
                ? substr($accStr, 0, 2) . str_repeat('*', max(2, strlen($accStr) - 2))
                : '****';
            $creds['account_id'] = '';
        }
        if (isset($creds['userName']) && ($p['slug'] ?? '') === 'shipedge' && $creds['userName'] !== '') {
            $creds['userName'] = '';
        }
        if (isset($creds['webhook_secret']) && $creds['webhook_secret'] !== '') {
            $creds['has_webhook_secret'] = true;
            if (($p['slug'] ?? '') === 'shipedge') {
                $creds['webhook_secret'] = '********';
            }
        }
        $p['credentials_masked'] = $creds;
        unset($p['credentials']); // No enviar raw
    }
    unset($p);
    echo json_encode(['success' => true, 'data' => $proveedores]);
    exit;
}

// ─── POST: Acciones ─────────────────────────────────────────────
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';

    switch ($action) {

        case 'crear':
            $slugNorm = strtolower(preg_replace('/[^a-z0-9_-]/', '', $input['slug'] ?? ''));
            $shipedgeCfg = is_array($input['shipedge_config'] ?? null) ? $input['shipedge_config'] : [];

            if ($slugNorm === 'shipedge') {
                require_once __DIR__ . '/../services/providers/ShipEdgeProvider.php';
                try {
                    $input['base_url'] = ShipEdgeProvider::normalizeBaseUrl($input['base_url'] ?? '');
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                $accId  = trim((string)($shipedgeCfg['account_id'] ?? $input['account_id'] ?? $input['userName'] ?? ''));
                $apiKey = trim((string)($shipedgeCfg['api_key'] ?? $input['api_key'] ?? $input['password'] ?? ''));
                if (empty($input['nombre']) || $accId === '' || $apiKey === '') {
                    echo json_encode(['success' => false, 'message' => 'Para ShipEdge se requiere Nombre, AccountID y API Key.']);
                    exit;
                }
                $input['userName']    = $accId;
                $input['password']    = $apiKey;
                $input['auth_method'] = 'api_key';
            } else {
                $required = ['nombre', 'slug', 'base_url', 'auth_method', 'password'];
                foreach ($required as $f) {
                    if (empty($input[$f])) {
                        echo json_encode(['success' => false, 'message' => "Campo requerido: $f"]);
                        exit;
                    }
                }
            }

            // userName es requerido para JWT y Basic, pero opcional para API Key (excepto ShipEdge que ya lo validó arriba)
            $authMethod = $input['auth_method'] ?? 'bearer_jwt';
            if ($authMethod === 'bearer_jwt' || $authMethod === 'basic') {
                if (empty($input['userName'])) {
                    echo json_encode(['success' => false, 'message' => "Campo requerido: userName"]);
                    exit;
                }
            }

            $credsToSave = [
                'userName'       => $input['userName'] ?? '',
                'password'       => $input['password'] ?? '',
                'webhook_secret' => !empty($input['webhook_secret']) ? $input['webhook_secret'] : bin2hex(random_bytes(16)),
            ];
            if ($slugNorm === 'shipedge') {
                $credsToSave['account_id']        = $input['userName'];
                $credsToSave['api_key']           = $input['password'];
                $credsToSave['shipping_method']   = trim((string)($shipedgeCfg['shipping_method'] ?? ''));
                $credsToSave['carrier_name']      = trim((string)($shipedgeCfg['carrier_name'] ?? ''));
                $credsToSave['store_channel']     = trim((string)($shipedgeCfg['store_channel'] ?? ''));
                $credsToSave['default_country']   = trim((string)($shipedgeCfg['default_country'] ?? ''));
                $credsToSave['currency_code']     = strtoupper(trim((string)($shipedgeCfg['currency_code'] ?? '')));
                $credsToSave['enable_sold_price'] = !empty($shipedgeCfg['enable_sold_price']);
            }

            $id = ForwardingModel::crearProveedor([
                'nombre'         => $input['nombre'],
                'slug'           => $slugNorm,
                'base_url'       => $input['base_url'],
                'auth_endpoint'  => $input['auth_endpoint'] ?? ($slugNorm === 'shipedge' ? '/ping' : '/api/AccountApi'),
                'order_endpoint' => $input['order_endpoint'] ?? ($slugNorm === 'shipedge' ? '/orders' : '/api/Orders/OrderAndOrderDetail'),
                'payload_format' => in_array($input['payload_format'] ?? '', ['json','xml','soap']) ? $input['payload_format'] : 'json',
                'auth_method'    => $authMethod,
                'credentials'    => json_encode($credsToSave, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'default_config' => buildDefaultConfig($input),
            ]);
            echo json_encode([
                'success' => $id !== false,
                'message' => $id ? 'Proveedor creado' : 'Error al crear proveedor',
                'id'      => $id,
            ]);
            break;

        case 'actualizar':
            if (empty($input['id'])) {
                echo json_encode(['success' => false, 'message' => 'ID requerido']);
                exit;
            }
            $existingProv = ForwardingModel::obtenerProveedorPorId((int)$input['id']);
            if (!$existingProv) {
                echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
                exit;
            }
            $slugEffective = strtolower($input['slug'] ?? $existingProv['slug'] ?? '');
            if ($slugEffective === 'shipedge' && !empty($input['base_url'])) {
                require_once __DIR__ . '/../services/providers/ShipEdgeProvider.php';
                try {
                    $input['base_url'] = ShipEdgeProvider::normalizeBaseUrl($input['base_url']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }

            $data = [];
            $fields = ['nombre', 'slug', 'base_url', 'auth_endpoint', 'order_endpoint', 'auth_method'];
            foreach ($fields as $f) {
                if (isset($input[$f])) $data[$f] = $input[$f];
            }
            // Actualizar payload_format si viene en el request
            if (!empty($input['payload_format']) && in_array($input['payload_format'], ['json','xml','soap'])) {
                $data['payload_format'] = $input['payload_format'];
            }
            // Actualizar credenciales si se proporcionan o cambian
            $creds = json_decode($existingProv['credentials'] ?? '{}', true) ?: [];
            $updateCreds = false;

            if (!empty($input['userName'])) {
                $creds['userName'] = $input['userName'];
                if ($slugEffective === 'shipedge') {
                    $creds['account_id'] = $input['userName'];
                }
                $updateCreds = true;
            }
            if (!empty($input['password'])) {
                $creds['password'] = $input['password'];
                if ($slugEffective === 'shipedge') {
                    $creds['api_key'] = $input['password'];
                }
                $updateCreds = true;
            }
            if (!empty($input['webhook_secret']) && $input['webhook_secret'] !== '********' && $input['webhook_secret'] !== ($input['existing_webhook_secret'] ?? '')) {
                $creds['webhook_secret'] = $input['webhook_secret'];
                $updateCreds = true;
            }

            if ($slugEffective === 'shipedge' && !empty($input['shipedge_config']) && is_array($input['shipedge_config'])) {
                $sc = $input['shipedge_config'];
                if (!empty($sc['account_id'])) {
                    $creds['account_id'] = trim((string)$sc['account_id']);
                    $creds['userName']   = $creds['account_id'];
                }
                if (!empty($sc['api_key'])) {
                    $creds['api_key']  = trim((string)$sc['api_key']);
                    $creds['password'] = $creds['api_key'];
                }
                foreach (['shipping_method', 'carrier_name', 'store_channel', 'default_country', 'currency_code'] as $sk) {
                    if (array_key_exists($sk, $sc)) {
                        $creds[$sk] = $sk === 'currency_code'
                            ? strtoupper(trim((string)$sc[$sk]))
                            : trim((string)$sc[$sk]);
                    }
                }
                if (array_key_exists('enable_sold_price', $sc)) {
                    $creds['enable_sold_price'] = !empty($sc['enable_sold_price']);
                }
                $updateCreds = true;
            }

            if ($updateCreds) {
                $data['credentials'] = json_encode($creds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            // Mergear soap_config, c807_config y shipedge_config dentro de default_config
            $data['default_config'] = buildDefaultConfig($input, $existingProv['default_config'] ?? null);

            $ok = ForwardingModel::actualizarProveedor((int)$input['id'], $data);
            if ($ok && $slugEffective === 'shipedge') {
                ForwardingModel::sembrarPlantillaShipEdge((int)$input['id'], false);
                ForwardingModel::sembrarEstadosShipEdge();
            }
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Proveedor actualizado' : 'Error al actualizar']);
            break;

        case 'toggle':
            if (empty($input['id']) || !isset($input['activo'])) {
                echo json_encode(['success' => false, 'message' => 'ID y activo requeridos']);
                exit;
            }
            $ok = ForwardingModel::toggleProveedor((int)$input['id'], (int)$input['activo']);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Estado actualizado' : 'Error']);
            break;

        case 'test':
            $shipedgeCfg = is_array($input['shipedge_config'] ?? null) ? $input['shipedge_config'] : [];
            $testPass = $input['password'] ?? ($shipedgeCfg['api_key'] ?? '');
            $testUser = $input['userName'] ?? ($shipedgeCfg['account_id'] ?? '');
            $authMethod = $input['auth_method'] ?? '';
            $slug = $input['slug'] ?? '';
            
            if (!empty($input['id'])) {
                $existingProv = ForwardingModel::obtenerProveedorPorId((int)$input['id']);
                if ($existingProv) {
                    $creds = json_decode($existingProv['credentials'] ?? '{}', true) ?: [];
                    if (empty($testPass)) $testPass = $creds['api_key'] ?? $creds['password'] ?? '';
                    if (empty($testUser)) $testUser = $creds['account_id'] ?? $creds['userName'] ?? '';
                    if (empty($authMethod)) $authMethod = $existingProv['auth_method'] ?? '';
                    if (empty($slug)) $slug = $existingProv['slug'] ?? '';
                }
            }

            if (empty($authMethod)) {
                $authMethod = (in_array($slug, ['hlexpress', 'shipedge'], true)) ? 'api_key' : 'bearer_jwt';
            }

            if ($slug === 'shipedge') {
                require_once __DIR__ . '/../services/providers/ShipEdgeProvider.php';
                try {
                    $input['base_url'] = ShipEdgeProvider::normalizeBaseUrl($input['base_url'] ?? '');
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                if (empty($testUser) || empty($testPass)) {
                    echo json_encode(['success' => false, 'message' => 'AccountID y Key son obligatorios para probar la conexión con ShipEdge (POST /ping).']);
                    exit;
                }
            } else {
                if (empty($input['base_url']) || empty($testPass)) {
                    echo json_encode(['success' => false, 'message' => 'base_url y password/API Key son requeridos']);
                    exit;
                }

                if (($authMethod === 'bearer_jwt' || $authMethod === 'basic') && empty($testUser)) {
                    echo json_encode(['success' => false, 'message' => 'userName es requerido para este método de autenticación']);
                    exit;
                }
            }
            require_once __DIR__ . '/../services/ForwardingService.php';

            // Pasar default_config y payload_format para que DynamicProvider SOAP funcione en el test
            $defaultConfig = null;
            $payloadFormat = $input['payload_format'] ?? 'json';
            $providerId    = (int)($input['id'] ?? 0);
            if (!empty($input['id']) && isset($existingProv)) {
                $defaultConfig = $existingProv['default_config'] ?? null;
                if (empty($payloadFormat) || $payloadFormat === 'json') {
                    $payloadFormat = $existingProv['payload_format'] ?? 'json';
                }
            }

            $result = ForwardingService::testConexion([
                'id'             => $providerId,
                'slug'           => $slug ?: ($input['slug'] ?? 'logispro'),
                'base_url'       => $input['base_url'],
                'auth_endpoint'  => $input['auth_endpoint'] ?? '',
                'order_endpoint' => $input['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail',
                'auth_method'    => $authMethod,
                'payload_format' => $payloadFormat,
                'default_config' => $defaultConfig,
                'credentials'    => [
                    'userName'   => $testUser,
                    'password'   => $testPass,
                    'account_id' => $testUser,
                    'api_key'    => $testPass,
                ],
            ]);
            echo json_encode($result);
            break;

        case 'reconcile_shipedge':
            try {
                require_once __DIR__ . '/../services/ShipEdgeWebhookAndReconcileService.php';
                $db = (new Conexion())->conectar();
                $idProv = !empty($input['id']) ? (int)$input['id'] : null;
                $summary = ShipEdgeWebhookAndReconcileService::conciliarOrdenesPendientes($db, $idProv, 50);
                echo json_encode([
                    'success' => empty($summary['errors']),
                    'message' => sprintf(
                        'Conciliación completada: %d órdenes verificadas, %d actualizadas.',
                        $summary['orders_checked'],
                        $summary['orders_updated']
                    ),
                    'summary' => $summary,
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'sync_c807_catalogs':
            try {
                require_once __DIR__ . '/../services/providers/C807Provider.php';
                require_once __DIR__ . '/../services/C807CatalogService.php';

                $baseUrl = !empty($input['base_url']) ? rtrim($input['base_url'], '/') : 'https://qaapp.c807.com';
                $authEp  = !empty($input['auth_endpoint']) ? $input['auth_endpoint'] : '/admin.php/sesion/get_token';

                // Si viene ID de proveedor registrado y password vacía, recuperar credenciales guardadas
                $userName = $input['userName'] ?? '';
                $password = $input['password'] ?? '';
                if (!empty($input['id']) && empty($password)) {
                    $prov = ForwardingModel::obtenerProveedorPorId((int)$input['id']);
                    if ($prov) {
                        $c = json_decode($prov['credentials'] ?? '{}', true);
                        if (empty($userName)) $userName = $c['userName'] ?? '';
                        $password = $c['password'] ?? '';
                    }
                }

                $c807 = new C807Provider($baseUrl, ['userName' => $userName, 'password' => $password], ['auth_endpoint' => $authEp]);
                $auth = $c807->authenticate();
                $token = $auth['token'];

                $sync = C807CatalogService::sincronizarDesdeApi($baseUrl, $token);
                echo json_encode([
                    'success'                     => true,
                    'message'                     => 'Catálogos sincronizados correctamente con C807',
                    'departamentos_sincronizados' => $sync['departamentos_sincronizados'],
                    'municipios_sincronizados'    => $sync['municipios_sincronizados'],
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida: ' . $action]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Método no soportado']);

/**
 * Construir el JSON de default_config mergeando la config existente con el soap_config y c807_config del request.
 *
 * @param array       $input         Input del request
 * @param string|null $existingJson  JSON existente en la BD (para merge en actualizar)
 * @return string|null               JSON resultante o null si no hay nada que guardar
 */
function buildDefaultConfig(array $input, ?string $existingJson = null): ?string
{
    // Partir de config existente
    $config = !empty($existingJson) ? (json_decode($existingJson, true) ?: []) : [];

    // Mergear soap_config si viene en el request
    if (!empty($input['soap_config']) && is_array($input['soap_config'])) {
        $soapFields = [
            'soap_action', 'soap_namespace', 'soap_envelope_tag', 'soap_item_tag',
            'soap_response_id_tags', 'soap_auth_tag', 'soap_auth_login_tag',
            'soap_auth_pass_tag', 'soap_auth_in_body',
        ];
        foreach ($soapFields as $k) {
            if (array_key_exists($k, $input['soap_config'])) {
                $v = $input['soap_config'][$k];
                // Si es el booleano, guardarlo como 1/0
                if ($k === 'soap_auth_in_body') {
                    $config[$k] = $v ? 1 : 0;
                } elseif ($v !== '' && $v !== null) {
                    $config[$k] = $v;
                } else {
                    unset($config[$k]); // Limpiar campo vacío
                }
            }
        }
    }

    // Mergear c807_config si viene en el request
    if (!empty($input['c807_config']) && is_array($input['c807_config'])) {
        $c807Fields = [
            'tipo_entrega', 'tipo_servicio', 'unidad_medida', 'sede',
            'politica_recolecta_fecha', 'recolecta_comentario',
        ];
        foreach ($c807Fields as $k) {
            if (array_key_exists($k, $input['c807_config'])) {
                $v = $input['c807_config'][$k];
                if ($v !== '' && $v !== null) {
                    $config[$k] = $v;
                } else {
                    unset($config[$k]);
                }
            }
        }
    }

    // Mergear shipedge_config si viene en el request
    if (!empty($input['shipedge_config']) && is_array($input['shipedge_config'])) {
        $shipedgeFields = [
            'shipping_method', 'carrier_name', 'store_channel',
            'default_country', 'currency_code', 'enable_sold_price',
        ];
        foreach ($shipedgeFields as $k) {
            if (array_key_exists($k, $input['shipedge_config'])) {
                $v = $input['shipedge_config'][$k];
                if ($k === 'enable_sold_price') {
                    $config[$k] = !empty($v);
                } elseif ($v !== '' && $v !== null) {
                    $config[$k] = $k === 'currency_code' ? strtoupper(trim((string)$v)) : trim((string)$v);
                } else {
                    unset($config[$k]);
                }
            }
        }
    }

    // Si el request trae un default_config JSON raw (legacy), también mergearlo
    if (!empty($input['default_config']) && is_string($input['default_config'])) {
        $rawConfig = json_decode($input['default_config'], true);
        if (is_array($rawConfig)) {
            $config = array_merge($config, $rawConfig);
        }
    }

    return !empty($config) ? json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}
