<?php
/**
 * Dashboard Logística (Cliente) - Redesign
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['registrado'])) {
    header('location:' . RUTA_URL . 'login');
    exit;
}

require_once __DIR__ . '/../../../utils/permissions.php';

// Incluir controlador
require_once "controlador/logistica.php";

$controller = new LogisticaController();
// Obtener datos (filtros se aplican solo al historial)
$data = $controller->dashboard();

$notificaciones     = $data['notificaciones'];
$pedidosActivos     = $data['historial'];        // Tab "En Proceso" (sin estados finales)
$historialCompleto  = $data['historialCompleto']; // Tab "Historial Completo" (todos los estados)
$filtros            = $data['filtros'];           // Filtros tab "En Proceso"
$filtrosHistorial   = $data['filtrosHistorial'];  // Filtros tab "Historial Completo"
$paginationH        = $data['paginationH'];       // Paginación Historial Completo
$estadosDisponibles = $data['estados']   ?? [];
$clientesLista      = $data['clientes']  ?? [];

// Mapa de Colores Estandarizado y Vibrante
$estadoColores = [
    'EN BODEGA'           => 'primary',           
    'EN RUTA'             => 'info text-dark',    
    'ENTREGADO'           => 'success',           
    'CANCELADO'           => 'danger',            
    'LIQUIDADO'           => 'dark',              
    'DEVOLUCION'          => 'warning text-dark', 
    'DEVOLUCION COMPLETA' => 'warning text-dark', 
    'EN_ESPERA'           => 'secondary',         
    'PENDIENTE'           => 'warning text-dark', 
    'VENDIDO'             => 'success',           
    'RECHAZADO'           => 'danger',            
    'DOMICILIO'           => 'warning text-dark', 
    'DEVUELTO'            => 'danger',            
    'TRANSITO'            => 'info text-dark'     
];

function getBadgeColor($estado, $map) {
    if (empty($estado)) return 'secondary';
    $estadoUpper = strtoupper($estado);
    foreach ($map as $key => $val) {
        if (strpos($estadoUpper, $key) !== false) return $val;
    }
    return 'secondary';
}

// Función Helper para Renderizar Card de Notificación (estilo CRM)
function renderNotificationCard($notif) {
    $time = date('d/m H:i', strtotime($notif['created_at']));
    
    // Determinar cambios
    $cambios = [];
    $datosNuevos = $notif['datos_nuevos'] ?? [];
    $datosViejos = $notif['datos_anteriores'] ?? [];
    
    if (isset($datosNuevos['id_estado']) && isset($datosViejos['id_estado'])) {
        if ($datosNuevos['id_estado'] != $datosViejos['id_estado']) {
            $cambios[] = "Estado actualizado";
        }
    }
    
    $accion = $notif['accion'];
    if ($accion === 'crear') {
        $title = "Nuevo Pedido #" . htmlspecialchars($notif['numero_orden']);
        $icon = '<i class="bi bi-box-seam"></i>';
        $iconClass = 'bg-soft-success';
        $typeClass = 'type-lead';
        $subtitle = "Pedido creado para " . htmlspecialchars($notif['destinatario']);
    } else {
        $title = "Actualización Pedido #" . htmlspecialchars($notif['numero_orden']);
        $icon = '<i class="bi bi-arrow-repeat"></i>';
        $iconClass = 'bg-soft-info';
        $typeClass = 'type-update';
        
        $detalles = empty($cambios) ? "Actualización de datos" : implode(", ", $cambios);
        $subtitle = "$detalles - " . htmlspecialchars($notif['destinatario']);
    }

    ?>
    <div class="col-12">
        <div class="card notif-card <?= $typeClass ?> p-3 h-100">
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
                    <a href="<?= RUTA_URL ?>logistica/ver/<?= $notif['pedido_id'] ?>" class="btn btn-sm btn-light border">Ver Detalles</a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

include "vista/includes/header.php";
?>

<style>
    .crm-inbox-header {
        background: white;
        border-bottom: 1px solid #e9ecef;
        padding: 1.5rem 0;
        margin-bottom: 2rem;
        margin-top: -1.5rem;
    }
    
    #pills-tab {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
        padding: 0.5rem !important;
        border-radius: 12px !important;
        border: 1px solid rgba(0,0,0,0.08) !important;
        gap: 0.5rem;
    }
    
    .nav-pills .nav-link {
        color: #495057;
        padding: 0.75rem 1.25rem;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.3s;
    }
    
    .nav-pills .nav-link:hover:not(.active) {
        background: rgba(255, 255, 255, 0.8);
        color: #0d6efd;
    }
    
    .nav-pills .nav-link.active {
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        color: white;
        font-weight: 600;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    }

    .notif-card {
        transition: all 0.2s;
        border: 1px solid #e9ecef;
        border-left: 4px solid transparent; 
        border-radius: 8px;
        background: white;
        margin-bottom: 0.75rem;
    }
    .notif-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    }
    .notif-card.type-lead { border-left-color: #198754; } 
    .notif-card.type-update { border-left-color: #0dcaf0; }
    
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
    .btn-detalle {
        border-radius: 50px;
        padding-left: 1.5rem;
        padding-right: 1.5rem;
    }
</style>

<!-- Header de Logística -->
<div class="container-fluid crm-inbox-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-truck me-2 text-primary"></i>Portal Logística
                </h4>
                <p class="mb-0 text-muted small">
                    Gestiona tus pedidos y mantente al día con las actualizaciones.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">
    
    <?php
    $activeTab = $_GET['tab'] ?? 'pedidos';
    
    $showPedidos = ($activeTab === 'pedidos') ? 'active' : '';
    $showUpdates = ($activeTab === 'updates') ? 'active' : '';
    $showAll = ($activeTab === 'all') ? 'active' : '';
    
    $panePedidos = ($activeTab === 'pedidos') ? 'show active' : '';
    $paneUpdates = ($activeTab === 'updates') ? 'show active' : '';
    $paneAll = ($activeTab === 'all') ? 'show active' : '';
    
    // Usar el total de pedidos de la paginación, no solo los de la página actual
    $countActivos = $data['pagination']['total'] ?? count($pedidosActivos);
    ?>

    <!-- Tabs -->
    <ul class="nav nav-pills mb-4 bg-light p-2 rounded border" id="pills-tab" role="tablist">
        <!-- Tab: Pedidos Activos -->
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $showPedidos ?> d-flex align-items-center gap-2" 
                    id="pills-pedidos-tab" 
                    data-bs-toggle="pill" 
                    data-bs-target="#pills-pedidos" 
                    type="button"
                    onclick="history.pushState(null, '', '?tab=pedidos')">
                <i class="bi bi-box-seam-fill text-primary"></i>
                <span>En Proceso</span>
                <?php if($countActivos > 0): ?>
                    <span class="badge bg-primary rounded-pill"><?= $countActivos ?></span>
                <?php endif; ?>
            </button>
        </li>
        
        <!-- Tab: Actualizaciones -->
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $showUpdates ?> d-flex align-items-center gap-2" 
                    id="pills-updates-tab" 
                    data-bs-toggle="pill" 
                    data-bs-target="#pills-updates" 
                    type="button"
                    onclick="history.pushState(null, '', '?tab=updates')">
                <i class="bi bi-bell-fill text-info"></i>
                <span>Actualizaciones</span>
            </button>
        </li>

        <!-- Tab: Historial -->
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $showAll ?> d-flex align-items-center gap-2" 
                    id="pills-all-tab" 
                    data-bs-toggle="pill" 
                    data-bs-target="#pills-all" 
                    type="button"
                    onclick="history.pushState(null, '', '?tab=all')">
                <i class="bi bi-archive-fill"></i>
                <span>Historial Completo</span>
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="pills-tabContent">
        
        <!-- TAB: PEDIDOS ACTIVOS (GRID DE CARDS) -->
        <div class="tab-pane fade <?= $panePedidos ?>" id="pills-pedidos" role="tabpanel">
            
            <!-- Barra de Filtros + Excel (Tab Pedidos Activos) -->
            <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body bg-light rounded">
                        <form method="GET" action="<?= RUTA_URL ?>logistica/dashboard" class="row g-2 align-items-end" id="formFiltrosPedidos">
                            <input type="hidden" name="tab" value="pedidos">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold mb-1">Desde</label>
                                <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['fecha_desde']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold mb-1">Hasta</label>
                                <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['fecha_hasta']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold mb-1">Cliente</label>
                                <select name="id_cliente" class="form-select form-select-sm">
                                    <option value="0">Todos</option>
                                    <?php foreach ($clientesLista as $cli): ?>
                                        <option value="<?= (int)$cli['id'] ?>" <?= (int)$filtros['id_cliente'] === (int)$cli['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cli['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold mb-1">Estado</label>
                                <select name="id_estado" class="form-select form-select-sm">
                                    <option value="0">Todos</option>
                                    <?php foreach ($estadosDisponibles as $est): ?>
                                        <option value="<?= (int)$est['id'] ?>" <?= (int)$filtros['id_estado'] === (int)$est['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($est['nombre_estado']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold mb-1">Buscar</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Orden / nombre..." value="<?= htmlspecialchars($filtros['search']) ?>">
                            </div>
                            <div class="col-md-2 d-flex gap-1">
                                <button class="btn btn-primary btn-sm flex-grow-1" type="submit"><i class="bi bi-search"></i> Aplicar</button>
                                <a href="<?= RUTA_URL ?>logistica/dashboard?tab=pedidos" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros"><i class="bi bi-x-circle"></i></a>
                                <a href="<?= RUTA_URL ?>logistica/export_pedidos_excel?tab=pedidos&fecha_desde=<?= urlencode($filtros['fecha_desde']) ?>&fecha_hasta=<?= urlencode($filtros['fecha_hasta']) ?>&id_cliente=<?= (int)$filtros['id_cliente'] ?>&id_estado=<?= (int)$filtros['id_estado'] ?>&search=<?= urlencode($filtros['search']) ?>" 
                                   class="btn btn-success btn-sm" title="Descargar Excel"><i class="bi bi-file-earmark-excel"></i></a>
                                <?php if (isCliente() || isSuperAdmin()): ?>
                                <button type="button" class="btn btn-warning btn-sm" id="btnAbrirBulk" title="Actualizar comentarios/estado masivamente" onclick="abrirModalBulk()">
                                    <i class="bi bi-file-earmark-arrow-up"></i> Actualizar
                                </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

            <?php if (empty($pedidosActivos)): ?>
                <div class="text-center py-5 border rounded bg-light">
                    <i class="bi bi-box2 display-1 text-muted opacity-25"></i>
                    <h5 class="mt-3 text-muted">Sin pedidos activos</h5>
                    <p class="text-muted">No tienes pedidos en tránsito o pendientes por el momento.</p>
                </div>
            <?php else: ?>
                <div class="row" id="gridActivos">
                <?php foreach ($pedidosActivos as $p): 
                     $color = getBadgeColor($p['estado'], $estadoColores);
                ?>
                    <div class="col-md-6 col-lg-4 mb-4 card-item">
                        <div class="card h-100 shadow-sm border-0 position-relative" style="transition: transform 0.2s;">
                             <div class="card-body">
                                 <div class="d-flex justify-content-between mb-3">
                                     <span class="badge bg-light text-dark border">
                                         #<?= htmlspecialchars($p['numero_orden']) ?>
                                     </span>
                                     <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($p['estado']) ?></span>
                                 </div>
                                 
                                 <h5 class="card-title fw-bold text-dark mb-1">
                                     <?= htmlspecialchars($p['destinatario']) ?>
                                 </h5>
                                 <div class="d-flex align-items-center text-muted small mb-3">
                                     <i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($p['telefono']) ?>
                                 </div>

                                 <hr class="my-3 opacity-25">

                                 <div class="d-flex justify-content-between align-items-end">
                                     <div>
                                         <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">Total</small>
                                         <span class="fw-bold fs-5 text-dark">
                                             <?= htmlspecialchars($p['moneda'] ?? 'GTQ') ?> 
                                             <?= number_format($p['precio_total_local'] ?? 0, 2) ?>
                                         </span>
                                     </div>
                                     <a href="<?= RUTA_URL ?>logistica/ver/<?= $p['id'] ?>" class="btn btn-outline-primary rounded-circle" title="Ver Detalles">
                                         <i class="bi bi-arrow-right"></i>
                                     </a>
                                 </div>
                             </div>
                             <div class="card-footer bg-light border-top-0 py-2">
                                 <small class="text-muted">
                                     <i class="bi bi-calendar-event me-1"></i> Ingreso: <?= date('d/m/Y', strtotime($p['fecha_ingreso'])) ?>
                                 </small>
                             </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php 
            // Mostrar paginación solo si hay más de una página
            if (!empty($data['pagination']) && $data['pagination']['total_pages'] > 1): 
                $pagination = $data['pagination'];
                $currentPage = $pagination['current_page'];
                $totalPages = $pagination['total_pages'];
                $total = $pagination['total'];
                $perPage = $pagination['per_page'];
                
                // Calcular rango de pedidos mostrados
                $start = (($currentPage - 1) * $perPage) + 1;
                $end = min($currentPage * $perPage, $total);
                
                // Construir URL base con todos los filtros activos
                $baseUrl = RUTA_URL . 'logistica/dashboard?tab=pedidos&';
                $params = [];
                if (!empty($filtros['fecha_desde']))  $params[] = 'fecha_desde='  . urlencode($filtros['fecha_desde']);
                if (!empty($filtros['fecha_hasta']))  $params[] = 'fecha_hasta='  . urlencode($filtros['fecha_hasta']);
                if (!empty($filtros['search']))       $params[] = 'search='       . urlencode($filtros['search']);
                if (!empty($filtros['id_cliente']))   $params[] = 'id_cliente='   . (int)$filtros['id_cliente'];
                if (!empty($filtros['id_estado']))    $params[] = 'id_estado='    . (int)$filtros['id_estado'];
                $baseUrl .= implode('&', $params) . (count($params) > 0 ? '&' : '');
            ?>
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <div class="text-muted small">
                        Mostrando <strong><?= $start ?></strong> - <strong><?= $end ?></strong> de <strong><?= $total ?></strong> pedidos
                    </div>
                    
                    <nav aria-label="Paginación de pedidos">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Primera página -->
                            <li class="page-item <?= $currentPage == 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $baseUrl ?>page=1" aria-label="Primera">
                                    <i class="bi bi-chevron-double-left"></i>
                                </a>
                            </li>
                            
                            <!-- Página anterior -->
                            <li class="page-item <?= $currentPage == 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $baseUrl ?>page=<?= max(1, $currentPage - 1) ?>" aria-label="Anterior">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            // Mostrar números de página (máximo 5)
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $baseUrl ?>page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Página siguiente -->
                            <li class="page-item <?= $currentPage == $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $baseUrl ?>page=<?= min($totalPages, $currentPage + 1) ?>" aria-label="Siguiente">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                            
                            <!-- Última página -->
                            <li class="page-item <?= $currentPage == $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $baseUrl ?>page=<?= $totalPages ?>" aria-label="Última">
                                    <i class="bi bi-chevron-double-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: ACTUALIZACIONES -->
        <div class="tab-pane fade <?= $paneUpdates ?>" id="pills-updates" role="tabpanel">
            <div class="row">
                <?php if (empty($notificaciones)): ?>
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-bell-slash display-4 opacity-25"></i>
                        <p class="mt-3">No hay actualizaciones recientes.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $uniqueNotifs = [];
                    foreach ($notificaciones as $notif) {
                        // Como vienen ordenadas por fecha DESC, la primera que encontremos es la más reciente
                        if (!isset($uniqueNotifs[$notif['pedido_id']])) {
                            $uniqueNotifs[$notif['pedido_id']] = $notif;
                        }
                    }
                    
                    foreach ($uniqueNotifs as $notif): 
                        renderNotificationCard($notif);
                    endforeach; 
                    ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB: HISTORIAL COMPLETO -->
        <div class="tab-pane fade <?= $paneAll ?>" id="pills-all" role="tabpanel">
            
            <!-- Barra de Filtros Avanzados + Excel (Tab Historial Completo) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body bg-light rounded">
                    <form method="GET" action="<?= RUTA_URL ?>logistica/dashboard" class="row g-2 align-items-end">
                        <input type="hidden" name="tab" value="all">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Desde</label>
                            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtrosHistorial['fecha_desde']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtrosHistorial['fecha_hasta']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Cliente</label>
                            <select name="id_cliente" class="form-select form-select-sm">
                                <option value="0">Todos</option>
                                <?php foreach ($clientesLista as $cli): ?>
                                    <option value="<?= (int)$cli['id'] ?>" <?= (int)$filtrosHistorial['id_cliente'] === (int)$cli['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cli['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Estado</label>
                            <select name="id_estado" class="form-select form-select-sm">
                                <option value="0">Todos</option>
                                <?php foreach ($estadosDisponibles as $est): ?>
                                    <option value="<?= (int)$est['id'] ?>" <?= (int)$filtrosHistorial['id_estado'] === (int)$est['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($est['nombre_estado']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Buscar</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Orden / nombre..." value="<?= htmlspecialchars($filtrosHistorial['search']) ?>">
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button class="btn btn-primary btn-sm flex-grow-1" type="submit"><i class="bi bi-search"></i> Aplicar</button>
                            <a href="<?= RUTA_URL ?>logistica/dashboard?tab=all" class="btn btn-outline-secondary btn-sm" title="Limpiar"><i class="bi bi-x-circle"></i></a>
                            <a href="<?= RUTA_URL ?>logistica/export_pedidos_excel?tab=all&fecha_desde=<?= urlencode($filtrosHistorial['fecha_desde']) ?>&fecha_hasta=<?= urlencode($filtrosHistorial['fecha_hasta']) ?>&id_cliente=<?= (int)$filtrosHistorial['id_cliente'] ?>&id_estado=<?= (int)$filtrosHistorial['id_estado'] ?>&search=<?= urlencode($filtrosHistorial['search']) ?>" 
                               class="btn btn-success btn-sm" title="Descargar Excel"><i class="bi bi-file-earmark-excel"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle border" id="tablaHistorial">
                    <thead class="table-light">
                        <tr>
                            <th>Orden</th>
                            <th>Destinatario</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historialCompleto)): ?>
                            <tr><td colspan="6" class="text-center py-4">No se encontraron registros.</td></tr>
                        <?php else: ?>
                            <?php foreach ($historialCompleto as $p): 
                                $color = getBadgeColor($p['estado'], $estadoColores);
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['numero_orden']) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($p['destinatario']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($p['direccion']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($p['fecha_ingreso'])) ?></td>
                                    <td><?= htmlspecialchars($p['moneda']) ?> <?= number_format($p['precio_total_local'], 2) ?></td>
                                    <td><span class="badge bg-<?= $color ?>"><?= htmlspecialchars($p['estado']) ?></span></td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                Acciones
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="<?= RUTA_URL ?>logistica/ver/<?= $p['id'] ?>"><i class="bi bi-eye me-2"></i>Ver Detalles</a></li>
                                                <?php if (!in_array(strtoupper($p['estado']), ['ENTREGADO', 'CANCELADO', 'DEVOLUCION COMPLETA', 'LIQUIDADO'])): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-warning" href="#" onclick="event.preventDefault(); openStatusModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['numero_orden']) ?>')">
                                                        <i class="bi bi-arrow-repeat me-2"></i>Cambiar Estado
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
            // Paginación Historial Completo
            if (!empty($paginationH) && $paginationH['total_pages'] > 1):
                $curH     = $paginationH['current_page'];
                $totPH    = $paginationH['total_pages'];
                $totalH   = $paginationH['total'];
                $perH     = $paginationH['per_page'];
                $startH   = (($curH - 1) * $perH) + 1;
                $endH     = min($curH * $perH, $totalH);

                // URL base conservando todos los filtros del historial
                $baseUrlH = RUTA_URL . 'logistica/dashboard?tab=all&';
                $paramsH  = [];
                if (!empty($filtrosHistorial['fecha_desde'])) $paramsH[] = 'fecha_desde=' . urlencode($filtrosHistorial['fecha_desde']);
                if (!empty($filtrosHistorial['fecha_hasta'])) $paramsH[] = 'fecha_hasta=' . urlencode($filtrosHistorial['fecha_hasta']);
                if (!empty($filtrosHistorial['search']))      $paramsH[] = 'search='      . urlencode($filtrosHistorial['search']);
                if (!empty($filtrosHistorial['id_cliente']))  $paramsH[] = 'id_cliente='  . (int)$filtrosHistorial['id_cliente'];
                if (!empty($filtrosHistorial['id_estado']))   $paramsH[] = 'id_estado='   . (int)$filtrosHistorial['id_estado'];
                $baseUrlH .= implode('&', $paramsH) . (count($paramsH) > 0 ? '&' : '');
            ?>
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <div class="text-muted small">
                        Mostrando <strong><?= $startH ?></strong> - <strong><?= $endH ?></strong> de <strong><?= $totalH ?></strong> pedidos
                    </div>
                    <nav aria-label="Paginación historial">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $curH == 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $baseUrlH ?>page_h=1"><i class="bi bi-chevron-double-left"></i></a>
                            </li>
                            <li class="page-item <?= $curH == 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $baseUrlH ?>page_h=<?= max(1, $curH - 1) ?>"><i class="bi bi-chevron-left"></i></a>
                            </li>
                            <?php
                            $spH = max(1, $curH - 2);
                            $epH = min($totPH, $curH + 2);
                            for ($i = $spH; $i <= $epH; $i++): ?>
                                <li class="page-item <?= $i == $curH ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $baseUrlH ?>page_h=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $curH == $totPH ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $baseUrlH ?>page_h=<?= min($totPH, $curH + 1) ?>"><i class="bi bi-chevron-right"></i></a>
                            </li>
                            <li class="page-item <?= $curH == $totPH ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $baseUrlH ?>page_h=<?= $totPH ?>"><i class="bi bi-chevron-double-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Modal Cambiar Estado -->
<div class="modal fade" id="cambiarEstadoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar Estado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle me-1"></i> Cambiando estado de orden seleccionada.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nuevo Estado</label>
                        <select name="estado" class="form-select" required>
                            <option value="">Seleccione un estado...</option>
                            <?php foreach ($estadosDisponibles as $est): ?>
                                <option value="<?= htmlspecialchars($est['nombre_estado']) ?>">
                                    <?= htmlspecialchars($est['nombre_estado']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted">Seleccione el nuevo estado.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3" placeholder="Razón del cambio..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php include "vista/includes/footer.php"; ?>

<script>
    // Búsqueda simple en grid activos (cliente side)
    const searchInput = document.getElementById('searchActivos');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const cards = document.querySelectorAll('#gridActivos .card-item');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    }
</script>

<script>
    function openStatusModal(id, orden) {
        const form = document.querySelector('#cambiarEstadoModal form');
        form.action = '<?= RUTA_URL ?>logistica/cambiarEstado/' + id;
        
        // Reset form
        form.reset();
        
        const modalEl = document.getElementById('cambiarEstadoModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        // Manejar el submit del formulario con AJAX
        form.onsubmit = function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            const formData = new FormData(form);
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (err) {
                    console.error('Error parseando JSON. Respuesta del servidor:', text);
                    throw new Error('La respuesta del servidor no es un JSON válido.');
                }
            })
            .then(data => {
                if (data.success) {
                    modal.hide();
                    
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Actualizado!',
                            text: data.message || 'El estado ha sido actualizado.',
                            confirmButtonColor: '#0d6efd'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        alert(data.message || 'Actualizado correctamente');
                        window.location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'No se pudo actualizar el estado.',
                            confirmButtonColor: '#0d6efd'
                        });
                    } else {
                        alert(data.message || 'Error al actualizar');
                    }
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Red',
                        text: 'Hubo un error en la comunicación con el servidor.',
                        confirmButtonColor: '#0d6efd'
                    });
                } else {
                    alert('Error en la comunicación con el servidor');
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        };
    }
</script>

<?php if (isCliente() || isSuperAdmin()): ?>
<!-- ===== MODAL: ACTUALIZACIÓN MASIVA ===== -->
<div class="modal fade" id="modalBulkUpdate" tabindex="-1" aria-labelledby="modalBulkLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="modalBulkLabel">
          <i class="bi bi-file-earmark-arrow-up text-warning me-2"></i>Actualización Masiva de Pedidos
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <!-- Indicador de pasos -->
        <div class="d-flex align-items-center mb-4" id="bulkStepsIndicator">
          <span class="badge rounded-pill bg-warning text-dark me-2" id="bulkStep1Badge">1</span>
          <span class="fw-semibold me-3" id="bulkStep1Label">Subir archivo</span>
          <div class="flex-grow-1 border-top mx-2"></div>
          <span class="badge rounded-pill bg-secondary me-2" id="bulkStep2Badge">2</span>
          <span class="text-muted me-3" id="bulkStep2Label">Vista previa</span>
          <div class="flex-grow-1 border-top mx-2"></div>
          <span class="badge rounded-pill bg-secondary me-2" id="bulkStep3Badge">3</span>
          <span class="text-muted" id="bulkStep3Label">Confirmado</span>
        </div>

        <!-- PASO 1: Subir archivo -->
        <div id="bulkPaso1">
          <div class="alert alert-info">
            <strong>Columnas reconocidas:</strong>
            <code>id_pedido</code> o <code>numero_orden</code> (al menos uno), 
            <code>comentario</code> y/o <code>estado</code> (al menos uno de los dos),
            <code>motivo</code> (opcional).
          </div>

          <!-- Plantillas de descarga -->
          <div class="mb-3">
            <p class="fw-bold mb-1"><i class="bi bi-download me-1"></i>Descargar plantilla de ejemplo:</p>
            <div class="d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="descargarPlantilla('comentario')">
                <i class="bi bi-file-earmark-text"></i> Solo comentario
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="descargarPlantilla('estado')">
                <i class="bi bi-file-earmark-text"></i> Solo estado
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="descargarPlantilla('completa')">
                <i class="bi bi-file-earmark-text"></i> Completa (todos los campos)
              </button>
            </div>
            <small class="text-muted">Las plantillas se descargan como <code>.csv</code> listas para editar en Excel.</small>
          </div>

          <!-- Estados válidos del sistema -->
          <div class="mb-3">
            <p class="fw-bold mb-1 small"><i class="bi bi-info-circle me-1"></i>Nombres de estado aceptados (columna <code>estado</code>):</p>
            <div class="d-flex flex-wrap gap-1">
              <?php foreach ($estadosDisponibles as $est): ?>
                <span class="badge bg-light text-dark border"><?= htmlspecialchars($est['nombre_estado']) ?></span>
              <?php endforeach; ?>
            </div>
            <small class="text-muted">Escríbelos exactamente así (sin importar mayúsculas).</small>
          </div>


          <div class="mb-3">
            <label for="bulkFileInput" class="form-label fw-bold">Seleccionar archivo (.csv o .xlsx)</label>
            <input type="file" class="form-control" id="bulkFileInput" accept=".csv,.xlsx,.xls">
            <div class="form-text">Máximo 10,000 filas. El estado puede escribirse como nombre (ej. <em>EN RUTA</em>) o como id.</div>
          </div>
          <div id="bulkUploadError" class="alert alert-danger d-none"></div>
        </div>


        <!-- PASO 2: Vista previa -->
        <div id="bulkPaso2" class="d-none">
          <!-- Resumen -->
          <div class="row g-3 mb-4" id="bulkSummaryCards">
            <div class="col-6 col-md-3"><div class="card text-center border-0" style="background:#f0f0f0"><div class="card-body py-2"><div class="fs-4 fw-bold text-dark" id="bulkTotalCount">0</div><small class="text-secondary">Total</small></div></div></div>
            <div class="col-6 col-md-3"><div class="card text-center border-0" style="background:#d6f0e0"><div class="card-body py-2"><div class="fs-4 fw-bold" style="color:#1a6e3c" id="bulkValidCount">0</div><small style="color:#1a6e3c">Válidas</small></div></div></div>
            <div class="col-6 col-md-3"><div class="card text-center border-0" style="background:#fadadd"><div class="card-body py-2"><div class="fs-4 fw-bold" style="color:#8b1a2a" id="bulkErrorCount">0</div><small style="color:#8b1a2a">Errores</small></div></div></div>
            <div class="col-6 col-md-3"><div class="card text-center border-0" style="background:#fff4cc"><div class="card-body py-2"><div class="fs-4 fw-bold" style="color:#7a5c00" id="bulkWarnCount">0</div><small style="color:#7a5c00">Advertencias</small></div></div></div>

          </div>

          <!-- Errores / advertencias -->
          <div id="bulkErrorList" class="mb-3"></div>
          <div id="bulkWarnList" class="mb-3"></div>

          <!-- Tabla preview -->
          <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover" id="bulkPreviewTable">
              <thead class="table-dark">
                <tr>
                  <th>#Línea</th><th>ID Pedido</th><th># Orden</th>
                  <th>Nuevo Comentario</th><th>Nuevo Estado ID</th>
                </tr>
              </thead>
              <tbody id="bulkPreviewBody"></tbody>
            </table>
          </div>
        </div>

        <!-- PASO 3: Resultado -->
        <div id="bulkPaso3" class="d-none">
          <div id="bulkResultContent"></div>
        </div>

      </div><!-- /modal-body -->

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="bulkBtnCerrar">Cerrar</button>
        <button type="button" class="btn btn-warning" id="bulkBtnPreview" onclick="enviarPreview()">
          <i class="bi bi-eye"></i> Vista previa
        </button>
        <button type="button" class="btn btn-success d-none" id="bulkBtnConfirmar" onclick="confirmarBulk()" disabled>
          <i class="bi bi-check-circle"></i> Confirmar y aplicar
        </button>
      </div>

    </div>
  </div>
</div>

<script>
(function() {
  let _bulkJobId = null;

  // Plantillas descargables — estados tomados de la BD en tiempo real
  <?php
    $__estados = array_column($estadosDisponibles, 'nombre_estado');
    $__e0 = addslashes($__estados[0] ?? 'EN RUTA');
    $__e1 = addslashes($__estados[1] ?? 'ENTREGADO');
    $__e2 = addslashes($__estados[2] ?? $__estados[0] ?? 'CANCELADO');
  ?>
  const _plantillas = {
    comentario: {
      nombre: 'plantilla_comentario.csv',
      contenido: [
        'id_pedido,numero_orden,comentario,motivo',
        '101,,Paquete entregado al vecino,Ausencia del destinatario',
        ',280001234,En camino reprogramado,Dirección incorrecta',
        '102,,Devuelto al remitente,No se encontró la dirección',
      ].join('\r\n')
    },
    estado: {
      nombre: 'plantilla_estado.csv',
      contenido: [
        'id_pedido,numero_orden,estado,motivo',
        '101,,<?= $__e0 ?>,Salió a ruta hoy',
        ',280001234,<?= $__e1 ?>,Recibido por familiar',
        '102,,<?= $__e2 ?>,Solicitud del cliente',
      ].join('\r\n')
    },
    completa: {
      nombre: 'plantilla_completa.csv',
      contenido: [
        'id_pedido,numero_orden,comentario,estado,motivo',
        '101,,Entregado con retraso,<?= $__e1 ?>,Tráfico en zona norte',
        ',280001234,Cliente ausente al primer intento,<?= $__e0 ?>,Se reprogramó para mañana',
        '102,,,<?= $__e2 ?>,Solicitud del cliente por teléfono',
        '103,,Dirección actualizada,,Cambió a Avenida 5 N°22',
      ].join('\r\n')
    }
  };

  window.descargarPlantilla = function(tipo) {
    const p = _plantillas[tipo];
    if (!p) return;
    const blob = new Blob(['\uFEFF' + p.contenido], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = p.nombre;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };

  // Exponer funciones al scope global
  window.abrirModalBulk = function() {

    _bulkJobId = null;
    // Reset pasos
    document.getElementById('bulkPaso1').classList.remove('d-none');
    document.getElementById('bulkPaso2').classList.add('d-none');
    document.getElementById('bulkPaso3').classList.add('d-none');
    document.getElementById('bulkBtnPreview').classList.remove('d-none');
    document.getElementById('bulkBtnConfirmar').classList.add('d-none');
    document.getElementById('bulkFileInput').value = '';
    document.getElementById('bulkUploadError').classList.add('d-none');
    actualizarPasoIndicador(1);
    const modal = new bootstrap.Modal(document.getElementById('modalBulkUpdate'));
    modal.show();
  };

  window.enviarPreview = function() {
    const fileInput = document.getElementById('bulkFileInput');
    const errDiv    = document.getElementById('bulkUploadError');
    errDiv.classList.add('d-none');

    if (!fileInput.files.length) {
      errDiv.textContent = 'Selecciona un archivo primero.';
      errDiv.classList.remove('d-none');
      return;
    }

    const btn = document.getElementById('bulkBtnPreview');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

    const fd = new FormData();
    fd.append('archivo', fileInput.files[0]);

    fetch('<?= RUTA_URL ?>logistica/bulk/preview', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-eye"></i> Vista previa';

        if (!data.ok) {
          errDiv.textContent = data.error || 'Error al procesar el archivo.';
          errDiv.classList.remove('d-none');
          return;
        }

        _bulkJobId = data.job_id;
        renderPreview(data);
      })
      .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-eye"></i> Vista previa';
        errDiv.textContent = 'Error de red. Intenta de nuevo.';
        errDiv.classList.remove('d-none');
      });
  };

  function renderPreview(data) {
    const s = data.summary || {};
    document.getElementById('bulkTotalCount').textContent = s.total       || 0;
    document.getElementById('bulkValidCount').textContent = s.validas     || 0;
    document.getElementById('bulkErrorCount').textContent = s.errores     || 0;
    document.getElementById('bulkWarnCount').textContent  = s.advertencias || 0;

    // Lista de errores
    const errList  = document.getElementById('bulkErrorList');
    const warnList = document.getElementById('bulkWarnList');
    errList.innerHTML  = '';
    warnList.innerHTML = '';

    if (data.errores && data.errores.length) {
      errList.innerHTML = '<div class="alert alert-danger"><strong>Errores (' + data.errores.length + '):</strong><ul class="mb-0 mt-1">' +
        data.errores.map(e => '<li>' + escHtml(e) + '</li>').join('') + '</ul></div>';
    }
    if (data.advertencias && data.advertencias.length) {
      warnList.innerHTML = '<div class="alert alert-warning"><strong>Advertencias (' + data.advertencias.length + '):</strong><ul class="mb-0 mt-1">' +
        data.advertencias.map(w => '<li>' + escHtml(w) + '</li>').join('') + '</ul></div>';
    }

    // Tabla de muestra
    const tbody = document.getElementById('bulkPreviewBody');
    tbody.innerHTML = '';
    (data.preview_rows || []).forEach(row => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>' + escHtml(row._line) + '</td>' +
                     '<td>' + escHtml(row.id_pedido) + '</td>' +
                     '<td>' + escHtml(row.numero_orden) + '</td>' +
                     '<td>' + escHtml(row.nuevo_comentario || '—') + '</td>' +
                     '<td>' + escHtml(row.nuevo_id_estado !== null ? row.nuevo_id_estado : '—') + '</td>';
      tbody.appendChild(tr);
    });

    // Mostrar paso 2
    document.getElementById('bulkPaso1').classList.add('d-none');
    document.getElementById('bulkPaso2').classList.remove('d-none');
    document.getElementById('bulkBtnPreview').classList.add('d-none');

    const btnConfirmar = document.getElementById('bulkBtnConfirmar');
    btnConfirmar.classList.remove('d-none');
    // Solo habilitar si no hay errores
    btnConfirmar.disabled = (s.errores > 0);
    if (s.errores > 0) {
      btnConfirmar.title = 'Corrija los errores antes de confirmar.';
    }

    actualizarPasoIndicador(2);
  }

  window.confirmarBulk = function() {
    if (!_bulkJobId) return;
    const btn = document.getElementById('bulkBtnConfirmar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Aplicando...';

    fetch('<?= RUTA_URL ?>logistica/bulk/commit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ job_id: _bulkJobId })
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirmar y aplicar';

      document.getElementById('bulkPaso2').classList.add('d-none');
      document.getElementById('bulkBtnConfirmar').classList.add('d-none');
      document.getElementById('bulkPaso3').classList.remove('d-none');
      actualizarPasoIndicador(3);

      if (data.ok && data.summary) {
        const s = data.summary;
        document.getElementById('bulkResultContent').innerHTML =
          '<div class="alert alert-success"><h5><i class="bi bi-check-circle-fill"></i> Operación completada</h5>' +
          '<ul class="mb-0">' +
          '<li><strong>Total procesadas:</strong> ' + s.total + '</li>' +
          '<li><strong>Actualizadas:</strong> <span class="text-success">' + s.actualizados + '</span></li>' +
          '<li><strong>Sin cambios (omitidas):</strong> ' + s.sin_cambios + '</li>' +
          (s.fallidos > 0 ? '<li><strong>Fallidas:</strong> <span class="text-danger">' + s.fallidos + '</span></li>' : '') +
          '</ul></div>' +
          (s.failed_rows && s.failed_rows.length ? '<div class="alert alert-warning"><strong>Detalle de fallos:</strong><ul class="mb-0">' + s.failed_rows.map(r => '<li>' + escHtml(r) + '</li>').join('') + '</ul></div>' : '');
      } else {
        document.getElementById('bulkResultContent').innerHTML =
          '<div class="alert alert-danger"><strong>Error:</strong> ' + escHtml(data.error || 'Error desconocido') + '</div>';
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirmar y aplicar';
      document.getElementById('bulkResultContent').innerHTML =
        '<div class="alert alert-danger">Error de red al aplicar los cambios.</div>';
      document.getElementById('bulkPaso2').classList.add('d-none');
      document.getElementById('bulkPaso3').classList.remove('d-none');
    });
  };

  function actualizarPasoIndicador(paso) {
    [1, 2, 3].forEach(n => {
      const badge = document.getElementById('bulkStep' + n + 'Badge');
      const label = document.getElementById('bulkStep' + n + 'Label');
      if (n < paso)  { badge.className = 'badge rounded-pill bg-success me-2'; label.className = 'text-muted me-3'; }
      if (n === paso){ badge.className = 'badge rounded-pill bg-warning text-dark me-2'; label.className = 'fw-semibold me-3'; }
      if (n > paso)  { badge.className = 'badge rounded-pill bg-secondary me-2'; label.className = 'text-muted me-3'; }
    });
  }

  function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
})();
</script>
<?php endif; ?>
