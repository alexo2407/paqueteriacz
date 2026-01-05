<?php 
start_secure_session();
if(!isset($_SESSION['registrado'])) { header('location:'.RUTA_URL.'login'); die(); }

require_once __DIR__ . '/../../../controlador/crm.php';
require_once __DIR__ . '/../../../utils/crm_roles.php';

// Obtener datos del controlador
$crmController = new CrmController();
$datos = $crmController->notificaciones(); 
$notificaciones = $datos['notificaciones'];
$unreadCount = $datos['unread_count'];

// helpers/roles.php ya cargado en index
$userId = $_SESSION['user_id'] ?? 0;

// Validar Permisos
if ($userId <= 0) {
    header("Location: ".RUTA_URL."login");
    exit;
}

// Permitir Clientes, Proveedores y Admins
$esCliente = isUserCliente($userId) && !isUserAdmin($userId);
$esProveedor = isUserProveedor($userId) && !isUserAdmin($userId);

// Validar que el usuario tenga al menos uno de estos roles
if (!$esCliente && !$esProveedor && !isUserAdmin($userId)) {
    header("Location: ".RUTA_URL."acceso-denegado");
    exit;
}

// =========================================================================
// FUNCIÓN HELPER DE RENDERIZADO (MOVIDA AL INICIO PARA USO AJAX Y NORMAL)
// =========================================================================
function renderNotificationCard($notifs) {
    // $notifs puede ser un array de notificaciones agrupadas o una sola notificación
    $notifs = is_array($notifs) && isset($notifs[0]) ? $notifs : [$notifs];
    $firstNotif = $notifs[0];
    
    // Asegurar payload
    $payload = is_array($firstNotif['payload']) ? $firstNotif['payload'] : json_decode($firstNotif['payload'], true);
    
    $isRead = $firstNotif['is_read'];
    $unreadClass = $isRead ? '' : 'unread shadow-sm';
    $time = date('H:i', strtotime($firstNotif['created_at']));
    
    // Datos Vivos
    $leadStatusLive = $firstNotif['lead_status_live'] ?? null;
    
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
    
    // Configuración según tipo
    if ($firstNotif['type'] === 'new_lead') {
        $icon = '<i class="bi bi-person-plus-fill"></i>';
        $title = $payload['nombre'] ?? 'Nuevo Lead';
        $leadId = $payload['lead_id'] ?? 0;
        $iconClass = 'bg-soft-success';
        $typeClass = 'type-lead';
        
        $badgeClass = $colores[$leadStatusLive] ?? 'bg-light text-dark border';
        $estadoBadge = $leadStatusLive ? "<span class='badge {$badgeClass} me-1'>{$leadStatusLive}</span>" : "";
        
        $prodInfo = isset($payload['producto']) ? "Interesado en: <b>{$payload['producto']}</b>" : 'Nuevo cliente asignado';
        $subtitle = $estadoBadge . " " . $prodInfo;
        
    } else {
        // Actualizaciones: Mostrar secuencia de cambios
        $icon = '<i class="bi bi-arrow-repeat"></i>';
        $iconClass = 'bg-soft-info';
        $typeClass = 'type-update';
        $leadId = $firstNotif['related_lead_id'] ?? 0;
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
    }
?>
    <div class="col-12">
        <div class="card notif-card <?= $unreadClass ?> <?= $typeClass ?> p-3 h-100" id="notif-card-<?= $firstNotif['id'] ?>">
            <div class="d-flex align-items-start">
                <div class="notif-icon <?= $iconClass ?> flex-shrink-0 me-3">
                    <?= $icon ?>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <h6 class="mb-1 fw-bold text-dark"><?= $title ?></h6>
                        <small class="text-muted ms-2"><?= $time ?></small>
                    </div>
                    <p class="mb-2 text-muted small"><?= $subtitle ?></p>
                    <a href="<?= RUTA_URL ?>crm/ver/<?= $leadId ?>" class="btn btn-sm btn-light border">Ver Detalles</a>
                </div>
            </div>
        </div>
    </div>
<?php
}

