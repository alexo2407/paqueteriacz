<?php
/**
 * GET /api/geoinfo/listar
 *
 * Devuelve un JSON con listados de referencia geográfica y monetaria:
 * - paises: [{id,nombre,codigo_iso}]
 * - departamentos: [{id,nombre,id_pais}]
 * - municipios: [{id,nombre,id_departamento}]
 * - barrios: [{id,nombre,id_municipio}]
 * - monedas: [{id,nombre,codigo,tasa_usd}]
 *
 * Útil para poblar selectores en el frontend.
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Cargar modelos
require_once __DIR__ . '/../../modelo/pais.php';
require_once __DIR__ . '/../../modelo/departamento.php';
require_once __DIR__ . '/../../modelo/municipio.php';
require_once __DIR__ . '/../../modelo/barrio.php';
require_once __DIR__ . '/../../modelo/moneda.php';

try {
    $paises = PaisModel::listar();
    $departamentos = DepartamentoModel::listarPorPais();
    // Algunos proyectos usan listarPorDepartamento o listarPorPais; el modelo actual
    // implementa listarPorPais(null) que devuelve todos los departamentos.
    $municipios = MunicipioModel::listarPorDepartamento();
    $barrios = BarrioModel::listarPorMunicipio();
    $monedas = MonedaModel::listar();

    $out = [
        'success' => true,
        'message' => 'Geoinfo listada',
        'data' => [
            'paises' => $paises,
            'departamentos' => $departamentos,
            'municipios' => $municipios,
            'barrios' => $barrios,
            'monedas' => $monedas,
        ]
    ];

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
    exit;
}

?>
