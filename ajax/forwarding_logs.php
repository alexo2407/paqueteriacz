<?php
/**
 * AJAX endpoint para consultar logs de forwarding.
 *
 * GET:  listar logs con filtros opcionales
 *   - id_provider, status, fecha_desde, fecha_hasta
 *
 * POST action=retry: reintentar manualmente un forwarding fallido
 *   - id: ID del log en forwarding_log
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../modelo/forwarding.php';

start_secure_session();
header('Content-Type: application/json');

if (empty($_SESSION['registrado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
if (!in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// ── GET: listar logs ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filtros = [];
    if (!empty($_GET['id_provider']))  $filtros['id_provider']  = $_GET['id_provider'];
    if (!empty($_GET['status']))       $filtros['status']       = $_GET['status'];
    if (!empty($_GET['fecha_desde']))  $filtros['fecha_desde']  = $_GET['fecha_desde'];
    if (!empty($_GET['fecha_hasta']))  $filtros['fecha_hasta']  = $_GET['fecha_hasta'];

    $limit  = isset($_GET['limit'])  ? min((int)$_GET['limit'], 200) : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $logs  = ForwardingModel::obtenerLogs($filtros, $limit, $offset);
    $total = ForwardingModel::contarLogs($filtros);

    echo json_encode([
        'success' => true,
        'data'    => $logs,
        'total'   => $total,
        'limit'   => $limit,
        'offset'  => $offset,
    ]);
    exit;
}

// ── POST: acciones sobre un log ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? $_POST['action'] ?? '';

    // ── Reintento manual ──────────────────────────────────────────────────
    if ($action === 'retry') {
        $logId = (int)($body['id'] ?? 0);
        if ($logId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de log inválido']);
            exit;
        }

        // Buscar directamente por ID en la BD para obtener regla y pedido
        try {
            $db   = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT fl.id_pedido, fl.id_rule
                FROM forwarding_log fl
                WHERE fl.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $logId]);
            $logRow = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al obtener log: ' . $e->getMessage()]);
            exit;
        }

        if (!$logRow) {
            echo json_encode(['success' => false, 'message' => "Log #$logId no encontrado"]);
            exit;
        }

        $idPedido = (int)$logRow['id_pedido'];
        $idRule   = (int)$logRow['id_rule'];

        require_once __DIR__ . '/../services/ForwardingService.php';

        // Reintentar solo la regla específica de este log para evitar duplicidades
        $resultado = ForwardingService::reintentarRegla($idPedido, $idRule);
        $ok = !empty($resultado['success']);

        echo json_encode([
            'success'   => $ok,
            'message'   => $resultado['message'] ?? ($ok ? 'Reenvío exitoso' : 'El reenvío falló. Revisa el nuevo log.'),
            'resultado' => $resultado,
        ]);
        exit;
    }

    // ── Cancelar envío y detener reintentos ────────────────────────────────
    if ($action === 'cancel') {
        $logIds = isset($body['ids']) ? (array)$body['ids'] : [];
        if (empty($logIds) && !empty($body['id'])) {
            $logIds = [(int)$body['id']];
        }

        if (empty($logIds)) {
            echo json_encode(['success' => false, 'message' => 'No se proporcionaron IDs de log válidos']);
            exit;
        }

        $logIds = array_filter(array_map('intval', $logIds));
        if (empty($logIds)) {
            echo json_encode(['success' => false, 'message' => 'IDs de log inválidos']);
            exit;
        }

        try {
            $db = (new Conexion())->conectar();
            $db->beginTransaction();

            // 1. Obtener los IDs de pedido de estos logs
            $placeholders = implode(',', array_fill(0, count($logIds), '?'));
            $stmt = $db->prepare("SELECT DISTINCT id_pedido FROM forwarding_log WHERE id IN ($placeholders)");
            $stmt->execute($logIds);
            $pedidos = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($pedidos)) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'No se encontraron los pedidos asociados']);
                exit;
            }

            // 2. Marcar los logs como cancelados
            $stmt = $db->prepare("
                UPDATE forwarding_log 
                SET status = 'cancelled', 
                    error_message = 'Cancelado manualmente por el usuario. Reintentos automáticos detenidos.' 
                WHERE id IN ($placeholders)
            ");
            $stmt->execute($logIds);

            // 3. Cancelar trabajos de forwarding en cola para cada pedido
            require_once __DIR__ . '/../services/LogisticsQueueService.php';
            foreach ($pedidos as $pedidoId) {
                LogisticsQueueService::cancelarTrabajosForwarding((int)$pedidoId);
            }

            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Envíos marcados como cancelados e intentos en segundo plano detenidos.'
            ]);
            exit;

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            echo json_encode(['success' => false, 'message' => 'Error al cancelar envíos: ' . $e->getMessage()]);
            exit;
        }
    }

    // ── Cancelar TODOS los envíos fallidos/pendientes ────────────────────────
    if ($action === 'cancel_all') {
        $idProvider = isset($body['id_provider']) && (int)$body['id_provider'] > 0 ? (int)$body['id_provider'] : null;

        try {
            $db = (new Conexion())->conectar();
            $db->beginTransaction();

            // 1. Obtener los IDs de pedido de logs fallidos o pendientes
            $sql = "SELECT DISTINCT id_pedido FROM forwarding_log WHERE status IN ('failed', 'pending')";
            $params = [];
            if ($idProvider !== null) {
                $sql .= " AND id_provider = ?";
                $params[] = $idProvider;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $pedidos = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($pedidos)) {
                $db->rollBack();
                echo json_encode(['success' => true, 'message' => 'No hay envíos fallidos o pendientes para cancelar.']);
                exit;
            }

            // 2. Marcar los logs como cancelados
            $updateSql = "UPDATE forwarding_log SET status = 'cancelled', error_message = 'Cancelado de forma masiva por el usuario. Reintentos automáticos detenidos.' WHERE status IN ('failed', 'pending')";
            $updateParams = [];
            if ($idProvider !== null) {
                $updateSql .= " AND id_provider = ?";
                $updateParams[] = $idProvider;
            }
            $stmt = $db->prepare($updateSql);
            $stmt->execute($updateParams);

            // 3. Eliminar de la cola de reintentos
            require_once __DIR__ . '/../services/LogisticsQueueService.php';
            $chunks = array_chunk($pedidos, 200);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $stmt = $db->prepare("
                    DELETE FROM logistics_queue 
                    WHERE pedido_id IN ($placeholders)
                      AND job_type IN ('forwarding_retry', 'forwarding_eval')
                      AND status IN ('pending', 'failed')
                ");
                $stmt->execute($chunk);
            }

            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Todos los envíos fallidos/pendientes (' . count($pedidos) . ') fueron cancelados e intentos detenidos.'
            ]);
            exit;

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            echo json_encode(['success' => false, 'message' => 'Error al cancelar todos los envíos: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Método no soportado']);

// ── Helper ────────────────────────────────────────────────────────────────
function collect_success(array $resultados): bool {
    foreach ($resultados as $r) {
        if (!empty($r['success'])) return true;
    }
    return false;
}
