<?php
/**
 * GET /api/pedidos/status
 *
 * Endpoint protegido para consultar el estado de los trabajos de logística (validación, etc.)
 * asociados a un pedido.
 *
 * Params:
 *  - numero_orden (required): El número de orden del cliente
 *
 * Headers:
 *  - Authorization: Bearer <token>
 *
 * Response:
 *  - success: bool
 *  - data: trabajos + última observación de estado
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../utils/autenticacion.php';
    require_once __DIR__ . '/../../controlador/pedido.php';
    require_once __DIR__ . '/../../services/LogisticsQueueService.php';

    require_once __DIR__ . '/../utils/responder.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        responder(false, 'Método no permitido. Use GET.', null, 405);
    }

    // Verificar autenticación
    $token = AuthMiddleware::obtenerTokenDeHeaders();
    if (!$token) {
        responder(false, 'Token de autorización requerido.', null, 401);
    }

    $auth = new AuthMiddleware();
    $validacion = $auth->validarToken($token);
    
    if (!$validacion['success']) {
        responder(false, 'Token inválido o expirado.', null, 401);
    }

    // 2. Validar parámetros (numero_orden O numeros_orden) OR return summary
    $numeros = [];
    $isBatch = false;
    $isSummary = false;

    if (isset($_GET['numeros_orden']) && !empty($_GET['numeros_orden'])) {
        // Modo Batch: lista separada por comas (ej: 1001,1002,1003)
        $numeros = array_map('trim', explode(',', $_GET['numeros_orden']));
        $isBatch = true;
    } elseif (isset($_GET['numero_orden']) && !empty($_GET['numero_orden'])) {
        // Modo Single
        $numeros = [$_GET['numero_orden']];
        $isBatch = false;
    } else {
        // Modo Resumen Automático (Dashboard) - Si no envía parámetros
        $isSummary = true;
    }

    $controller = new PedidosController();
    $authUserId = (int)($validacion['data']['id'] ?? 0);
    $authUserRole = (int)($validacion['data']['rol'] ?? 0);
    $isAdmin = ($authUserRole === (defined('ROL_ADMIN') ? ROL_ADMIN : 1));
    
    // --> MODO RESUMEN GENERAL (User Dashboard)
    if ($isSummary) {
        $userId = $authUserId;
        
        if (!$userId) {
             responder(false, 'No se pudo identificar al usuario para el resumen', null, 400);
        }

        $summary = LogisticsQueueService::obtenerResumenPorProveedor($userId);
        $recentFailures = LogisticsQueueService::obtenerFallidosRecientesPorProveedor($userId, 10);
        
        responder(true, "Dashboard resumen", [
            'mode' => 'provider_dashboard',
            'summary' => $summary,
            'recent_failures' => $recentFailures
        ], 200);
    }

    // --> MODO CONSULTA (Single o Batch)
    $results = [];

    // Limitar batch para evitar sobrecarga (ej. máx 50 a la vez)
    if (count($numeros) > 50) {
        $numeros = array_slice($numeros, 0, 50);
    }

    foreach ($numeros as $numeroOrden) {
        // Buscar el pedido
        $resultadoBusqueda = $controller->buscarPedidoPorNumero($numeroOrden);
        
        $itemStatus = [
            'numero_orden' => $numeroOrden,
            'found' => false,
            'jobs' => [],
            'observacion_estado' => null,
            'fecha_observacion_estado' => null,
            'observacion_por' => null
        ];

        if ($resultadoBusqueda['success'] && !empty($resultadoBusqueda['data'])) {
            $pedido = $resultadoBusqueda['data'];
            $idPedido = isset($pedido['id']) ? (int)$pedido['id'] : 0;
            $idCliente = isset($pedido['id_cliente']) ? (int)$pedido['id_cliente'] : 0;
            $idProveedor = isset($pedido['id_proveedor']) ? (int)$pedido['id_proveedor'] : 0;

            // Validar pertenencia para evitar exponer estado de pedidos ajenos
            $isOwner = ($authUserId > 0) && ($authUserId === $idCliente || $authUserId === $idProveedor);
            if (!$isAdmin && !$isOwner) {
                // Para no filtrar existencia de pedidos ajenos, se reporta como no encontrado
                $results[] = $itemStatus;
                continue;
            }

            // Pedido accesible y encontrado
            $itemStatus['found'] = true;

            if ($idPedido <= 0) {
                $results[] = $itemStatus;
                continue;
            }
            
            // Consultar trabajos
            $trabajos = LogisticsQueueService::obtenerPorPedido($idPedido);
            
            $itemStatus['jobs'] = array_map(function($job) {
                $updatedAtLocal = $job['updated_at'];
                if (!empty($updatedAtLocal)) {
                    try {
                        $systemTz = date_default_timezone_get();
                        $dt = new DateTime($updatedAtLocal, new DateTimeZone($systemTz));
                        $dt->setTimezone(new DateTimeZone('America/Managua'));
                        $updatedAtLocal = $dt->format('Y-m-d H:i:s');
                    } catch (Exception $e) {}
                }

                return [
                    'job_type' => $job['job_type'],
                    'status' => $job['status'],
                    'attempts' => (int)$job['attempts'],
                    'updated_at' => $updatedAtLocal,
                    'error' => $job['status'] === 'failed' ? $job['last_error'] : null
                ];
            }, $trabajos);

            // Última observación registrada del cambio de estado
            $historial = PedidosModel::obtenerHistorialEstados($idPedido);
            if (!empty($historial)) {
                $fechaObservacion = $historial[0]['created_at'] ?? null;
                if (!empty($fechaObservacion)) {
                     try {
                         $systemTz = date_default_timezone_get();
                         $dt = new DateTime($fechaObservacion, new DateTimeZone($systemTz));
                         $dt->setTimezone(new DateTimeZone('America/Managua'));
                         $fechaObservacion = $dt->format('Y-m-d H:i:s');
                    } catch (Exception $e) {}
                }

                $itemStatus['observacion_estado'] = $historial[0]['observaciones'] ?? null;
                $itemStatus['fecha_observacion_estado'] = $fechaObservacion;
                $itemStatus['observacion_por'] = $historial[0]['usuario_nombre'] ?? null;
            }
        }
        
        $results[] = $itemStatus;
    }

    if ($isBatch) {
        responder(true, "Resultados batch", ['results' => $results], 200);
    } else {
        // Mantener formato original para single request
        $single = $results[0];
        if (!$single['found']) {
             responder(false, 'Pedido no encontrado', null, 404);
        }
        responder(true, "Estado trabajos pedido", [
            'numero_orden' => $single['numero_orden'],
            'has_jobs' => count($single['jobs']) > 0,
            'jobs' => $single['jobs'],
            'observacion_estado' => $single['observacion_estado'],
            'fecha_observacion_estado' => $single['fecha_observacion_estado'],
            'observacion_por' => $single['observacion_por']
        ], 200);
    }

} catch (Throwable $e) {
    error_log("Error en api/pedidos/status.php: " . $e->getMessage());
    responder(false, 'Error interno del servidor.', null, 500);
}
