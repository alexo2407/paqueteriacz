<?php
start_secure_session();
if (!isset($_SESSION['registrado'])) { header('location:' . RUTA_URL . 'login'); die(); }

$usaDataTables = true;

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../controlador/logistica.php';

// Obtener datos del controlador
$logisticaController = new LogisticaController();
$datos = $logisticaController->notificaciones();

$notificaciones  = $datos['notificaciones'];
$pendientes      = $datos['pendientes'];
$unreadCount     = $datos['unread_count'];
$pagination      = $datos['pagination'] ?? [];
$currentPage     = $pagination['current_page'] ?? 1;
$totalPages      = $pagination['total_pages'] ?? 1;

$userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;

if ($userId <= 0) {
    header("Location: " . RUTA_URL . "login");
    exit;
}

// =========================================================================
// HELPER: Renderizar card de notificación logística
// =========================================================================
function renderLogisticaNotifCard(array $notif): void {
    require_once __DIR__ . '/../../../modelo/logistica_notification.php';

    $config   = LogisticaNotificationModel::getTipoConfig($notif['tipo'] ?? 'estado_cambiado');
    $isRead   = (bool)($notif['is_read'] ?? false);
    $unreadClass = $isRead ? '' : 'unread shadow-sm';
    $time     = date('H:i d/m', strtotime($notif['created_at']));
    $pedidoId = (int)($notif['pedido_id'] ?? 0);
    $orden    = htmlspecialchars($notif['numero_orden'] ?? ('Pedido #' . $pedidoId));
    $titulo   = htmlspecialchars($notif['titulo'] ?? 'Notificación');
    $mensaje  = htmlspecialchars($notif['mensaje'] ?? '');
    $tipo     = htmlspecialchars($notif['tipo'] ?? '');
    $borderColor = $config['border'];

    $payload = is_array($notif['payload']) ? $notif['payload'] : [];
    $estadoAnterior = $payload['estado_anterior'] ?? null;
    $estadoNuevo    = $payload['estado_nuevo']    ?? null;

    $coloresBadge = [
        'ENTREGADO'          => 'bg-success',
        'LIQUIDADO'          => 'bg-success',
        'EN CAMINO'          => 'bg-info text-dark',
        'EN PROCESO'         => 'bg-primary',
        'EN BODEGA'          => 'bg-secondary',
        'REPROGRAMADO'       => 'bg-warning text-dark',
        'DEVUELTO'           => 'bg-danger',
        'PENDIENTE'          => 'bg-warning text-dark',
        'CANCELADO'          => 'bg-danger',
        'NOVEDAD'            => 'bg-orange text-white',
    ];
?>
    <div class="col-12 col-md-6">
        <div class="card notif-card <?= $unreadClass ?> p-3 h-100" id="notif-card-<?= $notif['id'] ?>"
             style="border-left: 4px solid <?= $borderColor ?>;">
            <div class="d-flex align-items-start">
                <div class="notif-icon <?= $config['color'] ?> flex-shrink-0 me-3">
                    <i class="bi <?= $config['icon'] ?>"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between align-items-start">
                        <h6 class="mb-1 fw-bold text-dark text-truncate me-2"><?= $titulo ?></h6>
                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                            <small class="text-muted"><?= $time ?></small>
                            <?php if (!$isRead): ?>
                                <button class="btn btn-link btn-sm p-0 ms-1 text-muted btn-marcar-leida"
                                        data-id="<?= $notif['id'] ?>"
                                        title="Marcar como leída">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Subtítulo con cambio de estado si aplica -->
                    <?php if ($estadoAnterior && $estadoNuevo): ?>
                        <p class="mb-2 small">
                            <span class="badge <?= $coloresBadge[strtoupper($estadoAnterior)] ?? 'bg-secondary' ?>">
                                <?= htmlspecialchars($estadoAnterior) ?>
                            </span>
                            <i class="bi bi-arrow-right small mx-1"></i>
                            <span class="badge <?= $coloresBadge[strtoupper($estadoNuevo)] ?? 'bg-primary' ?>">
                                <?= htmlspecialchars($estadoNuevo) ?>
                            </span>
                        </p>
                    <?php elseif ($mensaje): ?>
                        <p class="mb-2 text-muted small"><?= $mensaje ?></p>
                    <?php endif; ?>

                    <!-- Pie de card -->
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if ($pedidoId > 0): ?>
                            <?php
                                $urlPedido = ($tipo === 'lote_creado')
                                    ? RUTA_URL . 'logistica/dashboard?tab=pedidos'
                                    : RUTA_URL . 'logistica/ver/' . $pedidoId;
                                $btnLabel = ($tipo === 'lote_creado')
                                    ? '<i class="bi bi-grid me-1"></i>Ver pedidos'
                                    : '<i class="bi bi-box-seam me-1"></i>' . $orden;
                            ?>
                            <a href="<?= $urlPedido ?>"
                               class="btn btn-sm btn-light border">
                                <?= $btnLabel ?>
                            </a>
                        <?php endif; ?>
                        <span class="badge bg-light text-dark border">
                            <i class="bi <?= $config['icon'] ?> me-1"></i><?= $config['label'] ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
}

