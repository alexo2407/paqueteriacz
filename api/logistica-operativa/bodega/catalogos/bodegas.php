<?php
/**
 * GET /api/logistica-operativa/bodega/catalogos/bodegas
 *
 * Devuelve la lista de bodegas activas para los selectores de la interfaz.
 *
 * Seguridad:
 *   - Requiere autenticación JWT.
 *   - Requiere autorización (logistica_operativa_bodega).
 *   - Requiere módulo habilitado y shadow mode.
 *   - Solo lectura. No escribe en base de datos.
 *   - No devuelve datos sensibles (contraseñas, DSN, etc.).
 *
 * Respuestas:
 *   200 → { success: true, data: [ { id, nombre, codigo, descripcion }, ... ] }
 *   401 → no autenticado
 *   403 → sin autorización o módulo deshabilitado
 *   500 → error interno
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../../controlador/logistica_operativa/BodegaUbicacionController.php';
    require_once __DIR__ . '/../../../../modelo/logistica_operativa/BodegaModel.php';

    $controller = new BodegaUbicacionController();
    $controller->aplicarHeaders('GET, OPTIONS');
    $controller->requerirMetodo('GET');
    $controller->verificarModulo();
    $usuario = $controller->autenticar();
    $controller->verificarAutorizacion($usuario);

    $db      = $controller->crearConexion();
    $model   = new BodegaModel($db);
    $bodegas = $model->listarActivas();

    // Sanitizar — solo campos necesarios, sin SQL, DSN ni datos sensibles
    $resultado = array_map(fn($b) => [
        'id'     => (int) $b['id'],
        'nombre' => (string) ($b['nombre'] ?? ''),
        'codigo' => (string) ($b['codigo'] ?? ''),
    ], $bodegas);

    http_response_code(200);
    echo json_encode(
        ['success' => true, 'data' => $resultado],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

} catch (Throwable $e) {
    error_log('[api/bodega/catalogos/bodegas] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
