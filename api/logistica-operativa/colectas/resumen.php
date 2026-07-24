<?php
/**
 * GET /api/logistica-operativa/colectas/resumen?id_colecta=N
 *
 * Devuelve el resumen completo de una colecta.
 *
 * Headers requeridos:
 *   Authorization: Bearer <JWT>
 *
 * Query string:
 *   id_colecta=<int>
 *
 * Respuestas:
 *   200  → resumen encontrado, { success: true, data: { colecta: {...}, conteos: {...} } }
 *   400  → parámetro id_colecta faltante o inválido
 *   401  → no autenticado
 *   403  → módulo deshabilitado
 *   404  → colecta no encontrada
 *   500  → error interno
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../controlador/logistica_operativa/ColectaController.php';

    $controller = new ColectaController();
    $controller->resumen();
} catch (Throwable $e) {
    error_log('[api/colectas/resumen] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
