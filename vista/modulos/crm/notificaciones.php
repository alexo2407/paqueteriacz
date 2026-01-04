<?php 
start_secure_session();
if(!isset($_SESSION['registrado'])) { header('location:'.RUTA_URL.'login'); die(); }

require_once __DIR__ . '/../../../controlador/crm.php';
require_once __DIR__ . '/../../../utils/crm_roles.php';

$crmController = new CrmController();
$datos = $crmController->notificaciones(); // Obtiene las últimas 50-100 por defecto
$notificaciones = $datos['notificaciones'];
$unreadCount = $datos['unread_count'];

// Verificar rol
$userId = (int)$_SESSION['idUsuario'];
$esCliente = isUserCliente($userId) && !isUserAdmin($userId);

// --- LÓGICA DE PROCESAMIENTO FRONTEND ---
// 1. Leads Pendientes (Vienen directo del controlador, lista completa de tareas)
$leadsPendientesList = $datos['leads_pendientes'] ?? [];

// 2. Historial y Actualizaciones (Vienen paginados)
$actualizaciones = [];
$groupedByDate = [
    'Hoy' => [],
    'Ayer' => [],
    'Anteriores' => []
];

$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));

foreach ($notificaciones as $notif) {
    // El payload ya viene decodificado por array_walk en el controlador
    
    // Separar Actualizaciones (solo para la tab de Updates)
    if ($notif['type'] !== 'new_lead') {
        $actualizaciones[] = $notif;
    }
    
    // Agrupar TODO para el Historial
    $fechaNotif = date('Y-m-d', strtotime($notif['created_at']));
    if ($fechaNotif === $hoy) {
        $groupedByDate['Hoy'][] = $notif;
    } elseif ($fechaNotif === $ayer) {
        $groupedByDate['Ayer'][] = $notif;
    } else {
        $groupedByDate['Anteriores'][] = $notif;
    }
}
// Recalcular conteo real
$countPendientes = count($leadsPendientesList);

// Extraer paginación
$pagination = $datos['pagination'] ?? [];
$currentPage = $pagination['current_page'] ?? 1;
$totalPages = $pagination['total_pages'] ?? 1;

include("vista/includes/header.php");
?>

