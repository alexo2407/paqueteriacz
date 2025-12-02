<?php
include_once __DIR__ . '/../../config/config.php';
include_once __DIR__ . '/../../controlador/geoinfo.php';
include_once __DIR__ . '/../autenticacion.php';

$method = $_SERVER['REQUEST_METHOD'];
$controller = new GeoinfoController();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$paisId = isset($_GET['id_pais']) ? (int)$_GET['id_pais'] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            $depto = DepartamentoModel::obtenerPorId($id);
            if (!$depto) {
                http_response_code(404);
                echo json_encode(['error' => 'Departamento no encontrado', 'code' => 'NOT_FOUND']);
            } else {
                echo json_encode($depto);
            }
        } else {
            echo json_encode($controller->listarDepartamentos($paisId));
        }
        break;

    case 'POST':
        echo json_encode($controller->crearDepartamento($input));
        break;

    case 'PUT':
        if (!$id) throw new Exception("ID requerido.", 400);
        echo json_encode($controller->actualizarDepartamento($id, $input));
        break;

    case 'DELETE':
        if (!$id) throw new Exception("ID requerido.", 400);
        echo json_encode($controller->eliminarDepartamento($id));
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido', 'code' => 'METHOD_NOT_ALLOWED']);
        break;
}
