<?php
/**
 * API - Resolución de Códigos Postales
 * Soporta búsqueda global (colisiones) y específica por país.
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once __DIR__ . '/../../config/config.php';
include_once __DIR__ . '/../../modelo/codigos_postales.php';
include_once __DIR__ . '/../../services/AddressService.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Método no permitido", "code" => "METHOD_NOT_ALLOWED"]);
    exit;
}

$action = $_GET['action'] ?? 'resolve';
$cp_raw = $_GET['cp'] ?? '';
$id_pais = isset($_GET['id_pais']) ? (int)$_GET['id_pais'] : null;
$id_barrio = isset($_GET['id_barrio']) ? (int)$_GET['id_barrio'] : null;

// Normalización obligatoria
$normalized_cp = AddressService::normalizarCP($cp_raw);

try {
    switch ($action) {
        case 'resolve':
            if (empty($normalized_cp)) {
                http_response_code(400);
                echo json_encode(["ok" => false, "error" => "Código postal (cp) es requerido", "code" => "BAD_REQUEST"]);
                exit;
            }

            if ($id_pais) {
                // Búsqueda específica por país + CP
                $match = CodigosPostalesModel::buscar($id_pais, $normalized_cp);
                
                if (!$match) {
                    http_response_code(404);
                    echo json_encode([
                        "ok" => false, 
                        "data" => ["normalized_cp" => $normalized_cp, "matches" => []],
                        "error" => "No se encontró el código postal para el país especificado",
                        "code" => "NOT_FOUND"
                    ]);
                } else {
                    // Adaptar respuesta al formato solicitado
                    $formatted_match = [
                        "id_codigo_postal" => (int)$match['id'],
                        "id_pais" => (int)$match['id_pais'],
                        "pais" => $match['nombre_pais'] ?? 'N/A',
                        "codigo_postal" => $match['codigo_postal'],
                        "activo" => (int)$match['activo'],
                        "id_departamento" => $match['id_departamento'] ? (int)$match['id_departamento'] : null,
                        "departamento" => $match['nombre_departamento'] ?? null,
                        "id_municipio" => $match['id_municipio'] ? (int)$match['id_municipio'] : null,
                        "municipio" => $match['nombre_municipio'] ?? null,
                        "id_barrio" => $match['id_barrio'] ? (int)$match['id_barrio'] : null,
                        "barrio" => $match['nombre_barrio'] ?? null,
                        "partial" => AddressService::isPartial($match)
                    ];

                    echo json_encode([
                        "ok" => true,
                        "data" => [
                            "normalized_cp" => $normalized_cp,
                            "matches" => [$formatted_match]
                        ],
                        "error" => null
                    ]);
                }
            } else {
                // Búsqueda global para resolver colisiones
                $results = CodigosPostalesModel::buscarGlobal($normalized_cp);
                $matches = [];

                foreach ($results as $row) {
                    $matches[] = [
                        "id_codigo_postal" => (int)$row['id'],
                        "id_pais" => (int)$row['id_pais'],
                        "pais" => $row['nombre_pais'],
                        "codigo_postal" => $row['codigo_postal'],
                        "activo" => (int)$row['activo'],
                        "id_departamento" => $row['id_departamento'] ? (int)$row['id_departamento'] : null,
                        "departamento" => $row['nombre_departamento'] ?? null,
                        "id_municipio" => $row['id_municipio'] ? (int)$row['id_municipio'] : null,
                        "municipio" => $row['nombre_municipio'] ?? null,
                        "id_barrio" => $row['id_barrio'] ? (int)$row['id_barrio'] : null,
                        "barrio" => $row['nombre_barrio'] ?? null,
                        "partial" => AddressService::isPartial($row)
                    ];
                }

                echo json_encode([
                    "ok" => true,
                    "data" => [
                        "normalized_cp" => $normalized_cp,
                        "matches" => $matches
                    ],
                    "error" => null
                ]);
            }
            break;

        case 'find_by_zone':
            if (!$id_pais || !$id_barrio) {
                http_response_code(400);
                echo json_encode(["ok" => false, "error" => "id_pais e id_barrio son requeridos", "code" => "BAD_REQUEST"]);
                exit;
            }

            $results = CodigosPostalesModel::buscarPorZona($id_pais, $id_barrio);
            $matches = [];

            foreach ($results as $row) {
                $matches[] = [
                    "id_codigo_postal" => (int)$row['id'],
                    "codigo_postal" => $row['codigo_postal'],
                    "id_pais" => (int)$row['id_pais'],
                    "pais" => $row['nombre_pais']
                ];
            }

            echo json_encode([
                "ok" => true,
                "data" => [
                    "id_barrio" => $id_barrio,
                    "matches" => $matches
                ],
                "error" => null
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(["ok" => false, "error" => "Acción no válida", "code" => "INVALID_ACTION"]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "Error interno del servidor: " . $e->getMessage(),
        "code" => "INTERNAL_SERVER_ERROR"
    ]);
}
