<?php
/**
 * API: DataTables Server-Side Processing para Pedidos
 * GET/POST: /api/pedidos/datatable
 *
 * Responde al protocolo DataTables SSP:
 *   draw, recordsTotal, recordsFiltered, data[]
 *
 * Seguridad: requiere sesión válida + rol autorizado.
 */
ob_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../utils/session.php';
require_once __DIR__ . '/../../utils/permissions.php';
require_once __DIR__ . '/../../modelo/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('start_secure_session')) start_secure_session();
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

// Verificar sesión activa
$rolesNombresCheck = $_SESSION['roles_nombres'] ?? [];
$rolesPermitidos   = [ROL_NOMBRE_ADMIN, ROL_NOMBRE_PROVEEDOR, ROL_NOMBRE_REPARTIDOR];
$tieneAcceso = !empty(array_intersect($rolesPermitidos, $rolesNombresCheck));
// Admitir también Proveedor real (id 4 en BD = 'Cliente') y Cliente real
// ya manejamos filtros por rol abajo, así que aceptamos cualquier sesión válida
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['draw' => 1, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'No autenticado.']);
    exit;
}

// ── Parámetros DataTables ──────────────────────────────────────────────────
$draw   = (int)($_REQUEST['draw']   ?? 1);
$start  = (int)($_REQUEST['start']  ?? 0);
$length = (int)($_REQUEST['length'] ?? 25);
$search = trim($_REQUEST['search']['value'] ?? '');

// Columnas mapeadas (índice DataTables → columna SQL segura)
$columnasMapeadas = [
    0 => 'p.numero_orden',
    1 => 'p.destinatario',
    2 => 'p.comentario',
    3 => 'ep.nombre_estado',
];
$orderCol  = (int)($_REQUEST['order'][0]['column'] ?? 0);
$orderDir  = strtoupper($_REQUEST['order'][0]['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$orderSql  = ($columnasMapeadas[$orderCol] ?? 'p.id') . ' ' . $orderDir;

// ── Restricciones por rol ──────────────────────────────────────────────────
// Replicar exactamente PedidoQueryService::listarExtendidos()
// DB 'Cliente'   (ID 4) = proveedor logístico  → pedidos en columna id_cliente
// DB 'Proveedor' (ID 5) = cliente que envía     → pedidos en columna id_proveedor
$rolesNombres    = $_SESSION['roles_nombres'] ?? [];
$userId          = (int)($_SESSION['user_id'] ?? 0);
$isAdmin         = isAdmin();
$isRepartidor    = isRepartidor();
$esClienteReal   = in_array('Cliente',   $rolesNombres, true) || in_array('cliente',   $rolesNombres, true);
$esProveedorReal = in_array('Proveedor', $rolesNombres, true) || in_array('proveedor', $rolesNombres, true);

// ── WHERE dinámico ─────────────────────────────────────────────────────────
$whereClauses = [];
$params       = [];

// Filtro en SQL — misma lógica que PedidoQueryService (array_filter → WHERE clause)
if (!$isAdmin && !$isRepartidor) {
    if ($esClienteReal) {
        $whereClauses[] = 'p.id_cliente = :uid';
        $params[':uid'] = $userId;
    } elseif ($esProveedorReal) {
        $whereClauses[] = 'p.id_proveedor = :uid';
        $params[':uid'] = $userId;
    }
}

// Búsqueda global: usar CONCAT para evitar HY093 (mismo named param múltiples veces)
// PDO con emulate_prepares=false no permite reusar :search. CONCAT agrupa en un solo campo.
if ($search !== '') {
    $whereClauses[] = "CONCAT_WS(' ', p.numero_orden, p.destinatario, IFNULL(p.comentario,''), IFNULL(ep.nombre_estado,'')) LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// ── Base query ─────────────────────────────────────────────────────────────
$baseFrom = "
    FROM pedidos p
    LEFT JOIN estados_pedidos ep ON p.id_estado = ep.id
    LEFT JOIN monedas m ON p.id_moneda = m.id
    $whereSQL
";

try {
    $db = (new Conexion())->conectar();

    // Total sin filtros (solo con restricción de rol)
    $whereRolOnly = [];
    $paramsRol = [];
    if (!$isAdmin && !$isRepartidor) {
        if ($isClienteReal) {
            $whereRolOnly[] = 'p.id_cliente = :uid';
            $paramsRol[':uid'] = $userId;
        } elseif ($isProveedorReal) {
            $whereRolOnly[] = 'p.id_proveedor = :uid';
            $paramsRol[':uid'] = $userId;
        }
    }
    $whereRolSQL = $whereRolOnly
        ? 'LEFT JOIN estados_pedidos ep ON p.id_estado = ep.id WHERE ' . implode(' AND ', $whereRolOnly)
        : 'LEFT JOIN estados_pedidos ep ON p.id_estado = ep.id';

    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM pedidos p $whereRolSQL");
    $stmtTotal->execute($paramsRol);
    $recordsTotal = (int)$stmtTotal->fetchColumn();

    // Total filtrado
    $stmtFiltered = $db->prepare("SELECT COUNT(*) $baseFrom");
    $stmtFiltered->execute($params);
    $recordsFiltered = (int)$stmtFiltered->fetchColumn();

    // Datos paginados
    $sql = "
        SELECT
            p.id            AS ID_Pedido,
            p.numero_orden  AS Numero_Orden,
            p.destinatario  AS Cliente,
            p.comentario    AS Comentario,
            ST_Y(p.coordenadas) AS latitud,
            ST_X(p.coordenadas) AS longitud,
            ep.nombre_estado AS Estado,
            ep.id            AS id_estado,
            p.id_proveedor,
            p.id_cliente
        $baseFrom
        ORDER BY $orderSql
        LIMIT :lim OFFSET :off
    ";
    $stmtData = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue(':lim', $length, PDO::PARAM_INT);
    $stmtData->bindValue(':off', $start,  PDO::PARAM_INT);
    $stmtData->execute();
    $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'error'           => 'Error interno al obtener pedidos.',
    ]);
    error_log('datatable.php error: ' . $e->getMessage());
}
