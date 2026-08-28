<?php
/**
 * GET /api/logistica-operativa/bodega/catalogos/kpis
 *
 * Devuelve los conteos KPI en tiempo real para el panel operativo de Bodega.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../../controlador/logistica_operativa/BodegaUbicacionController.php';

    $controller = new BodegaUbicacionController();
    $controller->aplicarHeaders('GET, OPTIONS');
    $controller->requerirMetodo('GET');
    $controller->verificarModulo();
    $usuario = $controller->autenticar();
    $controller->verificarAutorizacion($usuario);

    $db = $controller->crearConexion();

    // 1. Recibidos hoy
    $stmt1 = $db->query("SELECT COUNT(*) FROM logistica_recepciones WHERE DATE(recibido_at) = CURDATE()");
    $recibidosHoy = (int)($stmt1 ? $stmt1->fetchColumn() : 0);

    // 2. Pendientes de ubicación
    $stmt2 = $db->query("SELECT COUNT(*) FROM logistica_recepciones WHERE estado = 'RECIBIDO' AND (id_ubicacion IS NULL OR id_ubicacion = 0)");
    $pendientesUbicacion = (int)($stmt2 ? $stmt2->fetchColumn() : 0);

    // 3. En incidencia
    $stmt3 = $db->query("SELECT COUNT(*) FROM logistica_ruta_pedidos WHERE estado_entrega = 'INCIDENCIA'");
    $enIncidencia = (int)($stmt3 ? $stmt3->fetchColumn() : 0);

    // 4. Retirados hoy
    $stmt4 = $db->query("SELECT COUNT(*) FROM logistica_recepciones WHERE estado = 'RETIRADO' AND DATE(updated_at) = CURDATE()");
    $retiradosHoy = (int)($stmt4 ? $stmt4->fetchColumn() : 0);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'recibidos_hoy'        => $recibidosHoy,
            'pendientes_ubicacion' => $pendientesUbicacion,
            'en_incidencia'        => $enIncidencia,
            'retirados_hoy'        => $retiradosHoy
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[api/bodega/catalogos/kpis] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
