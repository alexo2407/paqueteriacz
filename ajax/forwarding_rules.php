<?php
/**
 * AJAX endpoint para gestionar reglas de forwarding.
 *
 * GET: listar todas las reglas
 * POST action=crear: crear nueva regla
 * POST action=toggle: activar/desactivar regla
 * POST action=eliminar: eliminar regla
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../modelo/forwarding.php';

start_secure_session();
header('Content-Type: application/json');

// Auth + Admin check
if (empty($_SESSION['registrado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
if (!in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $reglas = ForwardingModel::obtenerTodasLasReglas();
    echo json_encode(['success' => true, 'data' => $reglas]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'crear':
            if (empty($input['id_cliente']) || empty($input['id_provider'])) {
                echo json_encode(['success' => false, 'message' => 'id_cliente y id_provider requeridos']);
                exit;
            }
            $id = ForwardingModel::crearRegla(
                (int)$input['id_cliente'],
                (int)$input['id_provider'],
                $input['config_override'] ?? null
            );
            echo json_encode([
                'success' => $id !== false,
                'message' => $id ? 'Regla creada' : 'Error al crear regla (¿ya existe?)',
                'id'      => $id,
            ]);
            break;

        case 'actualizar':
            if (empty($input['id']) || (empty($input['id_cliente']) && empty($input['id_provider']))) {
                echo json_encode(['success' => false, 'message' => 'ID y al menos un campo a actualizar son requeridos']);
                exit;
            }
            $data = [];
            if (!empty($input['id_cliente']))  $data['id_cliente']  = (int)$input['id_cliente'];
            if (!empty($input['id_provider'])) $data['id_provider'] = (int)$input['id_provider'];
            if (array_key_exists('config_override', $input)) $data['config_override'] = $input['config_override'] ?: null;
            $ok = ForwardingModel::actualizarRegla((int)$input['id'], $data);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Regla actualizada' : 'Error al actualizar']);
            break;

        case 'toggle':
            if (empty($input['id']) || !isset($input['activo'])) {
                echo json_encode(['success' => false, 'message' => 'ID y activo requeridos']);
                exit;
            }
            $ok = ForwardingModel::toggleRegla((int)$input['id'], (int)$input['activo']);
            echo json_encode(['success' => $ok]);
            break;

        case 'eliminar':
            if (empty($input['id'])) {
                echo json_encode(['success' => false, 'message' => 'ID requerido']);
                exit;
            }
            $ok = ForwardingModel::eliminarRegla((int)$input['id']);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Regla eliminada' : 'Error']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Método no soportado']);
