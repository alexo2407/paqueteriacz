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

        // Obtener el log original para conocer pedido y regla
        $logs = ForwardingModel::obtenerLogs(['id' => $logId], 1, 0);

        // Buscar directamente por ID en la BD
        try {
            $db   = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT fl.*, r.id AS rule_id, r.config_override,
                       p.nombre AS provider_nombre, p.slug, p.base_url,
                       p.auth_endpoint, p.order_endpoint, p.auth_method,
                       p.credentials, p.default_config AS provider_config
                FROM forwarding_log fl
                INNER JOIN forwarding_rules r   ON r.id = fl.id_rule
                INNER JOIN forwarding_providers p ON p.id = fl.id_provider
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
            echo json_encode(['success' => false, 'message' => "Log #$logId no encontrado o sin regla asociada"]);
            exit;
        }

        $idPedido = (int)$logRow['id_pedido'];

        require_once __DIR__ . '/../services/ForwardingService.php';

        // Usamos la misma lógica: evaluarYReenviar con el cliente del pedido
        $pedido = ForwardingModel::obtenerPedidoParaForwarding($idPedido);
        if (!$pedido) {
            echo json_encode(['success' => false, 'message' => "Pedido #$idPedido no encontrado"]);
            exit;
        }

        $resultados = ForwardingService::evaluarYReenviar($idPedido, (int)$pedido['id_cliente']);

        if (empty($resultados)) {
            echo json_encode(['success' => false, 'message' => 'No se encontraron reglas activas para este pedido/cliente']);
            exit;
        }

        $ok = collect_success($resultados);
        echo json_encode([
            'success'    => $ok,
            'message'    => $ok ? 'Reenvío exitoso' : 'El reenvío falló. Revisa el nuevo log.',
            'resultados' => $resultados,
        ]);
        exit;
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