// --- LÓGICA AJAX: responder solo HTML y salir ---
if (isset($_GET['ajax_search'])) {
    while (ob_get_level()) ob_end_clean();

    $hoy  = date('Y-m-d');
    $ayer = date('Y-m-d', strtotime('-1 day'));
    $grouped = ['Hoy' => [], 'Ayer' => [], 'Anteriores' => []];

    foreach ($notificaciones as $notif) {
        $fecha = date('Y-m-d', strtotime($notif['created_at']));
        if ($fecha === $hoy) $grouped['Hoy'][] = $notif;
        elseif ($fecha === $ayer) $grouped['Ayer'][] = $notif;
        else $grouped['Anteriores'][] = $notif;
    }

    if (empty($notificaciones)) {
        $q = htmlspecialchars($_GET['q'] ?? '');
        echo "<div class='text-center py-5 text-muted'>
                <i class='bi bi-search display-4 opacity-25'></i>
                <p class='mt-3'>No se encontraron resultados para <strong>'$q'</strong>.</p>
              </div>";
    } else {
        foreach ($grouped as $label => $group) {
            if (empty($group)) continue;
            echo "<div class='timeline-label'>$label</div><div class='row g-3'>";
            foreach ($group as $notif) renderLogisticaNotifCard($notif);
            echo "</div>";
        }
    }
    exit;
}

// --- AGRUPACIÓN PARA VISTA NORMAL ---
$hoy  = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));

$actualizacionesPorPedido = [];
$groupedByDate = ['Hoy' => [], 'Ayer' => [], 'Anteriores' => []];

foreach ($notificaciones as $notif) {
    // Tab Actualizaciones: agrupar por pedido (evitar duplicados)
    $pid = $notif['pedido_id'] ?? 0;
    if (!isset($actualizacionesPorPedido[$pid])) {
        $actualizacionesPorPedido[$pid] = $notif;
    }

    // Historial: todos agrupados por fecha
    $fecha = date('Y-m-d', strtotime($notif['created_at']));
    if ($fecha === $hoy) $groupedByDate['Hoy'][] = $notif;
    elseif ($fecha === $ayer) $groupedByDate['Ayer'][] = $notif;
    else $groupedByDate['Anteriores'][] = $notif;
}

$countPendientes = count($pendientes);

// Tab activa
$activeTab    = $_GET['tab'] ?? 'pendientes';
$showPend     = $activeTab === 'pendientes' ? 'active' : '';
$showUpdates  = $activeTab === 'updates'    ? 'active' : '';
$showAll      = $activeTab === 'all'        ? 'active' : '';
$panePend     = $activeTab === 'pendientes' ? 'show active' : '';
$paneUpdates  = $activeTab === 'updates'    ? 'show active' : '';
$paneAll      = $activeTab === 'all'        ? 'show active' : '';

include("vista/includes/header.php");
?>

