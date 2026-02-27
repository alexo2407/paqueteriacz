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

    <script>const RUTA_URL = '<?= RUTA_URL ?>';</script>
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
        <a class="navbar-brand" href="<?= $homeUrl ?>">
            <i class="bi bi-box-seam"></i> RutaEx-Latam
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

    <!-- Header sidebar -->
    <div class="offcanvas-header bs-sidebar-header d-flex justify-content-between align-items-start p-0">
        <div class="bs-sidebar-header w-100">
            <div class="bs-sidebar-brand">
                <i class="bi bi-box-seam me-1"></i> App RutaEx-Latam
            </div>
            <?php if ($userName): ?>
            <div class="bs-sidebar-user"><?= htmlspecialchars($userName) ?></div>
            <?php endif; ?>
        </div>
        <button type="button" class="btn-close btn-close-white d-lg-none"
                data-bs-dismiss="offcanvas" style="margin:.75rem .75rem 0 0"></button>
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
<div class="bs-page-container">