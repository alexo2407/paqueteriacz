<?php
    require_once __DIR__ . '/../../utils/permissions.php';

    // ── Roles / home URL ────────────────────────────────────────────────────
    $rolesNombres = $_SESSION['roles_nombres'] ?? [];
    $isRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $rolesNombres, true);
    $isAdmin      = in_array(ROL_NOMBRE_ADMIN,      $rolesNombres, true);
    $isVendedor   = in_array(ROL_NOMBRE_VENDEDOR,   $rolesNombres, true);
    $isProveedor  = in_array(ROL_NOMBRE_PROVEEDOR,  $rolesNombres, true);

    $rolProveedorCRM = defined('ROL_NOMBRE_PROVEEDOR_CRM') ? ROL_NOMBRE_PROVEEDOR_CRM : 'Proveedor CRM';
    $rolClienteCRM   = defined('ROL_NOMBRE_CLIENTE_CRM')   ? ROL_NOMBRE_CLIENTE_CRM   : 'Cliente CRM';
    $isProveedorCRM  = in_array($rolProveedorCRM, $rolesNombres, true);
    $isClienteCRM    = in_array($rolClienteCRM,   $rolesNombres, true);
    $isNutraTradeClient = in_array(ROL_NOMBRE_PROVEEDOR, $rolesNombres, true) || ($_SESSION['rol'] ?? null) == (defined('ROL_PROVEEDOR') ? ROL_PROVEEDOR : 4);
    $isMensajero     = in_array(ROL_NOMBRE_CLIENTE, $rolesNombres, true) || ($_SESSION['rol'] ?? null) == (defined('ROL_CLIENTE') ? ROL_CLIENTE : 5);
    
    $isClienteId     = in_array(ROL_CLIENTE, $_SESSION['roles'] ?? [], true) || ($_SESSION['rol'] ?? null) == ROL_CLIENTE;
    $isCliente       = $isClienteId || in_array(ROL_NOMBRE_CLIENTE, $rolesNombres, true);

    if ($isRepartidor && !$isAdmin) {
        $homeUrl = RUTA_URL . 'seguimiento/listar';
    } elseif (($isProveedorCRM || $isClienteCRM) && !$isAdmin) {
        $homeUrl = RUTA_URL . 'crm/notificaciones';
    } elseif ($isNutraTradeClient && !$isAdmin) {
        $homeUrl = RUTA_URL . 'seguimiento/admin_tracking';
    } elseif ($isMensajero && !$isAdmin) {
        $homeUrl = RUTA_URL . 'logistica/dashboard';
    } else {
        $homeUrl = RUTA_URL . 'dashboard';
    }

    // ── Notificaciones ──────────────────────────────────────────────────────
    $unreadCount        = 0;
    $showProviderLeads  = false;
    $notifUrl           = RUTA_URL . 'crm/notificaciones'; // default
    $isLogisticaCliente = $isCliente && !$isAdmin && !$isProveedorCRM && !$isClienteCRM;
    $navNotifPreview    = []; // Para el dropdown preview (últimas 5)
    $navNotifModulo     = 'crm'; // 'logistica' | 'crm'

    if (!$isRepartidor || $isAdmin) {
        $navUserId = $_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? 0;

        if ($isLogisticaCliente) {
            require_once 'modelo/logistica_notification.php';
            $unreadCount     = $navUserId > 0 ? LogisticaNotificationModel::contarNoLeidas($navUserId) : 0;
            $notifUrl        = RUTA_URL . 'logistica/notificaciones';
            $navNotifModulo  = 'logistica';
            if ($navUserId > 0) {
                $navNotifPreview = LogisticaNotificationModel::obtenerPorUsuario($navUserId, 5, 0, false, '');
                foreach ($navNotifPreview as &$np) {
                    if (isset($np['payload']) && is_string($np['payload'])) {
                        $np['payload'] = json_decode($np['payload'], true) ?? [];
                    }
                }
                unset($np);
            }
        } else {
            require_once 'modelo/crm_notification.php';
            require_once 'modelo/crm_lead.php';
            $showProviderLeads = $isProveedor && !$isAdmin;
            if ($showProviderLeads) {
                $unreadCount = $navUserId > 0 ? CrmLead::contarPendientesProveedor($navUserId) : 0;
            } else {
                $unreadCount = $navUserId > 0 ? CrmNotificationModel::contarNoLeidas($navUserId) : 0;
                if ($navUserId > 0) {
                    $navNotifPreview = CrmNotificationModel::obtenerPorUsuario($navUserId, false, 5, 0) ?? [];
                }
            }
        }
    }
    $userName = $_SESSION['nombre'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App RutaEx-Latam</title>

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?= RUTA_URL ?>apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= RUTA_URL ?>favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= RUTA_URL ?>favicon-16x16.png">
    <link rel="manifest" href="<?= RUTA_URL ?>site.webmanifest">

    <!-- Google Fonts — Outfit + Montserrat + Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Montserrat:ital,wght@0,700;0,900;1,900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Select2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <?php if (!empty($usaDataTables)): ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
    <?php endif; ?>
    <!-- Shell Bootstrap (tema marca RutaEx Latam) -->
    <link rel="stylesheet" href="<?= RUTA_URL ?>vista/css/estilos_bs.css?v=<?= filemtime(__DIR__.'/../css/estilos_bs.css') ?>">
    <!-- Estilos propios de la app -->
    <link rel="stylesheet" href="<?= RUTA_URL ?>vista/css/estilos.css">
    <!-- Variables JS globales disponibles para todos los scripts de la app -->
    <meta name="base-url" content="<?= RUTA_URL ?>">
    <script>
        const RUTA_URL = '<?= RUTA_URL ?>';
    </script>
    <!-- Web Push: Service Worker manager -->
    <script src="<?= RUTA_URL ?>js/push-manager.js" defer></script>

</head>
<body class="bs-body">

<!-- ══════ NAVBAR ══════ -->
<nav class="bs-navbar navbar navbar-expand-lg">
    <div class="container-fluid px-3">

        <!-- Hamburger / offcanvas trigger -->
        <button class="navbar-toggler me-2" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#bsSidebar"
                aria-controls="bsSidebar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Brand — Logo tipográfico Inter con pipe (Manual de Marca) -->
        <a class="navbar-brand" href="<?= $homeUrl ?>" style="display:flex;align-items:center;gap:0;text-decoration:none;">
            <span style="font-family:'Inter',sans-serif;font-size:1.38rem;font-weight:800;color:#ffffff;letter-spacing:-0.3px;line-height:1;">Ruta<span style="color:#FF8A00;">Ex</span></span>
            <span style="font-family:'Inter',sans-serif;font-size:1.2rem;font-weight:200;color:rgba(255,255,255,0.35);margin:0 7px;line-height:1;">|</span>
            <span style="font-family:'Inter',sans-serif;font-size:1.38rem;font-weight:400;color:rgba(255,255,255,0.85);letter-spacing:0.2px;line-height:1;">Latam</span>
        </a>

        <!-- Derecha: notificaciones + usuario -->
        <ul class="navbar-nav ms-auto flex-row align-items-center gap-2">

            <?php if (!$isRepartidor || $isAdmin): ?>
            <!-- ═ NOTIF DROPDOWN ═ -->
            <li class="nav-item dropdown" id="navNotifDropdown">
                <a class="nav-link px-2 position-relative" href="#"
                   data-bs-toggle="dropdown" data-bs-auto-close="outside"
                   aria-expanded="false" aria-label="Notificaciones">
                    <i class="bi bi-<?= $showProviderLeads ? 'people-fill' : 'bell-fill' ?>" style="font-size:1.25rem"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="bs-nav-badge" id="navNotifBadge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>

                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0"
                     style="width:360px;max-width:95vw;border-radius:12px;overflow:hidden">

                    <!-- Header -->
                    <div class="d-flex align-items-center justify-content-between px-3 py-2"
                         style="background:linear-gradient(135deg,#061C4C,#0B4EA2);">
                        <span class="text-white fw-semibold">
                            <i class="bi bi-bell-fill me-1"></i>
                            <?= $showProviderLeads ? 'Leads pendientes' : 'Notificaciones' ?>
                            <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-white text-primary ms-1" style="font-size:.7rem"><?= $unreadCount ?> nuevas</span>
                            <?php endif; ?>
                        </span>
                        <?php if ($unreadCount > 0 && !$showProviderLeads): ?>
                        <button class="btn btn-sm text-white opacity-75 p-0 border-0" id="navMarkAllRead"
                                style="font-size:.75rem" title="Marcar todo leído">
                            <i class="bi bi-check-all"></i> Todo leído
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Lista -->
                    <div style="max-height:320px;overflow-y:auto">
                    <?php if (empty($navNotifPreview) && !$showProviderLeads): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-bell-slash display-6 opacity-25 d-block mb-1"></i>
                            <small>Sin notificaciones recientes</small>
                        </div>
                    <?php elseif ($showProviderLeads): ?>
                        <div class="px-3 py-3 text-center text-muted">
                            <i class="bi bi-people-fill display-6 opacity-25 d-block mb-1"></i>
                            <small>Tienes <strong><?= $unreadCount ?></strong> leads por atender</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($navNotifPreview as $np):
                            if ($navNotifModulo === 'logistica') {
                                require_once 'modelo/logistica_notification.php';
                                $cfg = LogisticaNotificationModel::getTipoConfig($np['tipo'] ?? '');
                                $npIcon  = $cfg['icon'];
                                $npColor = $cfg['border'];
                                // Lote: ir al dashboard; individual: ir al detalle
                                if (($np['tipo'] ?? '') === 'lote_creado') {
                                    $npUrl = RUTA_URL . 'logistica/dashboard?tab=pedidos';
                                } else {
                                    $npUrl = RUTA_URL . 'logistica/ver/' . ($np['pedido_id'] ?? '');
                                }
                                $npRead  = (bool)($np['is_read'] ?? false);
                                $npId    = $np['id'];
                                $npText  = htmlspecialchars($np['titulo'] ?? '');
                                $npSub   = htmlspecialchars($np['numero_orden'] ? "Pedido #{$np['numero_orden']}" : '');
                                $npTime  = date('d/m H:i', strtotime($np['created_at']));
                            } else {
                                $npIcon  = 'bi-bell';
                                $npColor = '#0B4EA2';
                                $npUrl   = RUTA_URL . 'crm/notificaciones';
                                $npRead  = (bool)($np['is_read'] ?? $np['read'] ?? false);
                                $npId    = $np['id'];
                                $npText  = htmlspecialchars($np['title'] ?? $np['titulo'] ?? $np['message'] ?? '');
                                $npSub   = '';
                                $npTime  = !empty($np['created_at']) ? date('d/m H:i', strtotime($np['created_at'])) : '';
                            }
                        ?>
                        <a href="<?= $npUrl ?>" class="d-flex align-items-start gap-2 px-3 py-2 text-decoration-none notif-drop-item <?= !$npRead ? 'notif-drop-unread' : '' ?>"
                           data-id="<?= $npId ?>" data-modulo="<?= $navNotifModulo ?>">
                            <div class="notif-drop-icon flex-shrink-0" style="border-color:<?= $npColor ?>">
                                <i class="bi <?= $npIcon ?>" style="color:<?= $npColor ?>"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="notif-drop-title"><?= $npText ?></div>
                                <?php if ($npSub): ?>
                                <div class="notif-drop-sub"><?= $npSub ?></div>
                                <?php endif; ?>
                                <div class="notif-drop-time"><?= $npTime ?></div>
                            </div>
                            <?php if (!$npRead): ?>
                            <span class="notif-drop-dot flex-shrink-0"></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>

                    <!-- Footer -->
                    <div class="border-top text-center py-2">
                        <a href="<?= $notifUrl ?>" class="text-primary small fw-semibold text-decoration-none">
                            Ver todas las notificaciones <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                </div>
            </li>
            <?php endif; ?>

            <?php if ($isLogisticaCliente): ?>
            <!-- ═ TOGGLE WEB PUSH ═ -->
            <li class="nav-item">
                <button id="btnTogglePush" type="button"
                        class="nav-link px-2 border-0 bg-transparent"
                        title="Activar notificaciones push del navegador"
                        style="cursor:pointer">
                    <i class="bi bi-bell-slash" style="font-size:1.25rem;color:rgba(255,255,255,0.55)"></i>
                </button>
            </li>
            <?php endif; ?>

            <style>
            .notif-drop-item {
                border-bottom: 1px solid #f1f3f4;
                transition: background .15s;
                color: #212529 !important;          /* texto siempre oscuro */
                background: #ffffff;                /* fondo blanco explicito */
            }
            .notif-drop-item:hover { background: #f8f9fa !important; }
            .notif-drop-unread {
                background: #eef3ff !important;     /* azul muy suave */
            }
            .notif-drop-unread:hover { background: #ddeaff !important; }
            .notif-drop-icon {
                width: 34px; height: 34px;
                border-radius: 8px;
                border: 1.5px solid #dee2e6;
                display: flex; align-items: center; justify-content: center;
                background: #fff;
                flex-shrink: 0;
            }
            .notif-drop-title {
                font-size: .82rem;
                font-weight: 600;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 240px;
                color: #061C4C !important;          /* oscuro, buen contraste */
            }
            .notif-drop-sub  { font-size: .72rem; color: #495057 !important; } /* gris medio */
            .notif-drop-time { font-size: .70rem; color: #6c757d !important; } /* gris suave */
            .notif-drop-dot {
                width: 8px; height: 8px;
                border-radius: 50%;
                background: #0B4EA2;
                margin-top: 4px;
            }
            #navNotifBadge {
                animation: pulse-notif 2s ease-in-out infinite;
            }
            @keyframes pulse-notif {
                0%,100% { transform: scale(1); }
                50% { transform: scale(1.15); }
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to   { transform: rotate(360deg); }
            }
            .spin-icon { animation: spin 0.8s linear infinite; display: inline-block; }
            </style>

            <script>
            (function(){
                // Marcar todo leído desde el dropdown
                const btnAll = document.getElementById('navMarkAllRead');
                if (btnAll) {
                    btnAll.addEventListener('click', function(e){
                        e.stopPropagation();
                        const modulo = '<?= $navNotifModulo ?>';
                        const url    = RUTA_URL + (modulo === 'logistica'
                            ? 'logistica/notificaciones/marcarTodasLeidas'
                            : 'crm/notificaciones/marcarTodasLeidas');
                        fetch(url, { method:'POST', credentials:'same-origin' })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success !== false) {
                                    document.querySelectorAll('.notif-drop-unread').forEach(el => {
                                        el.classList.remove('notif-drop-unread');
                                    });
                                    document.querySelectorAll('.notif-drop-dot').forEach(el => el.remove());
                                    const badge = document.getElementById('navNotifBadge');
                                    if (badge) badge.remove();
                                    btnAll.remove();
                                }
                            }).catch(() => {});
                    });
                }

                // Marcar como leída al hacer clic en un item del dropdown
                document.querySelectorAll('.notif-drop-item').forEach(function(link) {
                    link.addEventListener('click', function() {
                        const id     = this.dataset.id;
                        const modulo = this.dataset.modulo;
                        if (!id || this.classList.contains('notif-drop-read')) return;
                        const url = RUTA_URL + (modulo === 'logistica'
                            ? 'logistica/notificaciones/marcarLeida/' + id
                            : 'crm/notificaciones/marcarLeida/' + id);
                        fetch(url, { credentials: 'same-origin' })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success !== false) {
                                    this.classList.remove('notif-drop-unread');
                                    const dot = this.querySelector('.notif-drop-dot');
                                    if (dot) dot.remove();
                                    const badge = document.getElementById('navNotifBadge');
                                    if (badge) {
                                        const curr = parseInt(badge.textContent) - 1;
                                        if (curr <= 0) badge.remove();
                                        else badge.textContent = curr;
                                    }
                                }
                            }).catch(() => {});
                    });
                });
            })();
            </script>

            <?php if ($userName): ?>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#"
                   data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle"></i>
                    <span class="d-none d-md-inline"><?= htmlspecialchars($userName) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-header"><?= implode(', ', $rolesNombres) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= RUTA_URL ?>usuarios/perfil">
                        <i class="bi bi-person-gear me-2"></i>Editar Perfil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= RUTA_URL ?>salir">
                        <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </li>
            <?php endif; ?>

        </ul>
    </div>
