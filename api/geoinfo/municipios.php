<?php
include_once __DIR__ . '/../../config/config.php';
include_once __DIR__ . '/../../controlador/geoinfo.php';
include_once __DIR__ . '/../utils/autenticacion.php';

$method = $_SERVER['REQUEST_METHOD'];
$controller = new GeoinfoController();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$depId = isset($_GET['id_departamento']) ? (int)$_GET['id_departamento'] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            $muni = MunicipioModel::obtenerPorId($id);
            if (!$muni) {
                http_response_code(404);
                echo json_encode(['error' => 'Municipio no encontrado', 'code' => 'NOT_FOUND']);
            } else {
                echo json_encode($muni);
            }
        } else {
            echo json_encode($controller->listarMunicipios($depId));
        }
        break;

    case 'POST':
        echo json_encode($controller->crearMunicipio($input));
        break;

    case 'PUT':
        if (!$id) throw new Exception("ID requerido.", 400);
        echo json_encode($controller->actualizarMunicipio($id, $input));
        break;

    case 'DELETE':
        if (!$id) throw new Exception("ID requerido.", 400);
        echo json_encode($controller->eliminarMunicipio($id));
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido', 'code' => 'METHOD_NOT_ALLOWED']);
        break;
}
