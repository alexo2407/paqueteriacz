<?php
/**
 * ajax/webhook_logs.php
 *
 * Endpoint DataTables server-side processing para el log de webhooks.
 *
 * Acepta parámetros estándar de DataTables (draw, start, length, search, order)
 * más filtros personalizados pasados en el mismo request:
 *   fecha_desde, fecha_hasta, id_webhook, status
 *
 * También puede devolver el detalle de un log individual:
 *   GET ?action=detail&id=<id>
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/permissions.php';
require_once __DIR__ . '/../modelo/webhook.php';

start_secure_session();
header('Content-Type: application/json; charset=utf-8');

// ── Autenticación ──────────────────────────────────────────────────
if (empty($_SESSION['registrado'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}
if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

// ── Acción: estadísticas para las cards ───────────────────────────
$action = $_GET['action'] ?? '';
if ($action === 'stats') {
    try {
        $db     = (new Conexion())->conectar();
        $where  = ['1=1'];
        $bind   = [];
        $fechaDesde = trim($_GET['fecha_desde'] ?? '');
        $fechaHasta = trim($_GET['fecha_hasta'] ?? '');
        $idWebhook  = (int)($_GET['id_webhook'] ?? 0);
        $status     = trim($_GET['status']      ?? '');

        if ($fechaDesde && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) { $where[] = 'DATE(l.created_at) >= :fd'; $bind[':fd'] = $fechaDesde; }
        if ($fechaHasta && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) { $where[] = 'DATE(l.created_at) <= :fh'; $bind[':fh'] = $fechaHasta; }
        if ($idWebhook > 0)                                                    { $where[] = 'l.id_webhook_cliente = :iw'; $bind[':iw'] = $idWebhook; }
        if (in_array($status, ['ok','error','pending']))                       { $where[] = 'l.status = :st';            $bind[':st'] = $status; }

        $whereSQL = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT
                    SUM(l.status = 'ok')      AS ok,
                    SUM(l.status = 'error')   AS error,
                    SUM(l.status = 'pending') AS pending,
                    COUNT(*)                  AS total
                FROM webhooks_log l
                JOIN webhooks_clientes c ON c.id = l.id_webhook_cliente
                $whereSQL";
        $stmt = $db->prepare($sql);
        $stmt->execute($bind);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── Acción: detalle de un log (modal) ─────────────────────────────
if ($action === 'detail') {

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'ID inválido']);
        exit;
    }
    try {
        $db   = (new Conexion())->conectar();
        $stmt = $db->prepare('
            SELECT l.*, c.nombre AS cliente_nombre
            FROM webhooks_log l
            JOIN webhooks_clientes c ON c.id = l.id_webhook_cliente
            WHERE l.id = :id LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$log) {
            echo json_encode(['error' => 'Log no encontrado']);
            exit;
        }
        echo json_encode(['success' => true, 'log' => $log]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Acción principal: DataTables server-side ───────────────────────
$params = $_GET;    // DataTables puede enviar por GET o POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $params = array_merge($_GET, $_POST);
}

// Filtros personalizados (fuera de los params estándar de DT)
$filtros = [];
$fechaDesde = trim($params['fecha_desde'] ?? '');
$fechaHasta = trim($params['fecha_hasta'] ?? '');
$idWebhook  = (int)($params['id_webhook'] ?? 0);
$status     = trim($params['status']      ?? '');

if ($fechaDesde && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) $filtros['fecha_desde'] = $fechaDesde;
if ($fechaHasta && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) $filtros['fecha_hasta'] = $fechaHasta;
if ($idWebhook > 0)                                                    $filtros['id_webhook']  = $idWebhook;
if (in_array($status, ['ok', 'error', 'pending']))                     $filtros['status']      = $status;

$result = WebhookModel::serverSide($params, $filtros);
echo json_encode($result);