// --- LÓGICA AJAX: Si se pide búsqueda, devolver solo HTML y salir ---
if (isset($_GET['ajax_search'])) {
    // Limpiar buffers previos si hubiera
    while (ob_get_level()) ob_end_clean();
    
    // Procesar agrupación para el renderizado parcial
    $groupedAjax = [
        'Hoy' => [], 'Ayer' => [], 'Anteriores' => []
    ];
    $hoy = date('Y-m-d');
    $ayer = date('Y-m-d', strtotime('-1 day'));

    foreach ($notificaciones as $notif) {
        $fechaNotif = date('Y-m-d', strtotime($notif['created_at']));
        if ($fechaNotif === $hoy) $groupedAjax['Hoy'][] = $notif;
        elseif ($fechaNotif === $ayer) $groupedAjax['Ayer'][] = $notif;
        else $groupedAjax['Anteriores'][] = $notif;
    }

    if (empty($notificaciones)) {
        $q = htmlspecialchars($_GET['q'] ?? '');
        echo "<div class='text-center py-5 text-muted'>
                <i class='bi bi-search display-4 opacity-25'></i>
                <p class='mt-3'>No se encontraron resultados para <strong>'$q'</strong> en tu historial.</p>
                <div class='mt-3'>
                    <a href='" . RUTA_URL . "crm/listar?search=" . urlencode($_GET['q'] ?? '') . "' class='btn btn-outline-primary btn-sm'>
                        <i class='bi bi-search'></i> Buscar en todos los Leads
                    </a>
                </div>
              </div>";
    } else {
        foreach ($groupedAjax as $label => $group) {
            if(empty($group)) continue;
            echo "<div class='timeline-label'>$label</div><div class='row'>";
            foreach($group as $notif) renderNotificationCard($notif);
            echo "</div>";
        }
    }
    exit; // DETENER EJECUCIÓN (No renderizar el resto de la página)
}

// --- LÓGICA DE PROCESAMIENTO FRONTEND (NORMAL) ---
// 1. Leads Pendientes (Vienen directo del controlador, lista completa de tareas)
$leadsPendientesList = $datos['leads_pendientes'] ?? [];

// 2. Historial y Actualizaciones (Vienen paginados)
// NUEVA LÓGICA: Agrupar actualizaciones por lead_id
$actualizacionesPorLead = [];
$groupedByDate = [
    'Hoy' => [],
    'Ayer' => [],
    'Anteriores' => []
];

$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));

foreach ($notificaciones as $notif) {
    // Agrupar Actualizaciones (solo para la tab de Updates)
    if ($notif['type'] !== 'new_lead') {
        $leadId = $notif['related_lead_id'] ?? 0;
        if (!isset($actualizacionesPorLead[$leadId])) {
            $actualizacionesPorLead[$leadId] = [];
        }
        $actualizacionesPorLead[$leadId][] = $notif;
    }
    
    // Agrupar TODO para el Historial (sin agrupar por lead aquí)
    $fechaNotif = date('Y-m-d', strtotime($notif['created_at']));
    if ($fechaNotif === $hoy) {
        $groupedByDate['Hoy'][] = $notif;
    } elseif ($fechaNotif === $ayer) {
        $groupedByDate['Ayer'][] = $notif;
    } else {
        $groupedByDate['Anteriores'][] = $notif;
    }
}

