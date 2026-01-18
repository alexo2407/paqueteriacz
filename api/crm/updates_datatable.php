<?php
/**
 * API Endpoint para DataTables Server-Side - Tab Actualizaciones
 * Retorna notificaciones de tipo status_updated agrupadas por lead
 */

// Silenciar output de errores HTML que rompen el JSON
ini_set('display_errors', 0);
error_reporting(0);

// Limpiar cualquier output buffer previo
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modelo/crm_notification.php';
require_once __DIR__ . '/../../utils/crm_roles.php';
require_once __DIR__ . '/../../utils/session.php';

// Usar sesión segura
start_secure_session();

// Fallback para diferentes claves de sesión
$userId = $_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? $_SESSION['id'] ?? 0;

// Verificar también si el usuario está registrado
$isLoggedIn = !empty($_SESSION['registrado']);

// Validar permisos
if ($userId <= 0 || !$isLoggedIn) {
    echo json_encode([
        'error' => 'No autorizado',
        'message' => 'Sesión no válida. Por favor, inicia sesión nuevamente.'
    ]);
    exit;
}

try {

// Parámetros de DataTables
$draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
$start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
$length = isset($_POST['length']) ? (int)$_POST['length'] : 20;
$searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

// Parámetros de Filtros (Fecha y Cliente)
$startDate = $_POST['start_date'] ?? $_REQUEST['start_date'] ?? null;
$endDate = $_POST['end_date'] ?? $_REQUEST['end_date'] ?? null;
$clientId = $_POST['client_id'] ?? $_REQUEST['client_id'] ?? null;

// Validar filtros vacíos
if (empty($startDate) || $startDate == 'null') $startDate = null;
if (empty($endDate) || $endDate == 'null') $endDate = null;
if (empty($clientId) || $clientId == 'null' || $clientId == '') $clientId = null;

// Obtener todas las notificaciones de tipo status_updated del usuario
// Usamos un límite alto porque necesitamos agrupar por lead
$allNotifications = CrmNotificationModel::obtenerPorUsuario(
    $userId, 
    false, // no solo no leídas
    5000,  // límite alto para obtener todas
    0,     // offset 0
    $searchValue,
    $startDate,
    $endDate,
    null, // leadStatus
    $clientId
);

// Filtrar solo actualizaciones (no new_lead)
$statusUpdates = array_filter($allNotifications, function($notif) {
    return $notif['type'] !== 'new_lead';
});

// Agrupar por lead_id
$groupedByLead = [];
foreach ($statusUpdates as $notif) {
    $leadId = $notif['related_lead_id'] ?? 0;
    if (!isset($groupedByLead[$leadId])) {
        $groupedByLead[$leadId] = [];
    }
    $groupedByLead[$leadId][] = $notif;
}

// Ordenar cada grupo por fecha DESC y convertir a array plano
$updates = [];
foreach ($groupedByLead as $leadId => $notifs) {
    usort($notifs, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $updates[] = $notifs;
}

// Ordenar grupos por la fecha más reciente de cada grupo
usort($updates, function($a, $b) {
    $dateA = strtotime($a[0]['created_at']);
    $dateB = strtotime($b[0]['created_at']);
    return $dateB - $dateA;
});

// Total de registros
$totalRecords = count($updates);
$filteredRecords = $totalRecords; // Ya aplicamos búsqueda en la query

// Aplicar paginación
$paginatedUpdates = array_slice($updates, $start, $length);

// Generar HTML para cada card
$data = [];
foreach ($paginatedUpdates as $notifs) {
    $firstNotif = $notifs[0];
    
    // Decodificar payload
    $payload = is_array($firstNotif['payload']) 
        ? $firstNotif['payload'] 
        : json_decode($firstNotif['payload'], true);
    
    $isRead = $firstNotif['is_read'];
    $unreadClass = $isRead ? '' : 'unread shadow-sm';
    $time = date('H:i', strtotime($firstNotif['created_at']));
    $leadStatusLive = $firstNotif['lead_status_live'] ?? null;
    $leadId = $firstNotif['related_lead_id'] ?? 0;
    
    // Mapa de colores para estados
    $colores = [
        'EN_ESPERA' => 'bg-warning text-dark',
        'nuevo'     => 'bg-warning text-dark',
        'APROBADO'  => 'bg-success',
        'CONFIRMADO'=> 'bg-primary',
        'EN_TRANSITO'=> 'bg-info text-dark',
        'EN_BODEGA' => 'bg-secondary',
        'CANCELADO' => 'bg-danger'
    ];
    
    // Configuración para actualizaciones
    $icon = '<i class="bi bi-arrow-repeat"></i>';
    $iconClass = 'bg-soft-info';
    $typeClass = 'type-update';
    $title = "Actualización Lead #{$leadId}";
    
    // Si hay múltiples cambios, mostrar secuencia
    if (count($notifs) > 1) {
        $estados = [];
        foreach ($notifs as $n) {
            $p = is_array($n['payload']) ? $n['payload'] : json_decode($n['payload'], true);
            $estados[] = $p['estado_nuevo'] ?? '?';
        }
        // Agregar primer estado (estado_anterior del primer cambio)
        $firstPayload = is_array($notifs[count($notifs)-1]['payload']) 
            ? $notifs[count($notifs)-1]['payload'] 
            : json_decode($notifs[count($notifs)-1]['payload'], true);
        array_unshift($estados, $firstPayload['estado_anterior'] ?? '?');
        
        // Renderizar secuencia con badges
        $secuencia = [];
        foreach ($estados as $estado) {
            $badgeClass = $colores[$estado] ?? 'bg-secondary';
            $secuencia[] = "<span class='badge {$badgeClass}'>{$estado}</span>";
        }
        $subtitle = implode(" <i class='bi bi-arrow-right small mx-1'></i> ", $secuencia);
        $subtitle .= " <span class='badge bg-light text-dark ms-2'>" . count($notifs) . " cambios</span>";
    } else {
        // Un solo cambio
        $estadoAnterior = $payload['estado_anterior'] ?? '?';
        $estadoNuevo = $payload['estado_nuevo'] ?? '?';
        $badgeAnt = $colores[$estadoAnterior] ?? 'bg-secondary';
        $badgeNue = $colores[$estadoNuevo] ?? 'bg-primary';
        $subtitle = "<span class='badge {$badgeAnt}'>{$estadoAnterior}</span> <i class='bi bi-arrow-right small'></i> <span class='badge {$badgeNue}'>{$estadoNuevo}</span>";
    }
    
    // Generar HTML del card
    $cardHtml = "
        <div class='card notif-card {$unreadClass} {$typeClass} p-3 h-100' id='notif-card-{$firstNotif['id']}'>
            <div class='d-flex align-items-start'>
                <div class='notif-icon {$iconClass} flex-shrink-0 me-3'>
                    {$icon}
                </div>
                <div class='flex-grow-1'>
                    <div class='d-flex justify-content-between align-items-start'>
                        <h6 class='mb-1 fw-bold text-dark'>{$title}</h6>
                        <small class='text-muted ms-2'>{$time}</small>
                    </div>
                    <p class='mb-2 text-muted small'>{$subtitle}</p>
                    <a href='" . RUTA_URL . "crm/ver/{$leadId}' class='btn btn-sm btn-light border'>Ver Detalles</a>
                </div>
            </div>
        </div>
    ";
    
    
    // Generar texto de búsqueda para DataTables
    // Incluir: lead_id, nombre del lead, teléfono, estados, producto
    $searchElements = [];
    
    // Lead ID en múltiples formatos
    $searchElements[] = 'lead ' . $leadId;
    $searchElements[] = '#' . $leadId;
    $searchElements[] = (string)$leadId;
    
    // Estados de todos los cambios del grupo
    foreach ($notifs as $n) {
        $p = is_array($n['payload']) ? $n['payload'] : json_decode($n['payload'], true);
        if (isset($p['estado_anterior'])) $searchElements[] = $p['estado_anterior'];
        if (isset($p['estado_nuevo'])) $searchElements[] = $p['estado_nuevo'];
        if (isset($p['nombre'])) $searchElements[] = $p['nombre'];
        if (isset($p['telefono'])) $searchElements[] = $p['telefono'];
        if (isset($p['producto'])) $searchElements[] = $p['producto'];
    }
    
    // Estado actual del lead
    if ($leadStatusLive) {
        $searchElements[] = $leadStatusLive;
    }
    
    $searchText = strtolower(implode(' ', array_filter($searchElements)));
    
    // DataTables espera un array de columnas
    // Usamos una sola columna que contiene todo el card
    $data[] = [
        $cardHtml,
        strtotime($firstNotif['created_at']), // Para ordenamiento
        $searchText // Columna de búsqueda
    ];
}

    // Respuesta en formato DataTables
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data
    ]);

} catch (Exception $e) {
    error_log("Error in updates_datatable.php: " . $e->getMessage());
    echo json_encode([
        'draw' => $draw ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Error interno: ' . $e->getMessage()
    ]);
}

