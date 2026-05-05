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
            $required = ['nombre', 'slug', 'base_url', 'userName', 'password'];
            foreach ($required as $f) {
                if (empty($input[$f])) {
                    echo json_encode(['success' => false, 'message' => "Campo requerido: $f"]);
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
                'auth_method'    => $input['auth_method'] ?? 'bearer_jwt',
                'credentials'    => json_encode([
                    'userName' => $input['userName'],
                    'password' => $input['password'],
                    'webhook_secret' => !empty($input['webhook_secret']) ? $input['webhook_secret'] : bin2hex(random_bytes(16)),
                ]),
                'default_config' => !empty($input['default_config']) ? $input['default_config'] : null,
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
            if (isset($input['default_config'])) {
                $data['default_config'] = $input['default_config'];
            }
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
            
            if ((empty($testPass) || empty($testUser)) && !empty($input['id'])) {
                $existingProv = ForwardingModel::obtenerProveedorPorId((int)$input['id']);
                if ($existingProv) {
                    $creds = json_decode($existingProv['credentials'] ?? '{}', true);
                    if (empty($testPass)) $testPass = $creds['password'] ?? '';
                    if (empty($testUser)) $testUser = $creds['userName'] ?? '';
                }
            }

            if (empty($input['base_url']) || empty($testUser) || empty($testPass)) {
                echo json_encode(['success' => false, 'message' => 'base_url, userName y password requeridos']);
                exit;
            }
            require_once __DIR__ . '/../services/ForwardingService.php';
            $result = ForwardingService::testConexion([
                'slug'           => $input['slug'] ?? 'logispro',
                'base_url'       => $input['base_url'],
                'auth_endpoint'  => $input['auth_endpoint'] ?? '/api/AccountApi',
                'order_endpoint' => $input['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail',
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
