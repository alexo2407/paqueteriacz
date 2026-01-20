<?php
include_once __DIR__ . '/../../config/config.php';
include_once __DIR__ . '/../../controlador/geoinfo.php';
include_once __DIR__ . '/../utils/autenticacion.php'; // Ensure auth if needed, or remove if public

// Optional: Validate Auth
// $usuario = Autenticacion::verificar();

$method = $_SERVER['REQUEST_METHOD'];
$controller = new GeoinfoController();

// Parse input
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Helper to get ID from path or query
// Assuming path is /api/geoinfo/paises/123 or query param ?id=123
// Since we use simple routing in api/index.php, we might need to rely on query params or path parsing if implemented.
// Let's assume query param 'id' for simplicity unless router supports path params.
// Based on api/index.php, it just includes the file. So we rely on $_GET['id'] or similar.
// But standard REST uses path. Let's check api/index.php again.
// It parses path. But it doesn't seem to pass params.
// Let's assume standard query param ?id=X for GET/PUT/DELETE for now to be safe, or check how other APIs do it.
// `api/pedidos/crear.php` is a specific file.
// If we want `api/geoinfo/paises.php` to handle `/api/geoinfo/paises`, it works.
// If we want `/api/geoinfo/paises/1`, `api/index.php` might not route it correctly to `paises.php` unless we handle it.
// `api/index.php`:
// $path = rtrim($rawPath, '/');
// if (file_exists(__DIR__ . '/' . $path . '.php')) { include ... }
// So `/api/geoinfo/paises` maps to `api/geoinfo/paises.php`.
// `/api/geoinfo/paises/1` would look for `api/geoinfo/paises/1.php` which fails.
// So we must use query params: `/api/geoinfo/paises?id=1`.

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            $pais = PaisModel::obtenerPorId($id);
            if (!$pais) {
                http_response_code(404);
                echo json_encode(['error' => 'País no encontrado', 'code' => 'NOT_FOUND']);
            } else {
                echo json_encode($pais);
            }
        } else {
            echo json_encode($controller->listarPaises());
        }
        break;

    case 'POST':
        echo json_encode($controller->crearPais($input));
        break;

    case 'PUT':
        if (!$id) {
            throw new Exception("ID de país requerido para actualizar.", 400);
        }
        echo json_encode($controller->actualizarPais($id, $input));
        break;

    case 'DELETE':
        if (!$id) {
            throw new Exception("ID de país requerido para eliminar.", 400);
        }
        echo json_encode($controller->eliminarPais($id));
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido', 'code' => 'METHOD_NOT_ALLOWED']);
        break;
}