<style>
    /* Estilos personalizados para el Inbox CRM */
    .crm-inbox-header {
        background: white;
        border-bottom: 1px solid #e9ecef;
        padding: 1.5rem 0;
        margin-bottom: 2rem;
        margin-top: -1.5rem;
    }
    
    .nav-pills .nav-link {
        color: #495057;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
    .nav-pills .nav-link.active {
        background-color: #0d6efd;
        color: white;
        font-weight: 600;
    }
    
    .notif-card {
        transition: all 0.2s ease;
        border: 1px solid #e9ecef;
        border-left: 4px solid transparent; /* Indicador de tipo */
        border-radius: 8px;
        background: white;
        margin-bottom: 0.75rem;
    }
    .notif-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.03);
        border-color: #dee2e6;
    }
    .notif-card.unread {
        background-color: #f8fbff;
        border-left-color: #0d6efd;
    }
    .notif-card.type-lead { border-left-color: #198754; }
    .notif-card.type-update { border-left-color: #0dcaf0; }
    
    .timeline-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #adb5bd;
        text-transform: uppercase;
        margin: 1rem 0 0.5rem;
        padding-left: 0.5rem;
    }
    
    .notif-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    .bg-soft-primary { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
    .bg-soft-success { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
    .bg-soft-info { background-color: rgba(13, 202, 240, 0.1); color: #0dcaf0; }
</style>

<!-- Header de CRM -->
<div class="container-fluid crm-inbox-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <?php if($esCliente): ?>
                        <i class="bi bi-briefcase me-2 text-primary"></i>Mis Leads
                    <?php else: ?>
                        <i class="bi bi-inbox me-2 text-primary"></i>Centro de Notificaciones
                    <?php endif; ?>
                </h4>
                <p class="mb-0 text-muted small">
                    Gestiona tus leads y mantente al día con las actualizaciones.
                    <?php if($unreadCount > 0): ?>
                        <span class="badge bg-primary rounded-pill ms-1"><?= $unreadCount ?> sin leer</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                 <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
                <button class="btn btn-outline-primary btn-sm" onclick="marcarTodasLeidas()">
                    <i class="bi bi-check-all me-1"></i> Marcar todo leído
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">
    
    <div class="row">
        <div class="col-lg-3 mb-4">
             <!-- Filtros Verticales -->
            <div class="list-group list-group-flush border rounded shadow-sm">
                <!-- Tab: Pendientes (Prioridad) -->
                <a class="list-group-item list-group-item-action fw-bold active d-flex justify-content-between align-items-center" id="pills-leads-tab" data-bs-toggle="pill" href="#pills-leads">
                    <span><i class="bi bi-star-fill text-warning me-2"></i> Por Atender</span>
                    <?php if($countPendientes > 0): ?><span class="badge bg-danger rounded-pill"><?= $countPendientes ?></span><?php endif; ?>
                </a>
                
                <!-- Tab: Actualizaciones -->
                 <a class="list-group-item list-group-item-action" id="pills-updates-tab" data-bs-toggle="pill" href="#pills-updates">
                    <i class="bi bi-arrow-repeat me-2"></i> Actualizaciones
                </a>

                <!-- Tab: Historial -->
                <a class="list-group-item list-group-item-action" id="pills-all-tab" data-bs-toggle="pill" href="#pills-all">
                    <i class="bi bi-archive me-2"></i> Historial Completo
                </a>
            </div>
        
            <!-- Info Paginación -->
            <div class="mt-3 text-center text-muted small">
                Mostrando pág. <?= $currentPage ?> de <?= $totalPages ?>
                <br>
                (Total: <?= $pagination['total_items'] ?? 0 ?>)
            </div>
        </div>
        
        <div class="col-lg-9">
            <!-- Contenido -->
            <div class="tab-content" id="pills-tabContent">
                
                <!-- Tab: PENDIENTES (Default Active) -->
                <div class="tab-pane fade show active" id="pills-leads" role="tabpanel">
                    <?php 
                    if (empty($leadsPendientesList)) {
                         echo '<div class="text-center py-5 border rounded bg-light">
                                <i class="bi bi-check-circle display-1 text-success opacity-50"></i>
                                <h5 class="mt-3 text-success">¡Todo al día!</h5>
                                <p class="text-muted">No tienes leads nuevos pendientes de atender.</p>
                               </div>';
                    } else {
                        echo '<div class="row">';
                        foreach ($leadsPendientesList as $notif) { renderNotificationCard($notif); }
                        echo '</div>';
                    }
                    ?>
                </div>

                <!-- Tab: ACTUALIZACIONES -->
                <div class="tab-pane fade" id="pills-updates" role="tabpanel">
                    <?php 
                    if (empty($actualizaciones)) {
                        echo '<div class="alert alert-light text-center">No hay actualizaciones recientes.</div>';
                    } else {
                        echo '<div class="row">';
                        foreach ($actualizaciones as $notif) { renderNotificationCard($notif); }
                        echo '</div>';
                    }
                    ?>
                </div>
                
                <!-- Tab: HISTORIAL -->
                <div class="tab-pane fade" id="pills-all" role="tabpanel">
                    <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i> Aquí se muestra todo el historial, incluyendo lo ya atendido.</div>
                    <?php 
                    if (empty($notificaciones)) {
                        echo '<div class="text-center py-5 text-muted"><p>No hay historial disponible.</p></div>';
                    } else {
                        foreach ($groupedByDate as $label => $group): 
                            if(empty($group)) continue;
                    ?>
                        <div class="timeline-label"><?= $label ?></div>
                        <div class="row">
                            <?php foreach($group as $notif) { renderNotificationCard($notif); } ?>
                        </div>
                    <?php endforeach; } ?>
                </div>

            </div>
            
            <!-- Paginador -->
            <?php if($totalPages > 1): ?>
            <nav class="mt-4 border-top pt-3">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>">
                            <i class="bi bi-chevron-left"></i> Anterior
                        </a>
                    </li>
                    
                    <?php for($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                        <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>">
                            Siguiente <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php 
// FUNCIÓN HELPER PARA RENDERIZAR TARJETAS
function renderNotificationCard($notif) {
    // Asegurar payload (ya decodificado arriba, pero por si acaso)
    $payload = is_array($notif['payload']) ? $notif['payload'] : json_decode($notif['payload'], true);
    
    $isRead = $notif['is_read'];
    $unreadClass = $isRead ? '' : 'unread shadow-sm';
    $time = date('H:i', strtotime($notif['created_at']));
    
    // Datos Vivos del Lead (si existen)
    $leadStatusLive = $notif['lead_status_live'] ?? null;
    $leadPhoneLive = $notif['lead_phone_live'] ?? ($payload['telefono'] ?? '');
    
    // Si el estado es distinto a los de "espera", se considera atendido
    $yaAtendido = ($leadStatusLive && !in_array($leadStatusLive, ['nuevo', 'NUEVO', 'EN_ESPERA']));
    
    // Configuración según tipo
    if ($notif['type'] === 'new_lead') {
        $icon = '<i class="bi bi-person-plus-fill"></i>';
        $title = $payload['nombre'] ?? 'Nuevo Lead';
        $leadId = $payload['lead_id'] ?? 0;
        
        // Si ya fue atendido, cambiar visualmente
        if ($yaAtendido) {
            $iconClass = 'bg-light text-muted border';
            $typeClass = ''; // Sin borde de color
            $subtitle = "<span class='badge bg-light text-dark border'>Atendido: $leadStatusLive</span> <span class='text-muted small ms-1'>Lead #$leadId</span>";
        } else {
            $iconClass = 'bg-soft-success';
            $typeClass = 'type-lead';
            $subtitle = isset($payload['producto']) ? "Interesado en: <b>{$payload['producto']}</b>" : 'Nuevo cliente potencial asignado';
        }
        
    } else {
        $icon = '<i class="bi bi-arrow-repeat"></i>';
        $iconClass = 'bg-soft-info';
        $typeClass = 'type-update';
        $title = "Actualización Lead #{$notif['related_lead_id']}";
        $estadoAnterior = $payload['estado_anterior'] ?? '?';
        $estadoNuevo = $payload['estado_nuevo'] ?? '?';
        $subtitle = "Cambio de estado: <span class='badge bg-secondary'>$estadoAnterior</span> <i class='bi bi-arrow-right small'></i> <span class='badge bg-primary'>$estadoNuevo</span>";
        
        $leadId = $notif['related_lead_id'] ?? 0;
        // Para actualizaciones, usamos el estado nuevo como referencia
        $yaAtendido = true; // No requiere acción inmediata de "atender"
    }
?>
    <div class="col-12 col-lg-6">
        <div class="card notif-card <?= $unreadClass ?> <?= $typeClass ?> p-3" id="notif-<?= $notif['id'] ?>">
            <div class="d-flex align-items-start">
                <!-- Icono -->
                <div class="notif-icon <?= $iconClass ?> flex-shrink-0 me-3">
                    <?= $icon ?>
                </div>
                
                <!-- Contenido -->
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <h6 class="mb-1 fw-bold text-dark"><?= $title ?></h6>
                        <small class="text-muted ms-2"><?= $time ?></small>
                    </div>
                    <p class="mb-2 text-muted small"><?= $subtitle ?></p>
                    
                    <!-- Botones de Acción -->
                    <div class="d-flex gap-2 mt-2 flex-wrap">
                        <a href="<?= RUTA_URL ?>crm/ver/<?= $leadId ?>" class="btn btn-sm btn-light border" onclick="markAsRead(<?= $notif['id'] ?>, false)">
                            <i class="bi bi-eye"></i> Ver
                        </a>
                        
                        <?php if($notif['type'] === 'new_lead' && !$yaAtendido): ?>
                            <!-- Botones de Acción Rápida para Nuevos Leads -->
                            <?php if(!empty($leadPhoneLive)): ?>
                                <a href="https://wa.me/52<?= preg_replace('/[^0-9]/', '', $leadPhoneLive) ?>" target="_blank" class="btn btn-sm btn-success text-white" onclick="markAsRead(<?= $notif['id'] ?>, false)">
                                    <i class="bi bi-whatsapp"></i> Chat
                                </a>
                                <a href="tel:<?= $leadPhoneLive ?>" class="btn btn-sm btn-primary" onclick="markAsRead(<?= $notif['id'] ?>, false)">
                                    <i class="bi bi-telephone"></i>
                                </a>
                            <?php endif; ?>
                            
                            <!-- Botón Mágico: Avanzar estado -->
                            <button class="btn btn-sm btn-outline-success" onclick="quickStatusChange(<?= $leadId ?>, 'APROBADO', <?= $notif['id'] ?>)">
                                <i class="bi bi-check-circle"></i> Aprobar / Interesado
                            </button>
                        <?php endif; ?>
                        
                        <?php if(!$isRead): ?>
                            <button class="btn btn-sm btn-outline-secondary ms-auto" onclick="markAsRead(<?= $notif['id'] ?>, true)" title="Marcar como leída">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
}
?>

<script>
// Función para marcar como leída
function markAsRead(id, hideElement) {
    fetch('<?= RUTA_URL ?>api/crm/notifications/' + id + '/read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && hideElement) {
            const card = document.getElementById('notif-' + id);
            if(card) {
                card.classList.remove('unread', 'shadow-sm', 'type-lead', 'type-update'); // Quitar estilos de nuevo
                card.style.opacity = '0.6';
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Función para cambio rápido de estado
function quickStatusChange(leadId, nuevoEstado, notifId) {
    Swal.fire({
        title: '¿Confirmar contacto?',
        text: "El lead se marcará como 'Contactado' y la notificación como leída.",
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Sí, confirmar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Obtener token JWT (asumiendo que está en localStorage o cookie, si no, se requerirá auth header)
            // NOTA: Si usas sesión PHP normal para API, esto funciona. Si usas JWT estricto, necesitas el token.
            // Para este caso, asumimos sesión PHP o inyección de token.
            
            // Simular token para ejemplo (ajustar según tu auth.js real)
            const token = localStorage.getItem('jwt_token') || ''; 

            fetch('<?= RUTA_URL ?>api/crm/leads/' + leadId + '/estado', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({
                    estado: nuevoEstado,
                    observaciones: 'Contacto rápido desde notificaciones'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Actualizado!',
                        text: 'Lead marcado como contactado',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    // Marcar notificación como leída y actualizar UI
                    markAsRead(notifId, true);
                    // Actualizar tarjeta visualmente para reflejar que ya fue atendido
                    const card = document.getElementById('notif-' + notifId);
                    if(card) {
                        const subtitle = card.querySelector('p.text-muted');
                        if(subtitle) subtitle.innerHTML = "<span class='badge bg-light text-dark border'>Atendido: contactado</span>";
                        // Quitar botones de acción rápida
                        const statusBtn = card.querySelector('button[onclick*="quickStatusChange"]');
                        if(statusBtn) statusBtn.remove();
                    }
                } else {
                    Swal.fire('Error', data.message || 'No se pudo actualizar', 'error');
                }
            })
            .catch(error => {
                console.error(error);
                Swal.fire('Error', 'Hubo un problema de conexión', 'error');
            });
        }
    })
}

function marcarTodasLeidas() {
    Swal.fire({
        title: '¿Marcar todo como leído?',
        text: "Todas las notificaciones se marcarán como leídas.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, marcar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('<?= RUTA_URL ?>api/crm/notifications/read-all', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        '¡Listo!',
                        'Tus notificaciones han sido actualizadas.',
                        'success'
                    ).then(() => {
                        window.location.reload();
                    });
                }
            });
        }
    })
}
</script>

<?php include("vista/includes/footer.php"); ?>
