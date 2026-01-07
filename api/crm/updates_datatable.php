<?php
/**
 * API Endpoint para DataTables Server-Side - Tab Actualizaciones
 * Retorna notificaciones de tipo status_updated agrupadas por lead
 */

// Silenciar output de errores HTML que rompen el JSON
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modelo/crm_notification.php';
require_once __DIR__ . '/../../utils/crm_roles.php';
require_once __DIR__ . '/../../utils/session.php';

// Usar sesión segura
start_secure_session();
$userId = $_SESSION['idUsuario'] ?? 0;

// Validar permisos
if ($userId <= 0) {
    echo json_encode(['error' => 'Usuario no válido']);
    exit;
}

// Parámetros de DataTables
$draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
$start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
$length = isset($_POST['length']) ? (int)$_POST['length'] : 20;
$searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

// Obtener todas las notificaciones de tipo status_updated del usuario
// Usamos un límite alto porque necesitamos agrupar por lead
$allNotifications = CrmNotificationModel::obtenerPorUsuario(
    $userId, 
    false, // no solo no leídas
    5000,  // límite alto para obtener todas
    0,     // offset 0
    $searchValue,
    date('Y-m-d', strtotime('-6 months')), // últimos 6 meses
    date('Y-m-d')
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
    
    // DataTables espera un array de columnas
    // Usamos una sola columna que contiene todo el card
    $data[] = [
        $cardHtml,
        strtotime($firstNotif['created_at']), // Para ordenamiento
        '' // Columna de búsqueda (ya filtrado en server)
    ];
}

// Respuesta en formato DataTables
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $data
]);
