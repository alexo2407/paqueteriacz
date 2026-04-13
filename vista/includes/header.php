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

    <!-- Google Fonts — Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
    <!-- Shell Bootstrap (tema oscuro sidebar + navbar) -->
    <link rel="stylesheet" href="<?= RUTA_URL ?>vista/css/estilos_bs.css">
    <!-- Estilos propios de la app -->
    <link rel="stylesheet" href="<?= RUTA_URL ?>vista/css/estilos.css">

    <style>
        .brand-title {
            font-size: 1.25rem; font-weight: 900; 
            text-transform: uppercase; font-style: italic; 
            letter-spacing: -0.5px; color: #fff; line-height: 1;
        }
        .brand-subtitle {
            font-size: 0.55rem; font-weight: 800; 
            text-transform: uppercase; letter-spacing: 0.25em; 
            color: rgba(255,255,255,1); margin-top: 1px;
        }
    </style>
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

        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $homeUrl ?>" style="min-width: 0; overflow: visible;">
            <!-- Icono (Oculto en móvil) -->
            <div class="d-none d-md-flex" style="flex-shrink: 0; align-items: center;">
                <img src="<?= RUTA_URL ?>vista/img/icono-logo.png" alt="Icono" style="height: 34px; width: auto; filter: brightness(0) invert(1); object-fit: contain;">
            </div>
            <!-- Textos (Estables) -->
            <div class="d-flex flex-column" style="white-space: nowrap;">
                <span class="brand-title">RutaEx-Latam</span>
                <span class="brand-subtitle">Pan-Latam Logistics</span>
            </div>
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
                         style="background:linear-gradient(135deg,#0d6efd,#0a58ca);">
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
                                $npColor = '#0d6efd';
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
                color: #1a1a2e !important;          /* oscuro, buen contraste */
            }
            .notif-drop-sub  { font-size: .72rem; color: #495057 !important; } /* gris medio */
            .notif-drop-time { font-size: .70rem; color: #6c757d !important; } /* gris suave */
            .notif-drop-dot {
                width: 8px; height: 8px;
                border-radius: 50%;
                background: #0d6efd;
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
            <!-- Dashboard -->
            <?php if ((!$isRepartidor || $isAdmin) && !$isCliente): ?>
            <a href="<?= RUTA_URL ?>dashboard" class="nav-link">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <?php endif; ?>
            <?php if ($isMensajero): ?>
            <a href="<?= RUTA_URL ?>logistica/dashboard" class="nav-link">
                <i class="bi bi-truck"></i> Mis Pedidos
            </a>
            <a href="<?= RUTA_URL ?>logistica/notificaciones" class="nav-link d-flex align-items-center justify-content-between">
                <span><i class="bi bi-bell me-1"></i> Notificaciones</span>
                <?php if ($isLogisticaCliente && $unreadCount > 0): ?>
                <span class="badge bg-danger"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <?php if ($isCliente || $isProveedor || $isAdmin): ?>
            <a href="<?= RUTA_URL ?>codigos_postales" class="nav-link">
                <i class="bi bi-geo-fill"></i> Códigos Postales
            </a>
            <a href="<?= RUTA_URL ?>auditoria/historial" class="nav-link">
                <i class="bi bi-clock-history"></i> Auditoría
            </a>
            <?php endif; ?>

            <!-- Operaciones -->
            <?php if ($isAdmin || $isProveedor || $isCliente): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Operaciones</div>
            <a href="<?= RUTA_URL ?>pedidos/listar" class="nav-link">
                <i class="bi bi-clipboard-check"></i> Pedidos
            </a>
            <?php if ($isAdmin): ?>
            <a href="<?= RUTA_URL ?>pedidos/crearPedido" class="nav-link">
                <i class="bi bi-plus-circle"></i> Nuevo Pedido
            </a>
            <?php endif; ?>

            <?php 
            // Tracking de Estados
            if ($isAdmin || $isNutraTradeClient || $isCliente || $sessionRol == 4 || in_array('Cliente', $rolesNamesArr) || in_array('cliente', $rolesNamesArr)): 
            ?>
            <a href="<?= RUTA_URL ?>seguimiento/admin_tracking" class="nav-link">
                <i class="bi bi-geo-fill"></i>
                <span>Tracking de Estados</span>
            </a>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($isRepartidor || $isAdmin): ?>
            <a href="<?= RUTA_URL ?>seguimiento/listar" class="nav-link">
                <i class="bi bi-geo-alt"></i> Seguimiento
            </a>
            <?php endif; ?>

            <!-- Inventario -->
            <?php if ($isAdmin || $isProveedor || $isCliente): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Inventario</div>
            <a href="<?= RUTA_URL ?>productos/listar" class="nav-link"><i class="bi bi-grid"></i> Productos</a>
            <a href="<?= RUTA_URL ?>categorias/listar" class="nav-link"><i class="bi bi-folder2"></i> Categorías</a>
            <a href="<?= RUTA_URL ?>stock/listar" class="nav-link"><i class="bi bi-arrow-down-up"></i> Mov. de Stock</a>
            <a href="<?= RUTA_URL ?>stock/kardex" class="nav-link"><i class="bi bi-file-earmark-text"></i> Kardex</a>
            <?php if ($isAdmin): ?>
            <a href="<?= RUTA_URL ?>stock/movimientos" class="nav-link"><i class="bi bi-journal-arrow-down"></i> Reporte Movimientos</a>
            <a href="<?= RUTA_URL ?>stock/saldo" class="nav-link"><i class="bi bi-bar-chart-steps"></i> Saldo por Producto</a>
            <?php endif; ?>
            <a href="<?= RUTA_URL ?>stock/inventario_periodo" class="nav-link"><i class="bi bi-table"></i> Inventario Período</a>
            <a href="<?= RUTA_URL ?>stock/crear" class="nav-link"><i class="bi bi-plus-circle"></i> Nuevo Movimiento</a>
            <?php endif; ?>

            <!-- Catálogos -->
            <?php if ($isAdmin || $isProveedor || $isVendedor): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Catálogos</div>
            <a href="<?= RUTA_URL ?>paises/listar" class="nav-link"><i class="bi bi-globe"></i> Países</a>
            <a href="<?= RUTA_URL ?>departamentos/listar" class="nav-link"><i class="bi bi-map"></i> Departamentos</a>
            <a href="<?= RUTA_URL ?>municipios/listar" class="nav-link"><i class="bi bi-pin-map"></i> Municipios</a>
            <a href="<?= RUTA_URL ?>barrios/listar" class="nav-link"><i class="bi bi-building"></i> Barrios</a>
            <a href="<?= RUTA_URL ?>codigos_postales" class="nav-link"><i class="bi bi-geo-fill"></i> Códigos Postales</a>
            <a href="<?= RUTA_URL ?>monedas/listar" class="nav-link"><i class="bi bi-currency-exchange"></i> Monedas</a>
            <?php endif; ?>

            <!-- CRM -->
            <?php if ($isAdmin || $isProveedorCRM || $isClienteCRM): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">CRM Relay</div>
            <?php if ($isAdmin || $isProveedorCRM): ?>
            <a href="<?= RUTA_URL ?>crm/dashboard" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard CRM</a>
            <a href="<?= RUTA_URL ?>crm/listar" class="nav-link"><i class="bi bi-people-fill"></i> Leads</a>
            <?php endif; ?>
            <a href="<?= RUTA_URL ?>crm/notificaciones" class="nav-link">
                <i class="bi bi-bell"></i> Notificaciones CRM
                <?php if (!$isLogisticaCliente && $unreadCount > 0): ?>
                <span class="badge bg-danger ms-auto"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
            <?php if ($isAdmin): ?>
            <a href="<?= RUTA_URL ?>crm/integraciones" class="nav-link"><i class="bi bi-plug"></i> Integraciones</a>
            <a href="<?= RUTA_URL ?>crm/monitor" class="nav-link"><i class="bi bi-activity"></i> Monitor Worker</a>
            <a href="<?= RUTA_URL ?>crm/reportes" class="nav-link"><i class="bi bi-graph-up"></i> Reportes</a>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Administración -->
            <?php if ($isAdmin): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Administración</div>
            <a href="<?= RUTA_URL ?>usuarios/listar" class="nav-link"><i class="bi bi-people"></i> Usuarios</a>
            <a href="<?= RUTA_URL ?>auditoria/historial" class="nav-link"><i class="bi bi-clock-history"></i> Auditoría</a>
            <a href="<?= RUTA_URL ?>auditoria/accesos" class="nav-link"><i class="bi bi-person-badge"></i> Hist. Accesos</a>
            <a href="<?= RUTA_URL ?>webhooks" class="nav-link"><i class="bi bi-broadcast"></i> Webhooks</a>
            <a href="<?= RUTA_URL ?>api/doc/" class="nav-link"><i class="bi bi-book"></i> API Docs</a>
            <a href="<?= RUTA_URL ?>api/doc/crmdoc.php" class="nav-link"><i class="bi bi-file-earmark-code"></i> Doc. CRM</a>
            <a href="<?= RUTA_URL ?>crm/database_doc" class="nav-link"><i class="bi bi-database"></i> Doc. Base de Datos</a>
            <a href="<?= RUTA_URL ?>crm/logistics_worker_doc" class="nav-link"><i class="bi bi-diagram-3"></i> Doc. Worker Logístico</a>
            <?php endif; ?>
            <?php if ($isProveedor && !$isAdmin): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Administración</div>
            <a href="<?= RUTA_URL ?>auditoria/historial" class="nav-link"><i class="bi bi-clock-history"></i> Auditoría</a>
            <?php endif; ?>

            <!-- Logout -->
            <hr class="sidebar-divider">
            <?php if ($userName): ?>
            <a href="<?= RUTA_URL ?>usuarios/perfil" class="nav-link"><i class="bi bi-person"></i> <?= htmlspecialchars($userName) ?></a>
            <?php endif; ?>
            <a href="<?= RUTA_URL ?>salir" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
        </nav>
    </div>
</div><!-- /sidebar -->

<!-- ══════ MAIN CONTENT ══════ -->
<main class="bs-main">
<?php require_once __DIR__ . '/breadcrumb.php'; ?>
<div class="bs-page-container">