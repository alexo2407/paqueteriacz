<?php
/**
 * header_materialize.php
 * Layout header para TODA la aplicación. Carga Materialize siempre.
 * Carga Bootstrap 5 también por defecto (para vistas que aún usan clases Bootstrap).
 *
 * Variables opcionales antes del include:
 *   $loadBootstrap = false;  → suprime Bootstrap CSS/JS (solo Materialize)
 *   $usaDataTables = true;   → carga DataTables JS en el footer
 */

// Por defecto Bootstrap se carga (para compatibilidad con vistas heredadas)
if (!isset($loadBootstrap)) {
    $loadBootstrap = true;
}

// ── Misma lógica de roles/redirección del header.php original ─────────────
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
$isRepartidor  = in_array(ROL_NOMBRE_REPARTIDOR, $rolesNombres, true);
$isAdmin       = in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
$isVendedor    = in_array(ROL_NOMBRE_VENDEDOR, $rolesNombres, true);
$isProveedor   = in_array(ROL_NOMBRE_PROVEEDOR, $rolesNombres, true);

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

// ── Notificaciones (badge) ─────────────────────────────────────────────────
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

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- MaterializeCSS 1.0.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <!-- Google Fonts — Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <?php if ($loadBootstrap): ?>
    <!-- Bootstrap 5 CSS + Icons (para vistas con HTML Bootstrap) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Select2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <!-- DataTables CSS -->
    <?php if (!empty($usaDataTables)): ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
    <?php endif; ?>
    <?php endif; ?>

    <!-- Estilos globales Materialize (sin Bootstrap) -->
    <link rel="stylesheet" href="<?= RUTA_URL ?>vista/css/estilos_mz.css">

    <script>const RUTA_URL = '<?= RUTA_URL ?>';</script>
</head>
<body class="mz-body">

<!-- ══════════════════ SIDENAV (Mobile) ══════════════════ -->
<ul id="mz-sidenav" class="sidenav sidenav-fixed mz-sidenav">

    <!-- Cabecera sidenav -->
    <li>
        <div class="user-view mz-sidenav-header">
            <div class="background mz-sidenav-bg"></div>
            <span class="white-text name" style="font-weight:700;font-size:1rem">App RutaEx-Latam</span>
            <?php if ($userName): ?>
            <span class="white-text email"><?= htmlspecialchars($userName) ?></span>
            <?php endif; ?>
        </div>
    </li>

    <!-- Dashboard -->
    <?php if ((!$isRepartidor || $isAdmin) && !$isCliente): ?>
    <li><a href="<?= RUTA_URL ?>dashboard"><i class="material-icons">dashboard</i>Dashboard</a></li>
    <?php endif; ?>
    <?php if ($isCliente): ?>
    <li><a href="<?= RUTA_URL ?>logistica/dashboard"><i class="material-icons">local_shipping</i>Mis Pedidos</a></li>
    <li><a href="<?= RUTA_URL ?>productos/listar"><i class="material-icons">inventory_2</i>Mis Productos</a></li>
    <?php endif; ?>

    <!-- Pedidos -->
    <?php if ($isAdmin || $isProveedor): ?>
    <li><div class="divider"></div></li>
    <li><a class="subheader">Operaciones</a></li>
    <li><a href="<?= RUTA_URL ?>pedidos/listar"><i class="material-icons">assignment</i>Pedidos</a></li>
    <?php if ($isAdmin): ?>
    <li><a href="<?= RUTA_URL ?>pedidos/crearPedido"><i class="material-icons">add_circle</i>Nuevo Pedido</a></li>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($isRepartidor || $isAdmin): ?>
    <li><a href="<?= RUTA_URL ?>seguimiento/listar"><i class="material-icons">location_on</i>Seguimiento</a></li>
    <?php endif; ?>

    <!-- Inventario -->
    <?php if ($isAdmin || $isProveedor): ?>
    <li><div class="divider"></div></li>
    <li><a class="subheader">Inventario</a></li>
    <li><a href="<?= RUTA_URL ?>productos/listar"><i class="material-icons">inventory_2</i>Productos</a></li>
    <li><a href="<?= RUTA_URL ?>categorias/listar"><i class="material-icons">folder</i>Categorías</a></li>
    <li><a href="<?= RUTA_URL ?>stock/listar"><i class="material-icons">swap_vert</i>Mov. de Stock</a></li>
    <li><a href="<?= RUTA_URL ?>stock/kardex"><i class="material-icons">article</i>Kardex</a></li>
    <li><a href="<?= RUTA_URL ?>stock/movimientos"><i class="material-icons">journal_arrow_down</i>Reporte Movimientos</a></li>
    <li><a href="<?= RUTA_URL ?>stock/saldo"><i class="material-icons">bar_chart</i>Saldo por Producto</a></li>
    <li><a href="<?= RUTA_URL ?>stock/inventario_periodo"><i class="material-icons">table_chart</i>Inventario Período</a></li>
    <li><a href="<?= RUTA_URL ?>stock/crear"><i class="material-icons">add_circle</i>Nuevo Movimiento</a></li>
    <?php endif; ?>

    <!-- Catálogos -->
    <?php if ($isAdmin || $isProveedor || $isVendedor): ?>
    <li><div class="divider"></div></li>
    <li><a class="subheader">Catálogos</a></li>
    <li><a href="<?= RUTA_URL ?>paises/listar"><i class="material-icons">public</i>Países</a></li>
    <li><a href="<?= RUTA_URL ?>departamentos/listar"><i class="material-icons">map</i>Departamentos</a></li>
    <li><a href="<?= RUTA_URL ?>municipios/listar"><i class="material-icons">location_on</i>Municipios</a></li>
    <li><a href="<?= RUTA_URL ?>barrios/listar"><i class="material-icons">apartment</i>Barrios</a></li>
    <li><a href="<?= RUTA_URL ?>codigos_postales"><i class="material-icons">local_post_office</i>Códigos Postales</a></li>
    <li><a href="<?= RUTA_URL ?>monedas/listar"><i class="material-icons">currency_exchange</i>Monedas</a></li>
    <?php endif; ?>

    <!-- CRM -->
    <?php if ($isAdmin || $isProveedorCRM || $isClienteCRM): ?>
    <li><div class="divider"></div></li>
    <li><a class="subheader">CRM Relay</a></li>
    <?php if ($isAdmin || $isProveedorCRM): ?>
    <li><a href="<?= RUTA_URL ?>crm/dashboard"><i class="material-icons">speed</i>Dashboard CRM</a></li>
    <li><a href="<?= RUTA_URL ?>crm/listar"><i class="material-icons">people</i>Leads</a></li>
    <?php endif; ?>
    <li>
        <a href="<?= RUTA_URL ?>crm/notificaciones">
            <i class="material-icons">notifications</i>Notificaciones
            <?php if ($unreadCount > 0): ?>
            <span class="badge new red" data-badge-caption=""><?= $unreadCount ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php if ($isAdmin): ?>
    <li><a href="<?= RUTA_URL ?>crm/integraciones"><i class="material-icons">power</i>Integraciones</a></li>
    <li><a href="<?= RUTA_URL ?>crm/monitor"><i class="material-icons">monitor_heart</i>Monitor Worker</a></li>
    <li><a href="<?= RUTA_URL ?>crm/reportes"><i class="material-icons">bar_chart</i>Reportes</a></li>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Admin -->
    <?php if ($isAdmin): ?>
    <li><div class="divider"></div></li>
    <li><a class="subheader">Administración</a></li>
    <li><a href="<?= RUTA_URL ?>usuarios/listar"><i class="material-icons">manage_accounts</i>Usuarios</a></li>
    <li><a href="<?= RUTA_URL ?>auditoria/historial"><i class="material-icons">history</i>Auditoría</a></li>
    <li><a href="<?= RUTA_URL ?>api/doc/"><i class="material-icons">book</i>API Docs</a></li>
    <?php endif; ?>

    <!-- Logout -->
    <li><div class="divider"></div></li>
    <?php if ($userName): ?>
    <li><a href="<?= RUTA_URL ?>usuarios/perfil"><i class="material-icons">person</i><?= htmlspecialchars($userName) ?></a></li>
    <?php endif; ?>
    <li><a href="<?= RUTA_URL ?>salir" class="red-text"><i class="material-icons red-text">logout</i>Cerrar Sesión</a></li>