</nav>

<!-- ══════ LAYOUT: sidebar + main ══════ -->
<div class="bs-body-row">

<!-- ══════ SIDEBAR (offcanvas en mobile, fija en desktop) ══════ -->
<div class="offcanvas offcanvas-start offcanvas-lg bs-sidebar"
     tabindex="-1" id="bsSidebar" aria-labelledby="bsSidebarLabel">

    <!-- Header sidebar: solo usuario -->
    <div class="bs-sidebar-header" style="display:flex;justify-content:space-between;align-items:center">
        <div>
            <?php if ($userName): ?>
            <div class="bs-sidebar-brand"><?= htmlspecialchars($userName) ?></div>
            <div class="bs-sidebar-user"><?= implode(', ', $rolesNombres) ?></div>
            <?php else: ?>
            <div class="bs-sidebar-brand">App RutaEx-Latam</div>
            <?php endif; ?>
        </div>
        <button type="button" class="btn-close btn-close-white d-lg-none"
                data-bs-dismiss="offcanvas"></button>
    </div>

    <!-- Nav links -->
    <div class="offcanvas-body p-0" style="overflow-y:auto">
        <nav>
            <!-- ═══ Dashboard ═══ -->
            <?php if ((!$isRepartidor || $isAdmin) && !$isCliente): ?>
            <a href="<?= RUTA_URL ?>dashboard" class="nav-link">
                <i class="bi bi-house-door"></i> Dashboard
            </a>
            <?php endif; ?>

            <!-- ═══ Logística Cliente ═══ -->
            <?php if ($isMensajero): ?>
            <a href="<?= RUTA_URL ?>logistica/dashboard" class="nav-link">
                <i class="bi bi-truck-front"></i> Mis Pedidos
            </a>
            <a href="<?= RUTA_URL ?>logistica/notificaciones" class="nav-link d-flex align-items-center justify-content-between">
                <span><i class="bi bi-bell-fill me-1"></i> Notificaciones</span>
                <?php if ($isLogisticaCliente && $unreadCount > 0): ?>
                <span class="badge bg-danger"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <!-- ═══ Operaciones ═══ -->
            <?php if ($isAdmin || $isProveedor || $isCliente): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Operaciones</div>
            <a href="<?= RUTA_URL ?>pedidos/listar" class="nav-link">
                <i class="bi bi-box-seam"></i> Pedidos
            </a>
            <?php if ($isAdmin): ?>
            <a href="<?= RUTA_URL ?>pedidos/crearPedido" class="nav-link">
                <i class="bi bi-plus-circle"></i> Nuevo Pedido
            </a>
            <?php endif; ?>
            <?php 
            // Tracking de Estados
            if ($isAdmin || $isNutraTradeClient || $isCliente || $sessionRol == 4 || in_array('Cliente', $rolesNombres) || in_array('cliente', $rolesNombres)): 
            ?>
            <a href="<?= RUTA_URL ?>seguimiento/admin_tracking" class="nav-link">
                <i class="bi bi-crosshair"></i>
                <span>Tracking de Estados</span>
            </a>
            <?php endif; ?>
            <a href="<?= RUTA_URL ?>pedidos/reportes" class="nav-link">
                <i class="bi bi-bar-chart-line"></i> Reporte de Pedidos
            </a>
            <!-- ═══ Grupo Informes ═══ -->
            <?php if ($isAdmin || !$isProveedor): ?>
            <a href="#navInformes" class="nav-link d-flex align-items-center justify-content-between"
               data-bs-toggle="collapse" role="button"
               aria-expanded="<?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'informes') !== false) ? 'true' : 'false' ?>"
               aria-controls="navInformes"
               style="cursor:pointer">
                <span><i class="bi bi-graph-up-arrow me-1"></i> Informes</span>
                <i class="bi bi-chevron-down" style="font-size:.7rem;transition:transform .2s" id="chevronInformes"></i>
            </a>
            <div class="collapse <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'informes') !== false) ? 'show' : '' ?>" id="navInformes">
                <a href="<?= RUTA_URL ?>pedidos/informes/estatus" class="nav-link ps-4" style="font-size:.85rem">
                    <i class="bi bi-pie-chart-fill me-1"></i> Estatus de Órdenes
                </a>
                <a href="<?= RUTA_URL ?>pedidos/informes/region" class="nav-link ps-4" style="font-size:.85rem">
                    <i class="bi bi-map me-1"></i> Efectividad por Región
                </a>
                <a href="<?= RUTA_URL ?>pedidos/informes/producto" class="nav-link ps-4" style="font-size:.85rem">
                    <i class="bi bi-box-seam me-1"></i> Efectividad por Producto
                </a>
                <a href="<?= RUTA_URL ?>pedidos/informes/semana" class="nav-link ps-4" style="font-size:.85rem">
                    <i class="bi bi-calendar3-week me-1"></i> Tendencia Semanal
                </a>
            </div>
            <?php endif; // Admin || !$isProveedor (Informes) ?>
            <?php endif; // isAdmin || isProveedor || isCliente (Operaciones) ?>

            <?php if ($isRepartidor || $isAdmin): ?>
            <a href="<?= RUTA_URL ?>seguimiento/listar" class="nav-link">
                <i class="bi bi-geo-alt-fill"></i> Seguimiento
            </a>
            <?php endif; ?>

            <!-- ═══ Inventario ═══ -->
            <?php if ($isAdmin || $isProveedor || $isCliente): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Inventario</div>
            <a href="<?= RUTA_URL ?>productos/listar" class="nav-link"><i class="bi bi-box"></i> Productos</a>
            <?php if ($isAdmin || !$isProveedor): // ocultar gestión de stock al rol Cliente ?>
            <a href="<?= RUTA_URL ?>categorias/listar" class="nav-link"><i class="bi bi-tag"></i> Categorías</a>
            <a href="<?= RUTA_URL ?>stock/listar" class="nav-link"><i class="bi bi-arrow-left-right"></i> Mov. de Stock</a>
            <a href="<?= RUTA_URL ?>stock/kardex" class="nav-link"><i class="bi bi-file-ruled"></i> Kardex</a>
            <?php if ($isAdmin): ?>
            <a href="<?= RUTA_URL ?>stock/movimientos" class="nav-link"><i class="bi bi-file-earmark-bar-graph"></i> Reporte Movimientos</a>
            <a href="<?= RUTA_URL ?>stock/saldo" class="nav-link"><i class="bi bi-bar-chart"></i> Saldo por Producto</a>
            <?php endif; ?>
            <a href="<?= RUTA_URL ?>stock/inventario_periodo" class="nav-link"><i class="bi bi-calendar-range"></i> Inventario Período</a>
            <a href="<?= RUTA_URL ?>stock/crear" class="nav-link"><i class="bi bi-plus-circle"></i> Nuevo Movimiento</a>
            <?php endif; ?>
            <?php endif; ?>

            <!-- ═══ Geografía ═══ -->
            <?php if ($isAdmin || $isVendedor): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Geografía</div>
            <a href="<?= RUTA_URL ?>codigos_postales" class="nav-link"><i class="bi bi-mailbox"></i> Códigos Postales</a>
            <a href="<?= RUTA_URL ?>paises/listar" class="nav-link"><i class="bi bi-globe2"></i> Países</a>
            <a href="<?= RUTA_URL ?>departamentos/listar" class="nav-link"><i class="bi bi-map"></i> Departamentos</a>
            <a href="<?= RUTA_URL ?>municipios/listar" class="nav-link"><i class="bi bi-geo"></i> Municipios</a>
            <a href="<?= RUTA_URL ?>barrios/listar" class="nav-link"><i class="bi bi-building"></i> Barrios</a>
            <a href="<?= RUTA_URL ?>monedas/listar" class="nav-link"><i class="bi bi-currency-dollar"></i> Monedas</a>
            <?php elseif ($isProveedor || $isCliente): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Geografía</div>
            <a href="<?= RUTA_URL ?>codigos_postales" class="nav-link"><i class="bi bi-mailbox"></i> Códigos Postales</a>
            <?php endif; ?>


            <!-- ═══ CRM Relay ═══ -->
            <?php if ($isAdmin || $isProveedorCRM || $isClienteCRM): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">CRM Relay</div>
            <?php if ($isAdmin || $isProveedorCRM): ?>
            <a href="<?= RUTA_URL ?>crm/dashboard" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard CRM</a>
            <a href="<?= RUTA_URL ?>crm/listar" class="nav-link"><i class="bi bi-people"></i> Leads</a>
            <?php endif; ?>
            <a href="<?= RUTA_URL ?>crm/notificaciones" class="nav-link">
                <i class="bi bi-bell-fill"></i> Notificaciones CRM
                <?php if (!$isLogisticaCliente && $unreadCount > 0): ?>
                <span class="badge bg-danger ms-auto"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
            <?php if ($isAdmin): ?>
            <a href="<?= RUTA_URL ?>crm/integraciones" class="nav-link"><i class="bi bi-link-45deg"></i> Integraciones</a>
            <a href="<?= RUTA_URL ?>crm/monitor" class="nav-link"><i class="bi bi-activity"></i> Monitor Worker</a>
            <a href="<?= RUTA_URL ?>crm/reportes" class="nav-link"><i class="bi bi-graph-up"></i> Reportes</a>
            <?php endif; ?>
            <?php endif; ?>

            <!-- ═══ Integraciones (Admin) ═══ -->
            <?php if ($isAdmin): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Integraciones</div>
            <a href="<?= RUTA_URL ?>forwarding" class="nav-link"><i class="bi bi-arrows-left-right"></i> Forwarding</a>
            <a href="<?= RUTA_URL ?>webhooks" class="nav-link"><i class="bi bi-wifi"></i> Webhooks</a>
            <?php endif; ?>

            <!-- ═══ Administración (Admin) ═══ -->
            <?php if ($isAdmin): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Administración</div>
            <a href="<?= RUTA_URL ?>usuarios/listar" class="nav-link"><i class="bi bi-person-badge"></i> Usuarios</a>
            <a href="<?= RUTA_URL ?>auditoria/historial" class="nav-link"><i class="bi bi-shield-check"></i> Auditoría</a>
            <a href="<?= RUTA_URL ?>auditoria/accesos" class="nav-link"><i class="bi bi-person-lock"></i> Hist. Accesos</a>
            <?php endif; ?>

            <?php /* Auditoría solo visible para Admin */ ?>


            <!-- ═══ Documentación (Admin) ═══ -->
            <?php if ($isAdmin): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Documentación</div>
            <a href="<?= RUTA_URL ?>api/doc/" class="nav-link"><i class="bi bi-code-slash"></i> API Docs</a>
            <a href="<?= RUTA_URL ?>api/doc/generator.php" class="nav-link" style="background:linear-gradient(90deg,rgba(255,138,0,.12),transparent);border-left:2px solid #FF8A00"><i class="bi bi-stars"></i> Generar Doc. API</a>
            <a href="<?= RUTA_URL ?>api/doc/historial.php" class="nav-link"><i class="bi bi-clock-history"></i> Historial Docs</a>
            <a href="<?= RUTA_URL ?>api/doc/crmdoc.php" class="nav-link"><i class="bi bi-file-earmark-code"></i> Doc. CRM</a>
            <a href="<?= RUTA_URL ?>crm/database_doc" class="nav-link"><i class="bi bi-database"></i> Doc. Base de Datos</a>
            <a href="<?= RUTA_URL ?>crm/logistics_worker_doc" class="nav-link"><i class="bi bi-diagram-3"></i> Doc. Worker Logístico</a>
            <?php endif; ?>

            <!-- Logout -->
            <hr class="sidebar-divider">
            <?php if ($userName): ?>
            <a href="<?= RUTA_URL ?>usuarios/perfil" class="nav-link"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($userName) ?></a>
            <?php endif; ?>
            <a href="<?= RUTA_URL ?>salir" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
        </nav>
    </div>
</div><!-- /sidebar -->

<script>
// ── Chevron Informes collapse ─────────────────────────────────────────────────
(function() {
    const el = document.getElementById('navInformes');
    const ch = document.getElementById('chevronInformes');
    if (!el || !ch) return;
    el.addEventListener('show.bs.collapse',  () => ch.style.transform = 'rotate(-180deg)');
    el.addEventListener('hide.bs.collapse',  () => ch.style.transform = 'rotate(0deg)');
    if (el.classList.contains('show')) ch.style.transform = 'rotate(-180deg)';
})();

// ── FIX: Bootstrap 5 offcanvas inyecta padding-right al body en desktop ──
// Solo limpiamos padding-right (el padding-top lo maneja el CSS con body.bs-body).
(function() {
    function fixPaddingRight() {
        if (document.body.style.paddingRight) {
            document.body.style.paddingRight = '';
        }
    }
    var observer = new MutationObserver(fixPaddingRight);
    observer.observe(document.body, { attributes: true, attributeFilter: ['style'] });
})();
</script>

<!-- ══════ MAIN CONTENT ══════ -->
<main class="bs-main">
<?php require_once __DIR__ . '/breadcrumb.php'; ?>
<div class="bs-page-container">