<?php
include_once __DIR__ . '/../../config/config.php';
include_once __DIR__ . '/../../controlador/geoinfo.php';
include_once __DIR__ . '/../utils/autenticacion.php';

$method = $_SERVER['REQUEST_METHOD'];
$controller = new GeoinfoController();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$munId = isset($_GET['id_municipio']) ? (int)$_GET['id_municipio'] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            $barrio = BarrioModel::obtenerPorId($id);
            if (!$barrio) {
                http_response_code(404);
                echo json_encode(['error' => 'Barrio no encontrado', 'code' => 'NOT_FOUND']);
            } else {
                echo json_encode($barrio);
            }
        } else {
            echo json_encode($controller->listarBarrios($munId));
        }
        break;

    case 'POST':
        echo json_encode($controller->crearBarrio($input));
        break;

    case 'PUT':
        if (!$id) throw new Exception("ID requerido.", 400);
        echo json_encode($controller->actualizarBarrio($id, $input));
        break;

    case 'DELETE':
        if (!$id) throw new Exception("ID requerido.", 400);
        echo json_encode($controller->eliminarBarrio($id));
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido', 'code' => 'METHOD_NOT_ALLOWED']);
        break;
}