</ul>

<!-- ══════════════════ NAVBAR (Desktop + Mobile trigger) ══════════════════ -->
<nav class="mz-navbar" role="navigation">
    <div class="nav-wrapper container">

        <!-- Trigger sidenav mobile -->
        <a href="#" data-target="mz-sidenav" class="sidenav-trigger">
            <i class="material-icons">menu</i>
        </a>

        <!-- Logo / Brand -->
        <a href="<?= $homeUrl ?>" class="brand-logo mz-brand-logo">
            <i class="material-icons left">inventory</i> RutaEx-Latam
        </a>

        <!-- Links desktop (ocultos en mobile) -->
        <ul class="right hide-on-med-and-down">

            <!-- Notificaciones -->
            <?php if (!$isRepartidor || $isAdmin): ?>
            <li>
                <a href="<?= RUTA_URL ?>crm/notificaciones" class="tooltipped" data-tooltip="<?= $showProviderLeads ? 'Leads' : 'Notificaciones' ?>">
                    <i class="material-icons"><?= $showProviderLeads ? 'people' : 'notifications' ?></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="mz-badge-notif"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

            <!-- Menú usuario -->
            <?php if ($userName): ?>
            <li>
                <a class="dropdown-trigger btn-flat white-text" href="#" data-target="mz-user-dropdown">
                    <i class="material-icons left">account_circle</i>
                    <?= htmlspecialchars($userName) ?>
                    <i class="material-icons right">arrow_drop_down</i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<!-- Dropdown usuario -->
<?php if ($userName): ?>
<ul id="mz-user-dropdown" class="dropdown-content mz-user-dropdown">
    <li class="disabled"><a href="#" class="grey-text text-darken-2 small"><?= implode(', ', $rolesNombres) ?></a></li>
    <li class="divider" tabindex="-1"></li>
    <li><a href="<?= RUTA_URL ?>usuarios/perfil"><i class="material-icons left">manage_accounts</i>Editar Perfil</a></li>
    <li class="divider" tabindex="-1"></li>
    <li><a href="<?= RUTA_URL ?>salir" class="red-text"><i class="material-icons left red-text">logout</i>Cerrar Sesión</a></li>
</ul>
<?php endif; ?>

<!-- ══════════════════ MAIN CONTENT ══════════════════ -->
<main class="mz-main-content">
<div class="mz-page-container">
