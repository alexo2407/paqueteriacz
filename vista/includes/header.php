<?php
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
    $isClienteId     = in_array(ROL_CLIENTE, $_SESSION['roles'] ?? [], true) || ($_SESSION['rol'] ?? null) == ROL_CLIENTE;
    $isCliente       = $isClienteId || in_array(ROL_NOMBRE_CLIENTE, $rolesNombres, true);

    if ($isRepartidor && !$isAdmin) {
        $homeUrl = RUTA_URL . 'seguimiento/listar';
    } elseif (($isProveedorCRM || $isClienteCRM) && !$isAdmin) {
        $homeUrl = RUTA_URL . 'crm/notificaciones';
    } elseif ($isCliente && !$isAdmin) {
        $homeUrl = RUTA_URL . 'pedidos/listar';
    } else {
        $homeUrl = RUTA_URL . 'dashboard';
    }

    // ── Notificaciones ──────────────────────────────────────────────────────
    $unreadCount = 0;
    $showProviderLeads = false;
    if (!$isRepartidor || $isAdmin) {
        require_once "modelo/crm_notification.php";
        require_once "modelo/crm_lead.php";
        $navUserId = $_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? 0;
        $showProviderLeads = $isProveedor && !$isAdmin;
        if ($showProviderLeads) {
            $unreadCount = $navUserId > 0 ? CrmLead::contarPendientesProveedor($navUserId) : 0;
        } else {
            $unreadCount = $navUserId > 0 ? CrmNotificationModel::contarNoLeidas($navUserId) : 0;
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
            <li class="nav-item position-relative">
                <a class="nav-link px-2" href="<?= RUTA_URL ?>crm/notificaciones"
                   title="<?= $showProviderLeads ? 'Leads' : 'Notificaciones' ?>">
                    <i class="bi bi-<?= $showProviderLeads ? 'people-fill' : 'bell' ?>" style="font-size:1.2rem"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="bs-nav-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

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
            <?php if ($isCliente): ?>
            <a href="<?= RUTA_URL ?>logistica/dashboard" class="nav-link">
                <i class="bi bi-truck"></i> Mis Pedidos
            </a>
            <a href="<?= RUTA_URL ?>productos/listar" class="nav-link">
                <i class="bi bi-box-seam"></i> Mis Productos
            </a>
            <?php endif; ?>

            <!-- Operaciones -->
            <?php if ($isAdmin || $isProveedor): ?>
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
            <?php endif; ?>
            <?php if ($isRepartidor || $isAdmin): ?>
            <a href="<?= RUTA_URL ?>seguimiento/listar" class="nav-link">
                <i class="bi bi-geo-alt"></i> Seguimiento
            </a>
            <?php endif; ?>

            <!-- Inventario -->
            <?php if ($isAdmin || $isProveedor): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-label">Inventario</div>
            <a href="<?= RUTA_URL ?>productos/listar" class="nav-link"><i class="bi bi-grid"></i> Productos</a>
            <a href="<?= RUTA_URL ?>categorias/listar" class="nav-link"><i class="bi bi-folder2"></i> Categorías</a>
            <a href="<?= RUTA_URL ?>stock/listar" class="nav-link"><i class="bi bi-arrow-down-up"></i> Mov. de Stock</a>
            <a href="<?= RUTA_URL ?>stock/kardex" class="nav-link"><i class="bi bi-file-earmark-text"></i> Kardex</a>
            <a href="<?= RUTA_URL ?>stock/movimientos" class="nav-link"><i class="bi bi-journal-arrow-down"></i> Reporte Movimientos</a>
            <a href="<?= RUTA_URL ?>stock/saldo" class="nav-link"><i class="bi bi-bar-chart-steps"></i> Saldo por Producto</a>
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
                <i class="bi bi-bell"></i> Notificaciones
                <?php if ($unreadCount > 0): ?>
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
            <a href="<?= RUTA_URL ?>api/doc/" class="nav-link"><i class="bi bi-book"></i> API Docs</a>
            <a href="<?= RUTA_URL ?>api/doc/crmdoc.php" class="nav-link"><i class="bi bi-file-earmark-code"></i> Doc. CRM</a>
            <a href="<?= RUTA_URL ?>crm/database_doc" class="nav-link"><i class="bi bi-database"></i> Doc. Base de Datos</a>
            <a href="<?= RUTA_URL ?>crm/logistics_worker_doc" class="nav-link"><i class="bi bi-diagram-3"></i> Doc. Worker Logístico</a>
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