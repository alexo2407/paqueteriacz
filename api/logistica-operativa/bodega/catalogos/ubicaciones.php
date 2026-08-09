<?php
/**
 * GET /api/logistica-operativa/bodega/catalogos/ubicaciones?id_bodega=N
 *
 * Devuelve las ubicaciones activas de una bodega concreta.
 *
 * Seguridad:
 *   - Requiere autenticación JWT.
 *   - Requiere autorización (logistica_operativa_bodega).
 *   - Requiere módulo habilitado y shadow mode.
 *   - id_bodega es obligatorio y debe ser numérico positivo.
 *   - Solo lectura. No escribe en base de datos.
 *   - No devuelve datos sensibles.
 *
 * Respuestas:
 *   200 → { success: true, data: [ { id, codigo, zona, pasillo, estante, cajon, nivel, tipo, nomenclatura }, ... ] }
 *   400 → id_bodega faltante o inválido
 *   401 → no autenticado
 *   403 → sin autorización o módulo deshabilitado
 *   500 → error interno
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../../controlador/logistica_operativa/BodegaUbicacionController.php';
    require_once __DIR__ . '/../../../../modelo/logistica_operativa/UbicacionModel.php';

    $controller = new BodegaUbicacionController();
    $controller->aplicarHeaders('GET, OPTIONS');
    $controller->requerirMetodo('GET');
    $controller->verificarModulo();
    $usuario = $controller->autenticar();
    $controller->verificarAutorizacion($usuario);

    // Validar id_bodega
    $idBodegaRaw = $_GET['id_bodega'] ?? '';
    if ($idBodegaRaw === '' || !is_numeric($idBodegaRaw) || (int) $idBodegaRaw <= 0) {
        $controller->error('MISSING_PARAM', 'El parámetro id_bodega es requerido y debe ser un entero positivo.', 400);
    }

    $idBodega   = (int) $idBodegaRaw;
    $db         = $controller->crearConexion();
    $model      = new UbicacionModel($db);
    $ubicaciones = $model->listarActivasPorBodega($idBodega);

    // Sanitizar — solo campos necesarios
    $resultado = array_map(fn($u) => [
        'id'          => (int) $u['id'],
        'codigo'      => (string) ($u['codigo']      ?? ''),
        'zona'        => $u['zona']    !== null ? (string) $u['zona']    : null,
        'pasillo'     => $u['pasillo'] !== null ? (string) $u['pasillo'] : null,
        'estante'     => $u['estante'] !== null ? (string) $u['estante'] : null,
        'cajon'       => $u['cajon']   !== null ? (string) $u['cajon']   : null,
        'nivel'       => $u['nivel']   !== null ? (string) $u['nivel']   : null,
        'tipo'        => (string) ($u['tipo']        ?? ''),
        'nomenclatura'=> (string) ($u['nomenclatura'] ?? $u['codigo'] ?? ''),
    ], $ubicaciones);

    http_response_code(200);
    echo json_encode(
        ['success' => true, 'data' => $resultado],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

} catch (Throwable $e) {
    error_log('[api/bodega/catalogos/ubicaciones] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