<style>
    /* ======================== LOGISTICS INBOX ======================== */
    .logistica-inbox-header {
        background: white;
        border-bottom: 1px solid #e9ecef;
        padding: 1.5rem 0;
        margin-bottom: 2rem;
        margin-top: -1.5rem;
    }

    /* Pills tabs */
    #pills-tab-logistica {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
        padding: 0.5rem !important;
        border-radius: 12px !important;
        border: 1px solid rgba(0,0,0,0.08) !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        gap: 0.5rem;
    }
    .nav-pills-logistica .nav-link {
        color: #495057;
        padding: 0.75rem 1.25rem;
        font-size: 0.9rem;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        background: transparent;
        border: 1px solid transparent;
    }
    .nav-pills-logistica .nav-link:hover:not(.active) {
        background: rgba(255,255,255,0.8);
        color: #0d6efd;
        border-color: rgba(13, 110, 253, 0.1);
        box-shadow: 0 2px 8px rgba(13, 110, 253, 0.1);
        transform: translateY(-1px);
    }
    .nav-pills-logistica .nav-link.active {
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        color: white;
        font-weight: 600;
        border-color: #0d6efd;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3), 0 2px 4px rgba(13, 110, 253, 0.2);
        transform: translateY(-2px);
    }
    .nav-pills-logistica .nav-link i { transition: transform 0.3s ease; font-size: 1.1rem; }
    .nav-pills-logistica .nav-link:hover i,
    .nav-pills-logistica .nav-link.active i { transform: scale(1.1); }
    .nav-pills-logistica .nav-link .badge {
        transition: all 0.3s ease; font-weight: 600;
        padding: 0.35em 0.65em; font-size: 0.75rem;
    }
    .nav-pills-logistica .nav-link.active .badge {
        animation: pulse-badge 2s ease-in-out infinite;
    }
    @keyframes pulse-badge { 0%,100%{transform:scale(1)} 50%{transform:scale(1.05)} }

    /* Notification cards */
    .notif-card {
        transition: all 0.2s ease;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        background: white;
        margin-bottom: 0.75rem;
    }
    .notif-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.12) !important;
    }
    .notif-card.unread { background-color: #f8f9ff; }

    .notif-icon {
        width: 42px; height: 42px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    /* Soft background colors */
    .bg-soft-success   { background-color: rgba(25, 135, 84, 0.12);  color: #198754; }
    .bg-soft-info      { background-color: rgba(13, 202, 240, 0.12); color: #0dcaf0; }
    .bg-soft-primary   { background-color: rgba(13, 110, 253, 0.12); color: #0d6efd; }
    .bg-soft-danger    { background-color: rgba(220, 53, 69, 0.12);  color: #dc3545; }
    .bg-soft-warning   { background-color: rgba(255, 193, 7, 0.18);  color: #856404; }
    .bg-soft-secondary { background-color: rgba(108, 117, 125, 0.12);color: #6c757d; }
    .bg-soft-orange    { background-color: rgba(253, 126, 20, 0.12); color: #fd7e14; }

    .timeline-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: #adb5bd;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin: 1.25rem 0 0.5rem;
        padding-left: 0.5rem;
    }

    /* Empty state */
    .empty-state {
        padding: 4rem 2rem;
        text-align: center;
        color: #adb5bd;
        border: 2px dashed #dee2e6;
        border-radius: 16px;
        background: #fafafa;
    }
    .empty-state i { font-size: 4rem; opacity: 0.3; }

    /* Search bar */
    #historialSearch:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    }

    /* Pendientes table */
    #tablaPendientes th { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: #6c757d; }
    #tablaPendientes td { vertical-align: middle; }

    .bg-orange { background-color: #fd7e14 !important; }

    @media (max-width: 768px) {
        #pills-tab-logistica { flex-direction: column; padding: 0.75rem !important; }
        .nav-pills-logistica .nav-link { width: 100%; justify-content: space-between; margin-bottom: 0.5rem; }
        .notif-card.col-md-6 { width: 100%; }
    }
</style>

<!-- ========== HEADER ========== -->
<div class="container-fluid logistica-inbox-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-truck me-2 text-primary"></i>Centro de Notificaciones Logísticas
                </h4>
                <p class="mb-0 text-muted small">
                    Gestiona pedidos y mantente al día con sus actualizaciones.
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-primary rounded-pill ms-1"><?= $unreadCount ?> sin leer</span>
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($unreadCount > 0): ?>
            <button class="btn btn-outline-secondary btn-sm" id="btnMarcarTodasLeidas">
                <i class="bi bi-check-all me-1"></i>Marcar todo como leído
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container-fluid mb-5">

    <!-- ===== TABS ===== -->
    <ul class="nav nav-pills-logistica mb-4 d-flex flex-wrap bg-light p-2 rounded border" id="pills-tab-logistica" role="tablist">

        <!-- Por Atender -->
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $showPend ?> d-flex align-items-center gap-2"
                    id="pills-pendientes-tab"
                    data-bs-toggle="pill"
                    data-bs-target="#pills-pendientes"
                    type="button"
                    onclick="history.pushState(null,'','?tab=pendientes')">
                <i class="bi bi-star-fill text-warning"></i>
                <span>Por Atender</span>
                <?php if ($countPendientes > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?= $countPendientes ?></span>
                <?php endif; ?>
            </button>
        </li>

        <!-- Actualizaciones -->
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $showUpdates ?> d-flex align-items-center gap-2"
                    id="pills-updates-tab"
                    data-bs-toggle="pill"
                    data-bs-target="#pills-updates"
                    type="button"
                    onclick="history.pushState(null,'','?tab=updates')">
                <i class="bi bi-arrow-repeat"></i>
                <span>Actualizaciones</span>
            </button>
        </li>

        <!-- Historial Completo -->
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $showAll ?> d-flex align-items-center gap-2"
                    id="pills-all-tab"
                    data-bs-toggle="pill"
                    data-bs-target="#pills-all"
                    type="button"
                    onclick="history.pushState(null,'','?tab=all')">
                <i class="bi bi-archive"></i>
                <span>Historial Completo</span>
                <?php if (($pagination['total_items'] ?? 0) > 0): ?>
                    <span class="badge bg-secondary rounded-pill"><?= $pagination['total_items'] ?></span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <!-- ===== TAB CONTENT ===== -->
    <div class="tab-content" id="pills-tabContent-logistica">

        <!-- ======================== TAB: POR ATENDER ======================== -->
        <div class="tab-pane fade <?= $panePend ?>" id="pills-pendientes" role="tabpanel">
            <?php if (empty($pendientes)): ?>
                <div class="empty-state">
                    <i class="bi bi-check-circle-fill text-success d-block mb-3"></i>
                    <h5 class="text-success">¡Todo al día!</h5>
                    <p class="mb-0">No tienes notificaciones pendientes de atender.</p>
                </div>
            <?php else: ?>
                <!-- Buscador -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="pendientesSearch"
                                   class="form-control border-start-0 ps-0"
                                   placeholder="Buscar pedido, destinatario...">
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="tablaPendientes" class="table table-hover align-middle" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th>Evento</th>
                                <th>Pedido</th>
                                <th>Destinatario</th>
                                <th>Fecha</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pendientes as $notif):
                            $config = LogisticaNotificationModel::getTipoConfig($notif['tipo'] ?? '');
                            $orden  = htmlspecialchars($notif['numero_orden'] ?? ('Pedido #' . $notif['pedido_id']));
                            $dest   = htmlspecialchars($notif['destinatario'] ?? '—');
                            $fecha  = date('d/m H:i', strtotime($notif['created_at']));
                        ?>
                            <tr id="row-notif-<?= $notif['id'] ?>">
                                <td>
                                    <span class="notif-icon <?= $config['color'] ?> d-inline-flex" style="width:32px;height:32px;font-size:0.9rem;border-radius:6px;">
                                        <i class="bi <?= $config['icon'] ?> m-auto"></i>
                                    </span>
                                    <span class="ms-2 small fw-semibold"><?= $config['label'] ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= $orden ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($notif['titulo']) ?></small>
                                </td>
                                <td><span class="text-muted small"><?= $dest ?></span></td>
                                <td><small><?= $fecha ?></small></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <?php if ($notif['pedido_id']): ?>
                                        <a href="<?= RUTA_URL ?>logistica/ver/<?= $notif['pedido_id'] ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-box-seam"></i> Ver
                                        </a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-secondary btn-marcar-leida"
                                                data-id="<?= $notif['id'] ?>"
                                                title="Marcar como leída">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======================== TAB: ACTUALIZACIONES ======================== -->
        <div class="tab-pane fade <?= $paneUpdates ?>" id="pills-updates" role="tabpanel">
            <?php if (empty($actualizacionesPorPedido)): ?>
                <div class="empty-state">
                    <i class="bi bi-arrow-repeat d-block mb-3"></i>
                    <h5>Sin actualizaciones recientes</h5>
                    <p class="mb-0">Aquí aparecerán los cambios de estado y eventos de tus pedidos.</p>
                </div>
            <?php else: ?>
                <!-- Buscador actualizaciones -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="updatesSearch"
                                   class="form-control border-start-0 ps-0"
                                   placeholder="Buscar por pedido o destinatario...">
                        </div>
                    </div>
                </div>

                <div class="row g-3" id="updatesGrid">
                    <?php foreach ($actualizacionesPorPedido as $notif):
                        renderLogisticaNotifCard($notif);
                    endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======================== TAB: HISTORIAL COMPLETO ======================== -->
        <div class="tab-pane fade <?= $paneAll ?>" id="pills-all" role="tabpanel">

            <?php if ($totalPages > 1): ?>
            <div class="alert mb-3" style="background:linear-gradient(135deg,#e3f2fd 0%,#f3e5f5 100%);border:1px solid rgba(13,110,253,0.15);border-radius:10px;padding:.875rem 1.25rem;">
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle text-primary me-2" style="font-size:1.25rem;"></i>
                    <div class="small">
                        <strong class="text-primary">Paginación:</strong>
                        Página <strong><?= $currentPage ?></strong> de <strong><?= $totalPages ?></strong>
                        <span class="badge bg-primary bg-opacity-10 text-primary ms-2"><?= $pagination['total_items'] ?? 0 ?> registros</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Buscador historial (AJAX) -->
            <div class="row mb-3">
                <div class="col-md-7 col-lg-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="historialSearch"
                               class="form-control border-start-0 ps-0"
                               placeholder="Buscar por núm. orden, destinatario, tipo..."
                               value="<?= htmlspecialchars($datos['search_query'] ?? '') ?>">
                        <span class="input-group-text bg-white" id="searchSpinner" style="display:none;">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Grid resultado de búsqueda o historial inicial -->
            <div id="historialContainer">
                <?php if (empty($notificaciones)): ?>
                    <div class="empty-state" id="emptyHistorial">
                        <i class="bi bi-archive d-block mb-3"></i>
                        <h5>Sin notificaciones</h5>
                        <p class="mb-0">Cuando sucedan eventos en tus pedidos aparecerán aquí.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($groupedByDate as $label => $group):
                        if (empty($group)) continue;
                    ?>
                        <div class="timeline-label"><?= $label ?></div>
                        <div class="row g-3">
                            <?php foreach ($group as $notif): renderLogisticaNotifCard($notif); endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Paginación -->
                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-4 d-flex justify-content-center">
                        <ul class="pagination">
                            <?php if ($currentPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?tab=all&page=<?= $currentPage - 1 ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="?tab=all&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($currentPage < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?tab=all&page=<?= $currentPage + 1 ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div><!-- /.tab-pane all -->
    </div><!-- /.tab-content -->

</div><!-- /.container-fluid -->

<script>
(function() {
    const RUTA_URL = '<?= RUTA_URL ?>';

    // ────────────────────────────────────────────────
    // Marcar una notificación como leída
    // ────────────────────────────────────────────────
    function marcarLeida(id, btn) {
        fetch(RUTA_URL + 'logistica/notificaciones/marcarLeida/' + id, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Remover clases visuals de no-leída
                    const card = document.getElementById('notif-card-' + id);
                    if (card) {
                        card.classList.remove('unread', 'shadow-sm');
                        card.style.background = 'white';
                    }
                    // Remover fila en tabla si existe
                    const row = document.getElementById('row-notif-' + id);
                    if (row) row.style.opacity = '0.4';

                    // Actualizar badge header
                    updateUnreadBadge(data.unread_count);

                    // Quitar el botón de marcar leída
                    if (btn) btn.remove();
                }
            })
            .catch(console.error);
    }

    // ────────────────────────────────────────────────
    // Marcar todas como leídas
    // ────────────────────────────────────────────────
    const btnTodas = document.getElementById('btnMarcarTodasLeidas');
    if (btnTodas) {
        btnTodas.addEventListener('click', function() {
            if (!confirm('¿Marcar todas las notificaciones como leídas?')) return;
            fetch(RUTA_URL + 'logistica/notificaciones/marcarTodasLeidas', {
                method: 'POST',
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notif-card.unread').forEach(card => {
                        card.classList.remove('unread', 'shadow-sm');
                        card.style.background = 'white';
                    });
                    document.querySelectorAll('.btn-marcar-leida').forEach(b => b.remove());
                    updateUnreadBadge(0);
                    btnTodas.remove();
                }
            })
            .catch(console.error);
        });
    }

    // ────────────────────────────────────────────────
    // Delegar click en botones de marcar leída
    // ────────────────────────────────────────────────
    document.body.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-marcar-leida');
        if (btn) {
            e.preventDefault();
            const id = btn.dataset.id;
            marcarLeida(id, btn);
        }
    });

    // ────────────────────────────────────────────────
    // Actualizar badge de no leídas en el header del tab
    // ────────────────────────────────────────────────
    function updateUnreadBadge(count) {
        // Badge en el header principal
        const headerBadge = document.querySelector('.logistica-inbox-header .badge.bg-primary');
        if (headerBadge) {
            if (count > 0) headerBadge.textContent = count + ' sin leer';
            else headerBadge.remove();
        }
        // Badge en el tab "Por Atender"
        const tabBadge = document.querySelector('#pills-pendientes-tab .badge.bg-danger');
        if (tabBadge) {
            if (count > 0) tabBadge.textContent = count;
            else tabBadge.remove();
        }
    }

    // ────────────────────────────────────────────────
    // Búsqueda DataTable para tab "Por Atender"
    // ────────────────────────────────────────────────
    const tablaEl = document.getElementById('tablaPendientes');
    if (tablaEl && typeof $ !== 'undefined' && $.fn.DataTable) {
        const dtPend = $('#tablaPendientes').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json' },
            dom: 'rtip',
            pageLength: 20,
            order: [[3, 'desc']],
        });
        const pendSearch = document.getElementById('pendientesSearch');
        if (pendSearch) {
            pendSearch.addEventListener('input', function() {
                dtPend.search(this.value).draw();
            });
        }
    }

    // ────────────────────────────────────────────────
    // Búsqueda en vivo para "Actualizaciones" (filtrado local)
    // ────────────────────────────────────────────────
    const updSearch = document.getElementById('updatesSearch');
    if (updSearch) {
        updSearch.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#updatesGrid .col-12').forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

    // ────────────────────────────────────────────────
    // Búsqueda AJAX para "Historial Completo"
    // ────────────────────────────────────────────────
    const histSearch  = document.getElementById('historialSearch');
    const histContainer = document.getElementById('historialContainer');
    const searchSpinner = document.getElementById('searchSpinner');
    let histTimer;

    if (histSearch && histContainer) {
        histSearch.addEventListener('input', function() {
            clearTimeout(histTimer);
            const q = this.value.trim();
            histTimer = setTimeout(() => {
                if (searchSpinner) searchSpinner.style.display = '';
                const url = RUTA_URL + 'logistica/notificaciones?ajax_search=1&q=' + encodeURIComponent(q) + '&tab=all';
                fetch(url, { credentials: 'same-origin' })
                    .then(r => r.text())
                    .then(html => {
                        histContainer.innerHTML = html;
                        if (searchSpinner) searchSpinner.style.display = 'none';
                    })
                    .catch(() => { if (searchSpinner) searchSpinner.style.display = 'none'; });
            }, 350);
        });
    }

})();
</script>

<?php include("vista/includes/footer.php"); ?>
