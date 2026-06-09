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
        $creds = json_decode($p['credentials'] ?? '{}', true);
        if (isset($creds['password'])) {
            $creds['password'] = str_repeat('*', min(strlen($creds['password']), 8));
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
            $required = ['nombre', 'slug', 'base_url', 'auth_method', 'password'];
            foreach ($required as $f) {
                if (empty($input[$f])) {
                    echo json_encode(['success' => false, 'message' => "Campo requerido: $f"]);
                    exit;
                }
            }
            // userName es requerido para JWT y Basic, pero opcional para API Key
            $authMethod = $input['auth_method'] ?? 'bearer_jwt';
            if ($authMethod === 'bearer_jwt' || $authMethod === 'basic') {
                if (empty($input['userName'])) {
                    echo json_encode(['success' => false, 'message' => "Campo requerido: userName"]);
                    exit;
                }
            }

            // Verificar slug único
            $existing = ForwardingModel::obtenerProveedorPorSlug($input['slug']);
            if ($existing) {
                echo json_encode(['success' => false, 'message' => 'Ya existe un proveedor con ese slug']);
                exit;
            }
            $id = ForwardingModel::crearProveedor([
                'nombre'         => $input['nombre'],
                'slug'           => strtolower(preg_replace('/[^a-z0-9_-]/', '', $input['slug'])),
                'base_url'       => $input['base_url'],
                'auth_endpoint'  => $input['auth_endpoint'] ?? '/api/AccountApi',
                'order_endpoint' => $input['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail',
                'payload_format' => in_array($input['payload_format'] ?? '', ['json','xml','soap']) ? $input['payload_format'] : 'json',
                'auth_method'    => $authMethod,
                'credentials'    => json_encode([
                    'userName'       => $input['userName'] ?? '',
                    'password'       => $input['password'] ?? '',
                    'webhook_secret' => !empty($input['webhook_secret']) ? $input['webhook_secret'] : bin2hex(random_bytes(16)),
                ]),
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
            $existingProv = ForwardingModel::obtenerProveedorPorId((int)$input['id']);
            $creds = json_decode($existingProv['credentials'] ?? '{}', true);
            $updateCreds = false;

            if (!empty($input['userName'])) { $creds['userName'] = $input['userName']; $updateCreds = true; }
            if (!empty($input['password'])) { $creds['password'] = $input['password']; $updateCreds = true; }
            if (!empty($input['webhook_secret']) && $input['webhook_secret'] !== ($input['existing_webhook_secret'] ?? '')) {
                $creds['webhook_secret'] = $input['webhook_secret'];
                $updateCreds = true;
            }

            if ($updateCreds) {
                $data['credentials'] = json_encode($creds);
            }
            // Mergear soap_config dentro de default_config
            $data['default_config'] = buildDefaultConfig($input, $existingProv['default_config'] ?? null);

            $ok = ForwardingModel::actualizarProveedor((int)$input['id'], $data);
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
            $testPass = $input['password'] ?? '';
            $testUser = $input['userName'] ?? '';
            $authMethod = $input['auth_method'] ?? '';
            $slug = $input['slug'] ?? '';
            
            if (!empty($input['id'])) {
                $existingProv = ForwardingModel::obtenerProveedorPorId((int)$input['id']);
                if ($existingProv) {
                    $creds = json_decode($existingProv['credentials'] ?? '{}', true);
                    if (empty($testPass)) $testPass = $creds['password'] ?? '';
                    if (empty($testUser)) $testUser = $creds['userName'] ?? '';
                    if (empty($authMethod)) $authMethod = $existingProv['auth_method'] ?? '';
                    if (empty($slug)) $slug = $existingProv['slug'] ?? '';
                }
            }

            if (empty($authMethod)) {
                $authMethod = ($slug === 'hlexpress') ? 'api_key' : 'bearer_jwt';
            }

            if (empty($input['base_url']) || empty($testPass)) {
                echo json_encode(['success' => false, 'message' => 'base_url y password/API Key son requeridos']);
                exit;
            }

            if (($authMethod === 'bearer_jwt' || $authMethod === 'basic') && empty($testUser)) {
                echo json_encode(['success' => false, 'message' => 'userName es requerido para este método de autenticación']);
                exit;
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
                    'userName' => $testUser,
                    'password' => $testPass,
                ],
            ]);
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida: ' . $action]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Método no soportado']);

/**
 * Construir el JSON de default_config mergeando la config existente con el soap_config del request.
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

    // Si el request trae un default_config JSON raw (legacy), también mergearlo
    if (!empty($input['default_config']) && is_string($input['default_config'])) {
        $rawConfig = json_decode($input['default_config'], true);
        if (is_array($rawConfig)) {
            $config = array_merge($config, $rawConfig);
        }
    }

    return !empty($config) ? json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}