// Convertir agrupaciones de leads en array plano para tab Actualizaciones
$actualizaciones = [];
foreach ($actualizacionesPorLead as $leadId => $notifs) {
    // Ordenar por fecha DESC (más reciente primero)
    usort($notifs, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    // Guardar el array completo de notificaciones del lead
    $actualizaciones[] = $notifs;
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
    
    /* Estilos para cards de notificación */
    .notif-card {
        transition: all 0.2s;
        border: 1px solid #e9ecef;
        border-left: 4px solid transparent; /* Indicador de tipo */
        border-radius: 8px;
        background: white;
        margin-bottom: 0.75rem;
    }
    .notif-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
        border-color: #dee2e6; /* Keep original hover border color */
    }
    
    .notif-card.unread { background-color: #f8f9fa; border-left-color: #0d6efd; }
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
    /* DATATABLES GRID HACK: Transformar Tabla en Grid */
    #tablaHistorial thead { display: none; }
    #tablaHistorial tbody { display: flex; flex-wrap: wrap; margin-right: -15px; margin-left: -15px; }
    #tablaHistorial tr { width: 50%; padding: 0 15px; box-sizing: border-box; display: block; }
    #tablaHistorial td { display: block; width: 100%; padding: 0; border: none !important; }
    
    @media (max-width: 992px) {
        #tablaHistorial tr { width: 100%; }
    }
    
    /* Ajustes paginación DataTables */
    .dataTables_wrapper .dataTables_paginate { margin-top: 1rem; display: flex; justify-content: center; }
    .dataTables_wrapper .dataTables_filter { display: none !important; } /* Ocultar búsqueda por defecto */
    
    /* Estilo del campo de búsqueda personalizado */
    #customSearch {
        border: 1px solid #dee2e6;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    #customSearch:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    .input-group-text {
        border: 1px solid #dee2e6;
    }

</style>

<!-- Header de CRM -->
<div class="container-fluid crm-inbox-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <?php if($esProveedor): ?>
                        <i class="bi bi-graph-up me-2 text-primary"></i>Dashboard de Leads
                    <?php elseif($esCliente): ?>
                        <i class="bi bi-briefcase me-2 text-primary"></i>Mis Leads
                    <?php else: ?>
                        <i class="bi bi-inbox me-2 text-primary"></i>Centro de Notificaciones
                    <?php endif; ?>
                </h4>
                <p class="mb-0 text-muted small">
                    <?php if($esProveedor): ?>
                        Métricas y actualizaciones de tus leads enviados.
                    <?php else: ?>
                        Gestiona tus leads y mantente al día con las actualizaciones.
                    <?php endif; ?>
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

<!-- Dashboard de Métricas para Proveedores -->
<?php if ($esProveedor): ?>
<div class="container mt-4 mb-4">
    <?php
    // IMPORTANTE: Redeclarar userId porque estamos en un nuevo bloque PHP
    $userId = $_SESSION['user_id'] ?? 0;
    
    // Obtener métricas del proveedor
    require_once __DIR__ . '/../../../modelo/crm_lead.php';
    $metricas = CrmLeadModel::obtenerMetricasProveedor($userId);
    
    $totalLeads = $metricas['total'] ?? 0;
    $procesados = $metricas['procesados'] ?? 0;
    $enEspera = $metricas['en_espera'] ?? 0;
    $porcentajeProcesado = $totalLeads > 0 ? round(($procesados / $totalLeads) * 100) : 0;
    ?>
    
    <div class="row">
        <!-- Card: Total Leads -->
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-primary bg-opacity-10 p-3 rounded">
                            <i class="bi bi-send-fill text-primary fs-3"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total Enviados</h6>
                            <h2 class="mb-0 fw-bold"><?= number_format($totalLeads) ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card: Procesados -->
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-success bg-opacity-10 p-3 rounded">
                            <i class="bi bi-check-circle-fill text-success fs-3"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Procesados</h6>
                            <h2 class="mb-0 fw-bold"><?= number_format($procesados) ?></h2>
                            <small class="text-success">
                                <i class="bi bi-arrow-up"></i> <?= $porcentajeProcesado ?>%
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card: En Espera -->
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-warning bg-opacity-10 p-3 rounded">
                            <i class="bi bi-clock-fill text-warning fs-3"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">En Espera</h6>
                            <h2 class="mb-0 fw-bold"><?= number_format($enEspera) ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de Distribución por Estado -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="bi bi-pie-chart-fill me-2"></i>Distribución por Estado
                    </h5>
                    <div class="row">
                        <?php foreach ($metricas['por_estado'] ?? [] as $estado => $cantidad): 
                            $colores = [
                                'EN_ESPERA' => 'warning',
                                'nuevo' => 'warning',
                                'APROBADO' => 'success',
                                'CONFIRMADO' => 'primary',
                                'EN_TRANSITO' => 'info',
                                'EN_BODEGA' => 'secondary',
                                'CANCELADO' => 'danger'
                            ];
                            $color = $colores[$estado] ?? 'secondary';
                            $porcentaje = $totalLeads > 0 ? round(($cantidad / $totalLeads) * 100) : 0;
                        ?>
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <small class="text-muted"><?= $estado ?></small>
                                    <div class="progress mt-1" style="height: 8px;">
                                        <div class="progress-bar bg-<?= $color ?>" 
                                             style="width: <?= $porcentaje ?>%"></div>
                                    </div>
                                </div>
                                <div class="ms-2">
                                    <span class="badge bg-<?= $color ?>"><?= $cantidad ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="container mb-5">
    
    <?php
    // Determinar Tab Activa (Persistencia tras recarga)
    // Default: 'leads' (Por Atender)
    $activeTab = $_GET['tab'] ?? 'leads';
    
    // Mapeo simple para clases CSS
    $showLeads = ($activeTab === 'leads') ? 'active' : '';
    $showUpdates = ($activeTab === 'updates') ? 'active' : '';
    $showAll = ($activeTab === 'all') ? 'active' : ''; // Historial
    
    $paneLeads = ($activeTab === 'leads') ? 'show active' : '';
    $paneUpdates = ($activeTab === 'updates') ? 'show active' : '';
    $paneAll = ($activeTab === 'all') ? 'show active' : '';
    ?>

    <div class="row">
        <div class="col-lg-3 mb-4">
             <!-- Filtros Verticales -->
            <div class="list-group list-group-flush border rounded shadow-sm">
                <?php if (!$esProveedor): // Solo clientes y admins ven "Por Atender" ?>
                <!-- Tab: Pendientes (Prioridad) -->
                <a class="list-group-item list-group-item-action fw-bold <?= $showLeads ?> d-flex justify-content-between align-items-center" id="pills-leads-tab" data-bs-toggle="pill" href="#pills-leads" onclick="history.pushState(null, '', '?tab=leads')">
                    <span><i class="bi bi-star-fill text-warning me-2"></i> Por Atender</span>
                    <?php if($countPendientes > 0): ?><span class="badge bg-danger rounded-pill"><?= $countPendientes ?></span><?php endif; ?>
                </a>
                <?php endif; ?>
                
                <!-- Tab: Actualizaciones -->
                 <a class="list-group-item list-group-item-action <?= $showUpdates ?>" id="pills-updates-tab" data-bs-toggle="pill" href="#pills-updates" onclick="history.pushState(null, '', '?tab=updates')">
                    <i class="bi bi-arrow-repeat me-2"></i> Actualizaciones
                </a>

                <!-- Tab: Historial -->
                <a class="list-group-item list-group-item-action <?= $showAll ?>" id="pills-all-tab" data-bs-toggle="pill" href="#pills-all" onclick="history.pushState(null, '', '?tab=all')">
                    <i class="bi bi-archive me-2"></i> Historial Completo
                </a>
            </div>
        
            <!-- Info Paginación -->
            <?php if($activeTab === 'all'): ?>
            <div class="mt-3 text-center text-muted small fade show">
                Mostrando pág. <?= $currentPage ?> de <?= $totalPages ?>
                <br>
                (Total: <?= $pagination['total_items'] ?? 0 ?>)
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-9">
            <!-- Contenido -->
            <div class="tab-content" id="pills-tabContent">
                
                <!-- Tab: PENDIENTES -->
                <div class="tab-pane fade <?= $paneLeads ?>" id="pills-leads" role="tabpanel">
                    <?php if (empty($leadsPendientesList)): ?>
                         <div class="text-center py-5 border rounded bg-light">
                            <i class="bi bi-check-circle display-1 text-success opacity-50"></i>
                            <h5 class="mt-3 text-success">¡Todo al día!</h5>
                            <p class="text-muted">No tienes leads nuevos pendientes de atender.</p>
                         </div>
                    <?php else: ?>
                        
                        <!-- Buscador DataTables Pendientes -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" id="pendientesSearch" class="form-control border-start-0 ps-0" placeholder="Buscar lead, producto o teléfono...">
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="tablaPendientes" class="table table-hover align-middle" style="width:100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>Lead / Interesado</th>
                                        <th>Producto de Interés</th>
                                        <th style="min-width: 130px;">Contacto</th>
                                        <th>Fecha</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($leadsPendientesList as $notif): 
                                    $payload = is_array($notif['payload']) ? $notif['payload'] : json_decode($notif['payload'], true);
                                    $nombre = $payload['nombre'] ?? 'Desconocido';
                                    $producto = $payload['producto'] ?? 'N/A';
                                    $leadId = $payload['lead_id'] ?? 0;
                                    $leadPhoneLive = $notif['lead_phone_live'] ?? ($payload['telefono'] ?? '');
                                    $fecha = date('d/m H:i', strtotime($notif['created_at']));
                                ?>
                                    <tr id="row-notif-<?= $notif['id'] ?>">
                                        <td>
                                            <div class="fw-bold text-dark"><?= $nombre ?></div>
                                            <small class="text-muted">ID: #<?= $leadId ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= $producto ?></span>
                                        </td>
                                        <td>
                                            <?php if(!empty($leadPhoneLive)): ?>
                                                <div class="d-flex gap-1">
                                                    <a href="https://wa.me/52<?= preg_replace('/[^0-9]/', '', $leadPhoneLive) ?>" target="_blank" class="btn btn-sm btn-success text-white d-flex align-items-center gap-1" title="Enviar WhatsApp">
                                                        <i class="fab fa-whatsapp"></i> 
                                                    </a>
                                                    <a href="tel:<?= $leadPhoneLive ?>" class="btn btn-sm btn-outline-primary" title="Llamar">
                                                        <i class="bi bi-telephone"></i>
                                                    </a>
                                                    <small class="ms-1 align-self-center"><?= $leadPhoneLive ?></small>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?= $fecha ?></small></td>
                                        <td class="text-end">
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                <!-- Selector de Estado -->
                                                <select class="form-select form-select-sm" style="width: auto; font-weight: 500;" 
                                                        onchange="confirmarCambioEstado(this, <?= $leadId ?>, <?= $notif['id'] ?>)">
                                                    <option selected disabled value="">Acción...</option>
                                                    <option value="APROBADO" class="text-success fw-bold">APROBADO</option>
                                                    <option value="CONFIRMADO" class="text-primary fw-bold">CONFIRMADO</option>
                                                    <option value="EN_TRANSITO" class="text-info fw-bold">EN TRANSITO</option>
                                                    <option value="EN_BODEGA" class="text-secondary fw-bold">EN BODEGA</option>
                                                    <option value="CANCELADO" class="text-danger fw-bold">CANCELADO</option>
                                                </select>
                                                
                                                <a href="<?= RUTA_URL ?>crm/ver/<?= $leadId ?>" class="btn btn-sm btn-outline-secondary" title="Ver Detalles">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tab: ACTUALIZACIONES -->
                <div class="tab-pane fade <?= $paneUpdates ?>" id="pills-updates" role="tabpanel">
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
                
                <!-- Tab: HISTORIAL (DataTable Card View) -->
                <div class="tab-pane fade <?= $paneAll ?>" id="pills-all" role="tabpanel">
                    
                    <!-- Búsqueda y Filtros -->
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" id="customSearch" class="form-control" placeholder="Buscar por nombre, teléfono o estado...">
                        </div>
                    </div>
                    
                    <!-- Filtros de Fecha y Exportación -->
                    <div class="d-flex flex-wrap align-items-end gap-2 mb-3 bg-light p-2 rounded border">
                        <div class="col-auto">
                            <label class="small text-muted fw-bold">Desde:</label>
                            <input type="date" id="filterStartDate" class="form-control form-control-sm" 
                                   value="<?= htmlspecialchars($datos['start_date']) ?>">
                        </div>
                        <div class="col-auto">
                            <label class="small text-muted fw-bold">Hasta:</label>
                            <input type="date" id="filterEndDate" class="form-control form-control-sm" 
                                   value="<?= htmlspecialchars($datos['end_date']) ?>">
                        </div>
                        <div class="col-auto">
                            <label class="small text-muted fw-bold">Estado del Lead:</label>
                            <select id="filterLeadStatus" class="form-select form-select-sm" style="min-width: 150px;">
                                <option value="">Todos los estados</option>
                                <option value="EN_ESPERA">EN_ESPERA</option>
                                <option value="nuevo">NUEVO</option>
                                <option value="APROBADO">APROBADO</option>
                                <option value="CONFIRMADO">CONFIRMADO</option>
                                <option value="EN_TRANSITO">EN TRANSITO</option>
                                <option value="EN_BODEGA">EN BODEGA</option>
                                <option value="CANCELADO">CANCELADO</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-sm btn-primary" onclick="filtrarHistorial()"><i class="bi bi-filter"></i> Filtrar</button>
                        </div>
                        <div class="ms-auto">
                            <button class="btn btn-sm btn-success" onclick="descargarExcel()"><i class="bi bi-file-earmark-excel"></i> Exportar Excel</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="tablaHistorial" class="table table-borderless" style="width:100%">
                            <thead class="d-none">
                                <tr>
                                    <th>Contenido</th>
                                    <th>FechaSort</th>
                                    <th>SearchData</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data cargada via Server-Side AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            
                </div>

            </div>
        </div>
    </div>
</div>
<!-- Fin container principal de notificaciones -->



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
            // Eliminar fila de tabla si existe
            const row = document.getElementById('row-notif-' + id);
            if(row) row.remove();
        }
    });
}

// Confirmar cambio desde Dropdown
function confirmarCambioEstado(selectElem, leadId, notifId) {
    const nuevoEstado = selectElem.value;
    const textoEstado = selectElem.options[selectElem.selectedIndex].text;
    
    // Resetear valor visualmente por si cancela
    const reset = () => { selectElem.value = ""; };
    
    if(!nuevoEstado) return;

    Swal.fire({
        title: '¿Cambiar estado?',
        text: `El lead pasará a estado: ${textoEstado}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cambiar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            quickStatusChange(leadId, nuevoEstado, notifId);
        } else {
            reset();
        }
    });
}

// Función AJAX cambio de estado
function quickStatusChange(leadId, nuevoEstado, notifId) {
    fetch('<?= RUTA_URL ?>api/crm/leads/' + leadId + '/estado', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            estado: nuevoEstado,
            observaciones: 'Cambio rápido desde notificaciones'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Actualizado!',
                text: 'Estado cambiado correctamente',
                timer: 1500,
                showConfirmButton: false
            });
            // Al tener éxito, el lead sale de la lista de pendientes
            markAsRead(notifId, true); 
        } else {
            Swal.fire('Error', data.message || 'No se pudo actualizar', 'error');
        }
    })
    .catch(error => {
        console.error(error);
        Swal.fire('Error', 'Hubo un problema de conexión', 'error');
    });
}

// Función para marcar todas como leídas
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

// Inicializar DataTables PENDIENTES
document.addEventListener("DOMContentLoaded", function() {
    if (typeof $ !== 'undefined' && $.fn.dataTable) {
        
        // Tabla Pendientes (Normal)
        if ($('#tablaPendientes').length) {
            var tablePendientes = $('#tablaPendientes').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json' },
                order: [[3, 'desc']],
                pageLength: 15,
                lengthMenu: [10, 20, 50, 100],
                responsive: true,
                dom: 'lrtip',
                columnDefs: [{ orderable: false, targets: 4 }]
            });

            $('#pendientesSearch').on('keyup', function() {
                tablePendientes.search(this.value).draw();
            });
        }

        // Tabla Historial (Server-Side)
        if ($('#tablaHistorial').length) {
            historialTable = $('#tablaHistorial').DataTable({
                language: { 
                    url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json',
                    zeroRecords: "No se encontraron coincidencias en este periodo. Intenta ampliar el rango de fechas."
                },
                processing: true,
                serverSide: true,
                searching: true, // Habilitar búsqueda
                searchDelay: 500, // Esperar al escribir
                ajax: {
                    url: '<?= RUTA_URL ?>api/crm/notifications_datatable.php',
                    data: function(d) {
                        d.start_date = document.getElementById('filterStartDate').value;
                        d.end_date = document.getElementById('filterEndDate').value;
                        d.lead_status = document.getElementById('filterLeadStatus').value;
                        d.tab = 'all';
                    }
                },
                order: [[1, 'desc']], // Ordenar por columna oculta Timestamp
                pageLength: 20,
                lengthMenu: [10, 25, 50, 100],
                // El render ya viene como HTML en la col 0
                columns: [
                    { 
                        data: 0, 
                        render: function(data, type, row) { return '<div class="p-0 mb-3 d-block">' + data + '</div>'; } 
                    },
                    { data: 1 }, // Timestamp
                    { data: 2 }  // SearchText
                ],
                dom: '<"d-flex justify-content-between mb-3">rt<"d-flex justify-content-between mt-3"ip>', // Removido 'f' para ocultar búsqueda
                columnDefs: [
                    { targets: [1, 2], visible: false, searchable: true },
                    { targets: 0, orderable: false } // No ordenar por HTML
                ]
            });
            
            // Conectar búsqueda personalizada
            let searchTimeout;
            $('#customSearch').on('keyup', function() {
                clearTimeout(searchTimeout);
                const searchValue = this.value;
                searchTimeout = setTimeout(function() {
                    historialTable.search(searchValue).draw();
                }, 500); // Debounce de 500ms
            });
        }

    } else {
        console.warn('DataTables no está cargado o jQuery falla.');
    }
});

var historialTable;

function filtrarHistorial() {
    if(historialTable) {
        historialTable.draw(); // Recargar Ajax
    }
}

function descargarExcel() {
    const start = document.getElementById('filterStartDate').value;
    const end = document.getElementById('filterEndDate').value;
    const status = document.getElementById('filterLeadStatus').value;
    let url = '<?= RUTA_URL ?>api/crm/notifications_excel.php?start_date=' + start + '&end_date=' + end;
    if (status) {
        url += '&lead_status=' + status;
    }
    window.location.href = url;
}



</script>

<?php include("vista/includes/footer.php"); ?>
